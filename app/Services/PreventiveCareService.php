<?php

namespace App\Services;

use App\Core\Database;

use PDO;
use RuntimeException;

class PreventiveCareService
{
    public function createVaccination(
        array $data,
        int $environmentId,
        int $createdBy
    ): int {
        return Database::transaction(
            function (PDO $db) use (
                $data,
                $environmentId,
                $createdBy
            ) {
                $patientId
                    = (int) (
                        $data['animal_id']
                        ?? 0
                    );

                $vaccineId
                    = (int) (
                        $data['vacuna_id']
                        ?? 0
                    );

                if (!$patientId) {
                    throw new RuntimeException(
                        'Debes seleccionar un paciente.'
                    );
                }

                if (!$vaccineId) {
                    throw new RuntimeException(
                        'Debes seleccionar una vacuna.'
                    );
                }

                $patient
                    = $this->validatePatient(
                        $db,
                        $patientId,
                        $environmentId
                    );

                $this->validateVaccine(
                    $db,
                    $vaccineId
                );

                $eventTypeId
                    = $this->eventTypeId(
                        $db,
                        'VACUNACION'
                    );

                /*
                 * Evento clínico.
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
                    'animal'
                    => $patientId,

                    'tipo'
                    => $eventTypeId,

                    'responsable'
                    => $createdBy,

                    'fecha'
                    => $this->dateTimeOrNow(
                        $data['fecha_evento']
                            ?? null
                    ),

                    'titulo'
                    => 'Vacunación',

                    'observaciones'
                    => trim(
                        $data['observaciones_evento']
                            ?? ''
                    )
                        ?: null,
                ]);

                $eventId
                    = (int)
                    $db
                        ->lastInsertId();

                /*
                 * Examen clínico general.
                 *
                 * Según los requisitos,
                 * Vacunación reutiliza el
                 * examen clínico hasta tos.
                 */
                $this->insertClinicalExam(
                    $db,
                    $eventId,
                    $data
                );

                /*
                 * Vacunación.
                 */
                $stmt = $db->prepare(
                    '
                    INSERT INTO vacunaciones
                    (
                        evento_clinico_id,
                        vacuna_id,
                        dosis,
                        unidad_dosis_id,
                        lote,
                        casa_comercial,
                        fecha_revacunacion,
                        observaciones,
                        aplicada_por
                    )
                    VALUES
                    (
                        :evento,
                        :vacuna,
                        :dosis,
                        :unidad,
                        :lote,
                        :casa_comercial,
                        :revacunacion,
                        :observaciones,
                        :usuario
                    )
                    '
                );

                $stmt->execute([
                    'evento'
                    => $eventId,

                    'vacuna'
                    => $vaccineId,

                    'dosis'
                    => $this->positiveNumberOrNull(
                        $data['dosis']
                            ?? null,
                        'La dosis'
                    ),

                    'unidad'
                    => !empty($data['unidad_dosis_id'])
                        ? (int)
                        $data['unidad_dosis_id']
                        : null,

                    'lote'
                    => trim(
                        $data['lote']
                            ?? ''
                    )
                        ?: null,

                    'casa_comercial'
                    => trim(
                        $data['casa_comercial']
                            ?? ''
                    )
                        ?: null,

                    'revacunacion'
                    => $this->dateOrNull(
                        $data['fecha_revacunacion']
                            ?? null
                    ),

                    'observaciones'
                    => trim(
                        $data['observaciones']
                            ?? ''
                    )
                        ?: null,

                    'usuario'
                    => $createdBy,
                ]);

                $vaccinationId
                    = (int)
                    $db
                        ->lastInsertId();

                /*
 * Consumo automático de inventario.
 *
 * Es opcional para mantener compatibilidad
 * con vacunaciones que todavía no utilicen
 * control físico de existencias.
 */
                if (
                    isset($data['inventario_consumo'])
                    && is_array(
                        $data['inventario_consumo']
                    )
                ) {
                    $inventoryConsumption =
                        $data['inventario_consumo'];

                    $inventoryId = (int) (
                        $inventoryConsumption['inventario_id']
                        ?? 0
                    );

                    $productId = (int) (
                        $inventoryConsumption['producto_id']
                        ?? 0
                    );

                    $lotId = !empty($inventoryConsumption['lote_id'])
                        ? (int)
                        $inventoryConsumption['lote_id']
                        : null;

                    $consumedQuantity =
                        $inventoryConsumption['cantidad_consumida']
                        ?? null;

                    if ($inventoryId <= 0) {
                        throw new RuntimeException(
                            'El inventario para el consumo clínico es obligatorio.'
                        );
                    }

                    if ($productId <= 0) {
                        throw new RuntimeException(
                            'El producto para el consumo clínico es obligatorio.'
                        );
                    }

                    $normalizedConsumedQuantity = null;

                    if (
                        $consumedQuantity !== null
                        && $consumedQuantity !== ''
                        && is_numeric($consumedQuantity)
                    ) {
                        $normalizedConsumedQuantity =
                            (float) $consumedQuantity;
                    }

                    if (
                        $normalizedConsumedQuantity === null
                        || !is_finite(
                            $normalizedConsumedQuantity
                        )
                        || $normalizedConsumedQuantity <= 0
                    ) {
                        throw new RuntimeException(
                            'La cantidad consumida de inventario debe ser un número finito mayor que cero.'
                        );
                    }

                    (new InventoryService())
                        ->consumeClinical(
                            [
                                'inventario_id' =>
                                $inventoryId,

                                'producto_id' =>
                                $productId,

                                'lote_id' =>
                                $lotId,

                                'cantidad' =>
                                $normalizedConsumedQuantity,

                                'referencia_tipo' =>
                                'VACUNACION',

                                'referencia_id' =>
                                $vaccinationId,

                                'observaciones' =>
                                'Consumo automático por vacunación',
                            ],
                            $environmentId,
                            $createdBy
                        );
                }

                /*
                 * Peso opcional.
                 */
                $this->insertWeightIfPresent(
                    $db,
                    $patientId,
                    $data['peso_kg']
                        ?? null,
                    $createdBy,
                    'VACUNACION'
                );

                /*
                 * Crear recordatorio.
                 */
                if (
                    !empty($data['fecha_revacunacion'])
                ) {
                    (new ReminderService())
                        ->scheduleVaccinationReminder(
                            $db,
                            $environmentId,
                            $patient,
                            $vaccinationId,
                            $data['fecha_revacunacion'],
                            $createdBy
                        );
                }

                (new AuditService())
                    ->log(
                        $createdBy,
                        $environmentId,
                        'VACUNAS',
                        'CREAR',
                        'vacunaciones',
                        $vaccinationId,
                        null,
                        [
                            'animal_id'
                            => $patientId,

                            'vacuna_id'
                            => $vaccineId,

                            'fecha_revacunacion'
                            => $data['fecha_revacunacion']
                                ?? null,
                        ]
                    );

                return $eventId;
            }
        );
    }


