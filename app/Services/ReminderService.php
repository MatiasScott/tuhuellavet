<?php

namespace App\Services;

use PDO;

class ReminderService
{
    public function scheduleVaccinationReminder(
        PDO $db,
        int $environmentId,
        array $patient,
        int $vaccinationId,
        string $date,
        int $createdBy
    ): void {
        $this->schedule(
            $db,
            $environmentId,
            $patient,
            'VACUNA',
            'VACUNACION',
            $vaccinationId,
            'Recordatorio de vacunaciÃ³n',
            sprintf(
                'La prÃ³xima vacunaciÃ³n de %s estÃ¡ programada para el %s.',
                $patient['nombre']
                    ?: 'el paciente',
                $this->humanDate(
                    $date
                )
            ),
            $date,
            $createdBy
        );
    }


    public function scheduleDewormingReminder(
        PDO $db,
        int $environmentId,
        array $patient,
        int $dewormingId,
        string $date,
        int $createdBy
    ): void {
        $this->schedule(
            $db,
            $environmentId,
            $patient,
            'DESPARASITACION',
            'DESPARASITACION',
            $dewormingId,
            'Recordatorio de desparasitaciÃ³n',
            sprintf(
                'La prÃ³xima desparasitaciÃ³n de %s estÃ¡ programada para el %s.',
                $patient['nombre']
                    ?: 'el paciente',
                $this->humanDate(
                    $date
                )
            ),
            $date,
            $createdBy
        );
    }


    private function schedule(
        PDO $db,
        int $environmentId,
        array $patient,
        string $notificationTypeCode,
        string $referenceType,
        int $referenceId,
        string $subject,
        string $message,
        string $date,
        int $createdBy
    ): void {
        /*
         * Recordatorio 3 dÃ­as antes.
         *
         * Si ya estÃ¡ demasiado cerca,
         * programamos para la propia fecha.
         */
        $target
            = new \DateTimeImmutable(
                $date . ' 09:00:00'
            );

        $reminder
            = $target->modify(
                '-3 days'
            );

        $now
            = new \DateTimeImmutable();

        if ($reminder < $now) {
            $reminder = $target;
        }

        $typeId
            = $this->lookupId(
                $db,
                'tipos_notificacion',
                $notificationTypeCode
            );

        /*
         * Si no tiene propietario,
         * no creamos recordatorios externos.
         */
        $ownerId
            = !empty(
                $patient[
                    'propietario_id'
                ]
            )
                ? (int)
                    $patient[
                        'propietario_id'
                    ]
                : null;

        $userId
            = !empty(
                $patient[
                    'propietario_usuario_id'
                ]
            )
                ? (int)
                    $patient[
                        'propietario_usuario_id'
                    ]
                : null;

        if (
            !$ownerId
            && !$userId
        ) {
            return;
        }

        /*
         * Siempre generamos recordatorio
         * interno.
         */
        $this->insertNotification(
            $db,
            $environmentId,
            $typeId,
            $this->lookupId(
                $db,
                'canales_notificacion',
                'INTERNA'
            ),
            $userId,
            $ownerId,
            $subject,
            $message,
            $reminder,
            $referenceType,
            $referenceId
        );

        /*
         * Correo si el propietario
         * tiene email.
         */
        if (
            !empty(
                $patient[
                    'propietario_email'
                ]
            )
        ) {
            $this->insertNotification(
                $db,
                $environmentId,
                $typeId,
                $this->lookupId(
                    $db,
                    'canales_notificacion',
                    'EMAIL'
                ),
                $userId,
                $ownerId,
                $subject,
                $message,
                $reminder,
                $referenceType,
                $referenceId
            );
        }

        /*
         * WhatsApp si existe celular.
         */
        if (
            !empty(
                $patient[
                    'propietario_celular'
                ]
            )
        ) {
            $this->insertNotification(
                $db,
                $environmentId,
                $typeId,
                $this->lookupId(
                    $db,
                    'canales_notificacion',
                    'WHATSAPP'
                ),
                $userId,
                $ownerId,
                $subject,
                $message,
                $reminder,
                $referenceType,
                $referenceId
            );
        }
    }


    private function insertNotification(
        PDO $db,
        int $environmentId,
        int $typeId,
        int $channelId,
        ?int $userId,
        ?int $ownerId,
        string $subject,
        string $message,
        \DateTimeImmutable $date,
        string $referenceType,
        int $referenceId
    ): void {
        /*
         * Previene duplicar recordatorios
         * del mismo origen/canal.
         */
        $check = $db->prepare(
            '
            SELECT id

            FROM notificaciones

            WHERE tipo_notificacion_id
                = :tipo

              AND canal_id
                = :canal

              AND referencia_tipo
                = :referencia_tipo

              AND referencia_id
                = :referencia_id

              AND estado
                IN (
                    "PENDIENTE",
                    "PROGRAMADA"
                )

            LIMIT 1
            '
        );

        $check->execute([
            'tipo'
                => $typeId,

            'canal'
                => $channelId,

            'referencia_tipo'
                => $referenceType,

            'referencia_id'
                => $referenceId,
        ]);

        if (
            $check->fetchColumn()
        ) {
            return;
        }

        $stmt = $db->prepare(
            '
            INSERT INTO notificaciones
            (
                entorno_id,
                tipo_notificacion_id,
                canal_id,
                destinatario_usuario_id,
                destinatario_propietario_id,
                asunto,
                mensaje,
                fecha_programada,
                estado,
                referencia_tipo,
                referencia_id,
                intentos
            )
            VALUES
            (
                :entorno,
                :tipo,
                :canal,
                :usuario,
                :propietario,
                :asunto,
                :mensaje,
                :fecha,
                "PENDIENTE",
                :referencia_tipo,
                :referencia_id,
                0
            )
            '
        );

        $stmt->execute([
            'entorno'
                => $environmentId,

            'tipo'
                => $typeId,

            'canal'
                => $channelId,

            'usuario'
                => $userId,

            'propietario'
                => $ownerId,

            'asunto'
                => $subject,

            'mensaje'
                => $message,

            'fecha'
                => $date->format(
                    'Y-m-d H:i:s'
                ),

            'referencia_tipo'
                => $referenceType,

            'referencia_id'
                => $referenceId,
        ]);
    }


    private function lookupId(
        PDO $db,
        string $table,
        string $code
    ): int {
        /*
         * Tabla controlada internamente;
         * no viene del usuario.
         */
        $allowed = [
            'tipos_notificacion',
            'canales_notificacion',
        ];

        if (
            !in_array(
                $table,
                $allowed,
                true
            )
        ) {
            throw new \RuntimeException(
                'CatÃ¡logo no permitido.'
            );
        }

        $stmt = $db->prepare(
            "
            SELECT id

            FROM {$table}

            WHERE codigo = :codigo

            LIMIT 1
            "
        );

        $stmt->execute([
            'codigo'
                => $code,
        ]);

        $id = $stmt->fetchColumn();

        if (!$id) {
            throw new \RuntimeException(
                'No existe el catÃ¡logo requerido: '
                . $code
            );
        }

        return (int) $id;
    }


    private function humanDate(
        string $date
    ): string {
        return date(
            'd/m/Y',
            strtotime(
                $date
            )
        );
    }
}
