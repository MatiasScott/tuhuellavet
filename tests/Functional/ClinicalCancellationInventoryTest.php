<?php

namespace Tests\Functional;

use App\Services\InventoryService;
use App\Services\PreventiveCareService;
use App\Services\TreatmentService;
use PDO;
use RuntimeException;

class ClinicalCancellationInventoryTest extends ClinicalTestCase
{
    private int $userId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userId =
            $this->findSuperAdmin();
    }


    /*
     * =========================================================
     * HELPERS GENERALES
     * =========================================================
     */

    private function unitId(): int
    {
        $id = $this->scalar(
            '
            SELECT id
            FROM unidades_medida
            WHERE activo = 1
            ORDER BY id
            LIMIT 1
            '
        );

        if (!$id) {
            $this->fail(
                'No existe una unidad de medida activa.'
            );
        }

        return (int) $id;
    }


    private function movementType(
        string $code
    ): int {
        $id = $this->scalar(
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

        if (!$id) {
            $this->fail(
                'No existe el tipo de movimiento '
                    . $code
            );
        }

        return (int) $id;
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
                descripcion,
                activo
            )
            VALUES
            (
                :entorno,
                :nombre,
                :descripcion,
                1
            )
            '
        );

        $stmt->execute([
            'entorno' =>
            $environmentId,

            'nombre' =>
            'QA CANCEL '
                . bin2hex(random_bytes(4)),

            'descripcion' =>
            'Inventario temporal QA-09.8B',
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
            'Vacuna QA Cancel '
                . bin2hex(random_bytes(4)),

            'descripcion' =>
            'Fixture QA-09.8B',
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
                descripcion,
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
                :descripcion,
                :unidad,
                :vacuna,
                0,
                0,
                1
            )
            '
        );

        $token =
            strtoupper(
                bin2hex(
                    random_bytes(4)
                )
            );

        $stmt->execute([
            'codigo' =>
            'QA-VAC-' . $token,

            'nombre' =>
            'Producto vacuna QA '
                . $token,

            'descripcion' =>
            'Producto temporal QA-09.8B',

            'unidad' =>
            $this->unitId(),

            'vacuna' =>
            $vaccineId,
        ]);

        return (int)
        $this->db()->lastInsertId();
    }


    private function createVaccineLotProduct(
        int $vaccineId
    ): int {
        $stmt = $this->db()->prepare(
            '
            INSERT INTO productos
            (
                codigo,
                nombre,
                descripcion,
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
                :descripcion,
                :unidad,
                :vacuna,
                1,
                1,
                1
            )
            '
        );

        $token =
            strtoupper(
                bin2hex(
                    random_bytes(4)
                )
            );

        $stmt->execute([
            'codigo' =>
            'QA-VACL-' . $token,

            'nombre' =>
            'Producto vacuna lote QA '
                . $token,

            'descripcion' =>
            'Producto temporal QA-09.8B',

            'unidad' =>
            $this->unitId(),

            'vacuna' =>
            $vaccineId,
        ]);

        return (int)
        $this->db()->lastInsertId();
    }


    private function createLot(
        int $productId,
        string $expiration = '+1 year'
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
                :lote,
                :vencimiento
            )
            '
        );

        $stmt->execute([
            'producto' =>
            $productId,

            'lote' =>
            'QA-'
                . strtoupper(
                    bin2hex(
                        random_bytes(4)
                    )
                ),

            'vencimiento' =>
            date(
                'Y-m-d',
                strtotime($expiration)
            ),
        ]);

        return (int)
        $this->db()->lastInsertId();
    }


    private function associateProduct(
        int $inventoryId,
        int $productId
    ): void {
        $stmt = $this->db()->prepare(
            '
            INSERT INTO inventario_productos
            (
                inventario_id,
                producto_id,
                activo
            )
            VALUES
            (
                :inventario,
                :producto,
                1
            )
            '
        );

        $stmt->execute([
            'inventario' =>
            $inventoryId,

            'producto' =>
            $productId,
        ]);
    }


    private function addStock(
        int $inventoryId,
        int $productId,
        float $quantity,
        ?int $lotId = null,
        ?int $environmentId = null
    ): int {
        $environmentId ??=
            $this->vetEnvironment;

        return (new InventoryService())
            ->movement(
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

                    'costo_unitario' =>
                    1,
                ],
                $environmentId,
                $this->userId
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


    private function vaccinationIdFromEvent(
        int $eventId
    ): int {
        $id = $this->scalar(
            '
            SELECT id
            FROM vacunaciones
            WHERE evento_clinico_id = :evento
            LIMIT 1
            ',
            [
                'evento' =>
                $eventId,
            ]
        );

        if (!$id) {
            $this->fail(
                'No se encontró la vacunación del evento.'
            );
        }

        return (int) $id;
    }


    private function createVaccination(
        int $patientId,
        int $vaccineId,
        ?array $inventoryConsumption = null,
        ?string $revaccinationDate = null,
        ?int $environmentId = null
    ): array {
        $environmentId ??=
            $this->vetEnvironment;

        $data = [
            'animal_id' =>
            $patientId,

            'vacuna_id' =>
            $vaccineId,

            'dosis' =>
            1,

            'unidad_dosis_id' =>
            $this->unitId(),

            'fecha_evento' =>
            date('Y-m-d H:i:s'),

            'observaciones' =>
            'QA cancelación',
        ];

        if ($revaccinationDate !== null) {
            $data['fecha_revacunacion'] =
                $revaccinationDate;
        }

        if ($inventoryConsumption !== null) {
            $data['inventario_consumo'] =
                $inventoryConsumption;
        }

        $eventId =
            (new PreventiveCareService())
            ->createVaccination(
                $data,
                $environmentId,
                $this->userId
            );

        return [
            'event_id' =>
            $eventId,

            'vaccination_id' =>
            $this->vaccinationIdFromEvent(
                $eventId
            ),
        ];
    }


    private function notificationTypeId(): int
    {
        $id = $this->scalar(
            '
            SELECT id
            FROM tipos_notificacion
            WHERE activo = 1
            ORDER BY id
            LIMIT 1
            '
        );

        if (!$id) {
            $id = $this->scalar(
                '
                SELECT id
                FROM tipos_notificacion
                ORDER BY id
                LIMIT 1
                '
            );
        }

        return (int) $id;
    }


    private function notificationChannelId(): int
    {
        $id = $this->scalar(
            '
            SELECT id
            FROM canales_notificacion
            WHERE codigo = "INTERNA"
            LIMIT 1
            '
        );

        if (!$id) {
            $this->fail(
                'No existe canal INTERNA.'
            );
        }

        return (int) $id;
    }


    private function createNotification(
        int $vaccinationId,
        string $status
    ): int {
        $stmt = $this->db()->prepare(
            '
            INSERT INTO notificaciones
            (
                entorno_id,
                tipo_notificacion_id,
                canal_id,
                destinatario_usuario_id,
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
                :asunto,
                :mensaje,
                DATE_ADD(
                    NOW(),
                    INTERVAL 7 DAY
                ),
                :estado,
                "VACUNACION",
                :referencia,
                0
            )
            '
        );

        $stmt->execute([
            'entorno' =>
            $this->vetEnvironment,

            'tipo' =>
            $this->notificationTypeId(),

            'canal' =>
            $this->notificationChannelId(),

            'usuario' =>
            $this->userId,

            'asunto' =>
            'Recordatorio QA',

            'mensaje' =>
            'Notificación temporal QA-09.8B',

            'estado' =>
            $status,

            'referencia' =>
            $vaccinationId,
        ]);

        return (int)
        $this->db()->lastInsertId();
    }


    private function notificationStatus(
        int $notificationId
    ): string {
        return (string)
        $this->scalar(
            '
                SELECT estado
                FROM notificaciones
                WHERE id = :id
                ',
            [
                'id' =>
                $notificationId,
            ]
        );
    }


    private function eventCancelledAt(
        int $eventId
    ): mixed {
        return $this->scalar(
            '
            SELECT anulado_at
            FROM eventos_clinicos
            WHERE id = :id
            ',
            [
                'id' =>
                $eventId,
            ]
        );
    }


    /*
     * =========================================================
     * VACUNACIÓN
     * =========================================================
     */


    public function testVaccinationWithInventoryRestoresStockWhenCancelled(): void
    {
        $patient =
            $this->createPatient();

        $vaccine =
            $this->createVaccine();

        $inventory =
            $this->createInventory();

        $product =
            $this->createVaccineProduct(
                $vaccine
            );

        $this->associateProduct(
            $inventory,
            $product
        );

        $this->addStock(
            $inventory,
            $product,
            10
        );

        $before =
            $this->stock(
                $inventory,
                $product
            );

        $fixture =
            $this->createVaccination(
                $patient,
                $vaccine,
                [
                    'inventario_id' =>
                    $inventory,

                    'producto_id' =>
                    $product,

                    'cantidad_consumida' =>
                    2,
                ]
            );

        $afterConsumption =
            $this->stock(
                $inventory,
                $product
            );

        $this->assertSame(
            $before - 2,
            $afterConsumption
        );

        (new PreventiveCareService())
            ->cancelVaccination(
                $fixture['vaccination_id'],
                'Error de registro',
                $this->vetEnvironment,
                $this->userId
            );

        $afterCancellation =
            $this->stock(
                $inventory,
                $product
            );

        $this->assertSame(
            $before,
            $afterCancellation
        );

        $this->assertNotNull(
            $this->eventCancelledAt(
                $fixture['event_id']
            )
        );
    }


    public function testVaccinationWithoutInventoryCanBeCancelled(): void
    {
        $patient =
            $this->createPatient();

        $vaccine =
            $this->createVaccine();

        $fixture =
            $this->createVaccination(
                $patient,
                $vaccine
            );

        (new PreventiveCareService())
            ->cancelVaccination(
                $fixture['vaccination_id'],
                'Vacunación ingresada por error',
                $this->vetEnvironment,
                $this->userId
            );

        $this->assertNotNull(
            $this->eventCancelledAt(
                $fixture['event_id']
            )
        );
    }


    public function testVaccinationCannotBeCancelledTwice(): void
    {
        $fixture =
            $this->createVaccination(
                $this->createPatient(),
                $this->createVaccine()
            );

        $service =
            new PreventiveCareService();

        $service->cancelVaccination(
            $fixture['vaccination_id'],
            'Primera anulación',
            $this->vetEnvironment,
            $this->userId
        );

        $this->expectException(
            RuntimeException::class
        );

        $service->cancelVaccination(
            $fixture['vaccination_id'],
            'Segunda anulación',
            $this->vetEnvironment,
            $this->userId
        );
    }


    public function testVaccinationRequiresCancellationReason(): void
    {
        $fixture =
            $this->createVaccination(
                $this->createPatient(),
                $this->createVaccine()
            );

        $this->expectException(
            RuntimeException::class
        );

        (new PreventiveCareService())
            ->cancelVaccination(
                $fixture['vaccination_id'],
                '   ',
                $this->vetEnvironment,
                $this->userId
            );
    }


    public function testVaccinationFromAnotherEnvironmentCannotBeCancelled(): void
    {
        $otherEnvironment =
            $this->findEnvironment(
                'HACIENDA'
            );

        $fixture =
            $this->createVaccination(
                $this->createPatient(
                    $otherEnvironment
                ),
                $this->createVaccine(),
                null,
                null,
                $otherEnvironment
            );

        $this->expectException(
            RuntimeException::class
        );

        (new PreventiveCareService())
            ->cancelVaccination(
                $fixture['vaccination_id'],
                'Intento cruzado',
                $this->vetEnvironment,
                $this->userId
            );
    }


    public function testVaccinationCancellationRestoresSelectedLot(): void
    {
        $patient =
            $this->createPatient();

        $vaccine =
            $this->createVaccine();

        $inventory =
            $this->createInventory();

        $product =
            $this->createVaccineLotProduct(
                $vaccine
            );

        $this->associateProduct(
            $inventory,
            $product
        );

        $lot =
            $this->createLot(
                $product
            );

        $this->addStock(
            $inventory,
            $product,
            8,
            $lot
        );

        $fixture =
            $this->createVaccination(
                $patient,
                $vaccine,
                [
                    'inventario_id' =>
                    $inventory,

                    'producto_id' =>
                    $product,

                    'lote_id' =>
                    $lot,

                    'cantidad_consumida' =>
                    3,
                ]
            );

        $this->assertSame(
            5.0,
            $this->stock(
                $inventory,
                $product,
                $lot
            )
        );

        (new PreventiveCareService())
            ->cancelVaccination(
                $fixture['vaccination_id'],
                'Anulación QA',
                $this->vetEnvironment,
                $this->userId
            );

        $this->assertSame(
            8.0,
            $this->stock(
                $inventory,
                $product,
                $lot
            )
        );

        $reversalLot =
            $this->scalar(
                '
                SELECT r.lote_id

                FROM movimientos_inventario original

                INNER JOIN movimientos_inventario r
                    ON r.referencia_tipo =
                       "REVERSO_INVENTARIO"

                   AND r.referencia_id =
                       original.id

                WHERE original.referencia_tipo =
                      "VACUNACION"

                  AND original.referencia_id =
                      :vacunacion

                ORDER BY r.id DESC
                LIMIT 1
                ',
                [
                    'vacunacion' =>
                    $fixture['vaccination_id'],
                ]
            );

        $this->assertSame(
            $lot,
            (int) $reversalLot
        );
    }


    /*
     * =========================================================
     * NOTIFICACIONES
     * =========================================================
     */


    public function testPendingVaccinationNotificationIsCancelled(): void
    {
        $fixture =
            $this->createVaccination(
                $this->createPatient(),
                $this->createVaccine()
            );

        $notification =
            $this->createNotification(
                $fixture['vaccination_id'],
                'PENDIENTE'
            );

        (new PreventiveCareService())
            ->cancelVaccination(
                $fixture['vaccination_id'],
                'Anulación QA',
                $this->vetEnvironment,
                $this->userId
            );

        $this->assertSame(
            'CANCELADA',
            $this->notificationStatus(
                $notification
            )
        );
    }


    public function testScheduledVaccinationNotificationIsCancelled(): void
    {
        $fixture =
            $this->createVaccination(
                $this->createPatient(),
                $this->createVaccine()
            );

        $notification =
            $this->createNotification(
                $fixture['vaccination_id'],
                'PROGRAMADA'
            );

        (new PreventiveCareService())
            ->cancelVaccination(
                $fixture['vaccination_id'],
                'Anulación QA',
                $this->vetEnvironment,
                $this->userId
            );

        $this->assertSame(
            'CANCELADA',
            $this->notificationStatus(
                $notification
            )
        );
    }


    public function testSentVaccinationNotificationRemainsSent(): void
    {
        $fixture =
            $this->createVaccination(
                $this->createPatient(),
                $this->createVaccine()
            );

        $notification =
            $this->createNotification(
                $fixture['vaccination_id'],
                'ENVIADA'
            );

        (new PreventiveCareService())
            ->cancelVaccination(
                $fixture['vaccination_id'],
                'Anulación QA',
                $this->vetEnvironment,
                $this->userId
            );

        $this->assertSame(
            'ENVIADA',
            $this->notificationStatus(
                $notification
            )
        );
    }


    /*
     * =========================================================
     * IDEMPOTENCIA DEL INVENTARIO
     * =========================================================
     */


    public function testPreviouslyReversedConsumptionIsNotRestoredTwice(): void
    {
        $patient =
            $this->createPatient();

        $vaccine =
            $this->createVaccine();

        $inventory =
            $this->createInventory();

        $product =
            $this->createVaccineProduct(
                $vaccine
            );

        $this->associateProduct(
            $inventory,
            $product
        );

        $this->addStock(
            $inventory,
            $product,
            10
        );

        $fixture =
            $this->createVaccination(
                $patient,
                $vaccine,
                [
                    'inventario_id' =>
                    $inventory,

                    'producto_id' =>
                    $product,

                    'cantidad_consumida' =>
                    2,
                ]
            );

        $consumptionId =
            (int)
            $this->scalar(
                '
                SELECT id
                FROM movimientos_inventario
                WHERE referencia_tipo =
                      "VACUNACION"
                  AND referencia_id =
                      :vacunacion
                ORDER BY id DESC
                LIMIT 1
                ',
                [
                    'vacunacion' =>
                    $fixture['vaccination_id'],
                ]
            );

        (new InventoryService())
            ->reverseMovement(
                $consumptionId,
                $this->vetEnvironment,
                $this->userId,
                'Reverso previo QA'
            );

        $stockBeforeCancel =
            $this->stock(
                $inventory,
                $product
            );

        $this->assertSame(
            10.0,
            $stockBeforeCancel
        );

        (new PreventiveCareService())
            ->cancelVaccination(
                $fixture['vaccination_id'],
                'Anulación posterior',
                $this->vetEnvironment,
                $this->userId
            );

        $this->assertSame(
            $stockBeforeCancel,
            $this->stock(
                $inventory,
                $product
            )
        );

        $reversalCount =
            (int)
            $this->scalar(
                '
                SELECT COUNT(*)
                FROM movimientos_inventario
                WHERE referencia_tipo =
                      "REVERSO_INVENTARIO"
                  AND referencia_id =
                      :movimiento
                ',
                [
                    'movimiento' =>
                    $consumptionId,
                ]
            );

        $this->assertSame(
            1,
            $reversalCount
        );
    }


    /*
     * =========================================================
     * AUDITORÍA
     * =========================================================
     */


    public function testVaccinationCancellationWritesAudit(): void
    {
        $fixture =
            $this->createVaccination(
                $this->createPatient(),
                $this->createVaccine()
            );

        (new PreventiveCareService())
            ->cancelVaccination(
                $fixture['vaccination_id'],
                'Auditoría QA',
                $this->vetEnvironment,
                $this->userId
            );

        $audit =
            $this->db()
            ->prepare(
                '
                SELECT
                    id,
                    usuario_id,
                    entorno_id,
                    modulo,
                    accion,
                    tabla_afectada,
                    registro_id,
                    datos_nuevos

                FROM auditoria

                WHERE usuario_id =
                      :usuario

                  AND entorno_id =
                      :entorno

                  AND modulo =
                      "VACUNAS"

                  AND accion =
                      "ANULAR"

                  AND tabla_afectada =
                      "vacunaciones"

                  AND registro_id =
                      :registro

                ORDER BY id DESC

                LIMIT 1
                '
            );

        $audit->execute([
            'usuario' =>
            $this->userId,

            'entorno' =>
            $this->vetEnvironment,

            'registro' =>
            $fixture['vaccination_id'],
        ]);

        $row =
            $audit->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);

        $this->assertSame(
            'VACUNAS',
            $row['modulo']
        );

        $this->assertSame(
            'ANULAR',
            $row['accion']
        );

        $this->assertSame(
            'vacunaciones',
            $row['tabla_afectada']
        );

        $this->assertSame(
            $fixture['vaccination_id'],
            (int) $row['registro_id']
        );

        $newData =
            json_decode(
                $row['datos_nuevos'],
                true
            );

        $this->assertIsArray(
            $newData
        );

        $this->assertSame(
            'Auditoría QA',
            $newData['motivo_anulacion']
        );
    }

    private function pharmaceuticalFormId(): int
    {
        $id = $this->scalar(
            '
        SELECT id
        FROM formas_farmaceuticas
        WHERE activo = 1
        ORDER BY id
        LIMIT 1
        '
        );

        if (!$id) {
            $this->fail(
                'No existe una forma farmacéutica activa.'
            );
        }

        return (int) $id;
    }


    private function createDrugPresentation(
        int $drugId
    ): int {
        $stmt = $this->db()->prepare(
            '
        INSERT INTO farmaco_presentaciones
        (
            farmaco_id,
            forma_farmaceutica_id,
            nombre_comercial,
            contenido_cantidad,
            contenido_unidad_id,
            descripcion,
            activo
        )
        VALUES
        (
            :farmaco,
            :forma,
            :nombre,
            :cantidad,
            :unidad,
            :descripcion,
            1
        )
        '
        );

        $stmt->execute([
            'farmaco' =>
            $drugId,

            'forma' =>
            $this->pharmaceuticalFormId(),

            'nombre' =>
            'Presentación QA '
                . bin2hex(
                    random_bytes(4)
                ),

            'cantidad' =>
            100,

            'unidad' =>
            $this->unitId(),

            'descripcion' =>
            'Fixture QA-09.8B',
        ]);

        return (int)
        $this->db()->lastInsertId();
    }


    private function createMedicationProduct(
        int $presentationId
    ): int {
        $stmt = $this->db()->prepare(
            '
        INSERT INTO productos
        (
            codigo,
            nombre,
            descripcion,
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
            :descripcion,
            :unidad,
            :presentacion,
            0,
            0,
            1
        )
        '
        );

        $token =
            strtoupper(
                bin2hex(
                    random_bytes(4)
                )
            );

        $stmt->execute([
            'codigo' =>
            'QA-MED-' . $token,

            'nombre' =>
            'Medicamento QA '
                . $token,

            'descripcion' =>
            'Producto clínico QA-09.8B',

            'unidad' =>
            $this->unitId(),

            'presentacion' =>
            $presentationId,
        ]);

        return (int)
        $this->db()->lastInsertId();
    }


    private function createMedicationFixture(
        ?int $environmentId = null
    ): array {
        $environmentId ??=
            $this->vetEnvironment;

        $patientId =
            $this->createPatient(
                $environmentId
            );

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
            :responsable,
            NOW(),
            :titulo
        )
        '
        );

        $stmt->execute([
            'animal' =>
            $patientId,

            'tipo' =>
            $eventTypeId,

            'responsable' =>
            $this->userId,

            'titulo' =>
            'Consulta QA cancelación',
        ]);

        $eventId =
            (int)
            $this->db()->lastInsertId();

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
            fecha_inicio,
            instrucciones_generales
        )
        VALUES
        (
            :evento,
            :tipo,
            :usuario,
            NOW(),
            :instrucciones
        )
        '
        );

        $stmt->execute([
            'evento' =>
            $eventId,

            'tipo' =>
            $treatmentTypeId,

            'usuario' =>
            $this->userId,

            'instrucciones' =>
            'Tratamiento QA',
        ]);

        $treatmentId =
            (int)
            $this->db()->lastInsertId();

        $drugId =
            $this->createDrug();

        $presentationId =
            $this->createDrugPresentation(
                $drugId
            );

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
            :dosis,
            :unidad,
            0
        )
        '
        );

        $stmt->execute([
            'tratamiento' =>
            $treatmentId,

            'farmaco' =>
            $drugId,

            'presentacion' =>
            $presentationId,

            'dosis' =>
            1,

            'unidad' =>
            $this->unitId(),
        ]);

        $medicationId =
            (int)
            $this->db()->lastInsertId();

        return [
            'patient_id' =>
            $patientId,

            'event_id' =>
            $eventId,

            'treatment_id' =>
            $treatmentId,

            'medication_id' =>
            $medicationId,

            'drug_id' =>
            $drugId,

            'presentation_id' =>
            $presentationId,
        ];
    }


    private function latestApplicationId(
        int $medicationId
    ): int {
        $id = $this->scalar(
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

        if (!$id) {
            $this->fail(
                'No se encontró la aplicación creada.'
            );
        }

        return (int) $id;
    }


    private function applicationCancelledAt(
        int $applicationId
    ): mixed {
        return $this->scalar(
            '
        SELECT anulado_at
        FROM medicamento_aplicaciones
        WHERE id = :id
        ',
            [
                'id' =>
                $applicationId,
            ]
        );
    }

    public function testMedicationApplicationWithInventoryRestoresStockWhenCancelled(): void
    {
        $fixture =
            $this->createMedicationFixture();

        $inventory =
            $this->createInventory();

        $product =
            $this->createMedicationProduct(
                $fixture['presentation_id']
            );

        $this->associateProduct(
            $inventory,
            $product
        );

        $this->addStock(
            $inventory,
            $product,
            10
        );

        (new TreatmentService())
            ->addApplication(
                $fixture['medication_id'],
                1,
                $this->unitId(),
                'Aplicación QA',
                $this->vetEnvironment,
                $this->userId,
                [
                    'inventario_id' =>
                    $inventory,

                    'producto_id' =>
                    $product,

                    'cantidad_consumida' =>
                    2,
                ]
            );

        $applicationId =
            $this->latestApplicationId(
                $fixture['medication_id']
            );

        $this->assertSame(
            8.0,
            $this->stock(
                $inventory,
                $product
            )
        );

        (new TreatmentService())
            ->cancelApplication(
                $applicationId,
                'Aplicación registrada por error',
                $this->vetEnvironment,
                $this->userId
            );

        $this->assertSame(
            10.0,
            $this->stock(
                $inventory,
                $product
            )
        );

        $this->assertNotNull(
            $this->applicationCancelledAt(
                $applicationId
            )
        );
    }


    public function testMedicationApplicationWithoutInventoryCanBeCancelled(): void
    {
        $fixture =
            $this->createMedicationFixture();

        (new TreatmentService())
            ->addApplication(
                $fixture['medication_id'],
                1,
                $this->unitId(),
                null,
                $this->vetEnvironment,
                $this->userId
            );

        $applicationId =
            $this->latestApplicationId(
                $fixture['medication_id']
            );

        (new TreatmentService())
            ->cancelApplication(
                $applicationId,
                'Corrección clínica',
                $this->vetEnvironment,
                $this->userId
            );

        $this->assertNotNull(
            $this->applicationCancelledAt(
                $applicationId
            )
        );
    }


    public function testMedicationApplicationCannotBeCancelledTwice(): void
    {
        $fixture =
            $this->createMedicationFixture();

        (new TreatmentService())
            ->addApplication(
                $fixture['medication_id'],
                1,
                $this->unitId(),
                null,
                $this->vetEnvironment,
                $this->userId
            );

        $applicationId =
            $this->latestApplicationId(
                $fixture['medication_id']
            );

        $service =
            new TreatmentService();

        $service->cancelApplication(
            $applicationId,
            'Primera anulación',
            $this->vetEnvironment,
            $this->userId
        );

        $this->expectException(
            RuntimeException::class
        );

        $service->cancelApplication(
            $applicationId,
            'Segunda anulación',
            $this->vetEnvironment,
            $this->userId
        );
    }


    public function testMedicationApplicationRequiresCancellationReason(): void
    {
        $fixture =
            $this->createMedicationFixture();

        (new TreatmentService())
            ->addApplication(
                $fixture['medication_id'],
                1,
                $this->unitId(),
                null,
                $this->vetEnvironment,
                $this->userId
            );

        $applicationId =
            $this->latestApplicationId(
                $fixture['medication_id']
            );

        $this->expectException(
            RuntimeException::class
        );

        (new TreatmentService())
            ->cancelApplication(
                $applicationId,
                '   ',
                $this->vetEnvironment,
                $this->userId
            );
    }


    public function testMedicationApplicationFromAnotherEnvironmentCannotBeCancelled(): void
    {
        $otherEnvironment =
            $this->findEnvironment(
                'HACIENDA'
            );

        $fixture =
            $this->createMedicationFixture(
                $otherEnvironment
            );

        (new TreatmentService())
            ->addApplication(
                $fixture['medication_id'],
                1,
                $this->unitId(),
                null,
                $otherEnvironment,
                $this->userId
            );

        $applicationId =
            $this->latestApplicationId(
                $fixture['medication_id']
            );

        $this->expectException(
            RuntimeException::class
        );

        (new TreatmentService())
            ->cancelApplication(
                $applicationId,
                'Intento entre entornos',
                $this->vetEnvironment,
                $this->userId
            );
    }

    public function testVaccinationCancellationRollsBackCompletelyWhenInventoryReversalFails(): void
    {
        $patient =
            $this->createPatient();

        $vaccine =
            $this->createVaccine();

        $inventory =
            $this->createInventory();

        $product =
            $this->createVaccineProduct(
                $vaccine
            );

        $this->associateProduct(
            $inventory,
            $product
        );

        $this->addStock(
            $inventory,
            $product,
            10
        );

        $fixture =
            $this->createVaccination(
                $patient,
                $vaccine,
                [
                    'inventario_id' =>
                    $inventory,

                    'producto_id' =>
                    $product,

                    'cantidad_consumida' =>
                    2,
                ]
            );

        $notification =
            $this->createNotification(
                $fixture['vaccination_id'],
                'PENDIENTE'
            );

        /*
     * Después del consumo:
     * 10 - 2 = 8.
     */
        $this->assertSame(
            8.0,
            $this->stock(
                $inventory,
                $product
            )
        );

        /*
     * Localizamos el movimiento clínico.
     */
        $consumptionId =
            (int)
            $this->scalar(
                '
            SELECT id

            FROM movimientos_inventario

            WHERE referencia_tipo =
                  "VACUNACION"

              AND referencia_id =
                  :vacunacion

            ORDER BY id DESC

            LIMIT 1
            ',
                [
                    'vacunacion' =>
                    $fixture['vaccination_id'],
                ]
            );

        $this->assertGreaterThan(
            0,
            $consumptionId
        );

        /*
     * Saboteamos temporalmente el catálogo
     * de reverso.
     *
     * Todo el test ya vive dentro de la
     * transacción de ClinicalTestCase,
     * así que tearDown() restaurará este
     * cambio automáticamente.
     */
        $stmt =
            $this->db()->prepare(
                '
            UPDATE tipos_movimiento_inventario

            SET codigo =
                :codigo_temporal

            WHERE codigo =
                  "AJUSTE_POSITIVO"
            '
            );

        $stmt->execute([
            'codigo_temporal' =>
            'QA_AJUSTE_POS_'
                . strtoupper(
                    bin2hex(
                        random_bytes(3)
                    )
                ),
        ]);

        $this->assertSame(
            1,
            $stmt->rowCount()
        );

        try {
            (new PreventiveCareService())
                ->cancelVaccination(
                    $fixture['vaccination_id'],
                    'Debe hacer rollback',
                    $this->vetEnvironment,
                    $this->userId
                );

            $this->fail(
                'La anulación debía fallar al no existir AJUSTE_POSITIVO.'
            );
        } catch (RuntimeException $e) {
            /*
         * El error es esperado.
         */
            $this->assertNotSame(
                '',
                trim(
                    $e->getMessage()
                )
            );
        }

        /*
     * 1. El evento NO debe quedar anulado.
     */
        $this->assertNull(
            $this->eventCancelledAt(
                $fixture['event_id']
            )
        );

        /*
     * 2. La notificación tampoco puede quedar
     * cancelada porque todo el proceso falló.
     */
        $this->assertSame(
            'PENDIENTE',
            $this->notificationStatus(
                $notification
            )
        );

        /*
     * 3. El stock debe continuar en 8.
     *
     * Si apareciera 10 significaría que el
     * reverso sobrevivió al rollback.
     */
        $this->assertSame(
            8.0,
            $this->stock(
                $inventory,
                $product
            )
        );

        /*
     * 4. No puede existir ningún reverso
     * confirmado del consumo.
     */
        $reversalCount =
            (int)
            $this->scalar(
                '
            SELECT COUNT(*)

            FROM movimientos_inventario

            WHERE referencia_tipo =
                  "REVERSO_INVENTARIO"

              AND referencia_id =
                  :movimiento
            ',
                [
                    'movimiento' =>
                    $consumptionId,
                ]
            );

        $this->assertSame(
            0,
            $reversalCount
        );

        /*
     * 5. Tampoco debe haberse escrito
     * auditoría de anulación.
     */
        $auditCount =
            (int)
            $this->scalar(
                '
            SELECT COUNT(*)

            FROM auditoria

            WHERE modulo =
                  "VACUNAS"

              AND accion =
                  "ANULAR"

              AND tabla_afectada =
                  "vacunaciones"

              AND registro_id =
                  :registro
            ',
                [
                    'registro' =>
                    $fixture['vaccination_id'],
                ]
            );

        $this->assertSame(
            0,
            $auditCount
        );
    }
}
