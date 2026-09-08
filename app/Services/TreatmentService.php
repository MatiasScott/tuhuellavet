<?php

namespace App\Services;

use App\Core\Database;

use PDO;
use RuntimeException;

class TreatmentService
{
    public function create(
        int $eventId,
        array $data,
        int $environmentId,
        int $createdBy
    ): int {
        return Database::transaction(
            function (PDO $db) use (
                $eventId,
                $data,
                $environmentId,
                $createdBy
            ) {
                $this->validateEvent(
                    $db,
                    $eventId,
                    $environmentId
                );

                $typeId
                    = (int)
                        (
                            $data[
                                'tipo_tratamiento_id'
                            ]
                            ?? 0
                        );

                if (!$typeId) {
                    throw new RuntimeException(
                        'Debes seleccionar el tipo de tratamiento.'
                    );
                }

                $this->validateTreatmentType(
                    $db,
                    $typeId
                );

                $stmt = $db->prepare(
                    '
                    INSERT INTO tratamientos
                    (
                        evento_clinico_id,
                        tipo_tratamiento_id,
                        indicado_por,
                        fecha_inicio,
                        fecha_fin,
                        instrucciones_generales,
                        observaciones
                    )
                    VALUES
                    (
                        :evento,
                        :tipo,
                        :usuario,
                        :inicio,
                        :fin,
                        :instrucciones,
                        :observaciones
                    )
                    '
                );

                $stmt->execute([
                    'evento'
                        => $eventId,

                    'tipo'
                        => $typeId,

                    'usuario'
                        => $createdBy,

                    'inicio'
                        => !empty(
                            $data[
                                'fecha_inicio'
                            ]
                        )
                            ? $data[
                                'fecha_inicio'
                            ]
                            : date(
                                'Y-m-d H:i:s'
                            ),

                    'fin'
                        => !empty(
                            $data['fecha_fin']
                        )
                            ? $data[
                                'fecha_fin'
                            ]
                            : null,

                    'instrucciones'
                        => trim(
                            $data[
                                'instrucciones_generales'
                            ]
                            ?? ''
                        )
                            ?: null,

                    'observaciones'
                        => trim(
                            $data[
                                'observaciones'
                            ]
                            ?? ''
                        )
                            ?: null,
                ]);

                $treatmentId
                    = (int)
                        $db
                            ->lastInsertId();

                $medications
                    = $data[
                        'medicamentos'
                    ]
                    ?? [];

                if (
                    is_array(
                        $medications
                    )
                ) {
                    foreach (
                        $medications
                        as $index => $medication
                    ) {
                        if (
                            empty(
                                $medication[
                                    'farmaco_id'
                                ]
                            )
                        ) {
                            continue;
                        }

                        $this->insertMedication(
                            $db,
                            $treatmentId,
                            $medication,
                            $index
                        );
                    }
                }

                (new AuditService())
                    ->log(
                        $createdBy,
                        $environmentId,
                        'TRATAMIENTOS',
                        'CREAR',
                        'tratamientos',
                        $treatmentId,
                        null,
                        [
                            'evento_clinico_id'
                                => $eventId,

                            'tipo_tratamiento_id'
                                => $typeId,
                        ]
                    );

                return $treatmentId;
            }
        );
    }


