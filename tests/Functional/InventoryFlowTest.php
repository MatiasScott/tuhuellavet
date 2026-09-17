<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Models\Inventory;
use App\Services\InventoryService;
use RuntimeException;

class InventoryFlowTest extends ClinicalTestCase
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

        $suffix = uniqid(
            'QA_INV_',
            true
        );

        $stmt->execute([
            'codigo' =>
            strtoupper(
                str_replace(
                    '.',
                    '',
                    $suffix
                )
            ),

            'nombre' =>
            'Producto QA '
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
        $this->db()
            ->lastInsertId();
    }

    private function createInventory(
        int $environmentId,
        bool $active = true
    ): int {
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
                :activo
            )
            '
        );

        $stmt->execute([
            'entorno' =>
            $environmentId,

            'nombre' =>
            'Inventario QA '
                . uniqid(),

            'descripcion' =>
            'Inventario temporal QA',

            'activo' =>
            $active ? 1 : 0,
        ]);

        return (int)
        $this->db()
            ->lastInsertId();
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

    public function testDefaultInventoryCanBeCreated(): void
    {
        $id = (
            new InventoryService()
        )->ensureDefault(
            $this->vetEnvironment
        );

        $this->assertGreaterThan(
            0,
            $id
        );

        $environment = (int)
        $this->scalar(
            '
                SELECT entorno_id
                FROM inventarios
                WHERE id = :id
                ',
            [
                'id' => $id,
            ]
        );

        $this->assertSame(
            $this->vetEnvironment,
            $environment
        );
    }

    public function testEnsureDefaultDoesNotDuplicateActiveInventory(): void
    {
        $service =
            new InventoryService();

        $first =
            $service->ensureDefault(
                $this->vetEnvironment
            );

        $second =
            $service->ensureDefault(
                $this->vetEnvironment
            );

        $this->assertSame(
            $first,
            $second
        );
    }

    public function testEntryIncreasesStock(): void
    {
        $inventory =
            $this->createInventory(
                $this->vetEnvironment
            );

        $product =
            $this->createProduct();

        $id = (
            new InventoryService()
        )->movement(
            [
                'inventario_id' =>
                $inventory,

                'producto_id' =>
                $product,

                'tipo_movimiento_id' =>
                $this->movementTypeId(
                    'ENTRADA'
                ),

                'cantidad' => 10,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $this->assertGreaterThan(
            0,
            $id
        );

        $this->assertSame(
            10.0,
            $this->stock(
                $inventory,
                $product
            )
        );
    }

    public function testExitDecreasesStock(): void
    {
        $inventory =
            $this->createInventory(
                $this->vetEnvironment
            );

        $product =
            $this->createProduct();

        $service =
            new InventoryService();

        $service->movement(
            [
                'inventario_id' =>
                $inventory,

                'producto_id' =>
                $product,

                'tipo_movimiento_id' =>
                $this->movementTypeId(
                    'ENTRADA'
                ),

                'cantidad' => 10,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $service->movement(
            [
                'inventario_id' =>
                $inventory,

                'producto_id' =>
                $product,

                'tipo_movimiento_id' =>
                $this->movementTypeId(
                    'SALIDA'
                ),

                'cantidad' => 4,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $this->assertSame(
            6.0,
            $this->stock(
                $inventory,
                $product
            )
        );
    }

    public function testSeveralMovementsCalculateCorrectStock(): void
    {
        $inventory =
            $this->createInventory(
                $this->vetEnvironment
            );

        $product =
            $this->createProduct();

        $service =
            new InventoryService();

        foreach (
            [
                ['ENTRADA', 10],
                ['ENTRADA', 5],
                ['SALIDA', 4],
            ]
            as [$type, $quantity]
        ) {
            $service->movement(
                [
                    'inventario_id' =>
                    $inventory,

                    'producto_id' =>
                    $product,

                    'tipo_movimiento_id' =>
                    $this->movementTypeId(
                        $type
                    ),

                    'cantidad' =>
                    $quantity,
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );
        }

        $this->assertSame(
            11.0,
            $this->stock(
                $inventory,
                $product
            )
        );
    }

    public function testZeroQuantityIsRejected(): void
    {
        $this->expectException(
            RuntimeException::class
        );

        $inventory =
            $this->createInventory(
                $this->vetEnvironment
            );

        (
            new InventoryService()
        )->movement(
            [
                'inventario_id' =>
                $inventory,

                'producto_id' =>
                $this->createProduct(),

                'tipo_movimiento_id' =>
                $this->movementTypeId(
                    'ENTRADA'
                ),

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
        )->movement(
            [
                'inventario_id' =>
                $this->createInventory(
                    $this->vetEnvironment
                ),

                'producto_id' =>
                $this->createProduct(),

                'tipo_movimiento_id' =>
                $this->movementTypeId(
                    'ENTRADA'
                ),

                'cantidad' => -5,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testNonExistingProductIsRejected(): void
    {
        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Producto no válido.'
        );

        (
            new InventoryService()
        )->movement(
            [
                'inventario_id' =>
                $this->createInventory(
                    $this->vetEnvironment
                ),

                'producto_id' =>
                999999999,

                'tipo_movimiento_id' =>
                $this->movementTypeId(
                    'ENTRADA'
                ),

                'cantidad' => 1,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testInactiveProductIsRejected(): void
    {
        $inventory =
            $this->createInventory(
                $this->vetEnvironment
            );

        $product =
            $this->createProduct();

        $this->db()
            ->prepare(
                '
                UPDATE productos
                SET activo = 0
                WHERE id = :id
                '
            )
            ->execute([
                'id' => $product,
            ]);

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Producto no válido.'
        );

        (
            new InventoryService()
        )->movement(
            [
                'inventario_id' =>
                $inventory,

                'producto_id' =>
                $product,

                'tipo_movimiento_id' =>
                $this->movementTypeId(
                    'ENTRADA'
                ),

                'cantidad' => 1,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testDeletedProductIsRejected(): void
    {
        $inventory =
            $this->createInventory(
                $this->vetEnvironment
            );

        $product =
            $this->createProduct();

        $this->db()
            ->prepare(
                '
                UPDATE productos
                SET deleted_at =
                    CURRENT_TIMESTAMP
                WHERE id = :id
                '
            )
            ->execute([
                'id' => $product,
            ]);

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Producto no válido.'
        );

        (
            new InventoryService()
        )->movement(
            [
                'inventario_id' =>
                $inventory,

                'producto_id' =>
                $product,

                'tipo_movimiento_id' =>
                $this->movementTypeId(
                    'ENTRADA'
                ),

                'cantidad' => 1,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testInvalidMovementTypeIsRejected(): void
    {
        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Tipo de movimiento no válido.'
        );

        (
            new InventoryService()
        )->movement(
            [
                'inventario_id' =>
                $this->createInventory(
                    $this->vetEnvironment
                ),

                'producto_id' =>
                $this->createProduct(),

                'tipo_movimiento_id' =>
                999999,

                'cantidad' => 1,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testInventoryCannotCrossEnvironment(): void
    {
        $otherEnvironment =
            (int) $this->scalar(
                '
                SELECT id
                FROM entornos
                WHERE id <> :actual
                ORDER BY id
                LIMIT 1
                ',
                [
                    'actual' =>
                    $this->vetEnvironment,
                ]
            );

        $inventory =
            $this->createInventory(
                $otherEnvironment
            );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Inventario no válido.'
        );

        (
            new InventoryService()
        )->movement(
            [
                'inventario_id' =>
                $inventory,

                'producto_id' =>
                $this->createProduct(),

                'tipo_movimiento_id' =>
                $this->movementTypeId(
                    'ENTRADA'
                ),

                'cantidad' => 1,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testInactiveInventoryIsRejected(): void
    {
        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Inventario no válido.'
        );

        (
            new InventoryService()
        )->movement(
            [
                'inventario_id' =>
                $this->createInventory(
                    $this->vetEnvironment,
                    false
                ),

                'producto_id' =>
                $this->createProduct(),

                'tipo_movimiento_id' =>
                $this->movementTypeId(
                    'ENTRADA'
                ),

                'cantidad' => 1,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testInsufficientStockIsRejected(): void
    {
        $inventory =
            $this->createInventory(
                $this->vetEnvironment
            );

        $product =
            $this->createProduct();

        $service =
            new InventoryService();

        $service->movement(
            [
                'inventario_id' =>
                $inventory,

                'producto_id' =>
                $product,

                'tipo_movimiento_id' =>
                $this->movementTypeId(
                    'ENTRADA'
                ),

                'cantidad' => 3,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );

        try {
            $service->movement(
                [
                    'inventario_id' =>
                    $inventory,

                    'producto_id' =>
                    $product,

                    'tipo_movimiento_id' =>
                    $this->movementTypeId(
                        'SALIDA'
                    ),

                    'cantidad' => 4,
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

            $this->fail(
                'La salida debía ser rechazada.'
            );
        } catch (RuntimeException $e) {
            $this->assertSame(
                'Stock insuficiente para realizar el movimiento.',
                $e->getMessage()
            );
        }

        $this->assertSame(
            3.0,
            $this->stock(
                $inventory,
                $product
            )
        );
    }

    public function testMovementCreatesInventoryProductRelation(): void
    {
        $inventory =
            $this->createInventory(
                $this->vetEnvironment
            );

        $product =
            $this->createProduct();

        (
            new InventoryService()
        )->movement(
            [
                'inventario_id' =>
                $inventory,

                'producto_id' =>
                $product,

                'tipo_movimiento_id' =>
                $this->movementTypeId(
                    'ENTRADA'
                ),

                'cantidad' => 2,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $count = (int)
        $this->scalar(
            '
                SELECT COUNT(*)
                FROM inventario_productos
                WHERE inventario_id =
                      :inventario
                  AND producto_id =
                      :producto
                ',
            [
                'inventario' =>
                $inventory,

                'producto' =>
                $product,
            ]
        );

        $this->assertSame(
            1,
            $count
        );
    }

    public function testMovementWritesAudit(): void
    {
        $inventory =
            $this->createInventory(
                $this->vetEnvironment
            );

        $product =
            $this->createProduct();

        $movement =
            (
                new InventoryService()
            )->movement(
                [
                    'inventario_id' =>
                    $inventory,

                    'producto_id' =>
                    $product,

                    'tipo_movimiento_id' =>
                    $this->movementTypeId(
                        'ENTRADA'
                    ),

                    'cantidad' => 2,
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $row = $this->db()
            ->prepare(
                '
            SELECT
                usuario_id,
                entorno_id,
                modulo,
                accion,
                tabla_afectada,
                registro_id
            FROM auditoria
            WHERE tabla_afectada =
                  :tabla
              AND registro_id =
                  :registro
            ORDER BY id DESC
            LIMIT 1
            '
            );

        $row->execute([
            'tabla' =>
            'movimientos_inventario',

            'registro' =>
            $movement,
        ]);

        $audit = $row->fetch();

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
            'INVENTARIO',
            $audit['modulo']
        );

        $this->assertSame(
            'MOVIMIENTO',
            $audit['accion']
        );

        $this->assertSame(
            'movimientos_inventario',
            $audit['tabla_afectada']
        );

        $this->assertSame(
            $movement,
            (int) $audit['registro_id']
        );
    }

    public function testInventoryModelCalculatesStock(): void
    {
        $inventory =
            $this->createInventory(
                $this->vetEnvironment
            );

        $product =
            $this->createProduct();

        (
            new InventoryService()
        )->movement(
            [
                'inventario_id' =>
                $inventory,

                'producto_id' =>
                $product,

                'tipo_movimiento_id' =>
                $this->movementTypeId(
                    'ENTRADA'
                ),

                'cantidad' => 7.5,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $rows =
            (new Inventory())->stock(
                $this->vetEnvironment
            );

        $row = null;

        foreach ($rows as $candidate) {
            if (
                (int) $candidate['inventario_id'] === $inventory
                && (int) $candidate['producto_id'] === $product
            ) {
                $row = $candidate;
                break;
            }
        }

        $this->assertNotNull($row);

        $this->assertSame(
            7.5,
            (float) $row['stock']
        );
    }
}
