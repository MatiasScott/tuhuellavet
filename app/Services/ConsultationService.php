<?php

namespace App\Services;

use App\Core\Database;

use PDO;
use RuntimeException;

class ConsultationService
{
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
            ) {
                $patientId = (int) (
                    $data['animal_id']
                    ?? 0
                );

                if (!$patientId) {
                    throw new RuntimeException(
                        'Debes seleccionar un paciente.'
                    );
                }

                $this->validatePatient(
                    $db,
                    $patientId,
                    $environmentId
                );

                $eventTypeId = $this
                    ->getEventTypeId(
                        $db,
                        'CONSULTA_EXTERNA'
                    );

                /*
                 * Evento clÃ­nico.
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
                        => !empty(
                            $data['fecha_evento']
                        )
                            ? $data['fecha_evento']
                            : date(
                                'Y-m-d H:i:s'
                            ),

                    'titulo'
                        => trim(
                            $data['titulo']
                            ?? ''
                        )
                            ?: 'Consulta externa',

                    'observaciones'
                        => trim(
                            $data[
                                'observaciones_evento'
                            ]
                            ?? ''
                        )
                            ?: null,
                ]);

                $eventId = (int)
                    $db->lastInsertId();

                /*
                 * Consulta.
                 */
                $stmt = $db->prepare(
                    '
                    INSERT INTO consultas_externas
                    (
                        evento_clinico_id,
                        motivo_consulta,
                        anamnesis,
                        antecedentes,
                        recomendaciones
                    )
                    VALUES
                    (
                        :evento,
                        :motivo,
                        :anamnesis,
                        :antecedentes,
                        :recomendaciones
                    )
                    '
                );

                $stmt->execute([
                    'evento'
                        => $eventId,

                    'motivo'
                        => trim(
                            $data[
                                'motivo_consulta'
                            ]
                            ?? ''
                        )
                            ?: null,

                    'anamnesis'
                        => trim(
                            $data[
                                'anamnesis'
                            ]
                            ?? ''
                        )
                            ?: null,

                    'antecedentes'
                        => trim(
                            $data[
                                'antecedentes'
                            ]
                            ?? ''
                        )
                            ?: null,

                    'recomendaciones'
                        => trim(
                            $data[
                                'recomendaciones'
                            ]
                            ?? ''
                        )
                            ?: null,
                ]);

                /*
                 * Examen clÃ­nico general.
                 */
                $this->createClinicalExam(
                    $db,
                    $eventId,
                    $data
                );

                /*
                 * DiagnÃ³sticos.
                 */
                $this->createDiagnoses(
                    $db,
                    $eventId,
                    $data,
                    $createdBy
                );

                /*
                 * Peso opcional tomado
                 * durante consulta.
                 */
                if (
                    isset(
                        $data['peso_kg']
                    )
                    && $data['peso_kg'] !== ''
                ) {
                    $weight = (float)
                        $data['peso_kg'];

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
                            "CONSULTA_EXTERNA",
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

                        'observacion'
                            => 'Peso registrado durante consulta externa.',
                    ]);
                }

                (new AuditService())
                    ->log(
                        $createdBy,
                        $environmentId,
                        'CONSULTAS',
                        'CREAR',
                        'eventos_clinicos',
                        $eventId,
                        null,
                        [
                            'animal_id'
                                => $patientId,

                            'motivo_consulta'
                                => $data[
                                    'motivo_consulta'
                                ]
                                ?? null,
                        ]
                    );

                return $eventId;
            }
        );
    }


    private function createClinicalExam(
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
                :historial_reproductivo,
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

            'historial_reproductivo'
                => trim(
                    $data[
                        'historial_reproductivo'
                    ]
                    ?? ''
                )
                    ?: null,

            'fc'
                => $this->decimalOrNull(
                    $data[
                        'frecuencia_cardiaca'
                    ]
                    ?? null
                ),

            'fr'
                => $this->decimalOrNull(
                    $data[
                        'frecuencia_respiratoria'
                    ]
                    ?? null
                ),

            'temperatura'
                => $this->decimalOrNull(
                    $data[
                        'temperatura_c'
                    ]
                    ?? null
                ),

            'tlc'
                => $this->decimalOrNull(
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


    private function createDiagnoses(
        PDO $db,
        int $eventId,
        array $data,
        int $createdBy
    ): void {
        $map = [
            'diagnostico_diferencial'
                => 'DIFERENCIAL',

            'diagnostico_presuntivo'
                => 'PRESUNTIVO',

            'diagnostico_definitivo'
                => 'DEFINITIVO',
        ];

        foreach (
            $map
            as $field => $typeCode
        ) {
            $value = trim(
                (string) (
                    $data[$field]
                    ?? ''
                )
            );

            if ($value === '') {
                continue;
            }

            $typeId = $this
                ->getDiagnosisTypeId(
                    $db,
                    $typeCode
                );

            $stmt = $db->prepare(
                '
                INSERT INTO diagnosticos_clinicos
                (
                    evento_clinico_id,
                    tipo_diagnostico_id,
                    descripcion,
                    ingresado_por
                )
                VALUES
                (
                    :evento,
                    :tipo,
                    :descripcion,
                    :usuario
                )
                '
            );

            $stmt->execute([
                'evento'
                    => $eventId,

                'tipo'
                    => $typeId,

                'descripcion'
                    => $value,

                'usuario'
                    => $createdBy,
            ]);
        }
    }


    private function validatePatient(
        PDO $db,
        int $patientId,
        int $environmentId
    ): void {
        $stmt = $db->prepare(
            '
            SELECT COUNT(*)

            FROM animales

            WHERE id = :animal
              AND entorno_id = :entorno
              AND activo = 1
              AND deleted_at IS NULL
            '
        );

        $stmt->execute([
            'animal'
                => $patientId,

            'entorno'
                => $environmentId,
        ]);

        if (
            (int)
                $stmt->fetchColumn()
            === 0
        ) {
            throw new RuntimeException(
                'El paciente no pertenece al entorno actual.'
            );
        }
    }


    private function getEventTypeId(
        PDO $db,
        string $code
    ): int {
        $stmt = $db->prepare(
            '
            SELECT id

            FROM tipos_evento_clinico

            WHERE codigo = :codigo

            LIMIT 1
            '
        );

        $stmt->execute([
            'codigo' => $code,
        ]);

        $id = $stmt->fetchColumn();

        if (!$id) {
            throw new RuntimeException(
                'No existe el tipo de evento clÃ­nico solicitado.'
            );
        }

        return (int) $id;
    }


    private function getDiagnosisTypeId(
        PDO $db,
        string $code
    ): int {
        $stmt = $db->prepare(
            '
            SELECT id

            FROM tipos_diagnostico

            WHERE codigo = :codigo

            LIMIT 1
            '
        );

        $stmt->execute([
            'codigo' => $code,
        ]);

        $id = $stmt->fetchColumn();

        if (!$id) {
            throw new RuntimeException(
                'No existe el tipo de diagnÃ³stico.'
            );
        }

        return (int) $id;
    }


    private function decimalOrNull(
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
}