    public function addApplication(
        int $medicationId,
        float $quantity,
        ?int $unitId,
        ?string $observation,
        int $environmentId,
        int $createdBy
    ): void {
        if ($quantity <= 0) {
            throw new RuntimeException(
                'La cantidad aplicada debe ser mayor a cero.'
            );
        }

        $db
            = Database::connection();

        /*
         * Validamos que el medicamento
         * pertenezca a un paciente
         * del entorno actual.
         */
        $stmt = $db->prepare(
            '
            SELECT tm.id

            FROM tratamiento_medicamentos tm

            INNER JOIN tratamientos t
                ON t.id =
                   tm.tratamiento_id

            INNER JOIN eventos_clinicos ec
                ON ec.id =
                   t.evento_clinico_id

            INNER JOIN animales a
                ON a.id =
                   ec.animal_id

            WHERE tm.id = :medicamento

              AND a.entorno_id
                = :entorno

            LIMIT 1
            '
        );

        $stmt->execute([
            'medicamento'
                => $medicationId,

            'entorno'
                => $environmentId,
        ]);

        if (
            !$stmt->fetchColumn()
        ) {
            throw new RuntimeException(
                'Medicamento no encontrado.'
            );
        }

        $stmt = $db->prepare(
            '
            INSERT INTO medicamento_aplicaciones
            (
                tratamiento_medicamento_id,
                aplicado_por,
                fecha_hora,
                cantidad_aplicada,
                unidad_id,
                observaciones
            )
            VALUES
            (
                :medicamento,
                :usuario,
                NOW(),
                :cantidad,
                :unidad,
                :observaciones
            )
            '
        );

        $stmt->execute([
            'medicamento'
                => $medicationId,

            'usuario'
                => $createdBy,

            'cantidad'
                => $quantity,

            'unidad'
                => $unitId,

            'observaciones'
                => $observation,
        ]);

        (new AuditService())
            ->log(
                $createdBy,
                $environmentId,
                'TRATAMIENTOS',
                'APLICAR_MEDICAMENTO',
                'medicamento_aplicaciones',
                (int)
                    $db
                        ->lastInsertId()
            );
    }


    private function insertMedication(
        PDO $db,
        int $treatmentId,
        array $data,
        int $order
    ): void {
        $drugId
            = (int)
                $data[
                    'farmaco_id'
                ];

        $this->validateMedicationData(
            $db,
            $drugId,
            $data
        );

        $stmt = $db->prepare(
            '
            INSERT INTO tratamiento_medicamentos
            (
                tratamiento_id,
                farmaco_id,
                presentacion_id,
                via_administracion_id,
                dosis_cantidad,
                dosis_unidad_id,
                frecuencia_id,
                frecuencia_texto,
                duracion_cantidad,
                duracion_unidad_id,
                formula_ejecucion_id,
                instrucciones,
                orden
            )
            VALUES
            (
                :tratamiento,
                :farmaco,
                :presentacion,
                :via,
                :dosis,
                :unidad,
                :frecuencia,
                :frecuencia_texto,
                :duracion,
                :duracion_unidad,
                :formula,
                :instrucciones,
                :orden
            )
            '
        );

        $stmt->execute([
            'tratamiento'
                => $treatmentId,

            'farmaco'
                => $drugId,

            'presentacion'
                => !empty(
                    $data[
                        'presentacion_id'
                    ]
                )
                    ? (int)
                        $data[
                            'presentacion_id'
                        ]
                    : null,

            'via'
                => !empty(
                    $data[
                        'via_administracion_id'
                    ]
                )
                    ? (int)
                        $data[
                            'via_administracion_id'
                        ]
                    : null,

            'dosis'
                => $data[
                    'dosis_cantidad'
                ] !== ''
                    ? (float)
                        $data[
                            'dosis_cantidad'
                        ]
                    : null,

            'unidad'
                => !empty(
                    $data[
                        'dosis_unidad_id'
                    ]
                )
                    ? (int)
                        $data[
                            'dosis_unidad_id'
                        ]
                    : null,

            'frecuencia'
                => !empty(
                    $data[
                        'frecuencia_id'
                    ]
                )
                    ? (int)
                        $data[
                            'frecuencia_id'
                        ]
                    : null,

            'frecuencia_texto'
                => trim(
                    $data[
                        'frecuencia_texto'
                    ]
                    ?? ''
                )
                    ?: null,

            'duracion'
                => isset(
                    $data[
                        'duracion_cantidad'
                    ]
                )
                && $data[
                    'duracion_cantidad'
                ] !== ''
                    ? (float)
                        $data[
                            'duracion_cantidad'
                        ]
                    : null,

            'duracion_unidad'
                => !empty(
                    $data[
                        'duracion_unidad_id'
                    ]
                )
                    ? (int)
                        $data[
                            'duracion_unidad_id'
                        ]
                    : null,

            'formula'
                => !empty(
                    $data[
                        'formula_ejecucion_id'
                    ]
                )
                    ? (int)
                        $data[
                            'formula_ejecucion_id'
                        ]
                    : null,

            'instrucciones'
                => trim(
                    $data[
                        'instrucciones'
                    ]
                    ?? ''
                )
                    ?: null,

            'orden'
                => $order,
        ]);
    }


