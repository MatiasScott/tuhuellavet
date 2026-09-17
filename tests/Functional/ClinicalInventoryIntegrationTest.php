<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Services\ConsultationService;
use App\Services\InventoryService;
use App\Services\PreventiveCareService;
use App\Services\TreatmentService;
use RuntimeException;
use PHPUnit\Framework\Attributes\DataProvider;

class ClinicalInventoryIntegrationTest extends ClinicalTestCase
{
    private function unitId(): int
    {
        return (int) $this->scalar(
            '
            SELECT id
            FROM unidades_medida
            WHERE activo = 1
            ORDER BY id
            LIMIT 1
            '
        );
    }

    private function movementType(
        string $code
    ): int {
        return (int) $this->scalar(
            '
            SELECT id
            FROM tipos_movimiento_inventario
            WHERE codigo = :codigo
            LIMIT 1
            ',
            [
                'codigo' => $code,
            ]
        );
    }

    private function createInventory(): int
    {
        $stmt = $this->db()->prepare(
            '
            INSERT INTO inventarios
            (
                entorno_id,
                nombre,
                activo
            )
            VALUES
            (
                :entorno,
                :nombre,
                1
            )
            '
        );

        $stmt->execute([
            'entorno' =>
            $this->vetEnvironment,

            'nombre' =>
            'Inventario integración QA '
                . uniqid(),
        ]);

        return (int)
        $this->db()->lastInsertId();
    }

    private function createDrugPresentation(
        int $drugId
    ): int {
        $formId = (int) $this->scalar(
            '
            SELECT id
            FROM formas_farmaceuticas
            WHERE activo = 1
            ORDER BY id
            LIMIT 1
            '
        );

        $this->assertGreaterThan(
            0,
            $formId
        );

        $stmt = $this->db()->prepare(
            '
            INSERT INTO farmaco_presentaciones
            (
                farmaco_id,
                forma_farmaceutica_id,
                nombre_comercial,
                activo
            )
            VALUES
            (
                :farmaco,
                :forma,
                :nombre,
                1
            )
            '
        );

        $stmt->execute([
            'farmaco' =>
            $drugId,

            'forma' =>
            $formId,

            'nombre' =>
            'Presentación integración QA '
                . uniqid(),
        ]);

        return (int)
        $this->db()->lastInsertId();
    }

    private function createVaccine(): int
    {
        $stmt = $this->db()->prepare(
            '
            INSERT INTO vacunas
            (
                nombre,
                descripcion,
                activo
            )
            VALUES
            (
                :nombre,
                :descripcion,
                1
            )
            '
        );

        $stmt->execute([
            'nombre' =>
            'Vacuna integración QA '
                . uniqid(),

            'descripcion' =>
            'Fixture integración inventario',
        ]);

        return (int)
        $this->db()->lastInsertId();
    }

    private function createProduct(
        ?int $presentationId = null,
        ?int $vaccineId = null,
        bool $controlsLot = false,
        bool $controlsExpiration = false
    ): int {
        $stmt = $this->db()->prepare(
            '
            INSERT INTO productos
            (
                codigo,
                nombre,
                unidad_base_id,
                farmaco_presentacion_id,
                vacuna_id,
                controla_lote,
                controla_vencimiento,
                activo
            )
            VALUES
            (
                :codigo,
                :nombre,
                :unidad,
                :presentacion,
                :vacuna,
                :lote,
                :vencimiento,
                1
            )
            '
        );

        $stmt->execute([
            'codigo' =>
            'QA_INT_'
                . strtoupper(
                    str_replace(
                        '.',
                        '',
                        uniqid('', true)
                    )
                ),

            'nombre' =>
            'Producto integración QA '
                . uniqid(),

            'unidad' =>
            $this->unitId(),

            'presentacion' =>
            $presentationId,

            'vacuna' =>
            $vaccineId,

            'lote' =>
            $controlsLot ? 1 : 0,

            'vencimiento' =>
            $controlsExpiration
                ? 1
                : 0,
        ]);

        return (int)
        $this->db()->lastInsertId();
    }

    private function createLot(
        int $productId,
        ?string $expiration = null
    ): int {
        $stmt = $this->db()->prepare(
            '
            INSERT INTO lotes_producto
            (
                producto_id,
                numero_lote,
                fecha_vencimiento
            )
            VALUES
            (
                :producto,
                :numero,
                :vencimiento
            )
            '
        );

        $stmt->execute([
            'producto' =>
            $productId,

            'numero' =>
            'INT-LOT-'
                . uniqid(),

            'vencimiento' =>
            $expiration,
        ]);

        return (int)
        $this->db()->lastInsertId();
    }

