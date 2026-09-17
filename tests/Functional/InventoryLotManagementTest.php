<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Services\InventoryService;
use RuntimeException;

class InventoryLotManagementTest extends ClinicalTestCase
{
    private function activeUnitId(): int
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

    private function createInventoryProduct(
        bool $controlsLot = true,
        bool $controlsExpiration = false,
        bool $active = true
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
                :controla_lote,
                :controla_vencimiento,
                :activo
            )
            '
        );

        $stmt->execute([
            'codigo' =>
            'QA_LOT_'
                . strtoupper(
                    str_replace(
                        '.',
                        '',
                        uniqid('', true)
                    )
                ),

            'nombre' =>
            'Producto lote QA '
                . uniqid(),

            'unidad' =>
            $this->activeUnitId(),

            'controla_lote' =>
            $controlsLot ? 1 : 0,

            'controla_vencimiento' =>
            $controlsExpiration
                ? 1
                : 0,

            'activo' =>
            $active ? 1 : 0,
        ]);

        return (int)
        $this->db()->lastInsertId();
    }

    private function lotData(
        int $productId
    ): array {
        return [
            'producto_id' =>
            $productId,

            'numero_lote' =>
            'LOT-' . uniqid(),

            'fecha_fabricacion' =>
            date(
                'Y-m-d',
                strtotime('-1 month')
            ),

            'fecha_vencimiento' =>
            date(
                'Y-m-d',
                strtotime('+1 year')
            ),
        ];
    }

    public function testLotCanBeCreated(): void
    {
        $product =
            $this->createInventoryProduct(
                true,
                true
            );

        $data =
            $this->lotData(
                $product
            );

        $lotId = (
            new InventoryService()
        )->createLot(
            $data,
            $this->vetEnvironment,
            $this->superAdminId
        );

        $this->assertGreaterThan(
            0,
            $lotId
        );

        $stmt = $this->db()->prepare(
            '
            SELECT *
            FROM lotes_producto
            WHERE id = :id
            '
        );

        $stmt->execute([
            'id' => $lotId,
        ]);

        $row = $stmt->fetch();

        $this->assertNotFalse(
            $row
        );

        $this->assertSame(
            $product,
            (int) $row['producto_id']
        );

        $this->assertSame(
            $data['numero_lote'],
            $row['numero_lote']
        );

        $this->assertSame(
            $data['fecha_fabricacion'],
            $row['fecha_fabricacion']
        );

        $this->assertSame(
            $data['fecha_vencimiento'],
            $row['fecha_vencimiento']
        );
    }

    public function testMissingProductIsRejected(): void
    {
        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'El producto es obligatorio.'
        );

        (
            new InventoryService()
        )->createLot(
            [
                'producto_id' => 0,
                'numero_lote' => 'QA-X',
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testNonexistentProductIsRejected(): void
    {
        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Producto no válido.'
        );

        (
            new InventoryService()
        )->createLot(
            [
                'producto_id' =>
                999999999,

                'numero_lote' =>
                'QA-X',
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testInactiveProductIsRejected(): void
    {
        $product =
            $this->createInventoryProduct(
                true,
                false,
                false
            );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Producto no válido.'
        );

        (
            new InventoryService()
        )->createLot(
            [
                'producto_id' =>
                $product,

                'numero_lote' =>
                'QA-INACTIVE',
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testSoftDeletedProductIsRejected(): void
    {
        $product =
            $this->createInventoryProduct();

        $this->db()->prepare(
            '
            UPDATE productos
            SET deleted_at =
                CURRENT_TIMESTAMP
            WHERE id = :id
            '
        )->execute([
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
        )->createLot(
            [
                'producto_id' =>
                $product,

                'numero_lote' =>
                'QA-DELETED',
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testEmptyLotNumberIsRejected(): void
    {
        $product =
            $this->createInventoryProduct();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'El número de lote es obligatorio.'
        );

        (
            new InventoryService()
        )->createLot(
            [
                'producto_id' =>
                $product,

                'numero_lote' =>
                '   ',
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testDuplicateLotForSameProductIsRejected(): void
    {
        $product =
            $this->createInventoryProduct();

        $service =
            new InventoryService();

        $data = [
            'producto_id' =>
            $product,

            'numero_lote' =>
            'LOT-DUP-' . uniqid(),
        ];

        $service->createLot(
            $data,
            $this->vetEnvironment,
            $this->superAdminId
        );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Ya existe un lote con ese número para este producto.'
        );

        $service->createLot(
            $data,
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testSameLotNumberCanExistForDifferentProducts(): void
    {
        $productA =
            $this->createInventoryProduct();

        $productB =
            $this->createInventoryProduct();

        $number =
            'SHARED-' . uniqid();

        $service =
            new InventoryService();

        $lotA = $service->createLot(
            [
                'producto_id' =>
                $productA,

                'numero_lote' =>
                $number,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $lotB = $service->createLot(
            [
                'producto_id' =>
                $productB,

                'numero_lote' =>
                $number,
            ],
            $this->vetEnvironment,
            $this->superAdminId
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

    public function testExpirationControlledProductRequiresExpirationDate(): void
    {
        $product =
            $this->createInventoryProduct(
                true,
                true
            );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'La fecha de vencimiento es obligatoria para este producto.'
        );

        (
            new InventoryService()
        )->createLot(
            [
                'producto_id' =>
                $product,

                'numero_lote' =>
                'NO-EXP-' . uniqid(),

                'fecha_fabricacion' =>
                date('Y-m-d'),
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testProductWithoutExpirationControlMayOmitExpiration(): void
    {
        $product =
            $this->createInventoryProduct(
                true,
                false
            );

        $lotId = (
            new InventoryService()
        )->createLot(
            [
                'producto_id' =>
                $product,

                'numero_lote' =>
                'NO-EXP-' . uniqid(),
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $expiration =
            $this->scalar(
                '
                SELECT fecha_vencimiento
                FROM lotes_producto
                WHERE id = :id
                ',
                [
                    'id' => $lotId,
                ]
            );

        $this->assertNull(
            $expiration
        );
    }

    public function testManufactureDateMayBeOmitted(): void
    {
        $product =
            $this->createInventoryProduct(
                true,
                true
            );

        $lotId = (
            new InventoryService()
        )->createLot(
            [
                'producto_id' =>
                $product,

                'numero_lote' =>
                'NO-MFG-' . uniqid(),

                'fecha_vencimiento' =>
                date(
                    'Y-m-d',
                    strtotime('+1 year')
                ),
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $manufacture =
            $this->scalar(
                '
                SELECT fecha_fabricacion
                FROM lotes_producto
                WHERE id = :id
                ',
                [
                    'id' => $lotId,
                ]
            );

        $this->assertNull(
            $manufacture
        );
    }

    public function testExpirationBeforeManufactureIsRejected(): void
    {
        $product =
            $this->createInventoryProduct(
                true,
                true
            );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'La fecha de vencimiento no puede ser anterior a la fecha de fabricación.'
        );

        (
            new InventoryService()
        )->createLot(
            [
                'producto_id' =>
                $product,

                'numero_lote' =>
                'BAD-DATES-'
                    . uniqid(),

                'fecha_fabricacion' =>
                '2026-09-10',

                'fecha_vencimiento' =>
                '2026-09-01',
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testSameManufactureAndExpirationDateIsAllowed(): void
    {
        $product =
            $this->createInventoryProduct(
                true,
                true
            );

        $date = '2026-09-14';

        $lotId = (
            new InventoryService()
        )->createLot(
            [
                'producto_id' =>
                $product,

                'numero_lote' =>
                'SAME-DATE-'
                    . uniqid(),

                'fecha_fabricacion' =>
                $date,

                'fecha_vencimiento' =>
                $date,
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );

        $this->assertGreaterThan(
            0,
            $lotId
        );
    }

    public function testInvalidManufactureDateIsRejected(): void
    {
        $product =
            $this->createInventoryProduct();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Fecha no válida.'
        );

        (
            new InventoryService()
        )->createLot(
            [
                'producto_id' =>
                $product,

                'numero_lote' =>
                'BAD-MFG-'
                    . uniqid(),

                'fecha_fabricacion' =>
                '2026-02-30',
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testInvalidExpirationDateIsRejected(): void
    {
        $product =
            $this->createInventoryProduct();

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Fecha no válida.'
        );

        (
            new InventoryService()
        )->createLot(
            [
                'producto_id' =>
                $product,

                'numero_lote' =>
                'BAD-EXP-'
                    . uniqid(),

                'fecha_vencimiento' =>
                'not-a-date',
            ],
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testLotCreationWritesAudit(): void
    {
        $product =
            $this->createInventoryProduct(
                true,
                true
            );

        $data =
            $this->lotData(
                $product
            );

        $lotId = (
            new InventoryService()
        )->createLot(
            $data,
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
            WHERE tabla_afectada =
                  :tabla
              AND registro_id =
                  :registro
            ORDER BY id DESC
            LIMIT 1
            '
        );

        $stmt->execute([
            'tabla' =>
            'lotes_producto',

            'registro' =>
            $lotId,
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
            'INVENTARIO',
            $audit['modulo']
        );

        $this->assertSame(
            'CREAR_LOTE',
            $audit['accion']
        );

        $this->assertSame(
            'lotes_producto',
            $audit['tabla_afectada']
        );

        $this->assertSame(
            $lotId,
            (int) $audit['registro_id']
        );

        $newData = json_decode(
            (string) $audit['datos_nuevos'],
            true
        );

        $this->assertIsArray(
            $newData
        );

        $this->assertSame(
            $product,
            (int) $newData['producto_id']
        );

        $this->assertSame(
            $data['numero_lote'],
            $newData['numero_lote']
        );
    }
}