    private function validateMedicationData(
        PDO $db,
        int $drugId,
        array $data
    ): void {
        $stmt = $db->prepare(
            '
            SELECT COUNT(*)

            FROM farmacos

            WHERE id = :farmaco
              AND activo = 1
            '
        );

        $stmt->execute([
            'farmaco'
                => $drugId,
        ]);

        if (
            (int)
                $stmt->fetchColumn()
            === 0
        ) {
            throw new RuntimeException(
                'El fÃ¡rmaco seleccionado no existe.'
            );
        }

        /*
         * PresentaciÃ³n debe corresponder
         * al mismo fÃ¡rmaco.
         */
        if (
            !empty(
                $data[
                    'presentacion_id'
                ]
            )
        ) {
            $stmt = $db->prepare(
                '
                SELECT COUNT(*)

                FROM farmaco_presentaciones

                WHERE id = :presentacion

                  AND farmaco_id
                    = :farmaco

                  AND activo = 1
                '
            );

            $stmt->execute([
                'presentacion'
                    => (int)
                        $data[
                            'presentacion_id'
                        ],

                'farmaco'
                    => $drugId,
            ]);

            if (
                (int)
                    $stmt->fetchColumn()
                === 0
            ) {
                throw new RuntimeException(
                    'La presentaciÃ³n no pertenece al fÃ¡rmaco seleccionado.'
                );
            }
        }

        /*
         * Si existe vÃ­a y presentaciÃ³n,
         * respetamos las vÃ­as habilitadas.
         */
        if (
            !empty(
                $data[
                    'presentacion_id'
                ]
            )
            && !empty(
                $data[
                    'via_administracion_id'
                ]
            )
        ) {
            $stmt = $db->prepare(
                '
                SELECT COUNT(*)

                FROM farmaco_presentacion_vias

                WHERE presentacion_id
                    = :presentacion

                  AND via_administracion_id
                    = :via
                '
            );

            $stmt->execute([
                'presentacion'
                    => (int)
                        $data[
                            'presentacion_id'
                        ],

                'via'
                    => (int)
                        $data[
                            'via_administracion_id'
                        ],
            ]);

            /*
             * Solo restringimos si la
             * presentaciÃ³n tiene vÃ­as
             * configuradas.
             */
            $configured = $db->prepare(
                '
                SELECT COUNT(*)

                FROM farmaco_presentacion_vias

                WHERE presentacion_id
                    = :presentacion
                '
            );

            $configured->execute([
                'presentacion'
                    => (int)
                        $data[
                            'presentacion_id'
                        ],
            ]);

            if (
                (int)
                    $configured
                        ->fetchColumn()
                > 0
                && (int)
                    $stmt
                        ->fetchColumn()
                === 0
            ) {
                throw new RuntimeException(
                    'La vÃ­a seleccionada no estÃ¡ permitida para esta presentaciÃ³n.'
                );
            }
        }
    }


    private function validateEvent(
        PDO $db,
        int $eventId,
        int $environmentId
    ): void {
        $stmt = $db->prepare(
            '
            SELECT COUNT(*)

            FROM eventos_clinicos ec

            INNER JOIN animales a
                ON a.id =
                   ec.animal_id

            WHERE ec.id = :evento

              AND a.entorno_id
                = :entorno
            '
        );

        $stmt->execute([
            'evento'
                => $eventId,

            'entorno'
                => $environmentId,
        ]);

        if (
            (int)
                $stmt->fetchColumn()
            === 0
        ) {
            throw new RuntimeException(
                'Evento clÃ­nico no encontrado.'
            );
        }
    }


    private function validateTreatmentType(
        PDO $db,
        int $typeId
    ): void {
        $stmt = $db->prepare(
            '
            SELECT COUNT(*)

            FROM tipos_tratamiento

            WHERE id = :id
            '
        );

        $stmt->execute([
            'id'
                => $typeId,
        ]);

        if (
            (int)
                $stmt->fetchColumn()
            === 0
        ) {
            throw new RuntimeException(
                'Tipo de tratamiento no vÃ¡lido.'
            );
        }
    }
}
