<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Services\InventoryService;
use RuntimeException;

class InventoryTransferFlowTest extends ClinicalTestCase
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
            'Inventario transferencia QA '
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
            'QA_TRANSFER_'
                . strtoupper(
                    str_replace(
                        '.',
                        '',
                        uniqid('', true)
                    )
                ),

            'nombre' =>
            'Producto transferencia QA '
                . uniqid(),

            'unidad' =>
            $this->unitId(),

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
            'TRANSFER-LOT-'
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
    ): int {
        return (
            new InventoryService()
        )->movement(
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
                'Stock inicial QA',
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    private function stock(
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
            JOIN tipos_movimiento_inventario tm
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

    private function lotStock(
        int $inventoryId,
        int $productId,
        int $lotId
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
            JOIN tipos_movimiento_inventario tm
              ON tm.id =
                 mi.tipo_movimiento_id
            WHERE mi.inventario_id =
                  :inventario
              AND mi.producto_id =
                  :producto
              AND mi.lote_id =
                  :lote
            ',
            [
                'inventario' =>
                $inventoryId,

                'producto' =>
                $productId,

                'lote' =>
                $lotId,
            ]
        );
    }

    private function otherEnvironment(): int
    {
        return (int) $this->scalar(
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
    }

    public function testProductCanBeTransferredBetweenInventories(): void
    {
        $source =
            $this->createInventory();

        $destination =
            $this->createInventory();

        $product =
            $this->createProduct();

        $this->addStock(
            $source,
            $product,
            20
        );

        $result = (
            new InventoryService()
        )->transfer(
            [
                'inventario_origen_id' =>
                $source,

                'inventario_destino_id' =>
                $destination,

                'producto_id' =>
                $product,

                'cantidad' => 7,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $this->assertGreaterThan(
            0,
            $result['movimiento_salida_id']
        );

        $this->assertGreaterThan(
            0,
            $result['movimiento_entrada_id']
        );

        $this->assertSame(
            13.0,
            $this->stock(
                $source,
                $product
            )
        );

        $this->assertSame(
            7.0,
            $this->stock(
                $destination,
                $product
            )
        );
    }

    public function testTransferCreatesExactlyTwoTransferMovements(): void
    {
        $source =
            $this->createInventory();

        $destination =
            $this->createInventory();

        $product =
            $this->createProduct();

        $this->addStock(
            $source,
            $product,
            10
        );

        $result = (
            new InventoryService()
        )->transfer(
            [
                'inventario_origen_id' =>
                $source,

                'inventario_destino_id' =>
                $destination,

                'producto_id' =>
                $product,

                'cantidad' => 4,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $stmt = $this->db()->prepare(
            '
            SELECT
                mi.id,
                mi.inventario_id,
                tm.codigo,
                tm.factor,
                mi.cantidad
            FROM movimientos_inventario mi
            JOIN tipos_movimiento_inventario tm
              ON tm.id =
                 mi.tipo_movimiento_id
            WHERE mi.id IN (:salida, :entrada)
            ORDER BY mi.id
            '
        );

        $stmt->execute([
            'salida' =>
            $result['movimiento_salida_id'],

            'entrada' =>
            $result['movimiento_entrada_id'],
        ]);

        $rows = $stmt->fetchAll();

        $this->assertCount(
            2,
            $rows
        );

        $codes = array_column(
            $rows,
            'codigo'
        );

        $this->assertContains(
            'TRANSFERENCIA_SALIDA',
            $codes
        );

        $this->assertContains(
            'TRANSFERENCIA_ENTRADA',
            $codes
        );
    }

    public function testTransferMovementsAreCrossReferenced(): void
    {
        $source =
            $this->createInventory();

        $destination =
            $this->createInventory();

        $product =
            $this->createProduct();

        $this->addStock(
            $source,
            $product,
            10
        );

        $result = (
            new InventoryService()
        )->transfer(
            [
                'inventario_origen_id' =>
                $source,

                'inventario_destino_id' =>
                $destination,

                'producto_id' =>
                $product,

                'cantidad' =>
                2,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $exit = $this->db()->prepare(
            '
            SELECT
                referencia_tipo,
                referencia_id
            FROM movimientos_inventario
            WHERE id = :id
            '
        );

        $exit->execute([
            'id' =>
            $result['movimiento_salida_id'],
        ]);

        $exitRow = $exit->fetch();

        $entry = $this->db()->prepare(
            '
            SELECT
                referencia_tipo,
                referencia_id
            FROM movimientos_inventario
            WHERE id = :id
            '
        );

        $entry->execute([
            'id' =>
            $result['movimiento_entrada_id'],
        ]);

        $entryRow = $entry->fetch();

        $this->assertSame(
            'TRANSFERENCIA',
            $exitRow['referencia_tipo']
        );

        $this->assertSame(
            $result['movimiento_entrada_id'],
            (int) $exitRow['referencia_id']
        );

        $this->assertSame(
            'TRANSFERENCIA',
            $entryRow['referencia_tipo']
        );

        $this->assertSame(
            $result['movimiento_salida_id'],
            (int) $entryRow['referencia_id']
        );
    }

    public function testSourceAndDestinationCannotBeSame(): void
    {
        $inventory =
            $this->createInventory();

        $product =
            $this->createProduct();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'El inventario de origen y destino deben ser diferentes.'
        );

        (
            new InventoryService()
        )->transfer(
            [
                'inventario_origen_id' =>
                $inventory,

                'inventario_destino_id' =>
                $inventory,

                'producto_id' =>
                $product,

                'cantidad' => 1,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testZeroQuantityIsRejected(): void
    {
        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'La cantidad debe ser mayor que cero.'
        );

        (
            new InventoryService()
        )->transfer(
            [
                'inventario_origen_id' =>
                $this->createInventory(),

                'inventario_destino_id' =>
                $this->createInventory(),

                'producto_id' =>
                $this->createProduct(),

                'cantidad' => 0,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testNegativeQuantityIsRejected(): void
    {
        $this->expectException(
            RuntimeException::class
        );

        (
            new InventoryService()
        )->transfer(
            [
                'inventario_origen_id' =>
                $this->createInventory(),

                'inventario_destino_id' =>
                $this->createInventory(),

                'producto_id' =>
                $this->createProduct(),

                'cantidad' => -5,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testInsufficientStockIsRejected(): void
    {
        $source =
            $this->createInventory();

        $destination =
            $this->createInventory();

        $product =
            $this->createProduct();

        $this->addStock(
            $source,
            $product,
            5
        );

        $this->expectException(
            RuntimeException::class
        );

        (
            new InventoryService()
        )->transfer(
            [
                'inventario_origen_id' =>
                $source,

                'inventario_destino_id' =>
                $destination,

                'producto_id' =>
                $product,

                'cantidad' => 10,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testFailedTransferDoesNotModifyDestinationStock(): void
    {
        $source =
            $this->createInventory();

        $destination =
            $this->createInventory();

        $product =
            $this->createProduct();

        $this->addStock(
            $source,
            $product,
            5
        );

        try {
            (
                new InventoryService()
            )->transfer(
                [
                    'inventario_origen_id' =>
                    $source,

                    'inventario_destino_id' =>
                    $destination,

                    'producto_id' =>
                    $product,

                    'cantidad' => 10,
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

            $this->fail(
                'La transferencia debía fallar.'
            );
        } catch (RuntimeException) {
            $this->assertSame(
                5.0,
                $this->stock(
                    $source,
                    $product
                )
            );

            $this->assertSame(
                0.0,
                $this->stock(
                    $destination,
                    $product
                )
            );
        }
    }

    public function testDestinationFromAnotherEnvironmentIsRejected(): void
    {
        $otherEnvironment =
            $this->otherEnvironment();

        $this->assertGreaterThan(
            0,
            $otherEnvironment,
            'Se necesita un segundo entorno activo.'
        );

        $source =
            $this->createInventory();

        $destination =
            $this->createInventory(
                $otherEnvironment
            );

        $product =
            $this->createProduct();

        $this->addStock(
            $source,
            $product,
            10
        );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Inventario de destino no válido para el entorno.'
        );

        (
            new InventoryService()
        )->transfer(
            [
                'inventario_origen_id' =>
                $source,

                'inventario_destino_id' =>
                $destination,

                'producto_id' =>
                $product,

                'cantidad' => 2,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testLotControlledProductCanBeTransferred(): void
    {
        $source =
            $this->createInventory();

        $destination =
            $this->createInventory();

        $product =
            $this->createProduct(
                true,
                true
            );

        $lot = $this->createLot(
            $product,
            date(
                'Y-m-d',
                strtotime('+1 year')
            )
        );

        $this->addStock(
            $source,
            $product,
            20,
            $lot
        );

        (
            new InventoryService()
        )->transfer(
            [
                'inventario_origen_id' =>
                $source,

                'inventario_destino_id' =>
                $destination,

                'producto_id' =>
                $product,

                'lote_id' =>
                $lot,

                'cantidad' => 6,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $this->assertSame(
            14.0,
            $this->lotStock(
                $source,
                $product,
                $lot
            )
        );

        $this->assertSame(
            6.0,
            $this->lotStock(
                $destination,
                $product,
                $lot
            )
        );
    }

    public function testLotControlledProductRequiresLot(): void
    {
        $source =
            $this->createInventory();

        $destination =
            $this->createInventory();

        $product =
            $this->createProduct(
                true,
                false
            );

        /*
         * Creamos stock con un lote válido.
         */
        $lot =
            $this->createLot(
                $product
            );

        $this->addStock(
            $source,
            $product,
            10,
            $lot
        );

        $this->expectException(
            RuntimeException::class
        );

        (
            new InventoryService()
        )->transfer(
            [
                'inventario_origen_id' =>
                $source,

                'inventario_destino_id' =>
                $destination,

                'producto_id' =>
                $product,

                'cantidad' => 2,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testExpiredLotCannotBeTransferred(): void
    {
        $source =
            $this->createInventory();

        $destination =
            $this->createInventory();

        $product =
            $this->createProduct(
                true,
                true
            );

        $lot = $this->createLot(
            $product,
            date(
                'Y-m-d',
                strtotime('-1 day')
            )
        );

        /*
         * Entrada positiva a lote vencido está
         * permitida por nuestra regla actual.
         */
        $this->addStock(
            $source,
            $product,
            10,
            $lot
        );

        $this->expectException(
            RuntimeException::class
        );

        (
            new InventoryService()
        )->transfer(
            [
                'inventario_origen_id' =>
                $source,

                'inventario_destino_id' =>
                $destination,

                'producto_id' =>
                $product,

                'lote_id' =>
                $lot,

                'cantidad' => 2,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testTransferUsesOnlySelectedLotStock(): void
    {
        $source =
            $this->createInventory();

        $destination =
            $this->createInventory();

        $product =
            $this->createProduct(
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

        $this->addStock(
            $source,
            $product,
            3,
            $lotA
        );

        $this->addStock(
            $source,
            $product,
            100,
            $lotB
        );

        /*
         * Globalmente hay 103 unidades,
         * pero el lote A tiene solo 3.
         */
        try {
            (
                new InventoryService()
            )->transfer(
                [
                    'inventario_origen_id' =>
                    $source,

                    'inventario_destino_id' =>
                    $destination,

                    'producto_id' =>
                    $product,

                    'lote_id' =>
                    $lotA,

                    'cantidad' => 4,
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

            $this->fail(
                'No debía permitir consumir stock de otro lote.'
            );
        } catch (RuntimeException) {
            $this->assertSame(
                3.0,
                $this->lotStock(
                    $source,
                    $product,
                    $lotA
                )
            );

            $this->assertSame(
                100.0,
                $this->lotStock(
                    $source,
                    $product,
                    $lotB
                )
            );
        }
    }

    public function testDestinationProductAssociationIsCreated(): void
    {
        $source =
            $this->createInventory();

        $destination =
            $this->createInventory();

        $product =
            $this->createProduct();

        $this->addStock(
            $source,
            $product,
            10
        );

        (
            new InventoryService()
        )->transfer(
            [
                'inventario_origen_id' =>
                $source,

                'inventario_destino_id' =>
                $destination,

                'producto_id' =>
                $product,

                'cantidad' =>
                3,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $count = (int) $this->scalar(
            '
            SELECT COUNT(*)
            FROM inventario_productos
            WHERE inventario_id =
                  :inventario
              AND producto_id =
                  :producto
              AND activo = 1
            ',
            [
                'inventario' =>
                $destination,

                'producto' =>
                $product,
            ]
        );

        $this->assertSame(
            1,
            $count
        );
    }

    public function testTransferWritesHighLevelAudit(): void
    {
        $source =
            $this->createInventory();

        $destination =
            $this->createInventory();

        $product =
            $this->createProduct();

        $this->addStock(
            $source,
            $product,
            20
        );

        $result = (
            new InventoryService()
        )->transfer(
            [
                'inventario_origen_id' =>
                $source,

                'inventario_destino_id' =>
                $destination,

                'producto_id' =>
                $product,

                'cantidad' =>
                5,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $stmt = $this->db()->prepare(
            '
            SELECT
                usuario_id,
                entorno_id,
                modulo,
                accion,
                tabla_afectada,
                registro_id,
                datos_nuevos
            FROM auditoria
            WHERE modulo =
                  :modulo
              AND accion =
                  :accion
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
            'TRANSFERENCIA',

            'registro' =>
            $result['movimiento_salida_id'],
        ]);

        $audit = $stmt->fetch();

        $this->assertNotFalse(
            $audit
        );

        $this->assertSame(
            $this->superAdminId,
            (int) $audit['usuario_id']
        );

        $this->assertSame(
            $this->vetEnvironment,
            (int) $audit['entorno_id']
        );

        $this->assertSame(
            'movimientos_inventario',
            $audit['tabla_afectada']
        );

        $new = json_decode(
            (string) $audit['datos_nuevos'],
            true
        );

        $this->assertSame(
            $source,
            (int) $new['inventario_origen_id']
        );

        $this->assertSame(
            $destination,
            (int) $new['inventario_destino_id']
        );

        $this->assertSame(
            $product,
            (int) $new['producto_id']
        );

        $this->assertSame(
            5.0,
            (float) $new['cantidad']
        );
    }
}
