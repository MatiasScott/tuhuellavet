<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

class GroomingService
{
    /**
     * Crear servicio de peluquería.
     */
    public function create(
        array $data,
        int $environmentId,
        int $createdBy
    ): int {
        return Database::transaction(
            function (PDO $db) use (
                $data,
                $environmentId,
                $createdBy
            ): int {
                $patientId = (int) (
                    $data['animal_id']
                    ?? 0
                );

                $serviceId = (int) (
                    $data['servicio_id']
                    ?? 0
                );

                if ($patientId <= 0) {
                    throw new RuntimeException(
                        'Debes seleccionar un paciente.'
                    );
                }

                if ($serviceId <= 0) {
                    throw new RuntimeException(
                        'Debes seleccionar un servicio.'
                    );
                }

                /*
                 * Validamos paciente y recuperamos
                 * propietario/contacto para recordatorios.
                 */
                $patient = $this->validatePatient(
                    $db,
                    $patientId,
                    $environmentId
                );

                $this->validateService(
                    $db,
                    $serviceId
                );

                $eventTypeId = $this->eventTypeId(
                    $db,
                    'PELUQUERIA'
                );

                $eventDate = $this->dateTimeOrNow(
                    $data['fecha_evento']
                        ?? null
                );

                $nextDate = $this->dateOrNull(
                    $data['proxima_peluqueria']
                        ?? null
                );

                $observations = trim(
                    (string) (
                        $data['observaciones']
                        ?? ''
                    )
                );

                /*
                 * Evento clínico padre.
                 */
                $stmt = $db->prepare(
                    '
                    INSERT INTO eventos_clinicos
                    (
                        animal_id,
                        tipo_evento_id,
                        responsable_id,
                        fecha_evento,
                        titulo,
                        observaciones
                    )
                    VALUES
                    (
                        :animal,
                        :tipo,
                        :responsable,
                        :fecha,
                        :titulo,
                        :observaciones
                    )
                    '
                );

                $stmt->execute([
                    'animal' => $patientId,
                    'tipo' => $eventTypeId,
                    'responsable' => $createdBy,
                    'fecha' => $eventDate,
                    'titulo' => 'Peluquería',
                    'observaciones' =>
                    $observations !== ''
                        ? $observations
                        : null,
                ]);

                /*
                 * Este es también el ID de peluquería,
                 * porque peluquerias.evento_clinico_id
                 * es su PK.
                 */
                $eventId = (int)
                $db->lastInsertId();

                /*
                 * Registro específico de peluquería.
                 */
                $stmt = $db->prepare(
                    '
                    INSERT INTO peluquerias
                    (
                        evento_clinico_id,
                        servicio_id,
                        proxima_peluqueria,
                        observaciones
                    )
                    VALUES
                    (
                        :evento,
                        :servicio,
                        :proxima,
                        :observaciones
                    )
                    '
                );

                $stmt->execute([
                    'evento' => $eventId,
                    'servicio' => $serviceId,
                    'proxima' => $nextDate,
                    'observaciones' =>
                    $observations !== ''
                        ? $observations
                        : null,
                ]);

                /*
                 * Recordatorio.
                 *
                 * ReminderService se encarga de
                 * programarlo 3 días antes.
                 */
                if ($nextDate !== null) {
                    (new ReminderService())
                        ->scheduleGroomingReminder(
                            $db,
                            $environmentId,
                            $patient,
                            $eventId,
                            $nextDate,
                            $createdBy
                        );
                }

                /*
                 * Auditoría.
                 */
                (new AuditService())
                    ->log(
                        $createdBy,
                        $environmentId,
                        'PELUQUERIA',
                        'CREAR',
                        'peluquerias',
                        $eventId,
                        null,
                        [
                            'animal_id'
                            => $patientId,

                            'servicio_id'
                            => $serviceId,

                            'fecha_evento'
                            => $eventDate,

                            'proxima_peluqueria'
                            => $nextDate,

                            'observaciones'
                            => $observations !== ''
                                ? $observations
                                : null,
                        ]
                    );

                return $eventId;
            }
        );
    }


