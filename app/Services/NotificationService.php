<?php

namespace App\Services;

use App\Core\Database;
use RuntimeException;
use Throwable;

class NotificationService
{
    public function pending(
        int $limit = 50
    ): array {
        $limit = max(
            1,
            min(
                $limit,
                200
            )
        );

        return Database::connection()
            ->query(
                "SELECT
                    n.*,

                    tn.codigo AS tipo_codigo,
                    cn.codigo AS canal_codigo,

                    p.email AS propietario_email,
                    p.celular AS propietario_celular,

                    u.email AS usuario_email,
                    u.telefono AS usuario_telefono

                FROM notificaciones n

                JOIN tipos_notificacion tn
                    ON tn.id = n.tipo_notificacion_id

                JOIN canales_notificacion cn
                    ON cn.id = n.canal_id

                LEFT JOIN propietarios p
                    ON p.id = n.destinatario_propietario_id

                LEFT JOIN usuarios u
                    ON u.id = n.destinatario_usuario_id

                WHERE
                    n.estado = 'PENDIENTE'

                    AND (
                        n.fecha_programada IS NULL
                        OR n.fecha_programada <= NOW()
                    )

                ORDER BY
                    COALESCE(
                        n.fecha_programada,
                        n.created_at
                    )

                LIMIT {$limit}"
            )
            ->fetchAll();
    }


    public function processBatch(
        int $limit = 50
    ): array {
        $ok = 0;
        $failed = 0;

        foreach (
            $this->pending($limit)
            as $notification
        ) {
            try {
                $this->deliver(
                    $notification
                );

                Database::connection()
                    ->prepare(
                        "UPDATE notificaciones
                         SET
                            estado = 'ENVIADA',
                            fecha_envio = NOW(),
                            ultimo_error = NULL
                         WHERE id = :id"
                    )
                    ->execute([
                        'id'
                        => $notification['id'],
                    ]);

                $ok++;
            } catch (Throwable $e) {
                Database::connection()
                    ->prepare(
                        "UPDATE notificaciones
                         SET
                            intentos = intentos + 1,

                            ultimo_error = :error,

                            estado = IF(
                                intentos + 1 >= 5,
                                'FALLIDA',
                                'PENDIENTE'
                            )

                         WHERE id = :id"
                    )
                    ->execute([
                        'error'
                        => mb_substr(
                            $e->getMessage(),
                            0,
                            500
                        ),

                        'id'
                        => $notification['id'],
                    ]);

                $failed++;
            }
        }

        return [
            'sent' => $ok,
            'failed' => $failed,
        ];
    }


