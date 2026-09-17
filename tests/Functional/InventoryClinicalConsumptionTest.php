<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Services\ConsultationService;
use App\Services\InventoryService;
use App\Services\PreventiveCareService;
use App\Services\TreatmentService;
use RuntimeException;

class InventoryClinicalConsumptionTest extends ClinicalTestCase
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

    private function movementType(string $code): int
    {
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

    private function createInventory(
        ?int $environmentId = null
    ): int {
        $environmentId ??=
            $this->vetEnvironment;

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
            $environmentId,

            'nombre' =>
            'Inventario clínico QA '
                . uniqid(),
        ]);

        return (int)
        $this->db()->lastInsertId();
    }

    private function createDrugPresentation(
        int $drugId,
        bool $active = true
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
            $formId,
            'No existe una forma farmacéutica activa.'
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
                :activo
            )
            '
        );

        $stmt->execute([
            'farmaco' =>
            $drugId,

            'forma' =>
            $formId,

            'nombre' =>
            'Presentación QA '
                . uniqid(),

            'activo' =>
            $active ? 1 : 0,
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
            'Vacuna inventario QA '
                . uniqid(),

            'descripcion' =>
            'Fixture QA consumo clínico',
        ]);

        return (int)
        $this->db()->lastInsertId();
    }

    private function createProductForPresentation(
        int $presentationId,
        bool $controlsLot = false,
        bool $controlsExpiration = false
    ): int {
        return $this->createInventoryProduct(
            $presentationId,
            null,
            $controlsLot,
            $controlsExpiration
        );
    }

    private function createProductForVaccine(
        int $vaccineId,
        bool $controlsLot = false,
        bool $controlsExpiration = false
    ): int {
        return $this->createInventoryProduct(
            null,
            $vaccineId,
            $controlsLot,
            $controlsExpiration
        );
    }

    private function createInventoryProduct(
        ?int $presentationId,
        ?int $vaccineId,
        bool $controlsLot,
        bool $controlsExpiration
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
            'QA_CLIN_'
                . strtoupper(
                    str_replace(
                        '.',
                        '',
                        uniqid('', true)
                    )
                ),

            'nombre' =>
            'Producto clínico QA '
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
            $controlsExpiration ? 1 : 0,
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
            'CLIN-LOT-'
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

                'observaciones' =>
                'Stock clínico inicial QA',
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
                AND mi.lote_id = :lote
            ';

            $params['lote'] =
                $lotId;
        }

        return (float) $this->scalar(
            $sql,
            $params
        );
    }

    private function createMedicationApplication(
        ?int $environmentId = null,
        bool $withPresentation = true
    ): array {
        $environmentId ??=
            $this->vetEnvironment;

        $patientId =
            $this->createPatient(
                $environmentId
            );

        $eventId =
            (new ConsultationService())->create(
                [
                    'animal_id' =>
                    $patientId,

                    'motivo_consulta' =>
                    'Consulta consumo inventario QA',
                ],
                $environmentId,
                $this->superAdminId
            );

        $drugId =
            $this->createDrug();

        $presentationId =
            $withPresentation
            ? $this->createDrugPresentation(
                $drugId
            )
            : null;

        $treatmentTypeId =
            $this->catalogId(
                'tipos_tratamiento',
                'codigo',
                'CLINICO'
            );

        $medication = [
            'farmaco_id' =>
            $drugId,

            'dosis_cantidad' =>
            1.5,
        ];

        if ($presentationId !== null) {
            $medication['presentacion_id'] = $presentationId;
        }

        $treatmentId =
            (new TreatmentService())->create(
                $eventId,
                [
                    'tipo_tratamiento_id' =>
                    $treatmentTypeId,

                    'medicamentos' => [
                        $medication,
                    ],
                ],
                $environmentId,
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

        (new TreatmentService())
            ->addApplication(
                $medicationId,
                1.5,
                null,
                'Aplicación consumo QA',
                $environmentId,
                $this->superAdminId
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
                    $medicationId,
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

            'application_id' =>
            $applicationId,
        ];
    }

    private function createVaccinationReference(
        ?int $environmentId = null
    ): array {
        $environmentId ??=
            $this->vetEnvironment;

        $patientId =
            $this->createPatient(
                $environmentId
            );

        $vaccineId =
            $this->createVaccine();

        $eventId =
            (new PreventiveCareService())
            ->createVaccination(
                [
                    'animal_id' =>
                    $patientId,

                    'vacuna_id' =>
                    $vaccineId,

                    'dosis' =>
                    1,

                    'observaciones' =>
                    'Vacunación inventario QA',
                ],
                $environmentId,
                $this->superAdminId
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

        return [
            'patient_id' =>
            $patientId,

            'event_id' =>
            $eventId,

            'vaccine_id' =>
            $vaccineId,

            'vaccination_id' =>
            $vaccinationId,
        ];
    }

    public function testMedicationApplicationCanConsumeInventory(): void
    {
        $clinical =
            $this->createMedicationApplication();

        $inventory =
            $this->createInventory();

        $product =
            $this->createProductForPresentation(
                $clinical['presentation_id']
            );

        $this->addStock(
            $inventory,
            $product,
            10
        );

        $movementId =
            (new InventoryService())
            ->consumeClinical(
                [
                    'inventario_id' =>
                    $inventory,

                    'producto_id' =>
                    $product,

                    'cantidad' =>
                    2,

                    'referencia_tipo' =>
                    'MEDICAMENTO_APLICACION',

                    'referencia_id' =>
                    $clinical['application_id'],
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $this->assertGreaterThan(
            0,
            $movementId
        );

        $this->assertSame(
            8.0,
            $this->stock(
                $inventory,
                $product
            )
        );
    }

    public function testMedicationConsumptionCreatesClinicalMovement(): void
    {
        $clinical =
            $this->createMedicationApplication();

        $inventory =
            $this->createInventory();

        $product =
            $this->createProductForPresentation(
                $clinical['presentation_id']
            );

        $this->addStock(
            $inventory,
            $product,
            10
        );

        $movementId =
            (new InventoryService())
            ->consumeClinical(
                [
                    'inventario_id' =>
                    $inventory,

                    'producto_id' =>
                    $product,

                    'cantidad' =>
                    1.5,

                    'referencia_tipo' =>
                    'MEDICAMENTO_APLICACION',

                    'referencia_id' =>
                    $clinical['application_id'],
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $stmt = $this->db()->prepare(
            '
            SELECT
                mi.referencia_tipo,
                mi.referencia_id,
                mi.cantidad,
                tm.codigo,
                tm.factor
            FROM movimientos_inventario mi
            INNER JOIN tipos_movimiento_inventario tm
                ON tm.id =
                   mi.tipo_movimiento_id
            WHERE mi.id = :id
            '
        );

        $stmt->execute([
            'id' => $movementId,
        ]);

        $row = $stmt->fetch();

        $this->assertNotFalse($row);

        $this->assertSame(
            'CONSUMO_CLINICO',
            $row['codigo']
        );

        $this->assertSame(
            -1,
            (int) $row['factor']
        );

        $this->assertSame(
            'MEDICAMENTO_APLICACION',
            $row['referencia_tipo']
        );

        $this->assertSame(
            $clinical['application_id'],
            (int) $row['referencia_id']
        );

        $this->assertSame(
            1.5,
            (float) $row['cantidad']
        );
    }

    public function testMedicationConsumptionRejectsWrongPresentation(): void
    {
        $clinical =
            $this->createMedicationApplication();

        $otherDrug =
            $this->createDrug();

        $otherPresentation =
            $this->createDrugPresentation(
                $otherDrug
            );

        $product =
            $this->createProductForPresentation(
                $otherPresentation
            );

        $inventory =
            $this->createInventory();

        $this->addStock(
            $inventory,
            $product,
            10
        );

        $this->expectException(
            RuntimeException::class
        );

        (new InventoryService())
            ->consumeClinical(
                [
                    'inventario_id' =>
                    $inventory,

                    'producto_id' =>
                    $product,

                    'cantidad' => 1,

                    'referencia_tipo' =>
                    'MEDICAMENTO_APLICACION',

                    'referencia_id' =>
                    $clinical['application_id'],
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );
    }

    public function testMedicationWithoutPresentationCannotConsumeInventory(): void
    {
        $clinical =
            $this->createMedicationApplication(
                null,
                false
            );

        $drug =
            $this->createDrug();

        $presentation =
            $this->createDrugPresentation(
                $drug
            );

        $product =
            $this->createProductForPresentation(
                $presentation
            );

        $inventory =
            $this->createInventory();

        $this->addStock(
            $inventory,
            $product,
            10
        );

        $this->expectException(
            RuntimeException::class
        );

        (new InventoryService())
            ->consumeClinical(
                [
                    'inventario_id' =>
                    $inventory,

                    'producto_id' =>
                    $product,

                    'cantidad' => 1,

                    'referencia_tipo' =>
                    'MEDICAMENTO_APLICACION',

                    'referencia_id' =>
                    $clinical['application_id'],
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );
    }

    public function testVaccineCanConsumeInventory(): void
    {
        $clinical =
            $this->createVaccinationReference();

        $inventory =
            $this->createInventory();

        $product =
            $this->createProductForVaccine(
                $clinical['vaccine_id']
            );

        $this->addStock(
            $inventory,
            $product,
            15
        );

        $movementId =
            (new InventoryService())
            ->consumeClinical(
                [
                    'inventario_id' =>
                    $inventory,

                    'producto_id' =>
                    $product,

                    'cantidad' =>
                    1,

                    'referencia_tipo' =>
                    'VACUNACION',

                    'referencia_id' =>
                    $clinical['vaccination_id'],
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $this->assertGreaterThan(
            0,
            $movementId
        );

        $this->assertSame(
            14.0,
            $this->stock(
                $inventory,
                $product
            )
        );
    }

    public function testVaccineConsumptionStoresClinicalReference(): void
    {
        $clinical =
            $this->createVaccinationReference();

        $inventory =
            $this->createInventory();

        $product =
            $this->createProductForVaccine(
                $clinical['vaccine_id']
            );

        $this->addStock(
            $inventory,
            $product,
            5
        );

        $movementId =
            (new InventoryService())
            ->consumeClinical(
                [
                    'inventario_id' =>
                    $inventory,

                    'producto_id' =>
                    $product,

                    'cantidad' => 1,

                    'referencia_tipo' =>
                    'VACUNACION',

                    'referencia_id' =>
                    $clinical['vaccination_id'],
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $stmt = $this->db()->prepare(
            '
            SELECT
                referencia_tipo,
                referencia_id
            FROM movimientos_inventario
            WHERE id = :id
            '
        );

        $stmt->execute([
            'id' =>
            $movementId,
        ]);

        $row = $stmt->fetch();

        $this->assertSame(
            'VACUNACION',
            $row['referencia_tipo']
        );

        $this->assertSame(
            $clinical['vaccination_id'],
            (int) $row['referencia_id']
        );
    }

    public function testVaccineConsumptionRejectsWrongVaccineProduct(): void
    {
        $clinical =
            $this->createVaccinationReference();

        $otherVaccine =
            $this->createVaccine();

        $product =
            $this->createProductForVaccine(
                $otherVaccine
            );

        $inventory =
            $this->createInventory();

        $this->addStock(
            $inventory,
            $product,
            5
        );

        $this->expectException(
            RuntimeException::class
        );

        (new InventoryService())
            ->consumeClinical(
                [
                    'inventario_id' =>
                    $inventory,

                    'producto_id' =>
                    $product,

                    'cantidad' => 1,

                    'referencia_tipo' =>
                    'VACUNACION',

                    'referencia_id' =>
                    $clinical['vaccination_id'],
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );
    }

    public function testNonexistentClinicalReferenceIsRejected(): void
    {
        $this->expectException(
            RuntimeException::class
        );

        (new InventoryService())
            ->consumeClinical(
                [
                    'inventario_id' =>
                    $this->createInventory(),

                    'producto_id' =>
                    $this->createProductForVaccine(
                        $this->createVaccine()
                    ),

                    'cantidad' => 1,

                    'referencia_tipo' =>
                    'VACUNACION',

                    'referencia_id' =>
                    999999999,
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );
    }

    public function testInvalidClinicalReferenceTypeIsRejected(): void
    {
        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Tipo de referencia clínica no válido.'
        );

        (new InventoryService())
            ->consumeClinical(
                [
                    'inventario_id' =>
                    $this->createInventory(),

                    'producto_id' =>
                    $this->createProductForVaccine(
                        $this->createVaccine()
                    ),

                    'cantidad' => 1,

                    'referencia_tipo' =>
                    'CONSULTA',

                    'referencia_id' =>
                    1,
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );
    }

    public function testMedicationReferenceFromAnotherEnvironmentIsRejected(): void
    {
        $otherEnvironment =
            (int) $this->scalar(
                '
                SELECT id
                FROM entornos
                WHERE id <> :actual
                  AND activo = 1
                ORDER BY id
                LIMIT 1
                ',
                [
                    'actual' =>
                    $this->vetEnvironment,
                ]
            );

        $this->assertGreaterThan(
            0,
            $otherEnvironment,
            'Se requiere un segundo entorno activo.'
        );

        $clinical =
            $this->createMedicationApplication(
                $otherEnvironment
            );

        $product =
            $this->createProductForPresentation(
                $clinical['presentation_id']
            );

        $inventory =
            $this->createInventory();

        $this->addStock(
            $inventory,
            $product,
            10
        );

        $this->expectException(
            RuntimeException::class
        );

        (new InventoryService())
            ->consumeClinical(
                [
                    'inventario_id' =>
                    $inventory,

                    'producto_id' =>
                    $product,

                    'cantidad' => 1,

                    'referencia_tipo' =>
                    'MEDICAMENTO_APLICACION',

                    'referencia_id' =>
                    $clinical['application_id'],
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );
    }

    public function testInsufficientStockIsRejectedWithoutChangingStock(): void
    {
        $clinical =
            $this->createVaccinationReference();

        $product =
            $this->createProductForVaccine(
                $clinical['vaccine_id']
            );

        $inventory =
            $this->createInventory();

        $this->addStock(
            $inventory,
            $product,
            2
        );

        try {
            (new InventoryService())
                ->consumeClinical(
                    [
                        'inventario_id' =>
                        $inventory,

                        'producto_id' =>
                        $product,

                        'cantidad' =>
                        5,

                        'referencia_tipo' =>
                        'VACUNACION',

                        'referencia_id' =>
                        $clinical['vaccination_id'],
                    ],
                    $this->vetEnvironment,
                    $this->superAdminId
                );

            $this->fail(
                'El consumo debía fallar por stock insuficiente.'
            );
        } catch (RuntimeException) {
            $this->assertSame(
                2.0,
                $this->stock(
                    $inventory,
                    $product
                )
            );
        }
    }

    public function testLotControlledProductRequiresLot(): void
    {
        $clinical =
            $this->createVaccinationReference();

        $product =
            $this->createProductForVaccine(
                $clinical['vaccine_id'],
                true,
                false
            );

        $lot =
            $this->createLot(
                $product
            );

        $inventory =
            $this->createInventory();

        $this->addStock(
            $inventory,
            $product,
            10,
            $lot
        );

        $this->expectException(
            RuntimeException::class
        );

        (new InventoryService())
            ->consumeClinical(
                [
                    'inventario_id' =>
                    $inventory,

                    'producto_id' =>
                    $product,

                    'cantidad' => 1,

                    'referencia_tipo' =>
                    'VACUNACION',

                    'referencia_id' =>
                    $clinical['vaccination_id'],
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );
    }

    public function testExpiredLotCannotBeUsedForClinicalConsumption(): void
    {
        $clinical =
            $this->createVaccinationReference();

        $product =
            $this->createProductForVaccine(
                $clinical['vaccine_id'],
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

        $inventory =
            $this->createInventory();

        /*
         * Una entrada histórica a lote vencido
         * sí está permitida.
         */
        $this->addStock(
            $inventory,
            $product,
            10,
            $lot
        );

        $this->expectException(
            RuntimeException::class
        );

        (new InventoryService())
            ->consumeClinical(
                [
                    'inventario_id' =>
                    $inventory,

                    'producto_id' =>
                    $product,

                    'lote_id' =>
                    $lot,

                    'cantidad' => 1,

                    'referencia_tipo' =>
                    'VACUNACION',

                    'referencia_id' =>
                    $clinical['vaccination_id'],
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );
    }

    public function testLotFromAnotherProductIsRejected(): void
    {
        $clinical =
            $this->createVaccinationReference();

        $product =
            $this->createProductForVaccine(
                $clinical['vaccine_id'],
                true,
                false
            );

        $otherVaccine =
            $this->createVaccine();

        $otherProduct =
            $this->createProductForVaccine(
                $otherVaccine,
                true,
                false
            );

        $wrongLot =
            $this->createLot(
                $otherProduct
            );

        $inventory =
            $this->createInventory();

        $this->expectException(
            RuntimeException::class
        );

        (new InventoryService())
            ->consumeClinical(
                [
                    'inventario_id' =>
                    $inventory,

                    'producto_id' =>
                    $product,

                    'lote_id' =>
                    $wrongLot,

                    'cantidad' => 1,

                    'referencia_tipo' =>
                    'VACUNACION',

                    'referencia_id' =>
                    $clinical['vaccination_id'],
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );
    }

    public function testClinicalConsumptionUsesOnlySelectedLotStock(): void
    {
        $clinical =
            $this->createVaccinationReference();

        $product =
            $this->createProductForVaccine(
                $clinical['vaccine_id'],
                true,
                false
            );

        $lotA =
            $this->createLot(
                $product
            );

        $lotB =
            $this->createLot(
                $product
            );

        $inventory =
            $this->createInventory();

        $this->addStock(
            $inventory,
            $product,
            2,
            $lotA
        );

        $this->addStock(
            $inventory,
            $product,
            20,
            $lotB
        );

        try {
            (new InventoryService())
                ->consumeClinical(
                    [
                        'inventario_id' =>
                        $inventory,

                        'producto_id' =>
                        $product,

                        'lote_id' =>
                        $lotA,

                        'cantidad' => 3,

                        'referencia_tipo' =>
                        'VACUNACION',

                        'referencia_id' =>
                        $clinical['vaccination_id'],
                    ],
                    $this->vetEnvironment,
                    $this->superAdminId
                );

            $this->fail(
                'No debía consumir stock perteneciente a otro lote.'
            );
        } catch (RuntimeException) {
            $this->assertSame(
                2.0,
                $this->stock(
                    $inventory,
                    $product,
                    $lotA
                )
            );

            $this->assertSame(
                20.0,
                $this->stock(
                    $inventory,
                    $product,
                    $lotB
                )
            );
        }
    }

    public function testValidLotClinicalConsumptionDecreasesOnlySelectedLot(): void
    {
        $clinical =
            $this->createVaccinationReference();

        $product =
            $this->createProductForVaccine(
                $clinical['vaccine_id'],
                true,
                false
            );

        $lotA =
            $this->createLot(
                $product
            );

        $lotB =
            $this->createLot(
                $product
            );

        $inventory =
            $this->createInventory();

        $this->addStock(
            $inventory,
            $product,
            10,
            $lotA
        );

        $this->addStock(
            $inventory,
            $product,
            10,
            $lotB
        );

        (new InventoryService())
            ->consumeClinical(
                [
                    'inventario_id' =>
                    $inventory,

                    'producto_id' =>
                    $product,

                    'lote_id' =>
                    $lotA,

                    'cantidad' => 4,

                    'referencia_tipo' =>
                    'VACUNACION',

                    'referencia_id' =>
                    $clinical['vaccination_id'],
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $this->assertSame(
            6.0,
            $this->stock(
                $inventory,
                $product,
                $lotA
            )
        );

        $this->assertSame(
            10.0,
            $this->stock(
                $inventory,
                $product,
                $lotB
            )
        );

        $this->assertSame(
            16.0,
            $this->stock(
                $inventory,
                $product
            )
        );
    }

    public function testClinicalConsumptionWritesInventoryAudit(): void
    {
        $clinical =
            $this->createVaccinationReference();

        $product =
            $this->createProductForVaccine(
                $clinical['vaccine_id']
            );

        $inventory =
            $this->createInventory();

        $this->addStock(
            $inventory,
            $product,
            10
        );

        $movementId =
            (new InventoryService())
            ->consumeClinical(
                [
                    'inventario_id' =>
                    $inventory,

                    'producto_id' =>
                    $product,

                    'cantidad' => 1,

                    'referencia_tipo' =>
                    'VACUNACION',

                    'referencia_id' =>
                    $clinical['vaccination_id'],

                    'observaciones' =>
                    'Consumo vacuna QA',
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $stmt = $this->db()->prepare(
            '
            SELECT *
            FROM auditoria
            WHERE modulo = :modulo
              AND accion = :accion
              AND tabla_afectada =
                  :tabla
              AND registro_id =
                  :registro
            ORDER BY id DESC
            LIMIT 1
            '
        );

        $stmt->execute([
            'modulo' =>
            'INVENTARIO',

            'accion' =>
            'MOVIMIENTO',

            'tabla' =>
            'movimientos_inventario',

            'registro' =>
            $movementId,
        ]);

        $audit = $stmt->fetch();

        $this->assertNotFalse(
            $audit
        );

        $this->assertSame(
            $this->vetEnvironment,
            (int) $audit['entorno_id']
        );

        $this->assertSame(
            $this->superAdminId,
            (int) $audit['usuario_id']
        );
    }
}