    /**
     * Editar servicio de peluquería.
     */
    public function update(
        int $eventId,
        array $data,
        int $environmentId,
        int $updatedBy
    ): void {
        Database::transaction(
            function (PDO $db) use (
                $eventId,
                $data,
                $environmentId,
                $updatedBy
            ): void {
                if ($eventId <= 0) {
                    throw new RuntimeException(
                        'El registro de peluquería es obligatorio.'
                    );
                }

                $serviceId = (int) (
                    $data['servicio_id']
                    ?? 0
                );

                if ($serviceId <= 0) {
                    throw new RuntimeException(
                        'Debes seleccionar un servicio.'
                    );
                }

                $this->validateService(
                    $db,
                    $serviceId
                );

                /*
                 * Recuperamos y bloqueamos
                 * el registro actual.
                 */
                $stmt = $db->prepare(
                    '
                    SELECT
                        pe.evento_clinico_id,
                        pe.servicio_id,
                        pe.proxima_peluqueria,
                        pe.observaciones,

                        ec.fecha_evento,
                        ec.anulado_at,

                        a.id AS animal_id,
                        a.entorno_id

                    FROM peluquerias pe

                    INNER JOIN eventos_clinicos ec
                        ON ec.id =
                           pe.evento_clinico_id

                    INNER JOIN animales a
                        ON a.id =
                           ec.animal_id

                    WHERE pe.evento_clinico_id =
                          :evento

                    LIMIT 1

                    FOR UPDATE
                    '
                );

                $stmt->execute([
                    'evento' => $eventId,
                ]);

                $current = $stmt->fetch();

                if (!$current) {
                    throw new RuntimeException(
                        'Registro de peluquería no encontrado.'
                    );
                }

                if (
                    (int)$current['entorno_id']
                    !== $environmentId
                ) {
                    throw new RuntimeException(
                        'El registro de peluquería no pertenece al entorno actual.'
                    );
                }

                if (!empty($current['anulado_at'])) {
                    throw new RuntimeException(
                        'No puedes editar un registro de peluquería anulado.'
                    );
                }

                /*
                 * Recuperamos también los datos
                 * del paciente/propietario.
                 */
                $patient = $this->validatePatient(
                    $db,
                    (int)$current['animal_id'],
                    $environmentId
                );

                $nextDate = $this->dateOrNull(
                    $data['proxima_peluqueria']
                        ?? null
                );

                $observations = trim(
                    (string) (
                        $data['observaciones']
                        ?? ''
                    )
                );

                /*
                 * Actualizamos peluquería.
                 */
                $stmt = $db->prepare(
                    '
                    UPDATE peluquerias

                    SET
                        servicio_id = :servicio,
                        proxima_peluqueria = :proxima,
                        observaciones = :observaciones

                    WHERE evento_clinico_id = :evento
                    '
                );

                $stmt->execute([
                    'servicio' => $serviceId,

                    'proxima' => $nextDate,

                    'observaciones' =>
                    $observations !== ''
                        ? $observations
                        : null,

                    'evento' => $eventId,
                ]);

                /*
                 * Fecha del evento / ingreso.
                 */
                if (
                    isset($data['fecha_evento'])
                    && trim(
                        (string)$data['fecha_evento']
                    ) !== ''
                ) {
                    $stmt = $db->prepare(
                        '
                        UPDATE eventos_clinicos

                        SET
                            fecha_evento = :fecha,
                            observaciones = :observaciones

                        WHERE id = :evento
                          AND anulado_at IS NULL
                        '
                    );

                    $stmt->execute([
                        'fecha' =>
                        $this->dateTimeOrNow(
                            $data['fecha_evento']
                        ),

                        'observaciones' =>
                        $observations !== ''
                            ? $observations
                            : null,

                        'evento' => $eventId,
                    ]);
                } else {
                    /*
                     * Aunque no cambie la fecha,
                     * sincronizamos observaciones.
                     */
                    $stmt = $db->prepare(
                        '
                        UPDATE eventos_clinicos

                        SET
                            observaciones = :observaciones

                        WHERE id = :evento
                          AND anulado_at IS NULL
                        '
                    );

                    $stmt->execute([
                        'observaciones' =>
                        $observations !== ''
                            ? $observations
                            : null,

                        'evento' => $eventId,
                    ]);
                }

                /*
                 * Cancelamos recordatorios
                 * pendientes anteriores.
                 */
                $this->cancelPendingReminders(
                    $db,
                    $eventId,
                    $environmentId
                );

                /*
                 * Si todavía existe próxima fecha,
                 * generamos el nuevo recordatorio.
                 */
                if ($nextDate !== null) {
                    (new ReminderService())
                        ->scheduleGroomingReminder(
                            $db,
                            $environmentId,
                            $patient,
                            $eventId,
                            $nextDate,
                            $updatedBy
                        );
                }

                /*
                 * Auditoría.
                 */
                (new AuditService())
                    ->log(
                        $updatedBy,
                        $environmentId,
                        'PELUQUERIA',
                        'EDITAR',
                        'peluquerias',
                        $eventId,
                        [
                            'servicio_id'
                            => (int)$current['servicio_id'],

                            'fecha_evento'
                            => $current['fecha_evento'],

                            'proxima_peluqueria'
                            => $current['proxima_peluqueria'],

                            'observaciones'
                            => $current['observaciones'],
                        ],
                        [
                            'servicio_id'
                            => $serviceId,

                            'fecha_evento'
                            => isset($data['fecha_evento'])
                                && trim(
                                    (string)$data['fecha_evento']
                                ) !== ''
                                ? $this->dateTimeOrNow(
                                    $data['fecha_evento']
                                )
                                : $current['fecha_evento'],

                            'proxima_peluqueria'
                            => $nextDate,

                            'observaciones'
                            => $observations !== ''
                                ? $observations
                                : null,
                        ]
                    );
            }
        );
    }


