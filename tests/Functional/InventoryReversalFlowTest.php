<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Services\InventoryService;
use RuntimeException;

class InventoryReversalFlowTest extends ClinicalTestCase
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
            'Inventario reverso QA '
                . uniqid(),
        ]);

        return (int)
        $this->db()->lastInsertId();
    }

    private function createProduct(
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
                controla_lote,
                controla_vencimiento,
                activo
            )
            VALUES
            (
                :codigo,
                :nombre,
                :unidad,
                :lote,
                :vencimiento,
                1
            )
            '
        );

        $stmt->execute([
            'codigo' =>
            'QA_REV_'
                . strtoupper(
                    str_replace(
                        '.',
                        '',
                        uniqid('', true)
                    )
                ),

            'nombre' =>
            'Producto reverso QA '
                . uniqid(),

            'unidad' =>
            $this->unitId(),

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
            'REV-LOT-'
                . uniqid(),

            'vencimiento' =>
            $expiration ?? date(
                'Y-m-d',
                strtotime('+1 year')
            ),
        ]);

        return (int)
        $this->db()->lastInsertId();
    }

    private function movement(
        int $inventoryId,
        int $productId,
        string $type,
        float $quantity,
        ?int $lotId = null
    ): int {
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
                        $type
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
                AND mi.lote_id = :lote
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

    public function testNegativeMovementCanBeReversed(): void
    {
        $inventory =
            $this->createInventory();

        $product =
            $this->createProduct();

        $this->movement(
            $inventory,
            $product,
            'ENTRADA',
            10
        );

        $exitId =
            $this->movement(
                $inventory,
                $product,
                'SALIDA',
                4
            );

        $this->assertSame(
            6.0,
            $this->stock(
                $inventory,
                $product
            )
        );

        $reverseId =
            (new InventoryService())
            ->reverseMovement(
                $exitId,
                $this->vetEnvironment,
                $this->superAdminId,
                'Salida registrada por error'
            );

        $this->assertGreaterThan(
            0,
            $reverseId
        );

        $this->assertSame(
            10.0,
            $this->stock(
                $inventory,
                $product
            )
        );
    }

    public function testNegativeMovementReverseUsesPositiveAdjustment(): void
    {
        $inventory =
            $this->createInventory();

        $product =
            $this->createProduct();

        $this->movement(
            $inventory,
            $product,
            'ENTRADA',
            10
        );

        $exitId =
            $this->movement(
                $inventory,
                $product,
                'SALIDA',
                2
            );

        $reverseId =
            (new InventoryService())
            ->reverseMovement(
                $exitId,
                $this->vetEnvironment,
                $this->superAdminId
            );

        $stmt = $this->db()->prepare(
            '
            SELECT
                tm.codigo,
                tm.factor,
                mi.referencia_tipo,
                mi.referencia_id,
                mi.cantidad

            FROM movimientos_inventario mi

            INNER JOIN tipos_movimiento_inventario tm
                ON tm.id =
                   mi.tipo_movimiento_id

            WHERE mi.id = :id
            '
        );

        $stmt->execute([
            'id' =>
            $reverseId,
        ]);

        $row = $stmt->fetch();

        $this->assertNotFalse($row);

        $this->assertSame(
            'AJUSTE_POSITIVO',
            $row['codigo']
        );

        $this->assertSame(
            1,
            (int) $row['factor']
        );

        $this->assertSame(
            'REVERSO_INVENTARIO',
            $row['referencia_tipo']
        );

        $this->assertSame(
            $exitId,
            (int) $row['referencia_id']
        );

        $this->assertSame(
            2.0,
            (float) $row['cantidad']
        );
    }

    public function testPositiveMovementCanBeReversedWhenStockExists(): void
    {
        $inventory =
            $this->createInventory();

        $product =
            $this->createProduct();

        $entryId =
            $this->movement(
                $inventory,
                $product,
                'ENTRADA',
                10
            );

        $reverseId =
            (new InventoryService())
            ->reverseMovement(
                $entryId,
                $this->vetEnvironment,
                $this->superAdminId
            );

        $this->assertGreaterThan(
            0,
            $reverseId
        );

        $this->assertSame(
            0.0,
            $this->stock(
                $inventory,
                $product
            )
        );

        $code = $this->scalar(
            '
            SELECT tm.codigo

            FROM movimientos_inventario mi

            INNER JOIN tipos_movimiento_inventario tm
                ON tm.id =
                   mi.tipo_movimiento_id

            WHERE mi.id = :id
            ',
            [
                'id' =>
                $reverseId,
            ]
        );

        $this->assertSame(
            'AJUSTE_NEGATIVO',
            $code
        );
    }

    public function testPositiveMovementCannotBeReversedIfStockWasAlreadyConsumed(): void
    {
        $inventory =
            $this->createInventory();

        $product =
            $this->createProduct();

        $entryId =
            $this->movement(
                $inventory,
                $product,
                'ENTRADA',
                10
            );

        $this->movement(
            $inventory,
            $product,
            'SALIDA',
            4
        );

        try {
            (new InventoryService())
                ->reverseMovement(
                    $entryId,
                    $this->vetEnvironment,
                    $this->superAdminId
                );

            $this->fail(
                'El reverso debía fallar porque produciría stock negativo.'
            );
        } catch (RuntimeException) {
            $this->assertSame(
                6.0,
                $this->stock(
                    $inventory,
                    $product
                )
            );
        }
    }

    public function testMovementCannotBeReversedTwice(): void
    {
        $inventory =
            $this->createInventory();

        $product =
            $this->createProduct();

        $this->movement(
            $inventory,
            $product,
            'ENTRADA',
            10
        );

        $exitId =
            $this->movement(
                $inventory,
                $product,
                'SALIDA',
                2
            );

        $service =
            new InventoryService();

        $service->reverseMovement(
            $exitId,
            $this->vetEnvironment,
            $this->superAdminId
        );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'El movimiento ya fue revertido.'
        );

        $service->reverseMovement(
            $exitId,
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testReverseMovementCannotBeReversedAgain(): void
    {
        $inventory =
            $this->createInventory();

        $product =
            $this->createProduct();

        $this->movement(
            $inventory,
            $product,
            'ENTRADA',
            10
        );

        $exitId =
            $this->movement(
                $inventory,
                $product,
                'SALIDA',
                2
            );

        $reverseId =
            (new InventoryService())
            ->reverseMovement(
                $exitId,
                $this->vetEnvironment,
                $this->superAdminId
            );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'No se puede revertir un movimiento que ya es un reverso.'
        );

        (new InventoryService())
            ->reverseMovement(
                $reverseId,
                $this->vetEnvironment,
                $this->superAdminId
            );
    }

    public function testReversalPreservesLot(): void
    {
        $inventory =
            $this->createInventory();

        $product =
            $this->createProduct(
                true
            );

        $lot =
            $this->createLot(
                $product
            );

        $this->movement(
            $inventory,
            $product,
            'ENTRADA',
            10,
            $lot
        );

        $exitId =
            $this->movement(
                $inventory,
                $product,
                'SALIDA',
                4,
                $lot
            );

        $reverseId =
            (new InventoryService())
            ->reverseMovement(
                $exitId,
                $this->vetEnvironment,
                $this->superAdminId
            );

        $reverseLot =
            (int) $this->scalar(
                '
                SELECT lote_id
                FROM movimientos_inventario
                WHERE id = :id
                ',
                [
                    'id' =>
                    $reverseId,
                ]
            );

        $this->assertSame(
            $lot,
            $reverseLot
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

    public function testNonexistentMovementIsRejected(): void
    {
        $this->expectException(
            RuntimeException::class
        );

        (new InventoryService())
            ->reverseMovement(
                999999999,
                $this->vetEnvironment,
                $this->superAdminId
            );
    }

    public function testMovementFromAnotherEnvironmentCannotBeReversed(): void
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
            $otherEnvironment
        );

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
            $otherEnvironment,

            'nombre' =>
            'Otro entorno QA '
                . uniqid(),
        ]);

        $otherInventory =
            (int)
            $this->db()
                ->lastInsertId();

        $product =
            $this->createProduct();

        $movementId =
            (new InventoryService())
            ->movement(
                [
                    'inventario_id' =>
                    $otherInventory,

                    'producto_id' =>
                    $product,

                    'tipo_movimiento_id' =>
                    $this->movementType(
                        'ENTRADA'
                    ),

                    'cantidad' =>
                    10,
                ],
                $otherEnvironment,
                $this->superAdminId
            );

        $this->expectException(
            RuntimeException::class
        );

        (new InventoryService())
            ->reverseMovement(
                $movementId,
                $this->vetEnvironment,
                $this->superAdminId
            );
    }

    public function testTransferMovementCannotBeReversedIndividually(): void
    {
        $inventory =
            $this->createInventory();

        $product =
            $this->createProduct();

        $movementId =
            $this->movement(
                $inventory,
                $product,
                'TRANSFERENCIA_ENTRADA',
                5
            );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Las transferencias deben revertirse mediante el flujo de reverso de transferencias.'
        );

        (new InventoryService())
            ->reverseMovement(
                $movementId,
                $this->vetEnvironment,
                $this->superAdminId
            );
    }

    public function testReversalWritesHighLevelAudit(): void
    {
        $inventory =
            $this->createInventory();

        $product =
            $this->createProduct();

        $this->movement(
            $inventory,
            $product,
            'ENTRADA',
            10
        );

        $exitId =
            $this->movement(
                $inventory,
                $product,
                'SALIDA',
                3
            );

        $reverseId =
            (new InventoryService())
            ->reverseMovement(
                $exitId,
                $this->vetEnvironment,
                $this->superAdminId,
                'Corrección QA'
            );

        $count =
            (int) $this->scalar(
                '
                SELECT COUNT(*)
                FROM auditoria
                WHERE modulo =
                      "INVENTARIO"
                  AND accion =
                      "REVERSAR_MOVIMIENTO"
                  AND tabla_afectada =
                      "movimientos_inventario"
                  AND registro_id =
                      :registro
                ',
                [
                    'registro' =>
                    $reverseId,
                ]
            );

        $this->assertSame(
            1,
            $count
        );
    }

    public function testPositiveMovementWithExpiredLotCanBeReversed(): void
    {
        $inventory =
            $this->createInventory();

        $product =
            $this->createProduct(
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

        /*
     * Una entrada positiva sí puede registrarse
     * para mantener/importar histórico aunque
     * actualmente el lote esté vencido.
     */
        $entryId =
            $this->movement(
                $inventory,
                $product,
                'ENTRADA',
                10,
                $lot
            );

        $this->assertSame(
            10.0,
            $this->stock(
                $inventory,
                $product,
                $lot
            )
        );

        (new InventoryService())
            ->reverseMovement(
                $entryId,
                $this->vetEnvironment,
                $this->superAdminId,
                'Corrección de entrada histórica QA'
            );

        $this->assertSame(
            0.0,
            $this->stock(
                $inventory,
                $product,
                $lot
            )
        );
    }

    public function testExpiredLotReversalStillRequiresAvailableStock(): void
    {
        $inventory =
            $this->createInventory();

        $product =
            $this->createProduct(
                true,
                true
            );

        /*
     * Primero creamos el lote como vigente para
     * poder realizar una salida normal.
     */
        $lot =
            $this->createLot(
                $product,
                date(
                    'Y-m-d',
                    strtotime('+1 year')
                )
            );

        $entryId =
            $this->movement(
                $inventory,
                $product,
                'ENTRADA',
                10,
                $lot
            );

        /*
     * Consumimos/retiramos 6.
     * Quedan únicamente 4.
     */
        $this->movement(
            $inventory,
            $product,
            'SALIDA',
            6,
            $lot
        );

        $this->assertSame(
            4.0,
            $this->stock(
                $inventory,
                $product,
                $lot
            )
        );

        /*
     * Simulamos que posteriormente el lote quedó
     * vencido.
     */
        $stmt = $this->db()->prepare(
            '
        UPDATE lotes_producto
        SET fecha_vencimiento = :fecha
        WHERE id = :id
        '
        );

        $stmt->execute([
            'fecha' =>
            date(
                'Y-m-d',
                strtotime('-1 day')
            ),

            'id' =>
            $lot,
        ]);

        try {
            (new InventoryService())
                ->reverseMovement(
                    $entryId,
                    $this->vetEnvironment,
                    $this->superAdminId,
                    'Intento reverso sin stock QA'
                );

            $this->fail(
                'El reverso debía fallar porque ya no existe stock suficiente.'
            );
        } catch (RuntimeException $e) {
            $this->assertStringContainsString(
                'Stock insuficiente',
                $e->getMessage()
            );
        }

        /*
     * No debe haberse alterado nada.
     */
        $this->assertSame(
            4.0,
            $this->stock(
                $inventory,
                $product,
                $lot
            )
        );

        $reverseCount =
            (int) $this->scalar(
                '
            SELECT COUNT(*)
            FROM movimientos_inventario
            WHERE referencia_tipo =
                  "REVERSO_INVENTARIO"
              AND referencia_id =
                  :original
            ',
                [
                    'original' =>
                    $entryId,
                ]
            );

        $this->assertSame(
            0,
            $reverseCount
        );
    }
}