    private function addStock(
        int $inventoryId,
        int $productId,
        float $quantity,
        ?int $lotId = null
    ): void {
        (new InventoryService())->movement(
            [
                'inventario_id' =>
                $inventoryId,

                'producto_id' =>
                $productId,

                'lote_id' =>
                $lotId,

                'tipo_movimiento_id' =>
                $this->movementType(
                    'ENTRADA'
                ),

                'cantidad' =>
                $quantity,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    private function stock(
        int $inventoryId,
        int $productId,
        ?int $lotId = null
    ): float {
        $sql = '
            SELECT COALESCE(
                SUM(
                    tm.factor
                    * mi.cantidad
                ),
                0
            )

            FROM movimientos_inventario mi

            INNER JOIN tipos_movimiento_inventario tm
                ON tm.id =
                   mi.tipo_movimiento_id

            WHERE mi.inventario_id =
                  :inventario

              AND mi.producto_id =
                  :producto
        ';

        $params = [
            'inventario' =>
            $inventoryId,

            'producto' =>
            $productId,
        ];

        if ($lotId !== null) {
            $sql .= '
                AND mi.lote_id =
                    :lote
            ';

            $params['lote'] =
                $lotId;
        }

        return (float)
        $this->scalar(
            $sql,
            $params
        );
    }

    private function createMedicationFixture(): array
    {
        $patientId =
            $this->createPatient();

        $eventId =
            (new ConsultationService())
            ->create(
                [
                    'animal_id' =>
                    $patientId,

                    'motivo_consulta' =>
                    'Integración inventario',
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $drugId =
            $this->createDrug();

        $presentationId =
            $this->createDrugPresentation(
                $drugId
            );

        $typeId =
            $this->catalogId(
                'tipos_tratamiento',
                'codigo',
                'CLINICO'
            );

        $treatmentId =
            (new TreatmentService())->create(
                $eventId,
                [
                    'tipo_tratamiento_id' =>
                    $typeId,

                    'medicamentos' => [
                        [
                            'farmaco_id' =>
                            $drugId,

                            'presentacion_id' =>
                            $presentationId,

                            'dosis_cantidad' =>
                            1,
                        ],
                    ],
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $medicationId =
            (int) $this->scalar(
                '
                SELECT id
                FROM tratamiento_medicamentos
                WHERE tratamiento_id =
                      :tratamiento
                LIMIT 1
                ',
                [
                    'tratamiento' =>
                    $treatmentId,
                ]
            );

        return [
            'patient_id' =>
            $patientId,

            'event_id' =>
            $eventId,

            'drug_id' =>
            $drugId,

            'presentation_id' =>
            $presentationId,

            'treatment_id' =>
            $treatmentId,

            'medication_id' =>
            $medicationId,
        ];
    }

    public function testMedicationApplicationAutomaticallyConsumesInventory(): void
    {
        $clinical =
            $this->createMedicationFixture();

        $inventory =
            $this->createInventory();

        $product =
            $this->createProduct(
                $clinical['presentation_id']
            );

        $this->addStock(
            $inventory,
            $product,
            10
        );

        (new TreatmentService())
            ->addApplication(
                $clinical['medication_id'],
                2,
                null,
                'Aplicación automática QA',
                $this->vetEnvironment,
                $this->superAdminId,
                [
                    'inventario_id' =>
                    $inventory,

                    'producto_id' =>
                    $product,

                    'cantidad_consumida' =>
                    3,
                ]
            );

        $this->assertSame(
            7.0,
            $this->stock(
                $inventory,
                $product
            )
        );

        $applicationId =
            (int) $this->scalar(
                '
                SELECT id
                FROM medicamento_aplicaciones
                WHERE tratamiento_medicamento_id =
                      :medicamento
                ORDER BY id DESC
                LIMIT 1
                ',
                [
                    'medicamento' =>
                    $clinical['medication_id'],
                ]
            );

        $this->assertGreaterThan(
            0,
            $applicationId
        );

        $movementReference =
            (int) $this->scalar(
                '
                SELECT referencia_id
                FROM movimientos_inventario mi
                INNER JOIN tipos_movimiento_inventario tm
                    ON tm.id =
                       mi.tipo_movimiento_id
                WHERE tm.codigo =
                      "CONSUMO_CLINICO"
                  AND mi.referencia_tipo =
                      "MEDICAMENTO_APLICACION"
                  AND mi.referencia_id =
                      :referencia
                ORDER BY mi.id DESC
                LIMIT 1
                ',
                [
                    'referencia' =>
                    $applicationId,
                ]
            );

        $this->assertSame(
            $applicationId,
            $movementReference
        );
    }

    public function testMedicationWithoutInventoryKeepsExistingBehaviour(): void
    {
        $clinical =
            $this->createMedicationFixture();

        (new TreatmentService())
            ->addApplication(
                $clinical['medication_id'],
                1,
                null,
                'Sin inventario',
                $this->vetEnvironment,
                $this->superAdminId
            );

        $count = (int) $this->scalar(
            '
            SELECT COUNT(*)
            FROM medicamento_aplicaciones
            WHERE tratamiento_medicamento_id =
                  :medicamento
            ',
            [
                'medicamento' =>
                $clinical['medication_id'],
            ]
        );

        $this->assertSame(
            1,
            $count
        );
    }

    public function testMedicationApplicationRollsBackWhenStockIsInsufficient(): void
    {
        $clinical =
            $this->createMedicationFixture();

        $inventory =
            $this->createInventory();

        $product =
            $this->createProduct(
                $clinical['presentation_id']
            );

        $this->addStock(
            $inventory,
            $product,
            2
        );

        $beforeApplications =
            (int) $this->scalar(
                '
                SELECT COUNT(*)
                FROM medicamento_aplicaciones
                WHERE tratamiento_medicamento_id =
                      :medicamento
                ',
                [
                    'medicamento' =>
                    $clinical['medication_id'],
                ]
            );

        try {
            (new TreatmentService())
                ->addApplication(
                    $clinical['medication_id'],
                    1,
                    null,
                    'Debe hacer rollback',
                    $this->vetEnvironment,
                    $this->superAdminId,
                    [
                        'inventario_id' =>
                        $inventory,

                        'producto_id' =>
                        $product,

                        'cantidad_consumida' =>
                        10,
                    ]
                );

            $this->fail(
                'La aplicación debía fallar por stock insuficiente.'
            );
        } catch (RuntimeException) {
            $afterApplications =
                (int) $this->scalar(
                    '
                    SELECT COUNT(*)
                    FROM medicamento_aplicaciones
                    WHERE tratamiento_medicamento_id =
                          :medicamento
                    ',
                    [
                        'medicamento' =>
                        $clinical['medication_id'],
                    ]
                );

            $this->assertSame(
                $beforeApplications,
                $afterApplications
            );

            $this->assertSame(
                2.0,
                $this->stock(
                    $inventory,
                    $product
                )
            );
        }
    }

    public function testMedicationApplicationRollsBackForWrongProduct(): void
    {
        $clinical =
            $this->createMedicationFixture();

        $inventory =
            $this->createInventory();

        $otherDrug =
            $this->createDrug();

        $otherPresentation =
            $this->createDrugPresentation(
                $otherDrug
            );

        $wrongProduct =
            $this->createProduct(
                $otherPresentation
            );

        $this->addStock(
            $inventory,
            $wrongProduct,
            10
        );

        $before =
            (int) $this->scalar(
                '
                SELECT COUNT(*)
                FROM medicamento_aplicaciones
                WHERE tratamiento_medicamento_id =
                      :medicamento
                ',
                [
                    'medicamento' =>
                    $clinical['medication_id'],
                ]
            );

        try {
            (new TreatmentService())
                ->addApplication(
                    $clinical['medication_id'],
                    1,
                    null,
                    'Producto incorrecto',
                    $this->vetEnvironment,
                    $this->superAdminId,
                    [
                        'inventario_id' =>
                        $inventory,

                        'producto_id' =>
                        $wrongProduct,

                        'cantidad_consumida' =>
                        1,
                    ]
                );

            $this->fail(
                'Debía rechazarse el producto incorrecto.'
            );
        } catch (RuntimeException) {
            $after =
                (int) $this->scalar(
                    '
                    SELECT COUNT(*)
                    FROM medicamento_aplicaciones
                    WHERE tratamiento_medicamento_id =
                          :medicamento
                    ',
                    [
                        'medicamento' =>
                        $clinical['medication_id'],
                    ]
                );

            $this->assertSame(
                $before,
                $after
            );

            $this->assertSame(
                10.0,
                $this->stock(
                    $inventory,
                    $wrongProduct
                )
            );
        }
    }

    public function testVaccinationAutomaticallyConsumesInventory(): void
    {
        $patient =
            $this->createPatient();

        $vaccine =
            $this->createVaccine();

        $inventory =
            $this->createInventory();

        $product =
            $this->createProduct(
                null,
                $vaccine
            );

        $this->addStock(
            $inventory,
            $product,
            10
        );

        $eventId =
            (new PreventiveCareService())
            ->createVaccination(
                [
                    'animal_id' =>
                    $patient,

                    'vacuna_id' =>
                    $vaccine,

                    'dosis' =>
                    1,

                    'inventario_consumo' => [
                        'inventario_id' =>
                        $inventory,

                        'producto_id' =>
                        $product,

                        'cantidad_consumida' =>
                        1,
                    ],
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $this->assertGreaterThan(
            0,
            $eventId
        );

        $this->assertSame(
            9.0,
            $this->stock(
                $inventory,
                $product
            )
        );

        $vaccinationId =
            (int) $this->scalar(
                '
                SELECT id
                FROM vacunaciones
                WHERE evento_clinico_id =
                      :evento
                LIMIT 1
                ',
                [
                    'evento' =>
                    $eventId,
                ]
            );

        $referenceCount =
            (int) $this->scalar(
                '
                SELECT COUNT(*)
                FROM movimientos_inventario mi
                INNER JOIN tipos_movimiento_inventario tm
                    ON tm.id =
                       mi.tipo_movimiento_id
                WHERE tm.codigo =
                      "CONSUMO_CLINICO"
                  AND mi.referencia_tipo =
                      "VACUNACION"
                  AND mi.referencia_id =
                      :referencia
                ',
                [
                    'referencia' =>
                    $vaccinationId,
                ]
            );

        $this->assertSame(
            1,
            $referenceCount
        );
    }

    public function testVaccinationWithoutInventoryKeepsExistingBehaviour(): void
    {
        $patient =
            $this->createPatient();

        $vaccine =
            $this->createVaccine();

        $eventId =
            (new PreventiveCareService())
            ->createVaccination(
                [
                    'animal_id' =>
                    $patient,

                    'vacuna_id' =>
                    $vaccine,

                    'dosis' =>
                    1,
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $this->assertGreaterThan(
            0,
            $eventId
        );

        $count = (int) $this->scalar(
            '
            SELECT COUNT(*)
            FROM vacunaciones
            WHERE evento_clinico_id =
                  :evento
            ',
            [
                'evento' =>
                $eventId,
            ]
        );

        $this->assertSame(
            1,
            $count
        );
    }

    public function testVaccinationRollsBackCompletelyWhenStockIsInsufficient(): void
    {
        $patient =
            $this->createPatient();

        $vaccine =
            $this->createVaccine();

        $inventory =
            $this->createInventory();

        $product =
            $this->createProduct(
                null,
                $vaccine
            );

        $this->addStock(
            $inventory,
            $product,
            1
        );

        $beforeEvents =
            (int) $this->scalar(
                '
                SELECT COUNT(*)
                FROM eventos_clinicos
                WHERE animal_id =
                      :animal
                ',
                [
                    'animal' =>
                    $patient,
                ]
            );

        $beforeVaccinations =
            (int) $this->scalar(
                '
                SELECT COUNT(*)
                FROM vacunaciones v
                INNER JOIN eventos_clinicos ec
                    ON ec.id =
                       v.evento_clinico_id
                WHERE ec.animal_id =
                      :animal
                ',
                [
                    'animal' =>
                    $patient,
                ]
            );

        try {
            (new PreventiveCareService())
                ->createVaccination(
                    [
                        'animal_id' =>
                        $patient,

                        'vacuna_id' =>
                        $vaccine,

                        'dosis' =>
                        1,

                        'inventario_consumo' => [
                            'inventario_id' =>
                            $inventory,

                            'producto_id' =>
                            $product,

                            'cantidad_consumida' =>
                            5,
                        ],
                    ],
                    $this->vetEnvironment,
                    $this->superAdminId
                );

            $this->fail(
                'La vacunación debía fallar por stock insuficiente.'
            );
        } catch (RuntimeException) {
            $afterEvents =
                (int) $this->scalar(
                    '
                    SELECT COUNT(*)
                    FROM eventos_clinicos
                    WHERE animal_id =
                          :animal
                    ',
                    [
                        'animal' =>
                        $patient,
                    ]
                );

            $afterVaccinations =
                (int) $this->scalar(
                    '
                    SELECT COUNT(*)
                    FROM vacunaciones v
                    INNER JOIN eventos_clinicos ec
                        ON ec.id =
                           v.evento_clinico_id
                    WHERE ec.animal_id =
                          :animal
                    ',
                    [
                        'animal' =>
                        $patient,
                    ]
                );

            $this->assertSame(
                $beforeEvents,
                $afterEvents
            );

            $this->assertSame(
                $beforeVaccinations,
                $afterVaccinations
            );

            $this->assertSame(
                1.0,
                $this->stock(
                    $inventory,
                    $product
                )
            );
        }
    }

    public function testVaccinationWithLotConsumesSelectedLot(): void
    {
        $patient =
            $this->createPatient();

        $vaccine =
            $this->createVaccine();

        $inventory =
            $this->createInventory();

        $product =
            $this->createProduct(
                null,
                $vaccine,
                true,
                true
            );

        $lot =
            $this->createLot(
                $product,
                date(
                    'Y-m-d',
                    strtotime('+1 year')
                )
            );

        $this->addStock(
            $inventory,
            $product,
            10,
            $lot
        );

        (new PreventiveCareService())
            ->createVaccination(
                [
                    'animal_id' =>
                    $patient,

                    'vacuna_id' =>
                    $vaccine,

                    'dosis' =>
                    1,

                    'inventario_consumo' => [
                        'inventario_id' =>
                        $inventory,

                        'producto_id' =>
                        $product,

                        'lote_id' =>
                        $lot,

                        'cantidad_consumida' =>
                        2,
                    ],
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $this->assertSame(
            8.0,
            $this->stock(
                $inventory,
                $product,
                $lot
            )
        );
    }

    public function testVaccinationRollsBackWhenLotIsExpired(): void
    {
        $patient =
            $this->createPatient();

        $vaccine =
            $this->createVaccine();

        $inventory =
            $this->createInventory();

        $product =
            $this->createProduct(
                null,
                $vaccine,
                true,
                true
            );

        $lot =
            $this->createLot(
                $product,
                date(
                    'Y-m-d',
                    strtotime('-1 day')
                )
            );

        $this->addStock(
            $inventory,
            $product,
            10,
            $lot
        );

        $before =
            (int) $this->scalar(
                '
                SELECT COUNT(*)
                FROM eventos_clinicos
                WHERE animal_id =
                      :animal
                ',
                [
                    'animal' =>
                    $patient,
                ]
            );

        try {
            (new PreventiveCareService())
                ->createVaccination(
                    [
                        'animal_id' =>
                        $patient,

                        'vacuna_id' =>
                        $vaccine,

                        'dosis' =>
                        1,

                        'inventario_consumo' => [
                            'inventario_id' =>
                            $inventory,

                            'producto_id' =>
                            $product,

                            'lote_id' =>
                            $lot,

                            'cantidad_consumida' =>
                            1,
                        ],
                    ],
                    $this->vetEnvironment,
                    $this->superAdminId
                );

            $this->fail(
                'La vacunación debía fallar por lote vencido.'
            );
        } catch (RuntimeException) {
            $after =
                (int) $this->scalar(
                    '
                    SELECT COUNT(*)
                    FROM eventos_clinicos
                    WHERE animal_id =
                          :animal
                    ',
                    [
                        'animal' =>
                        $patient,
                    ]
                );

            $this->assertSame(
                $before,
                $after
            );

            $this->assertSame(
                10.0,
                $this->stock(
                    $inventory,
                    $product,
                    $lot
                )
            );
        }
    }

    public function testInvalidConsumedQuantityRollsBackMedicationApplication(): void
    {
        $clinical =
            $this->createMedicationFixture();

        $inventory =
            $this->createInventory();

        $product =
            $this->createProduct(
                $clinical['presentation_id']
            );

        $this->addStock(
            $inventory,
            $product,
            10
        );

        $before =
            (int) $this->scalar(
                '
                SELECT COUNT(*)
                FROM medicamento_aplicaciones
                WHERE tratamiento_medicamento_id =
                      :medicamento
                ',
                [
                    'medicamento' =>
                    $clinical['medication_id'],
                ]
            );

        try {
            (new TreatmentService())
                ->addApplication(
                    $clinical['medication_id'],
                    1,
                    null,
                    null,
                    $this->vetEnvironment,
                    $this->superAdminId,
                    [
                        'inventario_id' =>
                        $inventory,

                        'producto_id' =>
                        $product,

                        'cantidad_consumida' =>
                        0,
                    ]
                );

            $this->fail(
                'Debía rechazarse cantidad consumida igual a cero.'
            );
        } catch (RuntimeException) {
            $after =
                (int) $this->scalar(
                    '
                    SELECT COUNT(*)
                    FROM medicamento_aplicaciones
                    WHERE tratamiento_medicamento_id =
                          :medicamento
                    ',
                    [
                        'medicamento' =>
                        $clinical['medication_id'],
                    ]
                );

            $this->assertSame(
                $before,
                $after
            );

            $this->assertSame(
                10.0,
                $this->stock(
                    $inventory,
                    $product
                )
            );
        }
    }

    #[DataProvider('invalidConsumedQuantities')]
    public function testInvalidConsumedQuantityRollsBackEverything(
        float $invalidQuantity
    ): void {
        $clinical =
            $this->createMedicationFixture();

        $inventory =
            $this->createInventory();

        $product =
            $this->createProduct(
                $clinical['presentation_id']
            );

        $this->addStock(
            $inventory,
            $product,
            10
        );

        $medicationId =
            $clinical['medication_id'];

        $beforeApplications =
            (int) $this->scalar(
                '
            SELECT COUNT(*)
            FROM medicamento_aplicaciones
            WHERE tratamiento_medicamento_id = :medicamento
            ',
                [
                    'medicamento' => $medicationId,
                ]
            );

        $beforeMovements =
            (int) $this->scalar(
                '
            SELECT COUNT(*)
            FROM movimientos_inventario
            WHERE inventario_id = :inventario
              AND producto_id = :producto
            ',
                [
                    'inventario' => $inventory,
                    'producto' => $product,
                ]
            );

        $beforeAudit =
            (int) $this->scalar(
                '
            SELECT COUNT(*)
            FROM auditoria
            WHERE usuario_id = :usuario
              AND entorno_id = :entorno
              AND modulo = "TRATAMIENTOS"
              AND accion = "APLICAR_MEDICAMENTO"
            ',
                [
                    'usuario' => $this->superAdminId,
                    'entorno' => $this->vetEnvironment,
                ]
            );

        $failed = false;

        try {
            (new TreatmentService())->addApplication(
                $medicationId,
                1,
                null,
                'Prueba de rollback integral',
                $this->vetEnvironment,
                $this->superAdminId,
                [
                    'inventario_id' => $inventory,
                    'producto_id' => $product,
                    'cantidad_consumida' => $invalidQuantity,
                ]
            );
        } catch (RuntimeException $exception) {
            $failed = true;

            $this->assertStringContainsString(
                'número finito',
                $exception->getMessage()
            );
        }

        $this->assertTrue(
            $failed,
            'La cantidad infinita debía ser rechazada.'
        );

        $afterApplications =
            (int) $this->scalar(
                '
            SELECT COUNT(*)
            FROM medicamento_aplicaciones
            WHERE tratamiento_medicamento_id = :medicamento
            ',
                [
                    'medicamento' => $medicationId,
                ]
            );

        $afterMovements =
            (int) $this->scalar(
                '
            SELECT COUNT(*)
            FROM movimientos_inventario
            WHERE inventario_id = :inventario
              AND producto_id = :producto
            ',
                [
                    'inventario' => $inventory,
                    'producto' => $product,
                ]
            );

        $afterAudit =
            (int) $this->scalar(
                '
            SELECT COUNT(*)
            FROM auditoria
            WHERE usuario_id = :usuario
              AND entorno_id = :entorno
              AND modulo = "TRATAMIENTOS"
              AND accion = "APLICAR_MEDICAMENTO"
            ',
                [
                    'usuario' => $this->superAdminId,
                    'entorno' => $this->vetEnvironment,
                ]
            );

        $this->assertSame(
            $beforeApplications,
            $afterApplications,
            'Quedó una aplicación después del rollback.'
        );

        $this->assertSame(
            $beforeMovements,
            $afterMovements,
            'Quedó un movimiento después del rollback.'
        );

        $this->assertSame(
            10.0,
            $this->stock(
                $inventory,
                $product
            ),
            'El stock cambió después del rollback.'
        );

        $this->assertSame(
            $beforeAudit,
            $afterAudit,
            'Quedó una auditoría de aplicación después del rollback.'
        );
    }

    public static function invalidConsumedQuantities(): array
    {
        return [
            'infinito positivo' => [INF],
            'infinito negativo' => [-INF],
            'NaN' => [NAN],
        ];
    }
}