    /**
     * Anular servicio de peluquería.
     */
    public function cancel(
        int $eventId,
        string $reason,
        int $environmentId,
        int $cancelledBy
    ): void {
        Database::transaction(
            function (PDO $db) use (
                $eventId,
                $reason,
                $environmentId,
                $cancelledBy
            ): void {
                $reason = trim($reason);

                if ($eventId <= 0) {
                    throw new RuntimeException(
                        'El registro de peluquería es obligatorio.'
                    );
                }

                if ($reason === '') {
                    throw new RuntimeException(
                        'El motivo de anulación es obligatorio.'
                    );
                }

                /*
                 * Recuperamos y bloqueamos.
                 */
                $stmt = $db->prepare(
                    '
                    SELECT
                        pe.evento_clinico_id,
                        pe.servicio_id,
                        pe.proxima_peluqueria,
                        pe.observaciones,

                        ec.anulado_at,
                        ec.anulado_por,
                        ec.motivo_anulacion,

                        a.entorno_id

                    FROM peluquerias pe

                    INNER JOIN eventos_clinicos ec
                        ON ec.id =
                           pe.evento_clinico_id

                    INNER JOIN animales a
                        ON a.id =
                           ec.animal_id

                    WHERE pe.evento_clinico_id =
                          :evento

                    LIMIT 1

                    FOR UPDATE
                    '
                );

                $stmt->execute([
                    'evento' => $eventId,
                ]);

                $grooming = $stmt->fetch();

                if (!$grooming) {
                    throw new RuntimeException(
                        'Registro de peluquería no encontrado.'
                    );
                }

                if (
                    (int)$grooming['entorno_id']
                    !== $environmentId
                ) {
                    throw new RuntimeException(
                        'El registro de peluquería no pertenece al entorno actual.'
                    );
                }

                if (!empty($grooming['anulado_at'])) {
                    throw new RuntimeException(
                        'El registro de peluquería ya fue anulado.'
                    );
                }

                /*
                 * No borramos físicamente.
                 * Anulamos el evento clínico padre.
                 */
                $stmt = $db->prepare(
                    '
                    UPDATE eventos_clinicos

                    SET
                        anulado_at = NOW(),
                        anulado_por = :usuario,
                        motivo_anulacion = :motivo

                    WHERE id = :evento
                      AND anulado_at IS NULL
                    '
                );

                $stmt->execute([
                    'usuario' => $cancelledBy,
                    'motivo' => $reason,
                    'evento' => $eventId,
                ]);

                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException(
                        'No fue posible anular el registro de peluquería.'
                    );
                }

                /*
                 * Un registro anulado no debe
                 * conservar recordatorios futuros.
                 */
                $this->cancelPendingReminders(
                    $db,
                    $eventId,
                    $environmentId
                );

                /*
                 * Auditoría.
                 */
                (new AuditService())
                    ->log(
                        $cancelledBy,
                        $environmentId,
                        'PELUQUERIA',
                        'ANULAR',
                        'peluquerias',
                        $eventId,
                        null,
                        [
                            'evento_clinico_id'
                            => $eventId,

                            'servicio_id'
                            => (int)$grooming['servicio_id'],

                            'proxima_peluqueria'
                            => $grooming['proxima_peluqueria'],

                            'motivo_anulacion'
                            => $reason,
                        ]
                    );
            }
        );
    }


    /**
     * Cancela únicamente recordatorios
     * todavía no enviados.
     */
    private function cancelPendingReminders(
        PDO $db,
        int $eventId,
        int $environmentId
    ): void {
        $stmt = $db->prepare(
            '
            UPDATE notificaciones

            SET estado = "CANCELADA"

            WHERE referencia_tipo =
                  "PELUQUERIA"

              AND referencia_id =
                  :peluqueria

              AND entorno_id =
                  :entorno

              AND estado IN (
                  "PENDIENTE",
                  "PROGRAMADA"
              )
            '
        );

        $stmt->execute([
            'peluqueria' => $eventId,
            'entorno' => $environmentId,
        ]);
    }


    /**
     * Valida el paciente y devuelve además
     * la información necesaria para crear
     * los recordatorios.
     */
    private function validatePatient(
        PDO $db,
        int $patientId,
        int $environmentId
    ): array {
        $stmt = $db->prepare(
            '
            SELECT
                a.id,
                a.nombre,
                a.propietario_entorno_id,

                p.id AS propietario_id,

                p.usuario_id
                    AS propietario_usuario_id,

                p.email
                    AS propietario_email,

                p.celular
                    AS propietario_celular

            FROM animales a

            LEFT JOIN propietarios_entornos pe
                ON pe.id =
                   a.propietario_entorno_id

            LEFT JOIN propietarios p
                ON p.id =
                   pe.propietario_id

            WHERE a.id = :animal

              AND a.entorno_id =
                  :entorno

              AND a.activo = 1

              AND a.deleted_at
                  IS NULL

            LIMIT 1
            '
        );

        $stmt->execute([
            'animal' => $patientId,
            'entorno' => $environmentId,
        ]);

        $patient = $stmt->fetch();

        if (!$patient) {
            throw new RuntimeException(
                'El paciente no pertenece al entorno actual.'
            );
        }

        return $patient;
    }


    /**
     * Valida que el servicio exista
     * y esté activo.
     */
    private function validateService(
        PDO $db,
        int $serviceId
    ): void {
        $stmt = $db->prepare(
            '
            SELECT COUNT(*)

            FROM servicios

            WHERE id = :servicio
              AND activo = 1
            '
        );

        $stmt->execute([
            'servicio' => $serviceId,
        ]);

        if (
            (int)$stmt->fetchColumn()
            === 0
        ) {
            throw new RuntimeException(
                'El servicio seleccionado no está disponible.'
            );
        }
    }


    /**
     * Obtiene el tipo de evento clínico.
     */
    private function eventTypeId(
        PDO $db,
        string $code
    ): int {
        $stmt = $db->prepare(
            '
            SELECT id

            FROM tipos_evento_clinico

            WHERE codigo = :codigo
              AND activo = 1

            LIMIT 1
            '
        );

        $stmt->execute([
            'codigo' => $code,
        ]);

        $id = $stmt->fetchColumn();

        if (!$id) {
            throw new RuntimeException(
                'No existe el tipo de evento clínico '
                    . $code
                    . '.'
            );
        }

        return (int)$id;
    }


    /**
     * Convierte fecha Y-m-d.
     */
    private function dateOrNull(
        mixed $value
    ): ?string {
        if (
            $value === null
            || trim((string)$value) === ''
        ) {
            return null;
        }

        $value = trim(
            (string)$value
        );

        $date = \DateTimeImmutable
            ::createFromFormat(
                '!Y-m-d',
                $value
            );

        $errors =
            \DateTimeImmutable::getLastErrors();

        if (
            !$date
            || (
                is_array($errors)
                && (
                    $errors['warning_count'] > 0
                    || $errors['error_count'] > 0
                )
            )
            || $date->format('Y-m-d')
            !== $value
        ) {
            throw new RuntimeException(
                'La fecha ingresada no es válida.'
            );
        }

        return $date->format(
            'Y-m-d'
        );
    }


    /**
     * Convierte datetime-local a formato SQL.
     */
    private function dateTimeOrNow(
        mixed $value
    ): string {
        if (
            $value === null
            || trim((string)$value) === ''
        ) {
            return date(
                'Y-m-d H:i:s'
            );
        }

        $value = trim(
            (string)$value
        );

        $value = str_replace(
            'T',
            ' ',
            $value
        );

        if (strlen($value) === 16) {
            $value .= ':00';
        }

        $date = \DateTimeImmutable
            ::createFromFormat(
                '!Y-m-d H:i:s',
                $value
            );

        $errors =
            \DateTimeImmutable::getLastErrors();

        if (
            !$date
            || (
                is_array($errors)
                && (
                    $errors['warning_count'] > 0
                    || $errors['error_count'] > 0
                )
            )
            || $date->format(
                'Y-m-d H:i:s'
            ) !== $value
        ) {
            throw new RuntimeException(
                'La fecha y hora ingresadas no son válidas.'
            );
        }

        return $date->format(
            'Y-m-d H:i:s'
        );
    }
}