    private function deliver(
        array $notification
    ): void {
        $channel = strtoupper(
            trim(
                (string) (
                    $notification['canal_codigo']
                    ?? ''
                )
            )
        );


        /*
        |--------------------------------------------------------------------------
        | NOTIFICACIÓN INTERNA
        |--------------------------------------------------------------------------
        */

        if ($channel === 'INTERNA') {
            return;
        }


        /*
        |--------------------------------------------------------------------------
        | EMAIL
        |--------------------------------------------------------------------------
        */

        if ($channel === 'EMAIL') {
            $to = $this->emailRecipient(
                $notification
            );

            if ($to === null) {
                throw new RuntimeException(
                    'El destinatario no tiene un correo electrónico válido.'
                );
            }

            $subject = trim(
                (string) (
                    $notification['asunto']
                    ?? ''
                )
            );

            if ($subject === '') {
                $subject =
                    'Notificación Tu Huella Vet';
            }

            $message = (string) (
                $notification['mensaje']
                ?? ''
            );

            $html = $this->emailTemplate(
                $subject,
                $message
            );

            (new MailService())
                ->send(
                    $to,
                    $subject,
                    $html,
                    $message
                );

            return;
        }


        /*
        |--------------------------------------------------------------------------
        | WHATSAPP
        |--------------------------------------------------------------------------
        */

        if ($channel === 'WHATSAPP') {
            $to =
                $notification['propietario_celular']
                ?? $notification['usuario_telefono']
                ?? null;

            $to = trim(
                (string)$to
            );

            $url = trim(
                (string) (
                    $_ENV['WHATSAPP_API_URL']
                    ?? ''
                )
            );

            $token = trim(
                (string) (
                    $_ENV['WHATSAPP_TOKEN']
                    ?? ''
                )
            );

            if (
                $to === ''
                || $url === ''
                || $token === ''
            ) {
                throw new RuntimeException(
                    'WhatsApp no está configurado o falta el número del destinatario.'
                );
            }

            $ch = curl_init(
                $url
            );

            if ($ch === false) {
                throw new RuntimeException(
                    'No fue posible iniciar la conexión con WhatsApp.'
                );
            }

            curl_setopt_array(
                $ch,
                [
                    CURLOPT_POST
                    => true,

                    CURLOPT_RETURNTRANSFER
                    => true,

                    CURLOPT_HTTPHEADER
                    => [
                        'Authorization: Bearer '
                            . $token,

                        'Content-Type: application/json',
                    ],

                    CURLOPT_POSTFIELDS
                    => json_encode(
                        [
                            'to' => $to,

                            'message'
                            => (
                                $notification['mensaje']
                                ?? ''
                            ),
                        ],
                        JSON_UNESCAPED_UNICODE
                    ),
                ]
            );

            $response =
                curl_exec($ch);

            $code = (int)
            curl_getinfo(
                $ch,
                CURLINFO_HTTP_CODE
            );

            $error =
                curl_error($ch);

            curl_close($ch);

            if (
                $response === false
                || $code < 200
                || $code >= 300
            ) {
                throw new RuntimeException(
                    'WhatsApp falló: '
                        . (
                            $error !== ''
                            ? $error
                            : 'HTTP ' . $code
                        )
                );
            }

            return;
        }


        throw new RuntimeException(
            'Canal de notificación no soportado.'
        );
    }


    private function emailRecipient(
        array $notification
    ): ?string {
        /*
         * Para notificaciones clínicas
         * priorizamos el correo del propietario.
         *
         * Para notificaciones administrativas
         * normalmente existirá únicamente
         * destinatario_usuario_id.
         */
        $email = trim(
            (string) (
                $notification['propietario_email']
                ?? ''
            )
        );

        if (
            $email !== ''
            && filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            return $email;
        }


        $email = trim(
            (string) (
                $notification['usuario_email']
                ?? ''
            )
        );

        if (
            $email !== ''
            && filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            return $email;
        }


        return null;
    }


    private function emailTemplate(
        string $subject,
        string $message
    ): string {
        $safeSubject =
            htmlspecialchars(
                $subject,
                ENT_QUOTES,
                'UTF-8'
            );

        $safeMessage =
            nl2br(
                htmlspecialchars(
                    $message,
                    ENT_QUOTES,
                    'UTF-8'
                )
            );

        return '
        <!doctype html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
        </head>

        <body
            style="
                margin:0;
                padding:0;
                background:#f4f7f9;
                font-family:Arial,Helvetica,sans-serif;
                color:#17324d;
            "
        >
            <div
                style="
                    max-width:640px;
                    margin:0 auto;
                    padding:32px 16px;
                "
            >
                <div
                    style="
                        background:#ffffff;
                        border-radius:16px;
                        padding:32px;
                        box-shadow:0 8px 24px rgba(0,0,0,.06);
                    "
                >
                    <div
                        style="
                            font-size:13px;
                            font-weight:700;
                            text-transform:uppercase;
                            letter-spacing:.08em;
                            color:#0f9f98;
                            margin-bottom:10px;
                        "
                    >
                        Tu Huella Vet
                    </div>

                    <h1
                        style="
                            margin:0 0 20px;
                            font-size:24px;
                            color:#102a43;
                        "
                    >
                        ' . $safeSubject . '
                    </h1>

                    <div
                        style="
                            font-size:15px;
                            line-height:1.7;
                            color:#486581;
                        "
                    >
                        ' . $safeMessage . '
                    </div>
                </div>

                <div
                    style="
                        text-align:center;
                        color:#829ab1;
                        font-size:12px;
                        padding:18px;
                    "
                >
                    Mensaje automático de Tu Huella Vet.
                </div>
            </div>
        </body>
        </html>';
    }
}
