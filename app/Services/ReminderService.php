<?php

namespace App\Services;

use DateTimeImmutable;
use PDO;
use RuntimeException;

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
            'Recordatorio de vacunación',
            sprintf(
                'La próxima vacunación de %s está programada para el %s.',
                $patient['nombre'] ?: 'el paciente',
                $this->humanDate($date)
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
            'Recordatorio de desparasitación',
            sprintf(
                'La próxima desparasitación de %s está programada para el %s.',
                $patient['nombre'] ?: 'el paciente',
                $this->humanDate($date)
            ),
            $date,
            $createdBy
        );
    }

    public function scheduleGroomingReminder(
        PDO $db,
        int $environmentId,
        array $patient,
        int $groomingId,
        string $date,
        int $createdBy
    ): void {
        $this->schedule(
            $db,
            $environmentId,
            $patient,
            'PELUQUERIA',
            'PELUQUERIA',
            $groomingId,
            'Recordatorio de peluquería',
            sprintf(
                'La próxima atención de peluquería de %s está programada para el %s.',
                $patient['nombre'] ?: 'el paciente',
                $this->humanDate($date)
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
         * Recordatorio 3 días antes,
         * programado para las 09:00.
         */
        $target = new DateTimeImmutable(
            $date . ' 09:00:00'
        );

        $reminder = $target->modify('-3 days');
        $now = new DateTimeImmutable();

        /*
         * Si se registra cuando faltan menos
         * de 3 días, no enviamos inmediatamente.
         * Lo dejamos para la fecha objetivo.
         */
        if ($reminder < $now) {
            $reminder = $target;
        }

        $typeId = $this->lookupId(
            $db,
            'tipos_notificacion',
            $notificationTypeCode
        );

        $ownerId = !empty($patient['propietario_id'])
            ? (int)$patient['propietario_id']
            : null;

        $userId = !empty($patient['propietario_usuario_id'])
            ? (int)$patient['propietario_usuario_id']
            : null;

        /*
         * Si el paciente no tiene propietario
         * ni usuario vinculado no existe un
         * destinatario válido.
         */
        if (!$ownerId && !$userId) {
            return;
        }

        /*
         * Notificación interna.
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
         * Email.
         */
        if (
            !empty($patient['propietario_email'])
            || !empty($patient['usuario_email'])
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
         * WhatsApp.
         */
        if (
            !empty($patient['propietario_celular'])
            || !empty($patient['usuario_telefono'])
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
        DateTimeImmutable $date,
        string $referenceType,
        int $referenceId
    ): void {
        /*
         * Evita duplicados pendientes para
         * el mismo registro y canal.
         */
        $check = $db->prepare(
            '
            SELECT id
            FROM notificaciones

            WHERE entorno_id = :entorno
              AND tipo_notificacion_id = :tipo
              AND canal_id = :canal
              AND referencia_tipo = :referencia_tipo
              AND referencia_id = :referencia_id

              AND estado IN (
                  "PENDIENTE",
                  "PROGRAMADA"
              )

            LIMIT 1
            '
        );

        $check->execute([
            'entorno' => $environmentId,
            'tipo' => $typeId,
            'canal' => $channelId,
            'referencia_tipo' => $referenceType,
            'referencia_id' => $referenceId,
        ]);

        if ($check->fetchColumn()) {
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
            'entorno' => $environmentId,
            'tipo' => $typeId,
            'canal' => $channelId,
            'usuario' => $userId,
            'propietario' => $ownerId,
            'asunto' => $subject,
            'mensaje' => $message,
            'fecha' => $date->format('Y-m-d H:i:s'),
            'referencia_tipo' => $referenceType,
            'referencia_id' => $referenceId,
        ]);
    }

    private function lookupId(
        PDO $db,
        string $table,
        string $code
    ): int {
        $allowed = [
            'tipos_notificacion',
            'canales_notificacion',
        ];

        if (!in_array($table, $allowed, true)) {
            throw new RuntimeException(
                'Catálogo de notificación no permitido.'
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
            'codigo' => $code,
        ]);

        $id = $stmt->fetchColumn();

        if (!$id) {
            throw new RuntimeException(
                'No existe el catálogo requerido: ' . $code
            );
        }

        return (int)$id;
    }

    private function humanDate(
        string $date
    ): string {
        return date(
            'd/m/Y',
            strtotime($date)
        );
    }
}
