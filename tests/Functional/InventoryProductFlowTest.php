<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Services\InventoryService;
use RuntimeException;

class InventoryProductFlowTest extends ClinicalTestCase
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

    private function uniqueCode(
        string $prefix = 'QA_PROD'
    ): string {
        return strtoupper(
            str_replace(
                '.',
                '',
                uniqid(
                    $prefix . '_',
                    true
                )
            )
        );
    }

    private function createCategory(
        bool $active = true
    ): int {
        $stmt = $this->db()->prepare(
            '
            INSERT INTO categorias_producto
            (
                nombre,
                descripcion,
                activo
            )
            VALUES
            (
                :nombre,
                :descripcion,
                :activo
            )
            '
        );

        $stmt->execute([
            'nombre' =>
            'Categoría QA '
                . uniqid(),

            'descripcion' =>
            'Categoría temporal QA',

            'activo' =>
            $active ? 1 : 0,
        ]);

        return (int)
        $this->db()->lastInsertId();
    }

    private function createVaccine(
        bool $active = true
    ): int {
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
                :activo
            )
            '
        );

        $stmt->execute([
            'nombre' =>
            'Vacuna producto QA '
                . uniqid(),

            'descripcion' =>
            'Vacuna temporal QA',

            'activo' =>
            $active ? 1 : 0,
        ]);

        return (int)
        $this->db()->lastInsertId();
    }

    private function basicData(): array
    {
        return [
            'codigo' =>
            $this->uniqueCode(),

            'nombre' =>
            'Producto QA '
                . uniqid(),

            'descripcion' =>
            'Producto temporal de pruebas',

            'unidad_base_id' =>
            $this->activeUnitId(),

            'controla_lote' => 0,

            'controla_vencimiento' => 0,
        ];
    }

    public function testProductCanBeCreated(): void
    {
        $data = $this->basicData();

        $id = (
            new InventoryService()
        )->createProduct(
            $data,
            $this->vetEnvironment,
            $this->superAdminId
        );

        $this->assertGreaterThan(
            0,
            $id
        );

        $row = $this->db()
            ->prepare(
                '
                SELECT *
                FROM productos
                WHERE id = :id
                '
            );

        $row->execute([
            'id' => $id,
        ]);

        $product = $row->fetch();

        $this->assertNotFalse(
            $product
        );

        $this->assertSame(
            $data['codigo'],
            $product['codigo']
        );

        $this->assertSame(
            $data['nombre'],
            $product['nombre']
        );

        $this->assertSame(
            $data['unidad_base_id'],
            (int) $product['unidad_base_id']
        );

        $this->assertSame(
            1,
            (int) $product['activo']
        );

        $this->assertNull(
            $product['deleted_at']
        );
    }

    public function testCodeIsNormalizedToUppercase(): void
    {
        $data = $this->basicData();

        $data['codigo'] =
            'qa-lower-'
            . uniqid();

        $id = (
            new InventoryService()
        )->createProduct(
            $data,
            $this->vetEnvironment,
            $this->superAdminId
        );

        $code = (string)
        $this->scalar(
            '
                SELECT codigo
                FROM productos
                WHERE id = :id
                ',
            [
                'id' => $id,
            ]
        );

        $this->assertSame(
            strtoupper($data['codigo']),
            $code
        );
    }

    public function testEmptyCodeIsRejected(): void
    {
        $data = $this->basicData();

        $data['codigo'] = '   ';

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'El código del producto es obligatorio.'
        );

        (
            new InventoryService()
        )->createProduct(
            $data,
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testEmptyNameIsRejected(): void
    {
        $data = $this->basicData();

        $data['nombre'] = '   ';

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'El nombre del producto es obligatorio.'
        );

        (
            new InventoryService()
        )->createProduct(
            $data,
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testMissingUnitIsRejected(): void
    {
        $data = $this->basicData();

        $data['unidad_base_id'] = 0;

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'La unidad base es obligatoria.'
        );

        (
            new InventoryService()
        )->createProduct(
            $data,
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testInvalidUnitIsRejected(): void
    {
        $data = $this->basicData();

        $data['unidad_base_id'] =
            65535;

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Unidad de medida no válida.'
        );

        (
            new InventoryService()
        )->createProduct(
            $data,
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testDuplicateCodeIsRejected(): void
    {
        $service =
            new InventoryService();

        $data = $this->basicData();

        $service->createProduct(
            $data,
            $this->vetEnvironment,
            $this->superAdminId
        );

        $second = $this->basicData();

        $second['codigo'] =
            $data['codigo'];

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Ya existe un producto con ese código.'
        );

        $service->createProduct(
            $second,
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testActiveCategoryCanBeAssigned(): void
    {
        $category =
            $this->createCategory();

        $data = $this->basicData();

        $data['categoria_producto_id'] = $category;

        $productId = (
            new InventoryService()
        )->createProduct(
            $data,
            $this->vetEnvironment,
            $this->superAdminId
        );

        $stored = (int)
        $this->scalar(
            '
                SELECT categoria_producto_id
                FROM productos
                WHERE id = :id
                ',
            [
                'id' => $productId,
            ]
        );

        $this->assertSame(
            $category,
            $stored
        );
    }

    public function testInactiveCategoryIsRejected(): void
    {
        $data = $this->basicData();

        $data['categoria_producto_id'] = $this->createCategory(
            false
        );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Categoría de producto no válida.'
        );

        (
            new InventoryService()
        )->createProduct(
            $data,
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testInvalidCategoryIsRejected(): void
    {
        $data = $this->basicData();

        $data['categoria_producto_id'] = 999999999;

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Categoría de producto no válida.'
        );

        (
            new InventoryService()
        )->createProduct(
            $data,
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testActiveVaccineCanBeLinked(): void
    {
        $vaccine =
            $this->createVaccine();

        $data = $this->basicData();

        $data['vacuna_id'] =
            $vaccine;

        $productId = (
            new InventoryService()
        )->createProduct(
            $data,
            $this->vetEnvironment,
            $this->superAdminId
        );

        $stored = (int)
        $this->scalar(
            '
                SELECT vacuna_id
                FROM productos
                WHERE id = :id
                ',
            [
                'id' => $productId,
            ]
        );

        $this->assertSame(
            $vaccine,
            $stored
        );
    }

    public function testInactiveVaccineIsRejected(): void
    {
        $data = $this->basicData();

        $data['vacuna_id'] =
            $this->createVaccine(
                false
            );

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Vacuna no válida.'
        );

        (
            new InventoryService()
        )->createProduct(
            $data,
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testInvalidDrugPresentationIsRejected(): void
    {
        $data = $this->basicData();

        $data['farmaco_presentacion_id'] = 999999999;

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Presentación farmacológica no válida.'
        );

        (
            new InventoryService()
        )->createProduct(
            $data,
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testExpirationControlRequiresLotControl(): void
    {
        $data = $this->basicData();

        $data['controla_lote'] = 0;

        $data['controla_vencimiento'] = 1;

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Un producto que controla vencimiento también debe controlar lote.'
        );

        (
            new InventoryService()
        )->createProduct(
            $data,
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testProductCanControlLotAndExpiration(): void
    {
        $data = $this->basicData();

        $data['controla_lote'] = 1;

        $data['controla_vencimiento'] = 1;

        $productId = (
            new InventoryService()
        )->createProduct(
            $data,
            $this->vetEnvironment,
            $this->superAdminId
        );

        $stmt = $this->db()->prepare(
            '
            SELECT
                controla_lote,
                controla_vencimiento
            FROM productos
            WHERE id = :id
            '
        );

        $stmt->execute([
            'id' => $productId,
        ]);

        $row = $stmt->fetch();

        $this->assertSame(
            1,
            (int) $row['controla_lote']
        );

        $this->assertSame(
            1,
            (int) $row['controla_vencimiento']
        );
    }

    public function testDrugAndVaccineCannotBeLinkedTogether(): void
    {
        $data = $this->basicData();

        /*
         * La regla se evalúa antes de consultar
         * las tablas correspondientes, por eso
         * estos IDs no necesitan existir.
         */
        $data['farmaco_presentacion_id'] = 999998;

        $data['vacuna_id'] =
            999999;

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'El producto no puede estar vinculado simultáneamente a una presentación farmacológica y a una vacuna.'
        );

        (
            new InventoryService()
        )->createProduct(
            $data,
            $this->vetEnvironment,
            $this->superAdminId
        );
    }

    public function testProductCreationWritesAudit(): void
    {
        $data = $this->basicData();

        $productId = (
            new InventoryService()
        )->createProduct(
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
            'tabla' => 'productos',
            'registro' => $productId,
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
            'CREAR_PRODUCTO',
            $audit['accion']
        );

        $this->assertSame(
            'productos',
            $audit['tabla_afectada']
        );

        $this->assertSame(
            $productId,
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
            $data['codigo'],
            $newData['codigo']
        );
    }

    private function createInventoryDrugPresentation(
        bool $active = true
    ): int {
        $drugId = $this->createDrug();

        $formId = (int) $this->scalar(
            '
        SELECT id
        FROM formas_farmaceuticas
        WHERE activo = 1
        ORDER BY id
        LIMIT 1
        '
        );

        if ($formId <= 0) {
            throw new RuntimeException(
                'No existe una forma farmacéutica activa para las pruebas.'
            );
        }

        $stmt = $this->db()->prepare(
            '
        INSERT INTO farmaco_presentaciones
        (
            farmaco_id,
            forma_farmaceutica_id,
            nombre_comercial,
            descripcion,
            activo
        )
        VALUES
        (
            :farmaco,
            :forma,
            :nombre,
            :descripcion,
            :activo
        )
        '
        );

        $stmt->execute([
            'farmaco' => $drugId,
            'forma' => $formId,
            'nombre' =>
            'Presentación QA ' . uniqid(),
            'descripcion' =>
            'Presentación farmacológica temporal',
            'activo' =>
            $active ? 1 : 0,
        ]);

        return (int) $this->db()
            ->lastInsertId();
    }

    public function testActiveDrugPresentationCanBeLinked(): void
    {
        $presentationId =
            $this->createInventoryDrugPresentation();

        $data = $this->basicData();

        $data['farmaco_presentacion_id'] = $presentationId;

        $productId = (
            new InventoryService()
        )->createProduct(
            $data,
            $this->vetEnvironment,
            $this->superAdminId
        );

        $stored = (int)
        $this->scalar(
            '
            SELECT farmaco_presentacion_id
            FROM productos
            WHERE id = :id
            ',
            [
                'id' => $productId,
            ]
        );

        $this->assertSame(
            $presentationId,
            $stored
        );
    }

    public function testInactiveDrugPresentationIsRejected(): void
    {
        $presentationId =
            $this->createInventoryDrugPresentation(
                false
            );

        $data = $this->basicData();

        $data['farmaco_presentacion_id'] = $presentationId;

        $this->expectException(
            RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Presentación farmacológica no válida.'
        );

        (
            new InventoryService()
        )->createProduct(
            $data,
            $this->vetEnvironment,
            $this->superAdminId
        );
    }
}
