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
                            $data[
                                'fecha_evento'
                            ]
                            ?? null
                        ),

                    'titulo'
                        => 'Vacunación',

                    'observaciones'
                        => trim(
                            $data[
                                'observaciones_evento'
                            ]
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
                 * vacunación reutiliza el
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
                        => !empty(
                            $data[
                                'unidad_dosis_id'
                            ]
                        )
                            ? (int)
                                $data[
                                    'unidad_dosis_id'
                                ]
                            : null,

                    'lote'
                        => trim(
                            $data['lote']
                            ?? ''
                        )
                            ?: null,

                    'revacunacion'
                        => $this->dateOrNull(
                            $data[
                                'fecha_revacunacion'
                            ]
                            ?? null
                        ),

                    'observaciones'
                        => trim(
                            $data[
                                'observaciones'
                            ]
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
                 * Peso opcional.
                 */
                $this->insertWeightIfPresent(
                    $db,
                    $patientId,
                    $data[
                        'peso_kg'
                    ]
                    ?? null,
                    $createdBy,
                    'VACUNACION'
                );

                /*
                 * Crear recordatorio.
                 */
                if (
                    !empty(
                        $data[
                            'fecha_revacunacion'
                        ]
                    )
                ) {
                    (new ReminderService())
                        ->scheduleVaccinationReminder(
                            $db,
                            $environmentId,
                            $patient,
                            $vaccinationId,
                            $data[
                                'fecha_revacunacion'
                            ],
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
                                => $data[
                                    'fecha_revacunacion'
                                ]
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
                            $data[
                                'fecha_evento'
                            ]
                            ?? null
                        ),

                    'titulo'
                        => 'Desparasitación',

                    'observaciones'
                        => trim(
                            $data[
                                'observaciones_evento'
                            ]
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
                        => !empty(
                            $data[
                                'unidad_dosis_id'
                            ]
                        )
                            ? (int)
                                $data[
                                    'unidad_dosis_id'
                                ]
                            : null,

                    'proxima'
                        => $this->dateOrNull(
                            $data[
                                'proxima_desparasitacion'
                            ]
                            ?? null
                        ),

                    'observaciones'
                        => trim(
                            $data[
                                'observaciones'
                            ]
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
                 * en desparasitación se puede
                 * actualizar el peso.
                 */
                $this->insertWeightIfPresent(
                    $db,
                    $patientId,
                    $data[
                        'peso_kg'
                    ]
                    ?? null,
                    $createdBy,
                    'DESPARASITACION'
                );

                if (
                    !empty(
                        $data[
                            'proxima_desparasitacion'
                        ]
                    )
                ) {
                    (new ReminderService())
                        ->scheduleDewormingReminder(
                            $db,
                            $environmentId,
                            $patient,
                            $dewormingId,
                            $data[
                                'proxima_desparasitacion'
                            ],
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
                                => $data[
                                    'proxima_desparasitacion'
                                ]
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
                    $data[
                        'alimentacion'
                    ]
                    ?? ''
                )
                    ?: null,

            'reproductivo'
                => trim(
                    $data[
                        'historial_reproductivo'
                    ]
                    ?? ''
                )
                    ?: null,

            'fc'
                => $this->numberOrNull(
                    $data[
                        'frecuencia_cardiaca'
                    ]
                    ?? null
                ),

            'fr'
                => $this->numberOrNull(
                    $data[
                        'frecuencia_respiratoria'
                    ]
                    ?? null
                ),

            'temperatura'
                => $this->numberOrNull(
                    $data[
                        'temperatura_c'
                    ]
                    ?? null
                ),

            'tlc'
                => $this->numberOrNull(
                    $data[
                        'tiempo_llenado_capilar_seg'
                    ]
                    ?? null
                ),

            'ganglios'
                => trim(
                    $data[
                        'ganglios_linfaticos'
                    ]
                    ?? ''
                )
                    ?: null,

            'condicion'
                => trim(
                    $data[
                        'condicion_corporal'
                    ]
                    ?? ''
                )
                    ?: null,

            'vomitos'
                => !empty(
                    $data['vomitos']
                )
                    ? 1
                    : 0,

            'diarrea'
                => !empty(
                    $data['diarrea']
                )
                    ? 1
                    : 0,

            'tos'
                => !empty(
                    $data['tos']
                )
                    ? 1
                    : 0,

            'observaciones'
                => trim(
                    $data[
                        'examen_observaciones'
                    ]
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
}