<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Services\InventoryService;
use PDOException;
use RuntimeException;

class InventoryLotFlowTest extends ClinicalTestCase
{
    private function unitId(): int
    {
        return (int) $this->scalar(
            '
            SELECT id
            FROM unidades_medida
            ORDER BY id
            LIMIT 1
            '
        );
    }

    private function movementTypeId(
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
            $this->vetEnvironment,

            'nombre' =>
            'Inventario lotes QA '
                . uniqid(),

            'descripcion' =>
            'Inventario temporal para QA de lotes',
        ]);

        return (int)
        $this->db()->lastInsertId();
    }

    private function createProduct(
        bool $controlsLot = false,
        bool $controlsExpiration = false
    ): int {
        $suffix = strtoupper(
            str_replace(
                '.',
                '',
                uniqid('QA_LOT_', true)
            )
        );

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
            'codigo' => $suffix,

            'nombre' =>
            'Producto lote QA '
                . $suffix,

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
        ?string $expiration = null,
        ?string $manufacture = null,
        ?string $number = null
    ): int {
        $number ??=
            'LOTE-QA-'
            . strtoupper(
                str_replace(
                    '.',
                    '',
                    uniqid('', true)
                )
            );

        $stmt = $this->db()->prepare(
            '
            INSERT INTO lotes_producto
            (
                producto_id,
                numero_lote,
                fecha_fabricacion,
                fecha_vencimiento
            )
            VALUES
            (
                :producto,
                :numero,
                :fabricacion,
                :vencimiento
            )
            '
        );

        $stmt->execute([
            'producto' =>
            $productId,

            'numero' =>
            $number,

            'fabricacion' =>
            $manufacture,

            'vencimiento' =>
            $expiration,
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
        $data = [
            'inventario_id' =>
            $inventoryId,

            'producto_id' =>
            $productId,

            'tipo_movimiento_id' =>
            $this->movementTypeId(
                $type
            ),

            'cantidad' =>
            $quantity,
        ];

        if ($lotId !== null) {
            $data['lote_id'] =
                $lotId;
        }

        return (
            new InventoryService()
        )->movement(
            $data,
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
            SELECT
                COALESCE(
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

    private function lotStock(
        int $inventoryId,
        int $productId,
        int $lotId
    ): float {
        return (float) $this->scalar(
            '
            SELECT
                COALESCE(
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

    public function testProductWithoutLotControlCanMoveWithoutLot(): void
    {
        $inventory =
            $this->createInventory();

        $product =
            $this->createProduct(
                false,
                false
            );

        $movement =
            $this->movement(
                $inventory,
                $product,
                'ENTRADA',
                5
            );

        $this->assertGreaterThan(
            0,
            $movement
        );

        $this->assertSame(
            5.0,
            $this->stock(
                $inventory,
                $product
            )
        );
    }

    public function testProductWithLotControlRequiresLot(): void
    {
        $inventory =
            $this->createInventory();

        $product =
            $this->createProduct(
                true,
                false
            );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'El producto requiere un lote.'
        );

        $this->movement(
            $inventory,
            $product,
            'ENTRADA',
            5
        );
    }

    public function testProductWithExpirationControlRequiresLot(): void
    {
        $inventory =
            $this->createInventory();

        $product =
            $this->createProduct(
                false,
                true
            );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'El producto requiere un lote con fecha de vencimiento.'
        );

        $this->movement(
            $inventory,
            $product,
            'ENTRADA',
            5
        );
    }

    public function testExpirationControlledProductRequiresExpirationDate(): void
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
                $product
            );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'El lote requiere fecha de vencimiento.'
        );

        $this->movement(
            $inventory,
            $product,
            'ENTRADA',
            5,
            $lot
        );
    }

    public function testValidLotEntryCreatesStockForThatLot(): void
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
                    strtotime('+6 months')
                )
            );

        $this->movement(
            $inventory,
            $product,
            'ENTRADA',
            12,
            $lot
        );

        $this->assertSame(
            12.0,
            $this->stock(
                $inventory,
                $product
            )
        );

        $this->assertSame(
            12.0,
            $this->lotStock(
                $inventory,
                $product,
                $lot
            )
        );
    }

    public function testLotFromAnotherProductIsRejected(): void
    {
        $inventory =
            $this->createInventory();

        $productA =
            $this->createProduct(
                true,
                false
            );

        $productB =
            $this->createProduct(
                true,
                false
            );

        $lotA =
            $this->createLot(
                $productA
            );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Lote no válido para el producto seleccionado.'
        );

        $this->movement(
            $inventory,
            $productB,
            'ENTRADA',
            5,
            $lotA
        );
    }

    public function testDifferentLotsMaintainIndependentStock(): void
    {
        $inventory =
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

        $this->movement(
            $inventory,
            $product,
            'ENTRADA',
            10,
            $lotA
        );

        $this->movement(
            $inventory,
            $product,
            'ENTRADA',
            20,
            $lotB
        );

        $this->assertSame(
            30.0,
            $this->stock(
                $inventory,
                $product
            )
        );

        $this->assertSame(
            10.0,
            $this->lotStock(
                $inventory,
                $product,
                $lotA
            )
        );

        $this->assertSame(
            20.0,
            $this->lotStock(
                $inventory,
                $product,
                $lotB
            )
        );
    }

    public function testExitUsesStockFromSelectedLot(): void
    {
        $inventory =
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

        $this->movement(
            $inventory,
            $product,
            'ENTRADA',
            2,
            $lotA
        );

        $this->movement(
            $inventory,
            $product,
            'ENTRADA',
            20,
            $lotB
        );

        try {
            $this->movement(
                $inventory,
                $product,
                'SALIDA',
                5,
                $lotA
            );

            $this->fail(
                'La salida debía rechazarse porque el lote A solo tiene 2 unidades.'
            );
        } catch (RuntimeException $e) {
            $this->assertSame(
                'Stock insuficiente para realizar el movimiento.',
                $e->getMessage()
            );
        }

        $this->assertSame(
            2.0,
            $this->lotStock(
                $inventory,
                $product,
                $lotA
            )
        );

        $this->assertSame(
            20.0,
            $this->lotStock(
                $inventory,
                $product,
                $lotB
            )
        );

        $this->assertSame(
            22.0,
            $this->stock(
                $inventory,
                $product
            )
        );
    }

    public function testExitFromValidLotDecreasesOnlyThatLot(): void
    {
        $inventory =
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

        $this->movement(
            $inventory,
            $product,
            'ENTRADA',
            10,
            $lotA
        );

        $this->movement(
            $inventory,
            $product,
            'ENTRADA',
            8,
            $lotB
        );

        $this->movement(
            $inventory,
            $product,
            'SALIDA',
            3,
            $lotA
        );

        $this->assertSame(
            7.0,
            $this->lotStock(
                $inventory,
                $product,
                $lotA
            )
        );

        $this->assertSame(
            8.0,
            $this->lotStock(
                $inventory,
                $product,
                $lotB
            )
        );

        $this->assertSame(
            15.0,
            $this->stock(
                $inventory,
                $product
            )
        );
    }

    public function testExpiredLotCannotBeUsedForExit(): void
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
         * Permitimos registrar históricamente
         * la entrada del lote aunque ya esté
         * vencido.
         */
        $this->movement(
            $inventory,
            $product,
            'ENTRADA',
            10,
            $lot
        );

        try {
            $this->movement(
                $inventory,
                $product,
                'SALIDA',
                1,
                $lot
            );

            $this->fail(
                'Un lote vencido no debe poder salir.'
            );
        } catch (RuntimeException $e) {
            $this->assertSame(
                'No se puede retirar o consumir un lote vencido.',
                $e->getMessage()
            );
        }

        $this->assertSame(
            10.0,
            $this->lotStock(
                $inventory,
                $product,
                $lot
            )
        );
    }

    public function testExpiredLotCannotBeUsedForClinicalConsumption(): void
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
                    strtotime('-10 days')
                )
            );

        $this->movement(
            $inventory,
            $product,
            'ENTRADA',
            4,
            $lot
        );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'No se puede retirar o consumir un lote vencido.'
        );

        $this->movement(
            $inventory,
            $product,
            'CONSUMO_CLINICO',
            1,
            $lot
        );
    }

    public function testFutureLotCanBeConsumed(): void
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
                    strtotime('+1 year')
                )
            );

        $this->movement(
            $inventory,
            $product,
            'ENTRADA',
            10,
            $lot
        );

        $this->movement(
            $inventory,
            $product,
            'CONSUMO_CLINICO',
            2.5,
            $lot
        );

        $this->assertSame(
            7.5,
            $this->lotStock(
                $inventory,
                $product,
                $lot
            )
        );
    }

    public function testExpiredLotMayStillReceivePositiveHistoricalMovement(): void
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
                    strtotime('-1 month')
                )
            );

        $movement =
            $this->movement(
                $inventory,
                $product,
                'ENTRADA',
                3,
                $lot
            );

        $this->assertGreaterThan(
            0,
            $movement
        );

        $this->assertSame(
            3.0,
            $this->lotStock(
                $inventory,
                $product,
                $lot
            )
        );
    }

    public function testSameLotNumberCannotRepeatForSameProduct(): void
    {
        $product =
            $this->createProduct(
                true,
                false
            );

        $number =
            'LOTE-DUP-'
            . uniqid();

        $this->createLot(
            $product,
            null,
            null,
            $number
        );

        $this->expectException(
            PDOException::class
        );

        $this->createLot(
            $product,
            null,
            null,
            $number
        );
    }

    public function testSameLotNumberCanExistForDifferentProducts(): void
    {
        $productA =
            $this->createProduct(
                true,
                false
            );

        $productB =
            $this->createProduct(
                true,
                false
            );

        $number =
            'LOTE-SHARED-'
            . uniqid();

        $lotA =
            $this->createLot(
                $productA,
                null,
                null,
                $number
            );

        $lotB =
            $this->createLot(
                $productB,
                null,
                null,
                $number
            );

        $this->assertGreaterThan(
            0,
            $lotA
        );

        $this->assertGreaterThan(
            0,
            $lotB
        );

        $this->assertNotSame(
            $lotA,
            $lotB
        );
    }
}