    public function createDeworming(
        array $data,
        int $environmentId,
        int $createdBy
    ): int {
        return Database::transaction(
            function (PDO $db) use (
                $data,
                $environmentId,
                $createdBy
            ) {
                $patientId
                    = (int) (
                        $data['animal_id']
                        ?? 0
                    );

                $drugId
                    = (int) (
                        $data['farmaco_id']
                        ?? 0
                    );

                if (!$patientId) {
                    throw new RuntimeException(
                        'Debes seleccionar un paciente.'
                    );
                }

                if (!$drugId) {
                    throw new RuntimeException(
                        'Debes seleccionar el fármaco.'
                    );
                }

                $patient
                    = $this->validatePatient(
                        $db,
                        $patientId,
                        $environmentId
                    );

                $this->validateDrug(
                    $db,
                    $drugId
                );

                $eventTypeId
                    = $this->eventTypeId(
                        $db,
                        'DESPARASITACION'
                    );

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
                    'animal'
                    => $patientId,

                    'tipo'
                    => $eventTypeId,

                    'responsable'
                    => $createdBy,

                    'fecha'
                    => $this->dateTimeOrNow(
                        $data['fecha_evento']
                            ?? null
                    ),

                    'titulo'
                    => 'Desparasitación',

                    'observaciones'
                    => trim(
                        $data['observaciones_evento']
                            ?? ''
                    )
                        ?: null,
                ]);

                $eventId
                    = (int)
                    $db
                        ->lastInsertId();

                $stmt = $db->prepare(
                    '
                    INSERT INTO desparasitaciones
                    (
                        evento_clinico_id,
                        farmaco_id,
                        dosis,
                        unidad_dosis_id,
                        proxima_desparasitacion,
                        observaciones,
                        aplicada_por
                    )
                    VALUES
                    (
                        :evento,
                        :farmaco,
                        :dosis,
                        :unidad,
                        :proxima,
                        :observaciones,
                        :usuario
                    )
                    '
                );

                $stmt->execute([
                    'evento'
                    => $eventId,

                    'farmaco'
                    => $drugId,

                    'dosis'
                    => $this->positiveNumberOrNull(
                        $data['dosis']
                            ?? null,
                        'La dosis'
                    ),

                    'unidad'
                    => !empty($data['unidad_dosis_id'])
                        ? (int)
                        $data['unidad_dosis_id']
                        : null,

                    'proxima'
                    => $this->dateOrNull(
                        $data['proxima_desparasitacion']
                            ?? null
                    ),

                    'observaciones'
                    => trim(
                        $data['observaciones']
                            ?? ''
                    )
                        ?: null,

                    'usuario'
                    => $createdBy,
                ]);

                $dewormingId
                    = (int)
                    $db
                        ->lastInsertId();

                /*
                 * Según requisito original,
                 * en Desparasitación se puede
                 * actualizar el peso.
                 */
                $this->insertWeightIfPresent(
                    $db,
                    $patientId,
                    $data['peso_kg']
                        ?? null,
                    $createdBy,
                    'DESPARASITACION'
                );

                if (
                    !empty($data['proxima_desparasitacion'])
                ) {
                    (new ReminderService())
                        ->scheduleDewormingReminder(
                            $db,
                            $environmentId,
                            $patient,
                            $dewormingId,
                            $data['proxima_desparasitacion'],
                            $createdBy
                        );
                }

                (new AuditService())
                    ->log(
                        $createdBy,
                        $environmentId,
                        'DESPARASITACION',
                        'CREAR',
                        'desparasitaciones',
                        $dewormingId,
                        null,
                        [
                            'animal_id'
                            => $patientId,

                            'farmaco_id'
                            => $drugId,

                            'proxima_desparasitacion'
                            => $data['proxima_desparasitacion']
                                ?? null,
                        ]
                    );

                return $eventId;
            }
        );
    }


    private function insertClinicalExam(
        PDO $db,
        int $eventId,
        array $data
    ): void {
        $stmt = $db->prepare(
            '
            INSERT INTO examenes_clinicos_generales
            (
                evento_clinico_id,
                alimentacion,
                historial_reproductivo,
                frecuencia_cardiaca,
                frecuencia_respiratoria,
                temperatura_c,
                tiempo_llenado_capilar_seg,
                ganglios_linfaticos,
                condicion_corporal,
                vomitos,
                diarrea,
                tos,
                observaciones
            )
            VALUES
            (
                :evento,
                :alimentacion,
                :reproductivo,
                :fc,
                :fr,
                :temperatura,
                :tlc,
                :ganglios,
                :condicion,
                :vomitos,
                :diarrea,
                :tos,
                :observaciones
            )
            '
        );

        $stmt->execute([
            'evento'
            => $eventId,

            'alimentacion'
            => trim(
                $data['alimentacion']
                    ?? ''
            )
                ?: null,

            'reproductivo'
            => trim(
                $data['historial_reproductivo']
                    ?? ''
            )
                ?: null,

            'fc'
            => $this->numberOrNull(
                $data['frecuencia_cardiaca']
                    ?? null
            ),

            'fr'
            => $this->numberOrNull(
                $data['frecuencia_respiratoria']
                    ?? null
            ),

            'temperatura'
            => $this->numberOrNull(
                $data['temperatura_c']
                    ?? null
            ),

            'tlc'
            => $this->numberOrNull(
                $data['tiempo_llenado_capilar_seg']
                    ?? null
            ),

            'ganglios'
            => trim(
                $data['ganglios_linfaticos']
                    ?? ''
            )
                ?: null,

            'condicion'
            => trim(
                $data['condicion_corporal']
                    ?? ''
            )
                ?: null,

            'vomitos'
            => !empty($data['vomitos'])
                ? 1
                : 0,

            'diarrea'
            => !empty($data['diarrea'])
                ? 1
                : 0,

            'tos'
            => !empty($data['tos'])
                ? 1
                : 0,

            'observaciones'
            => trim(
                $data['examen_observaciones']
                    ?? ''
            )
                ?: null,
        ]);
    }


    private function insertWeightIfPresent(
        PDO $db,
        int $patientId,
        mixed $value,
        int $createdBy,
        string $origin
    ): void {
        if (
            $value === null
            || $value === ''
        ) {
            return;
        }

        $weight = (float) $value;

        if ($weight <= 0) {
            throw new RuntimeException(
                'El peso debe ser mayor a cero.'
            );
        }

        $stmt = $db->prepare(
            '
            INSERT INTO animales_pesos
            (
                animal_id,
                peso_kg,
                registrado_por,
                origen,
                fecha_registro,
                observacion
            )
            VALUES
            (
                :animal,
                :peso,
                :usuario,
                :origen,
                NOW(),
                :observacion
            )
            '
        );

        $stmt->execute([
            'animal'
            => $patientId,

            'peso'
            => $weight,

            'usuario'
            => $createdBy,

            'origen'
            => $origin,

            'observacion'
            => 'Peso registrado durante '
                . strtolower(
                    str_replace(
                        '_',
                        ' ',
                        $origin
                    )
                )
                . '.',
        ]);
    }


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
                p.usuario_id AS propietario_usuario_id,
                p.email AS propietario_email,
                p.celular AS propietario_celular

            FROM animales a

            LEFT JOIN propietarios_entornos pe
                ON pe.id =
                   a.propietario_entorno_id

            LEFT JOIN propietarios p
                ON p.id =
                   pe.propietario_id

            WHERE a.id = :animal

              AND a.entorno_id
                = :entorno

              AND a.activo = 1

              AND a.deleted_at
                IS NULL

            LIMIT 1
            '
        );

        $stmt->execute([
            'animal'
            => $patientId,

            'entorno'
            => $environmentId,
        ]);

        $patient
            = $stmt->fetch();

        if (!$patient) {
            throw new RuntimeException(
                'El paciente no pertenece al entorno actual.'
            );
        }

        return $patient;
    }


    private function validateVaccine(
        PDO $db,
        int $vaccineId
    ): void {
        $stmt = $db->prepare(
            '
            SELECT COUNT(*)

            FROM vacunas

            WHERE id = :id
              AND activo = 1
            '
        );

        $stmt->execute([
            'id'
            => $vaccineId,
        ]);

        if (
            (int)
            $stmt->fetchColumn()
            === 0
        ) {
            throw new RuntimeException(
                'La vacuna seleccionada no está disponible.'
            );
        }
    }


    private function validateDrug(
        PDO $db,
        int $drugId
    ): void {
        $stmt = $db->prepare(
            '
            SELECT COUNT(*)

            FROM farmacos

            WHERE id = :id
              AND activo = 1
            '
        );

        $stmt->execute([
            'id'
            => $drugId,
        ]);

        if (
            (int)
            $stmt->fetchColumn()
            === 0
        ) {
            throw new RuntimeException(
                'El fármaco seleccionado no está disponible.'
            );
        }
    }


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
            'codigo'
            => $code,
        ]);

        $id = $stmt->fetchColumn();

        if (!$id) {
            throw new RuntimeException(
                'No existe el tipo de evento clínico '
                    . $code
                    . '.'
            );
        }

        return (int) $id;
    }


    private function positiveNumberOrNull(
        mixed $value,
        string $label
    ): ?float {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        $number = (float) $value;

        if ($number <= 0) {
            throw new RuntimeException(
                $label
                    . ' debe ser mayor a cero.'
            );
        }

        return $number;
    }


    private function numberOrNull(
        mixed $value
    ): ?float {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        return (float) $value;
    }


    private function dateOrNull(
        mixed $value
    ): ?string {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        $date = \DateTimeImmutable
            ::createFromFormat(
                'Y-m-d',
                (string) $value
            );

        if (!$date) {
            throw new RuntimeException(
                'La fecha ingresada no es válida.'
            );
        }

        return $date->format(
            'Y-m-d'
        );
    }


    private function dateTimeOrNow(
        mixed $value
    ): string {
        if (
            $value === null
            || $value === ''
        ) {
            return date(
                'Y-m-d H:i:s'
            );
        }

        /*
         * datetime-local:
         * 2026-09-07T15:30
         */
        $value = str_replace(
            'T',
            ' ',
            (string) $value
        );

        if (
            strlen($value)
            === 16
        ) {
            $value .= ':00';
        }

        return $value;
    }

    public function updateVaccination(
        int $vaccinationId,
        array $data,
        int $environmentId,
        int $updatedBy
    ): void {
        Database::transaction(
            function (PDO $db) use (
                $vaccinationId,
                $data,
                $environmentId,
                $updatedBy
            ): void {
                if ($vaccinationId <= 0) {
                    throw new RuntimeException(
                        'La vacunación es obligatoria.'
                    );
                }

                $vaccineId = (int) (
                    $data['vacuna_id']
                    ?? 0
                );

                if ($vaccineId <= 0) {
                    throw new RuntimeException(
                        'Debes seleccionar una vacuna.'
                    );
                }

                /*
             * Validamos que la vacuna seleccionada
             * continúe disponible.
             */
                $this->validateVaccine(
                    $db,
                    $vaccineId
                );

                /*
             * Recuperamos y bloqueamos el registro.
             */
                $stmt = $db->prepare(
                    '
                SELECT
                    vac.id,
                    vac.evento_clinico_id,
                    vac.vacuna_id,
                    vac.dosis,
                    vac.unidad_dosis_id,
                    vac.lote,
                    vac.casa_comercial,
                    vac.fecha_revacunacion,
                    vac.observaciones,

                    ec.fecha_evento,
                    ec.anulado_at,

                    a.id AS animal_id,
                    a.entorno_id

                FROM vacunaciones vac

                INNER JOIN eventos_clinicos ec
                    ON ec.id =
                       vac.evento_clinico_id

                INNER JOIN animales a
                    ON a.id =
                       ec.animal_id

                WHERE vac.id = :vacunacion

                LIMIT 1

                FOR UPDATE
                '
                );

                $stmt->execute([
                    'vacunacion'
                    => $vaccinationId,
                ]);

                $current = $stmt->fetch();

                if (!$current) {
                    throw new RuntimeException(
                        'Vacunación no encontrada.'
                    );
                }

                if (
                    (int)$current['entorno_id']
                    !== $environmentId
                ) {
                    throw new RuntimeException(
                        'La vacunación no pertenece al entorno actual.'
                    );
                }

                if (!empty($current['anulado_at'])) {
                    throw new RuntimeException(
                        'No puedes editar una vacunación anulada.'
                    );
                }

                /*
             * Unidad opcional.
             */
                $unitId = !empty($data['unidad_dosis_id'])
                    ? (int)$data['unidad_dosis_id']
                    : null;

                /*
             * Datos normalizados.
             */
                $dose = $this->positiveNumberOrNull(
                    $data['dosis']
                        ?? null,
                    'La dosis'
                );

                $lot = trim(
                    (string)($data['lote'] ?? '')
                );

                $commercialHouse = trim(
                    (string)($data['casa_comercial'] ?? '')
                );

                $observations = trim(
                    (string)($data['observaciones'] ?? '')
                );

                $revaccinationDate =
                    $this->dateOrNull(
                        $data['fecha_revacunacion']
                            ?? null
                    );

                /*
             * Actualizamos vacunación.
             */
                $stmt = $db->prepare(
                    '
                UPDATE vacunaciones

                SET
                    vacuna_id = :vacuna,
                    dosis = :dosis,
                    unidad_dosis_id = :unidad,
                    lote = :lote,
                    casa_comercial = :casa_comercial,
                    fecha_revacunacion = :revacunacion,
                    observaciones = :observaciones

                WHERE id = :id
                '
                );

                $stmt->execute([
                    'vacuna'
                    => $vaccineId,

                    'dosis'
                    => $dose,

                    'unidad'
                    => $unitId,

                    'lote'
                    => $lot !== ''
                        ? $lot
                        : null,

                    'casa_comercial'
                    => $commercialHouse !== ''
                        ? $commercialHouse
                        : null,

                    'revacunacion'
                    => $revaccinationDate,

                    'observaciones'
                    => $observations !== ''
                        ? $observations
                        : null,

                    'id'
                    => $vaccinationId,
                ]);

                /*
             * La fecha del evento también puede
             * modificarse desde el formulario.
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
                    SET fecha_evento = :fecha
                    WHERE id = :id
                      AND anulado_at IS NULL
                    '
                    );

                    $stmt->execute([
                        'fecha'
                        => $this->dateTimeOrNow(
                            $data['fecha_evento']
                        ),

                        'id'
                        => (int)$current['evento_clinico_id'],
                    ]);
                }

                /*
             * Cancelamos recordatorios pendientes
             * anteriores.
             *
             * Los enviados/fallidos se conservan
             * como histórico.
             */
                $stmt = $db->prepare(
                    '
                UPDATE notificaciones

                SET estado = "CANCELADA"

                WHERE referencia_tipo =
                      "VACUNACION"

                  AND referencia_id =
                      :vacunacion

                  AND entorno_id =
                      :entorno

                  AND estado IN (
                      "PENDIENTE",
                      "PROGRAMADA"
                  )
                '
                );

                $stmt->execute([
                    'vacunacion'
                    => $vaccinationId,

                    'entorno'
                    => $environmentId,
                ]);

                /*
             * Si existe nueva fecha,
             * reconstruimos el recordatorio.
             */
                if ($revaccinationDate !== null) {
                    $patient = $this->validatePatient(
                        $db,
                        (int)$current['animal_id'],
                        $environmentId
                    );

                    (new ReminderService())
                        ->scheduleVaccinationReminder(
                            $db,
                            $environmentId,
                            $patient,
                            $vaccinationId,
                            $revaccinationDate,
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
                        'VACUNAS',
                        'EDITAR',
                        'vacunaciones',
                        $vaccinationId,
                        [
                            'vacuna_id'
                            => (int)$current['vacuna_id'],

                            'dosis'
                            => $current['dosis'],

                            'unidad_dosis_id'
                            => $current['unidad_dosis_id'],

                            'lote'
                            => $current['lote'],

                            'casa_comercial'
                            => $current['casa_comercial'],

                            'fecha_revacunacion'
                            => $current['fecha_revacunacion'],

                            'observaciones'
                            => $current['observaciones'],
                        ],
                        [
                            'vacuna_id'
                            => $vaccineId,

                            'dosis'
                            => $dose,

                            'unidad_dosis_id'
                            => $unitId,

                            'lote'
                            => $lot !== ''
                                ? $lot
                                : null,

                            'casa_comercial'
                            => $commercialHouse !== ''
                                ? $commercialHouse
                                : null,

                            'fecha_revacunacion'
                            => $revaccinationDate,

                            'observaciones'
                            => $observations !== ''
                                ? $observations
                                : null,
                        ]
                    );
            }
        );
    }

    public function updateDeworming(
        int $dewormingId,
        array $data,
        int $environmentId,
        int $updatedBy
    ): void {
        Database::transaction(
            function (PDO $db) use (
                $dewormingId,
                $data,
                $environmentId,
                $updatedBy
            ): void {
                if ($dewormingId <= 0) {
                    throw new RuntimeException(
                        'La desparasitación es obligatoria.'
                    );
                }

                $drugId = (int) (
                    $data['farmaco_id']
                    ?? 0
                );

                if ($drugId <= 0) {
                    throw new RuntimeException(
                        'Debes seleccionar el fármaco.'
                    );
                }

                $this->validateDrug(
                    $db,
                    $drugId
                );

                /*
             * Recuperamos y bloqueamos
             * el registro actual.
             */
                $stmt = $db->prepare(
                    '
                SELECT
                    d.id,
                    d.evento_clinico_id,
                    d.farmaco_id,
                    d.dosis,
                    d.unidad_dosis_id,
                    d.proxima_desparasitacion,
                    d.observaciones,

                    ec.fecha_evento,
                    ec.anulado_at,

                    a.id AS animal_id,
                    a.entorno_id

                FROM desparasitaciones d

                INNER JOIN eventos_clinicos ec
                    ON ec.id =
                       d.evento_clinico_id

                INNER JOIN animales a
                    ON a.id =
                       ec.animal_id

                WHERE d.id = :desparasitacion

                LIMIT 1

                FOR UPDATE
                '
                );

                $stmt->execute([
                    'desparasitacion'
                    => $dewormingId,
                ]);

                $current = $stmt->fetch();

                if (!$current) {
                    throw new RuntimeException(
                        'Desparasitación no encontrada.'
                    );
                }

                if (
                    (int)$current['entorno_id']
                    !== $environmentId
                ) {
                    throw new RuntimeException(
                        'La desparasitación no pertenece al entorno actual.'
                    );
                }

                if (!empty($current['anulado_at'])) {
                    throw new RuntimeException(
                        'No puedes editar una desparasitación anulada.'
                    );
                }

                $unitId = !empty($data['unidad_dosis_id'])
                    ? (int)$data['unidad_dosis_id']
                    : null;

                $dose = $this->positiveNumberOrNull(
                    $data['dosis']
                        ?? null,
                    'La dosis'
                );

                $nextDate = $this->dateOrNull(
                    $data['proxima_desparasitacion']
                        ?? null
                );

                $observations = trim(
                    (string) (
                        $data['observaciones']
                        ?? ''
                    )
                );

                /*
             * Actualizamos el registro.
             */
                $stmt = $db->prepare(
                    '
                UPDATE desparasitaciones

                SET
                    farmaco_id = :farmaco,
                    dosis = :dosis,
                    unidad_dosis_id = :unidad,
                    proxima_desparasitacion = :proxima,
                    observaciones = :observaciones

                WHERE id = :id
                '
                );

                $stmt->execute([
                    'farmaco'
                    => $drugId,

                    'dosis'
                    => $dose,

                    'unidad'
                    => $unitId,

                    'proxima'
                    => $nextDate,

                    'observaciones'
                    => $observations !== ''
                        ? $observations
                        : null,

                    'id'
                    => $dewormingId,
                ]);

                /*
             * Fecha del evento.
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

                    SET fecha_evento = :fecha

                    WHERE id = :id
                      AND anulado_at IS NULL
                    '
                    );

                    $stmt->execute([
                        'fecha'
                        => $this->dateTimeOrNow(
                            $data['fecha_evento']
                        ),

                        'id'
                        => (int)$current['evento_clinico_id'],
                    ]);
                }

                /*
             * Cancelamos recordatorios pendientes
             * anteriores.
             */
                $stmt = $db->prepare(
                    '
                UPDATE notificaciones

                SET estado = "CANCELADA"

                WHERE referencia_tipo =
                      "DESPARASITACION"

                  AND referencia_id =
                      :desparasitacion

                  AND entorno_id =
                      :entorno

                  AND estado IN (
                      "PENDIENTE",
                      "PROGRAMADA"
                  )
                '
                );

                $stmt->execute([
                    'desparasitacion'
                    => $dewormingId,

                    'entorno'
                    => $environmentId,
                ]);

                /*
             * Creamos el nuevo recordatorio
             * solamente si hay próxima fecha.
             */
                if ($nextDate !== null) {
                    $patient = $this->validatePatient(
                        $db,
                        (int)$current['animal_id'],
                        $environmentId
                    );

                    (new ReminderService())
                        ->scheduleDewormingReminder(
                            $db,
                            $environmentId,
                            $patient,
                            $dewormingId,
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
                        'DESPARASITACION',
                        'EDITAR',
                        'desparasitaciones',
                        $dewormingId,
                        [
                            'farmaco_id'
                            => (int)$current['farmaco_id'],

                            'dosis'
                            => $current['dosis'],

                            'unidad_dosis_id'
                            => $current['unidad_dosis_id'],

                            'proxima_desparasitacion'
                            => $current['proxima_desparasitacion'],

                            'observaciones'
                            => $current['observaciones'],
                        ],
                        [
                            'farmaco_id'
                            => $drugId,

                            'dosis'
                            => $dose,

                            'unidad_dosis_id'
                            => $unitId,

                            'proxima_desparasitacion'
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

    public function cancelDeworming(
        int $dewormingId,
        string $reason,
        int $environmentId,
        int $cancelledBy
    ): void {
        Database::transaction(
            function (PDO $db) use (
                $dewormingId,
                $reason,
                $environmentId,
                $cancelledBy
            ): void {
                $reason = trim($reason);

                if ($dewormingId <= 0) {
                    throw new RuntimeException(
                        'La desparasitación es obligatoria.'
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
                    d.id AS desparasitacion_id,
                    d.evento_clinico_id,

                    ec.anulado_at,
                    ec.anulado_por,
                    ec.motivo_anulacion,

                    a.entorno_id

                FROM desparasitaciones d

                INNER JOIN eventos_clinicos ec
                    ON ec.id =
                       d.evento_clinico_id

                INNER JOIN animales a
                    ON a.id =
                       ec.animal_id

                WHERE d.id =
                      :desparasitacion

                LIMIT 1

                FOR UPDATE
                '
                );

                $stmt->execute([
                    'desparasitacion'
                    => $dewormingId,
                ]);

                $deworming = $stmt->fetch();

                if (!$deworming) {
                    throw new RuntimeException(
                        'Desparasitación no encontrada.'
                    );
                }

                if (
                    (int)$deworming['entorno_id']
                    !== $environmentId
                ) {
                    throw new RuntimeException(
                        'La desparasitación no pertenece al entorno actual.'
                    );
                }

                if (!empty($deworming['anulado_at'])) {
                    throw new RuntimeException(
                        'La desparasitación ya fue anulada.'
                    );
                }

                /*
                * Cancelamos exclusivamente
                * notificaciones pendientes.
                */
                $stmt = $db->prepare(
                    '
                UPDATE notificaciones

                SET estado = "CANCELADA"

                WHERE referencia_tipo =
                      "DESPARASITACION"

                  AND referencia_id =
                      :desparasitacion

                  AND entorno_id =
                      :entorno

                  AND estado IN (
                      "PENDIENTE",
                      "PROGRAMADA"
                  )
                '
                );

                $stmt->execute([
                    'desparasitacion'
                    => $dewormingId,

                    'entorno'
                    => $environmentId,
                ]);

                $cancelledNotifications =
                    $stmt->rowCount();

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
                    'usuario'
                    => $cancelledBy,

                    'motivo'
                    => $reason,

                    'evento'
                    => (int)$deworming['evento_clinico_id'],
                ]);

                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException(
                        'No fue posible anular la desparasitación.'
                    );
                }

                /*
             * Auditoría.
             */
                (new AuditService())
                    ->log(
                        $cancelledBy,
                        $environmentId,
                        'DESPARASITACION',
                        'ANULAR',
                        'desparasitaciones',
                        $dewormingId,
                        null,
                        [
                            'evento_clinico_id'
                            => (int)$deworming['evento_clinico_id'],

                            'motivo_anulacion'
                            => $reason,

                            'notificaciones_canceladas'
                            => $cancelledNotifications,
                        ]
                    );
            }
        );
    }

    public function cancelVaccination(
        int $vaccinationId,
        string $reason,
        int $environmentId,
        int $cancelledBy
    ): void {
        Database::transaction(
            function (PDO $db) use (
                $vaccinationId,
                $reason,
                $environmentId,
                $cancelledBy
            ): void {
                $reason =
                    trim($reason);

                if ($vaccinationId <= 0) {
                    throw new RuntimeException(
                        'La vacunación es obligatoria.'
                    );
                }

                if ($reason === '') {
                    throw new RuntimeException(
                        'El motivo de anulación es obligatorio.'
                    );
                }

                /*
             * Bloqueamos la vacunación y su evento.
             */
                $stmt = $db->prepare(
                    '
                SELECT
                    v.id AS vacunacion_id,
                    v.evento_clinico_id,

                    ec.anulado_at,
                    ec.anulado_por,
                    ec.motivo_anulacion,

                    a.entorno_id

                FROM vacunaciones v

                INNER JOIN eventos_clinicos ec
                    ON ec.id =
                       v.evento_clinico_id

                INNER JOIN animales a
                    ON a.id =
                       ec.animal_id

                WHERE v.id =
                      :vacunacion

                LIMIT 1

                FOR UPDATE
                '
                );

                $stmt->execute([
                    'vacunacion' =>
                    $vaccinationId,
                ]);

                $vaccination =
                    $stmt->fetch();

                if (!$vaccination) {
                    throw new RuntimeException(
                        'Vacunación no encontrada.'
                    );
                }

                if (
                    (int) $vaccination['entorno_id']
                    !== $environmentId
                ) {
                    throw new RuntimeException(
                        'La vacunación no pertenece al entorno actual.'
                    );
                }

                if (
                    !empty($vaccination['anulado_at'])
                ) {
                    throw new RuntimeException(
                        'La vacunación ya fue anulada.'
                    );
                }

                /*
             * Reverso del consumo.
             *
             * Si la vacunación es histórica y nunca tuvo
             * integración con inventario devuelve [].
             */
                $reversalIds =
                    (new InventoryService())
                    ->reverseClinicalConsumption(
                        'VACUNACION',
                        $vaccinationId,
                        $environmentId,
                        $cancelledBy,
                        'Anulación de vacunación: '
                            . $reason
                    );

                /*
             * Cancelamos exclusivamente notificaciones
             * todavía pendientes.
             *
             * ENVIADA y FALLIDA se conservan intactas
             * como histórico.
             */
                $stmt = $db->prepare(
                    '
                UPDATE notificaciones
                SET estado = "CANCELADA"
                WHERE referencia_tipo =
                      "VACUNACION"
                  AND referencia_id =
                      :vacunacion
                  AND entorno_id =
                      :entorno
                  AND estado IN (
                      "PENDIENTE",
                      "PROGRAMADA"
                  )
                '
                );

                $stmt->execute([
                    'vacunacion' =>
                    $vaccinationId,

                    'entorno' =>
                    $environmentId,
                ]);

                $cancelledNotifications =
                    $stmt->rowCount();

                /*
             * La vacunación permanece físicamente.
             * Anulamos su evento clínico padre.
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
                    'usuario' =>
                    $cancelledBy,

                    'motivo' =>
                    $reason,

                    'evento' =>
                    (int)
                    $vaccination['evento_clinico_id'],
                ]);

                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException(
                        'No fue posible anular la vacunación.'
                    );
                }

                (new AuditService())
                    ->log(
                        $cancelledBy,
                        $environmentId,
                        'VACUNAS',
                        'ANULAR',
                        'vacunaciones',
                        $vaccinationId,
                        null,
                        [
                            'evento_clinico_id' =>
                            (int)
                            $vaccination['evento_clinico_id'],

                            'motivo_anulacion' =>
                            $reason,

                            'reversos_inventario' =>
                            $reversalIds,

                            'notificaciones_canceladas' =>
                            $cancelledNotifications,
                        ]
                    );
            }
        );
    }
}
