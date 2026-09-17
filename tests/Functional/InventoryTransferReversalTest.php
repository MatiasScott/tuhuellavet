<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Services\InventoryService;
use RuntimeException;

class InventoryTransferReversalTest extends ClinicalTestCase
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
                'Inventario reverso transferencia QA '
                . uniqid(),
        ]);

        return (int)
            $this->db()->lastInsertId();
    }


    private function createProduct(
        bool $controlsLot = false
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
                0,
                1
            )
            '
        );

        $token =
            strtoupper(
                str_replace(
                    '.',
                    '',
                    uniqid('', true)
                )
            );

        $stmt->execute([
            'codigo' =>
                'QA_REV_TRANSFER_'
                . $token,

            'nombre' =>
                'Producto reverso transferencia '
                . $token,

            'unidad' =>
                $this->unitId(),

            'lote' =>
                $controlsLot ? 1 : 0,
        ]);

        return (int)
            $this->db()->lastInsertId();
    }


    private function createLot(
        int $productId
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
                'REV-TRANSFER-'
                . uniqid(),

            'vencimiento' =>
                date(
                    'Y-m-d',
                    strtotime('+1 year')
                ),
        ]);

        return (int)
            $this->db()->lastInsertId();
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
                    'Stock inicial QA reverso transferencia',
            ],
            $environmentId,
            $this->superAdminId
        );
    }


    private function consumeStock(
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
                        'SALIDA'
                    ),

                'cantidad' =>
                    $quantity,

                'observaciones' =>
                    'Consumo previo al reverso QA',
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


    private function transfer(
        int $source,
        int $destination,
        int $product,
        float $quantity,
        ?int $lotId = null,
        ?int $environmentId = null
    ): array {
        $environmentId ??=
            $this->vetEnvironment;

        return (
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
                    $lotId,

                'cantidad' =>
                    $quantity,

                'observaciones' =>
                    'Transferencia QA reverso',
            ],
            $environmentId,
            $this->superAdminId
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


    public function testTransferCanBeReversedUsingExitMovement(): void
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

        $transfer =
            $this->transfer(
                $source,
                $destination,
                $product,
                5
            );

        $this->assertSame(
            15.0,
            $this->stock(
                $source,
                $product
            )
        );

        $this->assertSame(
            5.0,
            $this->stock(
                $destination,
                $product
            )
        );

        $result =
            (new InventoryService())
                ->reverseTransfer(
                    $transfer[
                        'movimiento_salida_id'
                    ],
                    $this->vetEnvironment,
                    $this->superAdminId,
                    'Transferencia incorrecta'
                );

        $this->assertGreaterThan(
            0,
            $result[
                'movimiento_reverso_origen_id'
            ]
        );

        $this->assertGreaterThan(
            0,
            $result[
                'movimiento_reverso_destino_id'
            ]
        );

        $this->assertSame(
            20.0,
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


    public function testTransferCanBeReversedUsingEntryMovement(): void
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
            12
        );

        $transfer =
            $this->transfer(
                $source,
                $destination,
                $product,
                4
            );

        (new InventoryService())
            ->reverseTransfer(
                $transfer[
                    'movimiento_entrada_id'
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $this->assertSame(
            12.0,
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


    public function testReversalCreatesExactlyTwoCompensatingMovements(): void
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

        $transfer =
            $this->transfer(
                $source,
                $destination,
                $product,
                3
            );

        (new InventoryService())
            ->reverseTransfer(
                $transfer[
                    'movimiento_salida_id'
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $count =
            (int) $this->scalar(
                '
                SELECT COUNT(*)
                FROM movimientos_inventario
                WHERE referencia_tipo =
                      "REVERSO_TRANSFERENCIA"

                  AND referencia_id IN (
                      :salida,
                      :entrada
                  )
                ',
                [
                    'salida' =>
                        $transfer[
                            'movimiento_salida_id'
                        ],

                    'entrada' =>
                        $transfer[
                            'movimiento_entrada_id'
                        ],
                ]
            );

        $this->assertSame(
            2,
            $count
        );
    }


    public function testOriginalTransferMovementsRemainIntact(): void
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

        $transfer =
            $this->transfer(
                $source,
                $destination,
                $product,
                4
            );

        (new InventoryService())
            ->reverseTransfer(
                $transfer[
                    'movimiento_salida_id'
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $count =
            (int) $this->scalar(
                '
                SELECT COUNT(*)
                FROM movimientos_inventario
                WHERE id IN (
                    :salida,
                    :entrada
                )
                  AND referencia_tipo =
                      "TRANSFERENCIA"
                ',
                [
                    'salida' =>
                        $transfer[
                            'movimiento_salida_id'
                        ],

                    'entrada' =>
                        $transfer[
                            'movimiento_entrada_id'
                        ],
                ]
            );

        $this->assertSame(
            2,
            $count
        );
    }


    public function testTransferCannotBeReversedTwice(): void
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

        $transfer =
            $this->transfer(
                $source,
                $destination,
                $product,
                3
            );

        $service =
            new InventoryService();

        $service->reverseTransfer(
            $transfer[
                'movimiento_salida_id'
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $this->expectException(
            RuntimeException::class
        );

        $service->reverseTransfer(
            $transfer[
                'movimiento_entrada_id'
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }


    public function testNormalMovementCannotBeReversedAsTransfer(): void
    {
        $inventory =
            $this->createInventory();

        $product =
            $this->createProduct();

        $movement =
            $this->addStock(
                $inventory,
                $product,
                10
            );

        $this->expectException(
            RuntimeException::class
        );

        (new InventoryService())
            ->reverseTransfer(
                $movement,
                $this->vetEnvironment,
                $this->superAdminId
            );
    }


    public function testTransferFromAnotherEnvironmentCannotBeReversed(): void
    {
        $other =
            $this->otherEnvironment();

        $source =
            $this->createInventory($other);

        $destination =
            $this->createInventory($other);

        $product =
            $this->createProduct();

        $this->addStock(
            $source,
            $product,
            10,
            null,
            $other
        );

        $transfer =
            $this->transfer(
                $source,
                $destination,
                $product,
                3,
                null,
                $other
            );

        $this->expectException(
            RuntimeException::class
        );

        (new InventoryService())
            ->reverseTransfer(
                $transfer[
                    'movimiento_salida_id'
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );
    }


    public function testTransferCannotBeReversedWhenDestinationStockWasConsumed(): void
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

        $transfer =
            $this->transfer(
                $source,
                $destination,
                $product,
                5
            );

        $this->consumeStock(
            $destination,
            $product,
            3
        );

        $this->assertSame(
            5.0,
            $this->stock(
                $source,
                $product
            )
        );

        $this->assertSame(
            2.0,
            $this->stock(
                $destination,
                $product
            )
        );

        try {
            (new InventoryService())
                ->reverseTransfer(
                    $transfer[
                        'movimiento_salida_id'
                    ],
                    $this->vetEnvironment,
                    $this->superAdminId
                );

            $this->fail(
                'La transferencia no debe poder revertirse sin stock suficiente en destino.'
            );
        } catch (RuntimeException $e) {
            $this->assertNotSame(
                '',
                trim($e->getMessage())
            );
        }

        /*
         * Nada debe cambiar.
         */
        $this->assertSame(
            5.0,
            $this->stock(
                $source,
                $product
            )
        );

        $this->assertSame(
            2.0,
            $this->stock(
                $destination,
                $product
            )
        );
    }


    public function testFailedReversalCreatesNoCompensatingMovements(): void
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

        $transfer =
            $this->transfer(
                $source,
                $destination,
                $product,
                5
            );

        $this->consumeStock(
            $destination,
            $product,
            4
        );

        try {
            (new InventoryService())
                ->reverseTransfer(
                    $transfer[
                        'movimiento_salida_id'
                    ],
                    $this->vetEnvironment,
                    $this->superAdminId
                );

            $this->fail(
                'Se esperaba un fallo por stock insuficiente.'
            );
        } catch (RuntimeException $e) {
            $this->assertNotSame(
                '',
                trim($e->getMessage())
            );
        }

        $count =
            (int) $this->scalar(
                '
                SELECT COUNT(*)
                FROM movimientos_inventario
                WHERE referencia_tipo =
                      "REVERSO_TRANSFERENCIA"

                  AND referencia_id IN (
                      :salida,
                      :entrada
                  )
                ',
                [
                    'salida' =>
                        $transfer[
                            'movimiento_salida_id'
                        ],

                    'entrada' =>
                        $transfer[
                            'movimiento_entrada_id'
                        ],
                ]
            );

        $this->assertSame(
            0,
            $count
        );
    }


    public function testLotTransferReversalRestoresSameLot(): void
    {
        $source =
            $this->createInventory();

        $destination =
            $this->createInventory();

        $product =
            $this->createProduct(true);

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

        $transfer =
            $this->transfer(
                $source,
                $destination,
                $product,
                4,
                $lot
            );

        (new InventoryService())
            ->reverseTransfer(
                $transfer[
                    'movimiento_salida_id'
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );

        $this->assertSame(
            10.0,
            $this->lotStock(
                $source,
                $product,
                $lot
            )
        );

        $this->assertSame(
            0.0,
            $this->lotStock(
                $destination,
                $product,
                $lot
            )
        );
    }


    public function testStockFromAnotherLotCannotCoverReversal(): void
    {
        $source =
            $this->createInventory();

        $destination =
            $this->createInventory();

        $product =
            $this->createProduct(true);

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
            10,
            $lotA
        );

        /*
         * Stock independiente ya existente
         * en destino para otro lote.
         */
        $this->addStock(
            $destination,
            $product,
            100,
            $lotB
        );

        $transfer =
            $this->transfer(
                $source,
                $destination,
                $product,
                5,
                $lotA
            );

        /*
         * Consumimos parte del lote transferido.
         */
        $this->consumeStock(
            $destination,
            $product,
            4,
            $lotA
        );

        $this->assertSame(
            1.0,
            $this->lotStock(
                $destination,
                $product,
                $lotA
            )
        );

        $this->assertSame(
            100.0,
            $this->lotStock(
                $destination,
                $product,
                $lotB
            )
        );

        $this->expectException(
            RuntimeException::class
        );

        (new InventoryService())
            ->reverseTransfer(
                $transfer[
                    'movimiento_salida_id'
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );
    }


    public function testReverseMovementStillRejectsTransferMovements(): void
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

        $transfer =
            $this->transfer(
                $source,
                $destination,
                $product,
                3
            );

        $this->expectException(
            RuntimeException::class
        );

        (new InventoryService())
            ->reverseMovement(
                $transfer[
                    'movimiento_salida_id'
                ],
                $this->vetEnvironment,
                $this->superAdminId
            );
    }


    public function testTransferReversalWritesHighLevelAudit(): void
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

        $transfer =
            $this->transfer(
                $source,
                $destination,
                $product,
                4
            );

        (new InventoryService())
            ->reverseTransfer(
                $transfer[
                    'movimiento_salida_id'
                ],
                $this->vetEnvironment,
                $this->superAdminId,
                'Auditoría reverso transferencia QA'
            );

        $audit =
            $this->db()
                ->prepare(
                    '
                    SELECT
                        accion,
                        tabla_afectada,
                        registro_id,
                        datos_nuevos

                    FROM auditoria

                    WHERE modulo =
                          "INVENTARIO"

                      AND accion =
                          "REVERSAR_TRANSFERENCIA"

                      AND registro_id =
                          :registro

                    ORDER BY id DESC
                    LIMIT 1
                    '
                );

        $audit->execute([
            'registro' =>
                $transfer[
                    'movimiento_salida_id'
                ],
        ]);

        $row =
            $audit->fetch();

        $this->assertIsArray(
            $row
        );

        $this->assertSame(
            'REVERSAR_TRANSFERENCIA',
            $row['accion']
        );

        $this->assertSame(
            'movimientos_inventario',
            $row['tabla_afectada']
        );

        $data =
            json_decode(
                $row['datos_nuevos'],
                true
            );

        $this->assertSame(
            'Auditoría reverso transferencia QA',
            $data['motivo']
        );
    }
}