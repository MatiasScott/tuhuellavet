<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Services\InventoryService;
use RuntimeException;

class InventoryStockLimitsTest extends ClinicalTestCase
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
            'Inventario stock QA '
                . uniqid(),
        ]);

        return (int)
        $this->db()->lastInsertId();
    }

    private function createProduct(): int
    {
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
                0,
                0,
                1
            )
            '
        );

        $stmt->execute([
            'codigo' =>
            'QA_STOCK_'
                . strtoupper(
                    str_replace(
                        '.',
                        '',
                        uniqid('', true)
                    )
                ),

            'nombre' =>
            'Producto stock QA '
                . uniqid(),

            'unidad' =>
            $this->unitId(),
        ]);

        return (int)
        $this->db()->lastInsertId();
    }

    private function addStock(
        int $inventoryId,
        int $productId,
        float $quantity
    ): void {
        (
            new InventoryService()
        )->movement(
            [
                'inventario_id' =>
                $inventoryId,

                'producto_id' =>
                $productId,

                'tipo_movimiento_id' =>
                $this->movementType(
                    'ENTRADA'
                ),

                'cantidad' =>
                $quantity,

                'observaciones' =>
                'QA stock',
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testStockLimitsCanBeConfigured(): void
    {
        $inventory =
            $this->createInventory();

        $product =
            $this->createProduct();

        (
            new InventoryService()
        )->configureStockLimits(
            $inventory,
            $product,
            10,
            100,
            $this->vetEnvironment,
            $this->superAdminId
        );

        $stmt = $this->db()->prepare(
            '
            SELECT
                stock_minimo,
                stock_maximo,
                activo
            FROM inventario_productos
            WHERE inventario_id =
                  :inventario
              AND producto_id =
                  :producto
            '
        );

        $stmt->execute([
            'inventario' => $inventory,
            'producto' => $product,
        ]);

        $row = $stmt->fetch();

        $this->assertNotFalse($row);

        $this->assertSame(
            10.0,
            (float) $row['stock_minimo']
        );

        $this->assertSame(
            100.0,
            (float) $row['stock_maximo']
        );

        $this->assertSame(
            1,
            (int) $row['activo']
        );
    }

    public function testLimitsCanBeNull(): void
    {
        $inventory =
            $this->createInventory();

        $product =
            $this->createProduct();

        (
            new InventoryService()
        )->configureStockLimits(
            $inventory,
            $product,
            null,
            null,
            $this->vetEnvironment,
            $this->superAdminId
        );

        $stmt = $this->db()->prepare(
            '
            SELECT
                stock_minimo,
                stock_maximo
            FROM inventario_productos
            WHERE inventario_id = :i
              AND producto_id = :p
            '
        );

        $stmt->execute([
            'i' => $inventory,
            'p' => $product,
        ]);

        $row = $stmt->fetch();

        $this->assertNull(
            $row['stock_minimo']
        );

        $this->assertNull(
            $row['stock_maximo']
        );
    }

    public function testNegativeMinimumIsRejected(): void
    {
        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'El stock mínimo no puede ser negativo.'
        );

        (
            new InventoryService()
        )->configureStockLimits(
            $this->createInventory(),
            $this->createProduct(),
            -1,
            100,
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testNegativeMaximumIsRejected(): void
    {
        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'El stock máximo no puede ser negativo.'
        );

        (
            new InventoryService()
        )->configureStockLimits(
            $this->createInventory(),
            $this->createProduct(),
            1,
            -1,
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testMaximumBelowMinimumIsRejected(): void
    {
        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'El stock máximo no puede ser menor que el stock mínimo.'
        );

        (
            new InventoryService()
        )->configureStockLimits(
            $this->createInventory(),
            $this->createProduct(),
            100,
            10,
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testNonNumericMinimumIsRejected(): void
    {
        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'El stock mínimo no es válido.'
        );

        (
            new InventoryService()
        )->configureStockLimits(
            $this->createInventory(),
            $this->createProduct(),
            'abc',
            100,
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testInvalidProductIsRejected(): void
    {
        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Producto no válido.'
        );

        (
            new InventoryService()
        )->configureStockLimits(
            $this->createInventory(),
            999999999,
            10,
            100,
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testInventoryFromAnotherEnvironmentIsRejected(): void
    {
        $otherEnvironmentId = (int) $this->scalar(
            '
        SELECT id
        FROM entornos
        WHERE id <> :actual
          AND activo = 1
        ORDER BY id
        LIMIT 1
        ',
            [
                'actual' => $this->vetEnvironment,
            ]
        );

        $this->assertGreaterThan(
            0,
            $otherEnvironmentId,
            'Se requiere al menos un segundo entorno activo para esta prueba.'
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
            $otherEnvironmentId,

            'nombre' =>
            'Inventario otro entorno '
                . uniqid(),
        ]);

        $inventory =
            (int) $this->db()
                ->lastInsertId();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Inventario no válido para el entorno.'
        );

        (
            new InventoryService()
        )->configureStockLimits(
            $inventory,
            $this->createProduct(),
            10,
            100,
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testZeroStockReportsOutOfStock(): void
    {
        $inventory =
            $this->createInventory();

        $product =
            $this->createProduct();

        $service =
            new InventoryService();

        $service->configureStockLimits(
            $inventory,
            $product,
            10,
            100,
            $this->vetEnvironment,
            $this->superAdminId
        );

        $status =
            $service->stockStatus(
                $inventory,
                $product,
                $this->vetEnvironment
            );

        $this->assertSame(
            'SIN_STOCK',
            $status['estado']
        );

        $this->assertSame(
            0.0,
            $status['stock']
        );
    }

    public function testStockBelowMinimumReportsLowStock(): void
    {
        $inventory =
            $this->createInventory();

        $product =
            $this->createProduct();

        $service =
            new InventoryService();

        $service->configureStockLimits(
            $inventory,
            $product,
            10,
            100,
            $this->vetEnvironment,
            $this->superAdminId
        );

        $this->addStock(
            $inventory,
            $product,
            5
        );

        $status =
            $service->stockStatus(
                $inventory,
                $product,
                $this->vetEnvironment
            );

        $this->assertSame(
            'BAJO_MINIMO',
            $status['estado']
        );

        $this->assertSame(
            5.0,
            $status['stock']
        );
    }

    public function testStockEqualMinimumIsNormal(): void
    {
        $inventory =
            $this->createInventory();

        $product =
            $this->createProduct();

        $service =
            new InventoryService();

        $service->configureStockLimits(
            $inventory,
            $product,
            10,
            100,
            $this->vetEnvironment,
            $this->superAdminId
        );

        $this->addStock(
            $inventory,
            $product,
            10
        );

        $status =
            $service->stockStatus(
                $inventory,
                $product,
                $this->vetEnvironment
            );

        $this->assertSame(
            'NORMAL',
            $status['estado']
        );
    }

    public function testStockBetweenLimitsIsNormal(): void
    {
        $inventory =
            $this->createInventory();

        $product =
            $this->createProduct();

        $service =
            new InventoryService();

        $service->configureStockLimits(
            $inventory,
            $product,
            10,
            100,
            $this->vetEnvironment,
            $this->superAdminId
        );

        $this->addStock(
            $inventory,
            $product,
            50
        );

        $status =
            $service->stockStatus(
                $inventory,
                $product,
                $this->vetEnvironment
            );

        $this->assertSame(
            'NORMAL',
            $status['estado']
        );
    }

    public function testStockEqualMaximumIsNormal(): void
    {
        $inventory =
            $this->createInventory();

        $product =
            $this->createProduct();

        $service =
            new InventoryService();

        $service->configureStockLimits(
            $inventory,
            $product,
            10,
            100,
            $this->vetEnvironment,
            $this->superAdminId
        );

        $this->addStock(
            $inventory,
            $product,
            100
        );

        $status =
            $service->stockStatus(
                $inventory,
                $product,
                $this->vetEnvironment
            );

        $this->assertSame(
            'NORMAL',
            $status['estado']
        );
    }

    public function testStockAboveMaximumReportsOverstock(): void
    {
        $inventory =
            $this->createInventory();

        $product =
            $this->createProduct();

        $service =
            new InventoryService();

        $service->configureStockLimits(
            $inventory,
            $product,
            10,
            100,
            $this->vetEnvironment,
            $this->superAdminId
        );

        $this->addStock(
            $inventory,
            $product,
            101
        );

        $status =
            $service->stockStatus(
                $inventory,
                $product,
                $this->vetEnvironment
            );

        $this->assertSame(
            'SOBRE_MAXIMO',
            $status['estado']
        );

        $this->assertSame(
            101.0,
            $status['stock']
        );
    }

    public function testConfigurationWritesAudit(): void
    {
        $inventory =
            $this->createInventory();

        $product =
            $this->createProduct();

        (
            new InventoryService()
        )->configureStockLimits(
            $inventory,
            $product,
            15,
            80,
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
              AND entorno_id =
                  :entorno
              AND usuario_id =
                  :usuario
            ORDER BY id DESC
            LIMIT 1
            '
        );

        $stmt->execute([
            'modulo' =>
            'INVENTARIO',

            'accion' =>
            'CONFIGURAR_STOCK',

            'tabla' =>
            'inventario_productos',

            'entorno' =>
            $this->vetEnvironment,

            'usuario' =>
            $this->superAdminId,
        ]);

        $audit = $stmt->fetch();

        $this->assertNotFalse(
            $audit
        );

        $new = json_decode(
            (string)
            $audit['datos_nuevos'],
            true
        );

        $this->assertSame(
            $inventory,
            (int)
            $new['inventario_id']
        );

        $this->assertSame(
            $product,
            (int)
            $new['producto_id']
        );

        $this->assertSame(
            15.0,
            (float)
            $new['stock_minimo']
        );

        $this->assertSame(
            80.0,
            (float)
            $new['stock_maximo']
        );
    }
}
