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
                        $data['tipo_tratamiento_id']
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
                    => !empty($data['fecha_inicio'])
                        ? $data['fecha_inicio']
                        : date(
                            'Y-m-d H:i:s'
                        ),

                    'fin'
                    => !empty($data['fecha_fin'])
                        ? $data['fecha_fin']
                        : null,

                    'instrucciones'
                    => trim(
                        $data['instrucciones_generales']
                            ?? ''
                    )
                        ?: null,

                    'observaciones'
                    => trim(
                        $data['observaciones']
                            ?? ''
                    )
                        ?: null,
                ]);

                $treatmentId
                    = (int)
                    $db
                        ->lastInsertId();

                $medications
                    = $data['medicamentos']
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
                            empty($medication['farmaco_id'])
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
        int $createdBy,
        ?array $inventoryConsumption = null
    ): void {
        if (
            !is_finite($quantity)
            || $quantity <= 0
        ) {
            throw new RuntimeException(
                'La cantidad aplicada debe ser un número finito mayor a cero.'
            );
        }

        Database::transaction(
            function (PDO $db) use (
                $medicationId,
                $quantity,
                $unitId,
                $observation,
                $environmentId,
                $createdBy,
                $inventoryConsumption
            ): void {
                /*
             * Validar que el medicamento pertenece
             * a un paciente del entorno actual.
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

                WHERE tm.id =
                      :medicamento

                  AND a.entorno_id =
                      :entorno

                LIMIT 1
                '
                );

                $stmt->execute([
                    'medicamento' =>
                    $medicationId,

                    'entorno' =>
                    $environmentId,
                ]);

                if (!$stmt->fetchColumn()) {
                    throw new RuntimeException(
                        'Medicamento no encontrado.'
                    );
                }

                /*
             * Registrar aplicación clínica.
             */
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
                    'medicamento' =>
                    $medicationId,

                    'usuario' =>
                    $createdBy,

                    'cantidad' =>
                    $quantity,

                    'unidad' =>
                    $unitId,

                    'observaciones' =>
                    $observation,
                ]);

                $applicationId =
                    (int) $db->lastInsertId();

                /*
             * Si el flujo clínico pidió consumo de
             * inventario, debe ocurrir dentro de la
             * MISMA transacción.
             */
                if ($inventoryConsumption !== null) {
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
                        || !is_finite($normalizedConsumedQuantity)
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
                                'MEDICAMENTO_APLICACION',

                                'referencia_id' =>
                                $applicationId,

                                'observaciones' =>
                                'Consumo automático por aplicación de medicamento',
                            ],
                            $environmentId,
                            $createdBy
                        );
                }

                /*
             * Si el consumo de inventario falla,
             * nunca llegamos aquí y toda la
             * transacción se revierte.
             */
                (new AuditService())
                    ->log(
                        $createdBy,
                        $environmentId,
                        'TRATAMIENTOS',
                        'APLICAR_MEDICAMENTO',
                        'medicamento_aplicaciones',
                        $applicationId
                    );
            }
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
            $data['farmaco_id'];

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
            => !empty($data['presentacion_id'])
                ? (int)
                $data['presentacion_id']
                : null,

            'via'
            => !empty($data['via_administracion_id'])
                ? (int)
                $data['via_administracion_id']
                : null,

            'dosis'
            => $this->optionalPositiveNumber(
                $data,
                'dosis_cantidad',
                'La dosis'
            ),

            'unidad'
            => !empty($data['dosis_unidad_id'])
                ? (int)
                $data['dosis_unidad_id']
                : null,

            'frecuencia'
            => !empty($data['frecuencia_id'])
                ? (int)
                $data['frecuencia_id']
                : null,

            'frecuencia_texto'
            => trim(
                $data['frecuencia_texto']
                    ?? ''
            )
                ?: null,

            'duracion'
            => $this->optionalPositiveNumber(
                $data,
                'duracion_cantidad',
                'La duración'
            ),

            'duracion_unidad'
            => !empty($data['duracion_unidad_id'])
                ? (int)
                $data['duracion_unidad_id']
                : null,

            'formula'
            => !empty($data['formula_ejecucion_id'])
                ? (int)
                $data['formula_ejecucion_id']
                : null,

            'instrucciones'
            => trim(
                $data['instrucciones']
                    ?? ''
            )
                ?: null,

            'orden'
            => $order,
        ]);
    }

    private function optionalPositiveNumber(
        array $data,
        string $key,
        string $label
    ): ?float {
        if (
            !array_key_exists($key, $data)
            || $data[$key] === null
            || (
                is_string($data[$key])
                && trim($data[$key]) === ''
            )
        ) {
            return null;
        }

        $value = $data[$key];

        if (!is_numeric($value)) {
            throw new RuntimeException(
                $label . ' debe ser un valor numérico válido.'
            );
        }

        $number = (float) $value;

        if (!is_finite($number)) {
            throw new RuntimeException(
                $label . ' debe ser un valor numérico finito.'
            );
        }

        if ($number <= 0) {
            throw new RuntimeException(
                $label . ' debe ser mayor a cero.'
            );
        }

        return $number;
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
                'El fármaco seleccionado no existe.'
            );
        }

        /*
         * Presentación debe corresponder
         * al mismo fármaco.
         */
        if (
            !empty($data['presentacion_id'])
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
                $data['presentacion_id'],

                'farmaco'
                => $drugId,
            ]);

            if (
                (int)
                $stmt->fetchColumn()
                === 0
            ) {
                throw new RuntimeException(
                    'La Presentación no pertenece al fármaco seleccionado.'
                );
            }
        }

        /*
         * Si existe vía y Presentación,
         * respetamos las vías habilitadas.
         */
        if (
            !empty($data['presentacion_id'])
            && !empty($data['via_administracion_id'])
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
                $data['presentacion_id'],

                'via'
                => (int)
                $data['via_administracion_id'],
            ]);

            /*
             * Solo restringimos si la
             * Presentación tiene vías
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
                $data['presentacion_id'],
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
                    'La vía seleccionada no está permitida para esta Presentación.'
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
                'Evento clínico no encontrado.'
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
                'Tipo de tratamiento no válido.'
            );
        }
    }

    public function cancelApplication(
        int $applicationId,
        string $reason,
        int $environmentId,
        int $cancelledBy
    ): void {
        Database::transaction(
            function (PDO $db) use (
                $applicationId,
                $reason,
                $environmentId,
                $cancelledBy
            ): void {
                $reason =
                    trim($reason);

                if ($applicationId <= 0) {
                    throw new RuntimeException(
                        'La aplicación de medicamento es obligatoria.'
                    );
                }

                if ($reason === '') {
                    throw new RuntimeException(
                        'El motivo de anulación es obligatorio.'
                    );
                }

                /*
             * Bloqueamos la aplicación.
             *
             * Así dos solicitudes simultáneas no pueden
             * anularla y devolver stock dos veces.
             */
                $stmt = $db->prepare(
                    '
                SELECT
                    ma.id,
                    ma.anulado_at,

                    ec.id AS evento_clinico_id,
                    a.entorno_id

                FROM medicamento_aplicaciones ma

                INNER JOIN tratamiento_medicamentos tm
                    ON tm.id =
                       ma.tratamiento_medicamento_id

                INNER JOIN tratamientos t
                    ON t.id =
                       tm.tratamiento_id

                INNER JOIN eventos_clinicos ec
                    ON ec.id =
                       t.evento_clinico_id

                INNER JOIN animales a
                    ON a.id =
                       ec.animal_id

                WHERE ma.id =
                      :aplicacion

                LIMIT 1

                FOR UPDATE
                '
                );

                $stmt->execute([
                    'aplicacion' =>
                    $applicationId,
                ]);

                $application =
                    $stmt->fetch();

                if (!$application) {
                    throw new RuntimeException(
                        'Aplicación de medicamento no encontrada.'
                    );
                }

                if (
                    (int) $application['entorno_id']
                    !== $environmentId
                ) {
                    throw new RuntimeException(
                        'La aplicación no pertenece al entorno actual.'
                    );
                }

                if (
                    !empty($application['anulado_at'])
                ) {
                    throw new RuntimeException(
                        'La aplicación de medicamento ya fue anulada.'
                    );
                }

                /*
             * Primero compensamos inventario.
             *
             * Si falla el reverso, toda esta transacción
             * vuelve atrás y la aplicación continúa activa.
             */
                $reversalIds =
                    (new InventoryService())
                    ->reverseClinicalConsumption(
                        'MEDICAMENTO_APLICACION',
                        $applicationId,
                        $environmentId,
                        $cancelledBy,
                        'Anulación de aplicación de medicamento: '
                            . $reason
                    );

                $stmt = $db->prepare(
                    '
                UPDATE medicamento_aplicaciones
                SET
                    anulado_at = NOW(),
                    anulado_por = :usuario,
                    motivo_anulacion = :motivo
                WHERE id = :id
                  AND anulado_at IS NULL
                '
                );

                $stmt->execute([
                    'usuario' =>
                    $cancelledBy,

                    'motivo' =>
                    $reason,

                    'id' =>
                    $applicationId,
                ]);

                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException(
                        'No fue posible anular la aplicación de medicamento.'
                    );
                }

                (new AuditService())
                    ->log(
                        $cancelledBy,
                        $environmentId,
                        'TRATAMIENTOS',
                        'ANULAR_APLICACION',
                        'medicamento_aplicaciones',
                        $applicationId,
                        null,
                        [
                            'motivo_anulacion' =>
                            $reason,

                            'reversos_inventario' =>
                            $reversalIds,
                        ]
                    );
            }
        );
    }
}
