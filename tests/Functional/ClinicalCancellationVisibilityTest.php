<?php

namespace Tests\Functional;

use App\Models\ClinicalHistory;
use App\Models\PreventiveCare;
use App\Models\Treatment;

use App\Services\PreventiveCareService;
use App\Services\TreatmentService;
use App\Services\InventoryService;

use RuntimeException;
use PDO;

class ClinicalCancellationVisibilityTest extends ClinicalTestCase
{
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId =
            $this->findSuperAdmin();
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
            'Vacuna visibilidad '
                . bin2hex(random_bytes(4)),

            'descripcion' =>
            'QA-09.8C',
        ]);

        return (int)
        $this->db()->lastInsertId();
    }


    private function createVaccination(
        ?string $revaccinationDate = null,
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

        $data = [
            'animal_id' =>
            $patientId,

            'vacuna_id' =>
            $vaccineId,

            'fecha_evento' =>
            date('Y-m-d H:i:s'),

            'dosis' =>
            1,

            'observaciones' =>
            'Vacunación QA-09.8C',
        ];

        if ($revaccinationDate !== null) {
            $data['fecha_revacunacion'] =
                $revaccinationDate;
        }

        $eventId =
            (new PreventiveCareService())
            ->createVaccination(
                $data,
                $environmentId,
                $this->userId
            );

        $vaccinationId =
            (int)
            $this->scalar(
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

            'vaccine_id' =>
            $vaccineId,

            'event_id' =>
            $eventId,

            'vaccination_id' =>
            $vaccinationId,
        ];
    }

    private function inventoryUnitId(): int
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


    private function inventoryMovementType(
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


    private function createInventoryForCancellationTest(): int
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
            'Inventario cancelación QA '
                . uniqid(),
        ]);

        return (int)
        $this->db()->lastInsertId();
    }


    private function createVaccineProduct(
        int $vaccineId
    ): int {
        $stmt = $this->db()->prepare(
            '
        INSERT INTO productos
        (
            codigo,
            nombre,
            unidad_base_id,
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
            :vacuna,
            0,
            0,
            1
        )
        '
        );

        $stmt->execute([
            'codigo' =>
            'QA_VAC_CANCEL_'
                . strtoupper(
                    str_replace(
                        '.',
                        '',
                        uniqid('', true)
                    )
                ),

            'nombre' =>
            'Producto vacuna cancelada '
                . uniqid(),

            'unidad' =>
            $this->inventoryUnitId(),

            'vacuna' =>
            $vaccineId,
        ]);

        return (int)
        $this->db()->lastInsertId();
    }


    private function addInventoryStock(
        int $inventoryId,
        int $productId,
        float $quantity
    ): int {
        return (new InventoryService())
            ->movement(
                [
                    'inventario_id' =>
                    $inventoryId,

                    'producto_id' =>
                    $productId,

                    'tipo_movimiento_id' =>
                    $this->inventoryMovementType(
                        'ENTRADA'
                    ),

                    'cantidad' =>
                    $quantity,
                ],
                $this->vetEnvironment,
                $this->userId
            );
    }


    private function inventoryStock(
        int $inventoryId,
        int $productId
    ): float {
        return (float) $this->scalar(
            '
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
        ',
            [
                'inventario' =>
                $inventoryId,

                'producto' =>
                $productId,
            ]
        );
    }


    private function containsVaccination(
        array $rows,
        int $vaccinationId
    ): bool {
        foreach ($rows as $row) {
            if (
                (int) ($row['id'] ?? 0)
                === $vaccinationId
            ) {
                return true;
            }
        }

        return false;
    }


    private function timelineEvent(
        array $rows,
        int $eventId
    ): ?array {
        foreach ($rows as $row) {
            if (
                (int) ($row['id'] ?? 0)
                === $eventId
            ) {
                return $row;
            }
        }

        return null;
    }


    public function testActiveVaccinationAppearsInOperationalList(): void
    {
        $fixture =
            $this->createVaccination();

        $rows =
            (new PreventiveCare())
            ->vaccinationsByEnvironment(
                $this->vetEnvironment
            );

        $this->assertTrue(
            $this->containsVaccination(
                $rows,
                $fixture['vaccination_id']
            )
        );
    }


    public function testCancelledVaccinationDisappearsFromOperationalList(): void
    {
        $fixture =
            $this->createVaccination();

        (new PreventiveCareService())
            ->cancelVaccination(
                $fixture['vaccination_id'],
                'Registro duplicado',
                $this->vetEnvironment,
                $this->userId
            );

        $rows =
            (new PreventiveCare())
            ->vaccinationsByEnvironment(
                $this->vetEnvironment
            );

        $this->assertFalse(
            $this->containsVaccination(
                $rows,
                $fixture['vaccination_id']
            )
        );
    }


    public function testCancelledVaccinationRemainsInClinicalHistory(): void
    {
        $fixture =
            $this->createVaccination();

        (new PreventiveCareService())
            ->cancelVaccination(
                $fixture['vaccination_id'],
                'Vacunación duplicada',
                $this->vetEnvironment,
                $this->userId
            );

        $timeline =
            (new ClinicalHistory())
            ->timeline(
                $fixture['patient_id'],
                $this->vetEnvironment
            );

        $event =
            $this->timelineEvent(
                $timeline,
                $fixture['event_id']
            );

        $this->assertIsArray(
            $event
        );

        $this->assertSame(
            $fixture['vaccination_id'],
            (int) $event['vacunacion_id']
        );
    }


    public function testClinicalHistoryMarksCancelledVaccination(): void
    {
        $fixture =
            $this->createVaccination();

        (new PreventiveCareService())
            ->cancelVaccination(
                $fixture['vaccination_id'],
                'Registro clínico incorrecto',
                $this->vetEnvironment,
                $this->userId
            );

        $timeline =
            (new ClinicalHistory())
            ->timeline(
                $fixture['patient_id'],
                $this->vetEnvironment
            );

        $event =
            $this->timelineEvent(
                $timeline,
                $fixture['event_id']
            );

        $this->assertIsArray(
            $event
        );

        $this->assertSame(
            1,
            (int) $event['esta_anulado']
        );

        $this->assertNotNull(
            $event['anulado_at']
        );

        $this->assertSame(
            'Registro clínico incorrecto',
            $event['motivo_anulacion']
        );

        $this->assertSame(
            $this->userId,
            (int) $event['anulado_por']
        );

        $this->assertNotSame(
            '',
            trim(
                (string)
                $event['anulado_por_nombre']
            )
        );
    }


    public function testActiveVaccinationIsNotMarkedCancelledInHistory(): void
    {
        $fixture =
            $this->createVaccination();

        $timeline =
            (new ClinicalHistory())
            ->timeline(
                $fixture['patient_id'],
                $this->vetEnvironment
            );

        $event =
            $this->timelineEvent(
                $timeline,
                $fixture['event_id']
            );

        $this->assertIsArray(
            $event
        );

        $this->assertSame(
            0,
            (int) $event['esta_anulado']
        );

        $this->assertNull(
            $event['anulado_at']
        );

        $this->assertNull(
            $event['motivo_anulacion']
        );
    }


    public function testCancelledVaccinationDoesNotAppearAsUpcoming(): void
    {
        $fixture =
            $this->createVaccination(
                date(
                    'Y-m-d',
                    strtotime('+5 days')
                )
            );

        $before =
            (new PreventiveCare())
            ->upcomingVaccinations(
                $this->vetEnvironment,
                30
            );

        $this->assertTrue(
            $this->containsVaccination(
                $before,
                $fixture['vaccination_id']
            )
        );

        (new PreventiveCareService())
            ->cancelVaccination(
                $fixture['vaccination_id'],
                'No debe generar próximo control',
                $this->vetEnvironment,
                $this->userId
            );

        $after =
            (new PreventiveCare())
            ->upcomingVaccinations(
                $this->vetEnvironment,
                30
            );

        $this->assertFalse(
            $this->containsVaccination(
                $after,
                $fixture['vaccination_id']
            )
        );
    }


    public function testCancellationDoesNotExposeVaccinationInAnotherEnvironment(): void
    {
        $otherEnvironment =
            $this->findEnvironment(
                'HACIENDA'
            );

        $fixture =
            $this->createVaccination(
                null,
                $otherEnvironment
            );

        $rows =
            (new PreventiveCare())
            ->vaccinationsByEnvironment(
                $this->vetEnvironment
            );

        $this->assertFalse(
            $this->containsVaccination(
                $rows,
                $fixture['vaccination_id']
            )
        );
    }

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


    private function pharmaceuticalFormId(): int
    {
        return (int) $this->scalar(
            '
        SELECT id
        FROM formas_farmaceuticas
        WHERE activo = 1
        ORDER BY id
        LIMIT 1
        '
        );
    }


    private function createMedicationFixture(): array
    {
        $patientId =
            $this->createPatient();

        $eventTypeId =
            $this->catalogId(
                'tipos_evento_clinico',
                'codigo',
                'CONSULTA_EXTERNA'
            );

        $stmt = $this->db()->prepare(
            '
        INSERT INTO eventos_clinicos
        (
            animal_id,
            tipo_evento_id,
            responsable_id,
            fecha_evento,
            titulo
        )
        VALUES
        (
            :animal,
            :tipo,
            :usuario,
            NOW(),
            "Consulta QA visibilidad"
        )
        '
        );

        $stmt->execute([
            'animal' => $patientId,
            'tipo' => $eventTypeId,
            'usuario' => $this->userId,
        ]);

        $eventId =
            (int) $this->db()->lastInsertId();

        $treatmentTypeId =
            $this->catalogId(
                'tipos_tratamiento',
                'codigo',
                'CLINICO'
            );

        $stmt = $this->db()->prepare(
            '
        INSERT INTO tratamientos
        (
            evento_clinico_id,
            tipo_tratamiento_id,
            indicado_por,
            fecha_inicio
        )
        VALUES
        (
            :evento,
            :tipo,
            :usuario,
            NOW()
        )
        '
        );

        $stmt->execute([
            'evento' => $eventId,
            'tipo' => $treatmentTypeId,
            'usuario' => $this->userId,
        ]);

        $treatmentId =
            (int) $this->db()->lastInsertId();

        $drugId =
            $this->createDrug();

        $stmt = $this->db()->prepare(
            '
        INSERT INTO farmaco_presentaciones
        (
            farmaco_id,
            forma_farmaceutica_id,
            nombre_comercial,
            contenido_cantidad,
            contenido_unidad_id,
            activo
        )
        VALUES
        (
            :farmaco,
            :forma,
            :nombre,
            100,
            :unidad,
            1
        )
        '
        );

        $stmt->execute([
            'farmaco' => $drugId,
            'forma' => $this->pharmaceuticalFormId(),
            'nombre' => 'Presentación QA ' . bin2hex(random_bytes(4)),
            'unidad' => $this->unitId(),
        ]);

        $presentationId =
            (int) $this->db()->lastInsertId();

        $stmt = $this->db()->prepare(
            '
        INSERT INTO tratamiento_medicamentos
        (
            tratamiento_id,
            farmaco_id,
            presentacion_id,
            dosis_cantidad,
            dosis_unidad_id,
            orden
        )
        VALUES
        (
            :tratamiento,
            :farmaco,
            :presentacion,
            1,
            :unidad,
            0
        )
        '
        );

        $stmt->execute([
            'tratamiento' => $treatmentId,
            'farmaco' => $drugId,
            'presentacion' => $presentationId,
            'unidad' => $this->unitId(),
        ]);

        return [
            'patient_id' => $patientId,
            'event_id' => $eventId,
            'treatment_id' => $treatmentId,
            'medication_id' =>
            (int) $this->db()->lastInsertId(),
            'presentation_id' =>
            $presentationId,
        ];
    }


    private function findApplication(
        array $applications,
        int $applicationId
    ): ?array {
        foreach ($applications as $application) {
            if (
                (int) $application['id']
                === $applicationId
            ) {
                return $application;
            }
        }

        return null;
    }

    public function testActiveMedicationApplicationAppearsAsActive(): void
    {
        $fixture =
            $this->createMedicationFixture();

        (new TreatmentService())
            ->addApplication(
                $fixture['medication_id'],
                1,
                $this->unitId(),
                'Aplicación activa',
                $this->vetEnvironment,
                $this->userId
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
                    $fixture['medication_id'],
                ]
            );

        $rows =
            (new Treatment())
            ->applications(
                $fixture['medication_id']
            );

        $application =
            $this->findApplication(
                $rows,
                $applicationId
            );

        $this->assertIsArray($application);

        $this->assertSame(
            0,
            (int) $application['esta_anulado']
        );

        $this->assertNull(
            $application['anulado_at']
        );

        $this->assertNull(
            $application['motivo_anulacion']
        );
    }


    public function testCancelledMedicationApplicationRemainsInHistory(): void
    {
        $fixture =
            $this->createMedicationFixture();

        (new TreatmentService())
            ->addApplication(
                $fixture['medication_id'],
                1,
                $this->unitId(),
                'Aplicación QA',
                $this->vetEnvironment,
                $this->userId
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
                    $fixture['medication_id'],
                ]
            );

        (new TreatmentService())
            ->cancelApplication(
                $applicationId,
                'Aplicación duplicada',
                $this->vetEnvironment,
                $this->userId
            );

        $rows =
            (new Treatment())
            ->applications(
                $fixture['medication_id']
            );

        $application =
            $this->findApplication(
                $rows,
                $applicationId
            );

        $this->assertIsArray($application);

        $this->assertSame(
            1,
            (int) $application['esta_anulado']
        );

        $this->assertNotNull(
            $application['anulado_at']
        );

        $this->assertSame(
            'Aplicación duplicada',
            $application['motivo_anulacion']
        );

        $this->assertSame(
            $this->userId,
            (int) $application['anulado_por']
        );

        $this->assertNotSame(
            '',
            trim(
                (string)
                $application['anulado_por_nombre']
            )
        );
    }

    public function testCancelledMedicationApplicationCannotConsumeInventoryAgain(): void
    {
        $fixture =
            $this->createMedicationFixture();

        /*
     * Creamos la aplicación sin inventario.
     */
        (new TreatmentService())
            ->addApplication(
                $fixture['medication_id'],
                1,
                $this->unitId(),
                'Aplicación QA',
                $this->vetEnvironment,
                $this->userId
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
                    $fixture['medication_id'],
                ]
            );

        /*
     * La anulamos.
     */
        (new TreatmentService())
            ->cancelApplication(
                $applicationId,
                'Aplicación inválida',
                $this->vetEnvironment,
                $this->userId
            );

        /*
     * Producto correspondiente a la presentación.
     */
        $inventoryStmt =
            $this->db()->prepare(
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

        $inventoryStmt->execute([
            'entorno' =>
            $this->vetEnvironment,

            'nombre' =>
            'Inventario QA '
                . bin2hex(random_bytes(4)),
        ]);

        $inventoryId =
            (int) $this->db()->lastInsertId();

        $token =
            strtoupper(
                bin2hex(random_bytes(4))
            );

        $stmt = $this->db()->prepare(
            '
        INSERT INTO productos
        (
            codigo,
            nombre,
            unidad_base_id,
            farmaco_presentacion_id,
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
            0,
            0,
            1
        )
        '
        );

        $stmt->execute([
            'codigo' =>
            'QA-CANCEL-' . $token,

            'nombre' =>
            'Producto QA ' . $token,

            'unidad' =>
            $this->unitId(),

            'presentacion' =>
            $fixture['presentation_id'],
        ]);

        $productId =
            (int) $this->db()->lastInsertId();

        /*
     * El consumo debe rechazarse ANTES de crear
     * ningún movimiento.
     */
        $before =
            (int) $this->scalar(
                '
            SELECT COUNT(*)
            FROM movimientos_inventario
            WHERE referencia_tipo =
                  "MEDICAMENTO_APLICACION"
              AND referencia_id =
                  :aplicacion
            ',
                [
                    'aplicacion' =>
                    $applicationId,
                ]
            );

        try {
            (new InventoryService())
                ->consumeClinical(
                    [
                        'inventario_id' =>
                        $inventoryId,

                        'producto_id' =>
                        $productId,

                        'cantidad' =>
                        1,

                        'referencia_tipo' =>
                        'MEDICAMENTO_APLICACION',

                        'referencia_id' =>
                        $applicationId,
                    ],
                    $this->vetEnvironment,
                    $this->userId
                );

            $this->fail(
                'Una aplicación anulada no debe poder consumir inventario.'
            );
        } catch (RuntimeException $e) {
            $this->assertNotSame(
                '',
                trim($e->getMessage())
            );
        }

        $after =
            (int) $this->scalar(
                '
            SELECT COUNT(*)
            FROM movimientos_inventario
            WHERE referencia_tipo =
                  "MEDICAMENTO_APLICACION"
              AND referencia_id =
                  :aplicacion
            ',
                [
                    'aplicacion' =>
                    $applicationId,
                ]
            );

        $this->assertSame(
            $before,
            $after
        );
    }

    public function testCancelledVaccinationCannotConsumeInventoryAgain(): void
    {
        $fixture =
            $this->createVaccination();

        $inventoryId =
            $this->createInventoryForCancellationTest();

        $productId =
            $this->createVaccineProduct(
                $fixture['vaccine_id']
            );

        $this->addInventoryStock(
            $inventoryId,
            $productId,
            10
        );

        $stockBefore =
            $this->inventoryStock(
                $inventoryId,
                $productId
            );

        $this->assertSame(
            10.0,
            $stockBefore
        );

        /*
     * Anulamos la vacunación antes de intentar
     * registrar consumo clínico.
     */
        (new PreventiveCareService())
            ->cancelVaccination(
                $fixture['vaccination_id'],
                'Vacunación anulada QA-09.9A',
                $this->vetEnvironment,
                $this->userId
            );

        $movementCountBefore =
            (int) $this->scalar(
                '
            SELECT COUNT(*)
            FROM movimientos_inventario
            WHERE referencia_tipo =
                  "VACUNACION"
              AND referencia_id =
                  :vacunacion
            ',
                [
                    'vacunacion' =>
                    $fixture['vaccination_id'],
                ]
            );

        try {
            (new InventoryService())
                ->consumeClinical(
                    [
                        'inventario_id' =>
                        $inventoryId,

                        'producto_id' =>
                        $productId,

                        'cantidad' =>
                        1,

                        'referencia_tipo' =>
                        'VACUNACION',

                        'referencia_id' =>
                        $fixture['vaccination_id'],
                    ],
                    $this->vetEnvironment,
                    $this->userId
                );

            $this->fail(
                'Una vacunación anulada no debe poder generar consumo de inventario.'
            );
        } catch (RuntimeException $e) {
            $this->assertNotSame(
                '',
                trim($e->getMessage())
            );
        }

        $movementCountAfter =
            (int) $this->scalar(
                '
            SELECT COUNT(*)
            FROM movimientos_inventario
            WHERE referencia_tipo =
                  "VACUNACION"
              AND referencia_id =
                  :vacunacion
            ',
                [
                    'vacunacion' =>
                    $fixture['vaccination_id'],
                ]
            );

        $this->assertSame(
            $movementCountBefore,
            $movementCountAfter
        );

        $this->assertSame(
            $stockBefore,
            $this->inventoryStock(
                $inventoryId,
                $productId
            )
        );
    }
}
