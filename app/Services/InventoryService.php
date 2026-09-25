<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

class InventoryService
{
    public function ensureDefault(int $environmentId): int
    {
        $db = Database::connection();

        $stmt = $db->prepare(
            '
            SELECT id
            FROM inventarios
            WHERE entorno_id = :entorno
              AND activo = 1
            ORDER BY id
            LIMIT 1
            '
        );

        $stmt->execute([
            'entorno' => $environmentId,
        ]);

        $id = $stmt->fetchColumn();

        if ($id) {
            return (int) $id;
        }

        $stmt = $db->prepare(
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
            'entorno' => $environmentId,
            'nombre' => 'Inventario principal',
            'descripcion' => 'Inventario general',
        ]);

        return (int) $db->lastInsertId();
    }

    public function createProduct(
        array $data,
        int $environmentId,
        int $createdBy
    ): int {
        return Database::transaction(
            function (PDO $db) use (
                $data,
                $environmentId,
                $createdBy
            ): int {
                $code = strtoupper(
                    trim(
                        (string) (
                            $data['codigo']
                            ?? ''
                        )
                    )
                );

                $name = trim(
                    (string) (
                        $data['nombre']
                        ?? ''
                    )
                );

                $description = trim(
                    (string) (
                        $data['descripcion']
                        ?? ''
                    )
                );

                $unitId = (int) (
                    $data['unidad_base_id']
                    ?? 0
                );

                $categoryId = !empty($data['categoria_producto_id'])
                    ? (int) $data['categoria_producto_id']
                    : null;

                $drugPresentationId = !empty($data['farmaco_presentacion_id'])
                    ? (int) $data['farmaco_presentacion_id']
                    : null;

                $vaccineId = !empty($data['vacuna_id'])
                    ? (int) $data['vacuna_id']
                    : null;

                $controlsLot = !empty($data['controla_lote'])
                    ? 1
                    : 0;

                $controlsExpiration = !empty($data['controla_vencimiento'])
                    ? 1
                    : 0;

                /*
                * ==========================================
                * DATOS COMERCIALES / FACTURACIÓN
                * ==========================================
                */

                $priceRaw = trim(
                    (string) (
                        $data['precio_venta']
                        ?? ''
                    )
                );

                $salePrice = null;

                if ($priceRaw !== '') {
                    if (
                        !preg_match(
                            '/^\d{1,10}(?:\.\d{1,4})?$/',
                            $priceRaw
                        )
                    ) {
                        throw new RuntimeException(
                            'El precio de venta debe ser un número válido con hasta cuatro decimales.'
                        );
                    }

                    $salePrice = $priceRaw;
                }

                $taxRateId = !empty($data['impuesto_tarifa_id'])
                    ? (int) $data['impuesto_tarifa_id']
                    : null;

                $priceIncludesTax = !empty($data['precio_incluye_impuesto'])
                    ? 1
                    : 0;

                /*
                * DATOS DEL LOTE Y EXISTENCIAS INICIALES
                */
                $lotNumber = trim(
                    (string) ($data['numero_lote'] ?? '')
                );

                $manufactureDate = $this->dateOnly(
                    $data['fecha_fabricacion'] ?? null
                );

                $expirationDate = $this->dateOnly(
                    $data['fecha_vencimiento'] ?? null
                );

                $initialQuantityRaw = trim(
                    (string) ($data['cantidad_inicial'] ?? '0')
                );

                if ($initialQuantityRaw === '') {
                    $initialQuantityRaw = '0';
                }

                if (
                    !preg_match(
                        '/^\d{1,14}(?:\.\d{1,4})?$/',
                        $initialQuantityRaw
                    )
                ) {
                    throw new RuntimeException(
                        'La cantidad inicial debe ser un número válido con hasta cuatro decimales.'
                    );
                }

                $initialQuantity = (float) $initialQuantityRaw;

                if (
                    !is_finite($initialQuantity)
                    || $initialQuantity < 0
                ) {
                    throw new RuntimeException(
                        'La cantidad inicial no es válida.'
                    );
                }

                if (
                    $controlsLot === 0
                    && (
                        $lotNumber !== ''
                        || $manufactureDate !== null
                        || $expirationDate !== null
                    )
                ) {
                    throw new RuntimeException(
                        'Activa el control de lotes para registrar datos de un lote.'
                    );
                }

                if (
                    $controlsLot === 1
                    && $lotNumber === ''
                ) {
                    throw new RuntimeException(
                        'El número de lote es obligatorio para este producto.'
                    );
                }

                if (
                    $controlsExpiration === 1
                    && $expirationDate === null
                ) {
                    throw new RuntimeException(
                        'La fecha de vencimiento es obligatoria.'
                    );
                }

                if (
                    $manufactureDate !== null
                    && $expirationDate !== null
                    && $expirationDate < $manufactureDate
                ) {
                    throw new RuntimeException(
                        'El vencimiento no puede ser anterior a la fabricación.'
                    );
                }

                /*
                * ==========================================
                * VALIDAR STOCK MÍNIMO Y MÁXIMO
                * ==========================================
                */

                $parseStockLimit = static function (
                    mixed $value,
                    string $field
                ): ?string {

                    if ($value === null || trim((string) $value) === '') {
                        return null;
                    }

                    $value = trim((string) $value);

                    /*
                    * DECIMAL(18,4):
                    * Hasta 14 dígitos enteros y 4 decimales.
                    */
                    if (
                        !preg_match(
                            '/^\d{1,14}(?:\.\d{1,4})?$/',
                            $value
                        )
                    ) {
                        throw new RuntimeException(
                            "El campo {$field} debe ser un número positivo válido con hasta cuatro decimales."
                        );
                    }

                    return $value;
                };

                $minimumStock = $parseStockLimit(
                    $data['stock_minimo'] ?? null,
                    'stock mínimo'
                );

                $unlimitedMaximum = !empty($data['stock_maximo_sin_limite']);

                $maximumStock = $unlimitedMaximum
                    ? null
                    : $parseStockLimit(
                        $data['stock_maximo'] ?? null,
                        'stock máximo'
                    );

                /*
                * Si el usuario desactiva "Sin límite",
                * debe indicar un stock máximo.
                */
                if (
                    !$unlimitedMaximum
                    && $maximumStock === null
                ) {
                    throw new RuntimeException(
                        'Debes indicar el stock máximo o seleccionar "Sin límite".'
                    );
                }

                /*
                * Comparar valores numéricos.
                */
                if (
                    $minimumStock !== null
                    && $maximumStock !== null
                    && (float) $maximumStock < (float) $minimumStock
                ) {
                    throw new RuntimeException(
                        'El stock máximo no puede ser menor que el mínimo.'
                    );
                }

                if ($code === '') {
                    throw new RuntimeException(
                        'El código del producto es obligatorio.'
                    );
                }

                if ($name === '') {
                    throw new RuntimeException(
                        'El nombre del producto es obligatorio.'
                    );
                }

                if ($unitId <= 0) {
                    throw new RuntimeException(
                        'La unidad base es obligatoria.'
                    );
                }

                /*
             * El vencimiento se controla por lote.
             */
                if (
                    $controlsExpiration === 1
                    && $controlsLot !== 1
                ) {
                    throw new RuntimeException(
                        'Un producto que controla vencimiento también debe controlar lote.'
                    );
                }

                /*
             * Un producto físico no debe representar
             * simultáneamente una presentación de
             * fármaco y una vacuna.
             */
                if (
                    $drugPresentationId !== null
                    && $vaccineId !== null
                ) {
                    throw new RuntimeException(
                        'El producto no puede estar vinculado simultáneamente a una presentación farmacológica y a una vacuna.'
                    );
                }

                /*
             * Código único entre productos activos,
             * inactivos o eliminados lógicamente.
             * No reutilizamos códigos históricos.
             */
                $stmt = $db->prepare(
                    '
                SELECT id
                FROM productos
                WHERE codigo = :codigo
                LIMIT 1
                '
                );

                $stmt->execute([
                    'codigo' => $code,
                ]);

                if ($stmt->fetchColumn()) {
                    throw new RuntimeException(
                        'Ya existe un producto con ese código.'
                    );
                }

                /*
             * Unidad activa.
             */
                $stmt = $db->prepare(
                    '
                SELECT 1
                FROM unidades_medida
                WHERE id = :id
                  AND activo = 1
                LIMIT 1
                '
                );

                $stmt->execute([
                    'id' => $unitId,
                ]);

                if (!$stmt->fetchColumn()) {
                    throw new RuntimeException(
                        'Unidad de medida no válida.'
                    );
                }

                /*
             * Categoría opcional, pero si llega debe
             * existir y estar activa.
             */
                if ($categoryId !== null) {
                    $stmt = $db->prepare(
                        '
                    SELECT 1
                    FROM categorias_producto
                    WHERE id = :id
                      AND activo = 1
                    LIMIT 1
                    '
                    );

                    $stmt->execute([
                        'id' => $categoryId,
                    ]);

                    if (!$stmt->fetchColumn()) {
                        throw new RuntimeException(
                            'Categoría de producto no válida.'
                        );
                    }
                }

                /*
             * Presentación farmacológica opcional.
             */
                if (
                    $drugPresentationId !== null
                ) {
                    $stmt = $db->prepare(
                        '
                    SELECT 1
                    FROM farmaco_presentaciones
                    WHERE id = :id
                      AND activo = 1
                    LIMIT 1
                    '
                    );

                    $stmt->execute([
                        'id' =>
                        $drugPresentationId,
                    ]);

                    if (!$stmt->fetchColumn()) {
                        throw new RuntimeException(
                            'Presentación farmacológica no válida.'
                        );
                    }
                }

                /*
             * Vacuna opcional.
             */
                if ($vaccineId !== null) {
                    $stmt = $db->prepare(
                        '
                    SELECT 1
                    FROM vacunas
                    WHERE id = :id
                      AND activo = 1
                    LIMIT 1
                    '
                    );

                    $stmt->execute([
                        'id' => $vaccineId,
                    ]);

                    if (!$stmt->fetchColumn()) {
                        throw new RuntimeException(
                            'Vacuna no válida.'
                        );
                    }
                }

                /*
                * ==========================================
                * VALIDAR TARIFA DE IMPUESTO
                * ==========================================
                */

                if ($taxRateId !== null) {
                    $stmt = $db->prepare(
                        '
        SELECT
            it.id
        FROM impuesto_tarifas it
        INNER JOIN impuestos i
            ON i.id = it.impuesto_id
        WHERE it.id = :id
          AND it.activo = 1
          AND i.activo = 1
          AND it.fecha_desde <= CURDATE()
          AND (
                it.fecha_hasta IS NULL
                OR it.fecha_hasta >= CURDATE()
              )
        LIMIT 1
        '
                    );

                    $stmt->execute([
                        'id' => $taxRateId,
                    ]);

                    if (!$stmt->fetchColumn()) {
                        throw new RuntimeException(
                            'La tarifa de impuesto seleccionada no es válida o no está vigente.'
                        );
                    }
                }

                /*
 * Si no existe una tarifa configurada,
 * el indicador "precio incluye impuesto"
 * no tiene sentido.
 */
                if ($taxRateId === null) {
                    $priceIncludesTax = 0;
                }

                $stmt = $db->prepare(
                    '
                    INSERT INTO productos
                    (
                        categoria_producto_id,
                        codigo,
                        nombre,
                        descripcion,
                        unidad_base_id,
                        farmaco_presentacion_id,
                        vacuna_id,
                        controla_lote,
                        controla_vencimiento,

                        precio_venta,
                        precio_incluye_impuesto,
                        impuesto_tarifa_id,

                        activo
                    )
                    VALUES
                    (
                        :categoria,
                        :codigo,
                        :nombre,
                        :descripcion,
                        :unidad,
                        :farmaco_presentacion,
                        :vacuna,
                        :controla_lote,
                        :controla_vencimiento,

                        :precio_venta,
                        :precio_incluye_impuesto,
                        :impuesto_tarifa_id,

                        1
                    )
                    '
                );

                $stmt->execute([
                    'categoria' =>
                    $categoryId,

                    'codigo' =>
                    $code,

                    'nombre' =>
                    $name,

                    'descripcion' =>
                    $description !== ''
                        ? $description
                        : null,

                    'unidad' =>
                    $unitId,

                    'farmaco_presentacion' =>
                    $drugPresentationId,

                    'vacuna' =>
                    $vaccineId,

                    'controla_lote' =>
                    $controlsLot,

                    'controla_vencimiento' =>
                    $controlsExpiration,

                    'precio_venta' =>
                    $salePrice,

                    'precio_incluye_impuesto' =>
                    $priceIncludesTax,

                    'impuesto_tarifa_id' =>
                    $taxRateId,
                ]);

                $productId =
                    (int) $db->lastInsertId();

                /*
                * ==========================================
                * ASOCIAR PRODUCTO AL INVENTARIO DEL ENTORNO
                * ==========================================
                *
                * El producto ya fue creado en productos.
                * Ahora lo asociamos a un inventario activo
                * del entorno actual, sin generar movimientos
                * ni alterar cantidades.
                */

                $requestedInventoryId = (int) (
                    $data['inventario_id'] ?? 0
                );

                if ($requestedInventoryId > 0) {

                    // Si el formulario especifica un inventario,
                    // comprobamos que pertenezca al entorno activo.

                    $stmt = $db->prepare(
                        '
                        SELECT id
                        FROM inventarios
                        WHERE id = :inventario
                        AND entorno_id = :entorno
                        AND activo = 1
                        LIMIT 1
                        '
                    );

                    $stmt->execute([
                        'inventario' => $requestedInventoryId,
                        'entorno' => $environmentId,
                    ]);
                } else {

                    // Compatibilidad con el formulario actual:
                    // utiliza el primer inventario activo del entorno.

                    $stmt = $db->prepare(
                        '
                        SELECT id
                        FROM inventarios
                        WHERE entorno_id = :entorno
                        AND activo = 1
                        ORDER BY id
                        LIMIT 1
                        '
                    );

                    $stmt->execute([
                        'entorno' => $environmentId,
                    ]);
                }

                $inventoryId = (int) $stmt->fetchColumn();

                if ($inventoryId <= 0) {
                    throw new RuntimeException(
                        'No existe un inventario activo válido para asociar el producto.'
                    );
                }

                /*
 * Asociar el producto al inventario
 * y guardar sus límites de existencias.
 */
                $stmt = $db->prepare(
                    '
                    INSERT INTO inventario_productos
                    (
                        inventario_id,
                        producto_id,
                        stock_minimo,
                        stock_maximo,
                        activo
                    )
                    VALUES
                    (
                        :inventario,
                        :producto,
                        :stock_minimo,
                        :stock_maximo,
                        1
                    )
                    '
                );

                $stmt->execute([
                    'inventario'   => $inventoryId,
                    'producto'     => $productId,
                    'stock_minimo' => $minimumStock,
                    'stock_maximo' => $maximumStock,
                ]);

                /*
 * ==========================================
 * CREAR LOTE INICIAL
 * ==========================================
 */

                $lotId = null;

                if ($controlsLot === 1) {

                    $stmt = $db->prepare(
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
            :numero_lote,
            :fabricacion,
            :vencimiento
        )
        '
                    );

                    $stmt->execute([
                        'producto'    => $productId,
                        'numero_lote' => $lotNumber,
                        'fabricacion' => $manufactureDate,
                        'vencimiento' => $expirationDate,
                    ]);

                    $lotId = (int) $db->lastInsertId();

                    (new AuditService())->log(
                        $createdBy,
                        $environmentId,
                        'INVENTARIO',
                        'CREAR_LOTE',
                        'lotes_producto',
                        $lotId,
                        null,
                        [
                            'producto_id' => $productId,
                            'numero_lote' => $lotNumber,
                            'fecha_fabricacion' => $manufactureDate,
                            'fecha_vencimiento' => $expirationDate,
                            'precio_venta' => $salePrice,
                            'impuesto_tarifa_id' => $taxRateId,
                            'precio_incluye_impuesto' => $priceIncludesTax,
                        ]
                    );
                }

                /*
 * ==========================================
 * REGISTRAR EXISTENCIAS INICIALES
 * ==========================================
 */

                if ($initialQuantity > 0) {

                    /*
     * Validar el tipo de movimiento.
     * ID 1 = Entrada.
     */
                    $entryType = $this->movementType($db, 1);

                    if (
                        (int) $entryType['factor'] !== 1
                        || mb_strtoupper(
                            trim((string) $entryType['nombre'])
                        ) !== 'ENTRADA'
                    ) {
                        throw new RuntimeException(
                            'El tipo de movimiento inicial no corresponde a una entrada.'
                        );
                    }

                    /*
     * Registrar la entrada sin modificar
     * directamente el stock.
     */
                    $stmt = $db->prepare(
                        '
        INSERT INTO movimientos_inventario
        (
            inventario_id,
            producto_id,
            lote_id,
            tipo_movimiento_id,
            cantidad,
            costo_unitario,
            realizado_por,
            fecha_movimiento,
            referencia_tipo,
            referencia_id,
            observaciones
        )
        VALUES
        (
            :inventario,
            :producto,
            :lote,
            :tipo,
            :cantidad,
            :costo,
            :usuario,
            NOW(),
            :referencia_tipo,
            :referencia_id,
            :observaciones
        )
        '
                    );

                    $stmt->execute([
                        'inventario' => $inventoryId,
                        'producto' => $productId,
                        'lote' => $lotId,
                        'tipo' => 1,
                        'cantidad' => $initialQuantityRaw,
                        'costo' => null,
                        'usuario' => $createdBy,
                        'referencia_tipo' => 'CREACION_PRODUCTO',
                        'referencia_id' => $productId,
                        'observaciones' =>
                        'Existencias iniciales registradas al crear el producto.',
                    ]);

                    $movementId = (int) $db->lastInsertId();

                    (new AuditService())->log(
                        $createdBy,
                        $environmentId,
                        'INVENTARIO',
                        'MOVIMIENTO',
                        'movimientos_inventario',
                        $movementId,
                        null,
                        [
                            'inventario_id' => $inventoryId,
                            'producto_id' => $productId,
                            'lote_id' => $lotId,
                            'tipo_movimiento_id' => 1,
                            'cantidad' => $initialQuantityRaw,
                            'referencia_tipo' => 'CREACION_PRODUCTO',
                        ]
                    );
                }

                // Eliminar la ejecución duplicada innecesaria.

                (new AuditService())->log(
                    $createdBy,
                    $environmentId,
                    'INVENTARIO',
                    'CREAR_PRODUCTO',
                    'productos',
                    $productId,
                    null,
                    [
                        'codigo' => $code,
                        'nombre' => $name,
                        'categoria_producto_id' =>
                        $categoryId,
                        'unidad_base_id' =>
                        $unitId,
                        'farmaco_presentacion_id' =>
                        $drugPresentationId,
                        'vacuna_id' =>
                        $vaccineId,
                        'controla_lote' =>
                        $controlsLot,
                        'controla_vencimiento' =>
                        $controlsExpiration,
                    ]
                );

                return $productId;
            }
        );
    }

    public function updateProduct(
        int $productId,
        array $data,
        int $environmentId,
        int $updatedBy
    ): void {
        if ($productId <= 0) {
            throw new RuntimeException(
                'El producto seleccionado no es válido.'
            );
        }

        $code = strtoupper(
            trim((string)($data['codigo'] ?? ''))
        );

        $name = trim(
            (string)($data['nombre'] ?? '')
        );

        $description = trim(
            (string)($data['descripcion'] ?? '')
        );

        $categoryId = !empty($data['categoria_producto_id'])
            ? (int)$data['categoria_producto_id']
            : null;

        $unitId = (int)(
            $data['unidad_base_id']
            ?? 0
        );

        $controlsLot = !empty($data['controla_lote'])
            ? 1
            : 0;

        $controlsExpiration = !empty($data['controla_vencimiento'])
            ? 1
            : 0;

        /*
     * ==========================================
     * VALIDACIONES GENERALES
     * ==========================================
     */

        if ($code === '') {
            throw new RuntimeException(
                'El código del producto es obligatorio.'
            );
        }

        if ($name === '') {
            throw new RuntimeException(
                'El nombre del producto es obligatorio.'
            );
        }

        if ($unitId <= 0) {
            throw new RuntimeException(
                'Debe seleccionar una unidad base.'
            );
        }

        /*
     * Si controla vencimiento necesariamente
     * debe controlar lote.
     */
        if (
            $controlsExpiration === 1
            && $controlsLot !== 1
        ) {
            throw new RuntimeException(
                'Un producto que controla vencimiento debe controlar lote.'
            );
        }

        /*
     * ==========================================
     * PRECIO
     * ==========================================
     */

        $priceRaw = trim(
            (string)(
                $data['precio_venta']
                ?? ''
            )
        );

        $salePrice = null;

        if ($priceRaw !== '') {
            if (
                !preg_match(
                    '/^\d{1,10}(?:\.\d{1,4})?$/',
                    $priceRaw
                )
            ) {
                throw new RuntimeException(
                    'El precio de venta debe ser un número válido con hasta cuatro decimales.'
                );
            }

            $salePrice = $priceRaw;
        }

        /*
     * ==========================================
     * IMPUESTO
     * ==========================================
     */

        $taxRateId = !empty($data['impuesto_tarifa_id'])
            ? (int)$data['impuesto_tarifa_id']
            : null;

        $priceIncludesTax = !empty($data['precio_incluye_impuesto'])
            ? 1
            : 0;

        if ($taxRateId === null) {
            $priceIncludesTax = 0;
        }

        /*
     * ==========================================
     * STOCK MÍNIMO / MÁXIMO
     * ==========================================
     */

        $minimumStockRaw = trim(
            (string)(
                $data['stock_minimo']
                ?? ''
            )
        );

        if (
            $minimumStockRaw !== ''
            && !preg_match('/^\d{1,10}(?:\.\d{1,4})?$/', $minimumStockRaw)
        ) {
            throw new RuntimeException(
                'El stock mínimo debe ser un número válido con hasta cuatro decimales.'
            );
        }

        $minimumStock = $minimumStockRaw === ''
            ? '0'
            : $minimumStockRaw;

        $unlimitedMaximum = !empty($data['stock_maximo_sin_limite']);

        $maximumStock = null;

        if (!$unlimitedMaximum) {
            $maximumStockRaw = trim(
                (string)($data['stock_maximo'] ?? '')
            );

            if ($maximumStockRaw === '') {
                throw new RuntimeException(
                    'Debe indicar el stock máximo o seleccionar "Sin límite".'
                );
            }

            if (
                !preg_match('/^\d{1,10}(?:\.\d{1,4})?$/', $maximumStockRaw)
            ) {
                throw new RuntimeException(
                    'El stock máximo debe ser un número válido con hasta cuatro decimales.'
                );
            }

            $maximumStock = $maximumStockRaw;

            if ((float)$maximumStock < (float)$minimumStock) {
                throw new RuntimeException(
                    'El stock máximo no puede ser menor que el stock mínimo.'
                );
            }
        }

        $db = Database::connection();

        $db->beginTransaction();

        try {
            /*
         * ==========================================
         * PRODUCTO ACTUAL
         * ==========================================
         */

            $stmt = $db->prepare(
                '
            SELECT
                p.*
            FROM productos p
            WHERE p.id = :id
              AND p.deleted_at IS NULL
            LIMIT 1
            FOR UPDATE
            '
            );

            $stmt->execute([
                'id' => $productId,
            ]);

            $before = $stmt->fetch();

            if (!$before) {
                throw new RuntimeException(
                    'El producto no existe.'
                );
            }

            /*
         * ==========================================
         * VERIFICAR QUE PERTENECE AL ENTORNO
         * ==========================================
         *
         * El producto es global, pero para poder
         * editarlo desde este entorno debe estar
         * asociado a uno de sus inventarios.
         */

            $stmt = $db->prepare(
                '
            SELECT
                ip.inventario_id,
                ip.stock_minimo,
                ip.stock_maximo
            FROM inventario_productos ip
            INNER JOIN inventarios i
                ON i.id = ip.inventario_id
            WHERE ip.producto_id = :producto
              AND i.entorno_id = :entorno
              AND i.activo = 1
              AND ip.activo = 1
            ORDER BY ip.inventario_id
            LIMIT 1
            FOR UPDATE
            '
            );

            $stmt->execute([
                'producto' => $productId,
                'entorno' => $environmentId,
            ]);

            $inventoryProduct = $stmt->fetch();

            if (!$inventoryProduct) {
                throw new RuntimeException(
                    'El producto no pertenece al inventario del entorno activo.'
                );
            }

            $inventoryId = (int)$inventoryProduct['inventario_id'];

            /*
         * ==========================================
         * CÓDIGO ÚNICO
         * ==========================================
         */

            $stmt = $db->prepare(
                '
            SELECT id
            FROM productos
            WHERE codigo = :codigo
              AND id <> :id
              AND deleted_at IS NULL
            LIMIT 1
            '
            );

            $stmt->execute([
                'codigo' => $code,
                'id' => $productId,
            ]);

            if ($stmt->fetchColumn()) {
                throw new RuntimeException(
                    'Ya existe otro producto con ese código.'
                );
            }

            /*
         * ==========================================
         * VALIDAR CATEGORÍA
         * ==========================================
         */

            if ($categoryId !== null) {
                $stmt = $db->prepare(
                    '
                SELECT id
                FROM categorias_producto
                WHERE id = :id
                  AND activo = 1
                LIMIT 1
                '
                );

                $stmt->execute([
                    'id' => $categoryId,
                ]);

                if (!$stmt->fetchColumn()) {
                    throw new RuntimeException(
                        'La categoría seleccionada no es válida.'
                    );
                }
            }

            /*
         * ==========================================
         * VALIDAR UNIDAD
         * ==========================================
         */

            $stmt = $db->prepare(
                '
            SELECT id
            FROM unidades_medida
            WHERE id = :id
              AND activo = 1
            LIMIT 1
            '
            );

            $stmt->execute([
                'id' => $unitId,
            ]);

            if (!$stmt->fetchColumn()) {
                throw new RuntimeException(
                    'La unidad seleccionada no es válida.'
                );
            }

            /*
         * ==========================================
         * VALIDAR TARIFA
         * ==========================================
         */

            if ($taxRateId !== null) {
                $stmt = $db->prepare(
                    '
                SELECT
                    it.id
                FROM impuesto_tarifas it
                INNER JOIN impuestos i
                    ON i.id = it.impuesto_id
                WHERE it.id = :id
                  AND it.activo = 1
                  AND i.activo = 1
                  AND it.fecha_desde <= CURDATE()
                  AND (
                        it.fecha_hasta IS NULL
                        OR it.fecha_hasta >= CURDATE()
                      )
                LIMIT 1
                '
                );

                $stmt->execute([
                    'id' => $taxRateId,
                ]);

                if (!$stmt->fetchColumn()) {
                    throw new RuntimeException(
                        'La tarifa de impuesto seleccionada no es válida o no está vigente.'
                    );
                }
            }

            /*
         * ==========================================
         * ACTUALIZAR PRODUCTO
         * ==========================================
         */

            $stmt = $db->prepare(
                '
            UPDATE productos
            SET
                categoria_producto_id = :categoria,
                codigo = :codigo,
                nombre = :nombre,
                descripcion = :descripcion,
                unidad_base_id = :unidad,
                controla_lote = :controla_lote,
                controla_vencimiento = :controla_vencimiento,
                precio_venta = :precio_venta,
                precio_incluye_impuesto = :precio_incluye_impuesto,
                impuesto_tarifa_id = :impuesto_tarifa_id,
                updated_at = NOW()
            WHERE id = :id
            '
            );

            $stmt->execute([
                'categoria' => $categoryId,
                'codigo' => $code,
                'nombre' => $name,

                'descripcion' => $description !== ''
                    ? $description
                    : null,

                'unidad' => $unitId,

                'controla_lote' => $controlsLot,

                'controla_vencimiento' =>
                $controlsExpiration,

                'precio_venta' => $salePrice,

                'precio_incluye_impuesto' =>
                $priceIncludesTax,

                'impuesto_tarifa_id' =>
                $taxRateId,

                'id' => $productId,
            ]);

            /*
         * ==========================================
         * ACTUALIZAR CONFIGURACIÓN DE INVENTARIO
         * ==========================================
         *
         * No modificamos stock actual.
         * Solo mínimos/máximos.
         */

            $stmt = $db->prepare(
                '
            UPDATE inventario_productos
            SET
                stock_minimo = :minimo,
                stock_maximo = :maximo
            WHERE inventario_id = :inventario
              AND producto_id = :producto
            '
            );

            $stmt->execute([
                'minimo' => $minimumStock,
                'maximo' => $maximumStock,
                'inventario' => $inventoryId,
                'producto' => $productId,
            ]);

            /*
         * ==========================================
         * AUDITORÍA
         * ==========================================
         */

            (new AuditService())->log(
                $updatedBy,
                $environmentId,
                'INVENTARIO',
                'EDITAR_PRODUCTO',
                'productos',
                $productId,
                $before,
                [
                    'categoria_producto_id' =>
                    $categoryId,

                    'codigo' =>
                    $code,

                    'nombre' =>
                    $name,

                    'descripcion' =>
                    $description !== ''
                        ? $description
                        : null,

                    'unidad_base_id' =>
                    $unitId,

                    'controla_lote' =>
                    $controlsLot,

                    'controla_vencimiento' =>
                    $controlsExpiration,

                    'precio_venta' =>
                    $salePrice,

                    'impuesto_tarifa_id' =>
                    $taxRateId,

                    'precio_incluye_impuesto' =>
                    $priceIncludesTax,

                    'stock_minimo' =>
                    $minimumStock,

                    'stock_maximo' =>
                    $maximumStock,
                ]
            );

            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }

    public function movement(
        array $data,
        int $environmentId,
        int $createdBy,
        bool $allowExpiredLotWithdrawal = false
    ): int {
        return Database::transaction(
            function (PDO $db) use (
                $data,
                $environmentId,
                $createdBy,
                $allowExpiredLotWithdrawal
            ): int {
                // You can now use $allowExpiredLotWithdrawal within this closure as needed.
                $inventoryId = !empty($data['inventario_id'])
                    ? (int) $data['inventario_id']
                    : $this->ensureDefault(
                        $environmentId
                    );

                $productId = (int) (
                    $data['producto_id']
                    ?? 0
                );

                $movementTypeId = (int) (
                    $data['tipo_movimiento_id']
                    ?? 0
                );

                $quantity = (float) (
                    $data['cantidad']
                    ?? 0
                );

                if (
                    $productId <= 0
                    || $movementTypeId <= 0
                    || $quantity <= 0
                ) {
                    throw new RuntimeException(
                        'Producto, tipo y cantidad son obligatorios.'
                    );
                }

                $this->assertInventory(
                    $db,
                    $inventoryId,
                    $environmentId
                );

                $product = $this->product(
                    $db,
                    $productId
                );

                $movementType =
                    $this->movementType(
                        $db,
                        $movementTypeId
                    );

                $lotId = !empty($data['lote_id'])
                    ? (int) $data['lote_id']
                    : null;

                $lot = null;

                if ($lotId !== null) {
                    $lot = $this->lot(
                        $db,
                        $lotId,
                        $productId
                    );
                }

                if (
                    (int) $product['controla_lote'] === 1
                    && $lotId === null
                ) {
                    throw new RuntimeException(
                        'El producto requiere un lote.'
                    );
                }

                if (
                    (int) $product['controla_vencimiento'] === 1
                ) {
                    if ($lot === null) {
                        throw new RuntimeException(
                            'El producto requiere un lote con fecha de vencimiento.'
                        );
                    }

                    if (
                        empty($lot['fecha_vencimiento'])
                    ) {
                        throw new RuntimeException(
                            'El lote requiere fecha de vencimiento.'
                        );
                    }
                }

                $factor = (int) $movementType['factor'];

                if (
                    $factor < 0
                    && !$allowExpiredLotWithdrawal
                    && $lot !== null
                    && !empty($lot['fecha_vencimiento'])
                    && $lot['fecha_vencimiento'] < date('Y-m-d')
                ) {
                    throw new RuntimeException(
                        'No se puede retirar o consumir un lote vencido.'
                    );
                }

                /*
                 * Evitamos que una salida deje
                 * existencias negativas.
                 */
                if ($factor < 0) {
                    $currentStock =
                        $this->currentStock(
                            $db,
                            $inventoryId,
                            $productId,
                            $lotId
                        );

                    if (
                        $quantity >
                        $currentStock
                    ) {
                        throw new RuntimeException(
                            'Stock insuficiente para realizar el movimiento.'
                        );
                    }
                }

                /*
                 * Asociamos el producto al inventario
                 * únicamente cuando todo lo anterior
                 * ya fue validado.
                 */
                $stmt = $db->prepare(
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
                    ON DUPLICATE KEY UPDATE
                        activo = VALUES(activo)
                    '
                );

                $stmt->execute([
                    'inventario' =>
                    $inventoryId,

                    'producto' =>
                    $productId,
                ]);

                $cost = null;

                if (
                    array_key_exists(
                        'costo_unitario',
                        $data
                    )
                    && $data['costo_unitario'] !== ''
                    && $data['costo_unitario'] !== null
                ) {
                    $cost = (float) $data['costo_unitario'];

                    if ($cost < 0) {
                        throw new RuntimeException(
                            'El costo unitario no puede ser negativo.'
                        );
                    }
                }

                $stmt = $db->prepare(
                    '
                    INSERT INTO movimientos_inventario
                    (
                        inventario_id,
                        producto_id,
                        lote_id,
                        tipo_movimiento_id,
                        cantidad,
                        costo_unitario,
                        realizado_por,
                        fecha_movimiento,
                        referencia_tipo,
                        referencia_id,
                        observaciones
                    )
                    VALUES
                    (
                        :inventario,
                        :producto,
                        :lote,
                        :tipo,
                        :cantidad,
                        :costo,
                        :usuario,
                        :fecha,
                        :referencia_tipo,
                        :referencia_id,
                        :observaciones
                    )
                    '
                );

                $stmt->execute([
                    'inventario' =>
                    $inventoryId,

                    'producto' =>
                    $productId,

                    'lote' =>
                    $lotId,

                    'tipo' =>
                    $movementTypeId,

                    'cantidad' =>
                    $quantity,

                    'costo' =>
                    $cost,

                    'usuario' =>
                    $createdBy,

                    'fecha' =>
                    $this->dateTime(
                        $data['fecha_movimiento']
                            ?? null
                    ),

                    'referencia_tipo' =>
                    trim(
                        (string) (
                            $data['referencia_tipo']
                            ?? ''
                        )
                    ) ?: null,

                    'referencia_id' =>
                    !empty($data['referencia_id'])
                        ? (int) $data['referencia_id']
                        : null,

                    'observaciones' =>
                    trim(
                        (string) (
                            $data['observaciones']
                            ?? ''
                        )
                    ) ?: null,
                ]);

                $movementId =
                    (int) $db->lastInsertId();

                (new AuditService())->log(
                    $createdBy,
                    $environmentId,
                    'INVENTARIO',
                    'MOVIMIENTO',
                    'movimientos_inventario',
                    $movementId
                );

                return $movementId;
            }
        );
    }

    private function assertInventory(
        PDO $db,
        int $inventoryId,
        int $environmentId
    ): void {
        $stmt = $db->prepare(
            '
            SELECT 1
            FROM inventarios
            WHERE id = :inventario
              AND entorno_id = :entorno
              AND activo = 1
            LIMIT 1
            '
        );

        $stmt->execute([
            'inventario' =>
            $inventoryId,

            'entorno' =>
            $environmentId,
        ]);

        if (!$stmt->fetchColumn()) {
            throw new RuntimeException(
                'Inventario no válido.'
            );
        }
    }

    private function product(
        PDO $db,
        int $productId
    ): array {
        $stmt = $db->prepare(
            '
            SELECT
                id,
                controla_lote,
                controla_vencimiento
            FROM productos
            WHERE id = :producto
              AND activo = 1
              AND deleted_at IS NULL
            LIMIT 1
            '
        );

        $stmt->execute([
            'producto' => $productId,
        ]);

        $product = $stmt->fetch();

        if (!$product) {
            throw new RuntimeException(
                'Producto no válido.'
            );
        }

        return $product;
    }

    private function movementType(
        PDO $db,
        int $movementTypeId
    ): array {
        $stmt = $db->prepare(
            '
            SELECT
                id,
                codigo,
                nombre,
                factor
            FROM tipos_movimiento_inventario
            WHERE id = :id
            LIMIT 1
            '
        );

        $stmt->execute([
            'id' => $movementTypeId,
        ]);

        $type = $stmt->fetch();

        if (!$type) {
            throw new RuntimeException(
                'Tipo de movimiento no válido.'
            );
        }

        if (
            !in_array(
                (int) $type['factor'],
                [-1, 1],
                true
            )
        ) {
            throw new RuntimeException(
                'El tipo de movimiento tiene un factor no válido.'
            );
        }

        return $type;
    }

    private function lot(
        PDO $db,
        int $lotId,
        int $productId
    ): array {
        $stmt = $db->prepare(
            '
            SELECT
                id,
                producto_id,
                numero_lote,
                fecha_fabricacion,
                fecha_vencimiento
            FROM lotes_producto
            WHERE id = :lote
              AND producto_id = :producto
            LIMIT 1
            '
        );

        $stmt->execute([
            'lote' => $lotId,
            'producto' => $productId,
        ]);

        $lot = $stmt->fetch();

        if (!$lot) {
            throw new RuntimeException(
                'Lote no válido para el producto seleccionado.'
            );
        }

        return $lot;
    }

    private function currentStock(
        PDO $db,
        int $inventoryId,
        int $productId,
        ?int $lotId = null
    ): float {
        $sql = '
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
        ';

        $params = [
            'inventario' => $inventoryId,
            'producto' => $productId,
        ];

        /*
         * Si se está retirando por lote,
         * comprobamos las existencias
         * específicas de ese lote.
         */
        if ($lotId !== null) {
            $sql .= '
                AND mi.lote_id = :lote
            ';

            $params['lote'] = $lotId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return (float) $stmt->fetchColumn();
    }

    private function dateTime(
        mixed $value
    ): string {
        if (
            $value === null
            || $value === ''
        ) {
            return date(
                'Y-m-d H:i:s'
            );
        }

        $timestamp =
            strtotime((string) $value);

        if ($timestamp === false) {
            throw new RuntimeException(
                'Fecha de movimiento no válida.'
            );
        }

        return date(
            'Y-m-d H:i:s',
            $timestamp
        );
    }

    public function createLot(
        array $data,
        int $environmentId,
        int $createdBy
    ): int {
        return Database::transaction(
            function (PDO $db) use (
                $data,
                $environmentId,
                $createdBy
            ): int {
                $productId = (int) (
                    $data['producto_id']
                    ?? 0
                );

                $lotNumber = trim(
                    (string) (
                        $data['numero_lote']
                        ?? ''
                    )
                );

                $manufactureDate = $this->dateOnly(
                    $data['fecha_fabricacion']
                        ?? null
                );

                $expirationDate = $this->dateOnly(
                    $data['fecha_vencimiento']
                        ?? null
                );

                if ($productId <= 0) {
                    throw new RuntimeException(
                        'El producto es obligatorio.'
                    );
                }

                if ($lotNumber === '') {
                    throw new RuntimeException(
                        'El número de lote es obligatorio.'
                    );
                }

                /*
             * Validar producto.
             */
                $stmt = $db->prepare(
                    '
                SELECT
                    id,
                    controla_lote,
                    controla_vencimiento
                FROM productos
                WHERE id = :id
                  AND activo = 1
                  AND deleted_at IS NULL
                LIMIT 1
                '
                );

                $stmt->execute([
                    'id' => $productId,
                ]);

                $product = $stmt->fetch();

                if (!$product) {
                    throw new RuntimeException(
                        'Producto no válido.'
                    );
                }

                /*
             * Si el producto controla vencimiento,
             * el lote debe tener fecha de vencimiento.
             */
                if (
                    (int) $product['controla_vencimiento'] === 1
                    && $expirationDate === null
                ) {
                    throw new RuntimeException(
                        'La fecha de vencimiento es obligatoria para este producto.'
                    );
                }

                /*
             * La fecha de vencimiento no puede ser
             * anterior a la fecha de fabricación.
             */
                if (
                    $manufactureDate !== null
                    && $expirationDate !== null
                    && $expirationDate < $manufactureDate
                ) {
                    throw new RuntimeException(
                        'La fecha de vencimiento no puede ser anterior a la fecha de fabricación.'
                    );
                }

                /*
             * Número de lote único dentro
             * del mismo producto.
             */
                $stmt = $db->prepare(
                    '
                SELECT id
                FROM lotes_producto
                WHERE producto_id = :producto
                  AND numero_lote = :numero_lote
                LIMIT 1
                '
                );

                $stmt->execute([
                    'producto' =>
                    $productId,

                    'numero_lote' =>
                    $lotNumber,
                ]);

                if ($stmt->fetchColumn()) {
                    throw new RuntimeException(
                        'Ya existe un lote con ese número para este producto.'
                    );
                }

                $stmt = $db->prepare(
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
                    :numero_lote,
                    :fecha_fabricacion,
                    :fecha_vencimiento
                )
                '
                );

                $stmt->execute([
                    'producto' =>
                    $productId,

                    'numero_lote' =>
                    $lotNumber,

                    'fecha_fabricacion' =>
                    $manufactureDate,

                    'fecha_vencimiento' =>
                    $expirationDate,
                ]);

                $lotId =
                    (int) $db->lastInsertId();

                (new AuditService())->log(
                    $createdBy,
                    $environmentId,
                    'INVENTARIO',
                    'CREAR_LOTE',
                    'lotes_producto',
                    $lotId,
                    null,
                    [
                        'producto_id' =>
                        $productId,

                        'numero_lote' =>
                        $lotNumber,

                        'fecha_fabricacion' =>
                        $manufactureDate,

                        'fecha_vencimiento' =>
                        $expirationDate,
                    ]
                );

                return $lotId;
            }
        );
    }

    private function dateOnly(
        mixed $value
    ): ?string {
        if (
            $value === null
            || trim((string) $value) === ''
        ) {
            return null;
        }

        $value = trim(
            (string) $value
        );

        $date = \DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $value
        );

        $errors = \DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || (
                is_array($errors)
                && (
                    $errors['warning_count'] > 0
                    || $errors['error_count'] > 0
                )
            )
        ) {
            throw new RuntimeException(
                'Fecha no válida.'
            );
        }

        return $date->format(
            'Y-m-d'
        );
    }

    public function configureStockLimits(
        int $inventoryId,
        int $productId,
        mixed $minimum,
        mixed $maximum,
        int $environmentId,
        int $createdBy
    ): void {
        Database::transaction(
            function (PDO $db) use (
                $inventoryId,
                $productId,
                $minimum,
                $maximum,
                $environmentId,
                $createdBy
            ): void {
                if ($inventoryId <= 0) {
                    throw new RuntimeException(
                        'El inventario es obligatorio.'
                    );
                }

                if ($productId <= 0) {
                    throw new RuntimeException(
                        'El producto es obligatorio.'
                    );
                }

                /*
             * Validar inventario y aislamiento
             * por entorno.
             */
                $stmt = $db->prepare(
                    '
                SELECT id
                FROM inventarios
                WHERE id = :id
                  AND entorno_id = :entorno
                  AND activo = 1
                LIMIT 1
                '
                );

                $stmt->execute([
                    'id' => $inventoryId,
                    'entorno' => $environmentId,
                ]);

                if (!$stmt->fetchColumn()) {
                    throw new RuntimeException(
                        'Inventario no válido para el entorno.'
                    );
                }

                /*
             * Validar producto.
             */
                $stmt = $db->prepare(
                    '
                SELECT id
                FROM productos
                WHERE id = :id
                  AND activo = 1
                  AND deleted_at IS NULL
                LIMIT 1
                '
                );

                $stmt->execute([
                    'id' => $productId,
                ]);

                if (!$stmt->fetchColumn()) {
                    throw new RuntimeException(
                        'Producto no válido.'
                    );
                }

                $min = $this->nullableDecimal(
                    $minimum,
                    'El stock mínimo no es válido.'
                );

                $max = $this->nullableDecimal(
                    $maximum,
                    'El stock máximo no es válido.'
                );

                if ($min !== null && $min < 0) {
                    throw new RuntimeException(
                        'El stock mínimo no puede ser negativo.'
                    );
                }

                if ($max !== null && $max < 0) {
                    throw new RuntimeException(
                        'El stock máximo no puede ser negativo.'
                    );
                }

                if (
                    $min !== null
                    && $max !== null
                    && $max < $min
                ) {
                    throw new RuntimeException(
                        'El stock máximo no puede ser menor que el stock mínimo.'
                    );
                }

                /*
             * Guardamos los valores anteriores
             * para auditoría.
             */
                $stmt = $db->prepare(
                    '
                SELECT
                    stock_minimo,
                    stock_maximo
                FROM inventario_productos
                WHERE inventario_id = :inventario
                  AND producto_id = :producto
                LIMIT 1
                '
                );

                $stmt->execute([
                    'inventario' => $inventoryId,
                    'producto' => $productId,
                ]);

                $before = $stmt->fetch();

                /*
             * Si todavía no existe asociación entre
             * inventario y producto, se crea.
             */
                $stmt = $db->prepare(
                    '
                INSERT INTO inventario_productos
                (
                    inventario_id,
                    producto_id,
                    stock_minimo,
                    stock_maximo,
                    activo
                )
                VALUES
                (
                    :inventario,
                    :producto,
                    :minimo,
                    :maximo,
                    1
                )
                ON DUPLICATE KEY UPDATE
                    stock_minimo =
                        VALUES(stock_minimo),
                    stock_maximo =
                        VALUES(stock_maximo),
                    activo = 1
                '
                );

                $stmt->execute([
                    'inventario' => $inventoryId,
                    'producto' => $productId,
                    'minimo' => $min,
                    'maximo' => $max,
                ]);

                (new AuditService())->log(
                    $createdBy,
                    $environmentId,
                    'INVENTARIO',
                    'CONFIGURAR_STOCK',
                    'inventario_productos',
                    null,
                    $before ?: null,
                    [
                        'inventario_id' =>
                        $inventoryId,

                        'producto_id' =>
                        $productId,

                        'stock_minimo' =>
                        $min,

                        'stock_maximo' =>
                        $max,
                    ]
                );
            }
        );
    }

    private function nullableDecimal(
        mixed $value,
        string $errorMessage
    ): ?float {
        if (
            $value === null
            || trim((string) $value) === ''
        ) {
            return null;
        }

        if (!is_numeric($value)) {
            throw new RuntimeException(
                $errorMessage
            );
        }

        $number = (float) $value;

        if (!is_finite($number)) {
            throw new RuntimeException(
                $errorMessage
            );
        }

        return $number;
    }

    public function stockStatus(
        int $inventoryId,
        int $productId,
        int $environmentId
    ): array {
        $db = Database::connection();

        $stmt = $db->prepare(
            '
        SELECT
            i.id AS inventario_id,
            ip.producto_id,
            ip.stock_minimo,
            ip.stock_maximo,
            COALESCE(
                SUM(
                    tm.factor
                    * mi.cantidad
                ),
                0
            ) AS stock
        FROM inventarios i

        JOIN inventario_productos ip
          ON ip.inventario_id = i.id

        LEFT JOIN movimientos_inventario mi
          ON mi.inventario_id = i.id
         AND mi.producto_id =
             ip.producto_id

        LEFT JOIN tipos_movimiento_inventario tm
          ON tm.id =
             mi.tipo_movimiento_id

        WHERE i.id = :inventario
          AND i.entorno_id = :entorno
          AND i.activo = 1
          AND ip.producto_id = :producto
          AND ip.activo = 1

        GROUP BY
            i.id,
            ip.producto_id,
            ip.stock_minimo,
            ip.stock_maximo
        '
        );

        $stmt->execute([
            'inventario' => $inventoryId,
            'entorno' => $environmentId,
            'producto' => $productId,
        ]);

        $row = $stmt->fetch();

        if (!$row) {
            throw new RuntimeException(
                'Producto no asociado al inventario.'
            );
        }

        $stock = (float) $row['stock'];

        $minimum =
            $row['stock_minimo'] !== null
            ? (float) $row['stock_minimo']
            : null;

        $maximum =
            $row['stock_maximo'] !== null
            ? (float) $row['stock_maximo']
            : null;

        /*
     * Prioridad:
     *
     * 1. SIN_STOCK
     * 2. BAJO_MINIMO
     * 3. SOBRE_MAXIMO
     * 4. NORMAL
     */
        if ($stock <= 0) {
            $status = 'SIN_STOCK';
        } elseif (
            $minimum !== null
            && $stock < $minimum
        ) {
            $status = 'BAJO_MINIMO';
        } elseif (
            $maximum !== null
            && $stock > $maximum
        ) {
            $status = 'SOBRE_MAXIMO';
        } else {
            $status = 'NORMAL';
        }

        return [
            'inventario_id' =>
            (int) $row['inventario_id'],

            'producto_id' =>
            (int) $row['producto_id'],

            'stock' => $stock,

            'stock_minimo' => $minimum,

            'stock_maximo' => $maximum,

            'estado' => $status,
        ];
    }

    public function transfer(
        array $data,
        int $environmentId,
        int $createdBy
    ): array {
        return Database::transaction(
            function (PDO $db) use (
                $data,
                $environmentId,
                $createdBy
            ): array {
                $sourceInventoryId = (int) (
                    $data['inventario_origen_id']
                    ?? 0
                );

                $destinationInventoryId = (int) (
                    $data['inventario_destino_id']
                    ?? 0
                );

                $productId = (int) (
                    $data['producto_id']
                    ?? 0
                );

                $lotId = !empty($data['lote_id'])
                    ? (int) $data['lote_id']
                    : null;

                $quantityRaw =
                    $data['cantidad']
                    ?? null;

                if ($sourceInventoryId <= 0) {
                    throw new RuntimeException(
                        'El inventario de origen es obligatorio.'
                    );
                }

                if ($destinationInventoryId <= 0) {
                    throw new RuntimeException(
                        'El inventario de destino es obligatorio.'
                    );
                }

                if (
                    $sourceInventoryId
                    === $destinationInventoryId
                ) {
                    throw new RuntimeException(
                        'El inventario de origen y destino deben ser diferentes.'
                    );
                }

                if ($productId <= 0) {
                    throw new RuntimeException(
                        'El producto es obligatorio.'
                    );
                }

                if (
                    $quantityRaw === null
                    || $quantityRaw === ''
                    || !is_numeric($quantityRaw)
                ) {
                    throw new RuntimeException(
                        'La cantidad no es válida.'
                    );
                }

                $quantity =
                    (float) $quantityRaw;

                if (
                    !is_finite($quantity)
                    || $quantity <= 0
                ) {
                    throw new RuntimeException(
                        'La cantidad debe ser mayor que cero.'
                    );
                }

                /*
             * Validamos ambos inventarios ANTES
             * de registrar cualquier movimiento.
             */
                $stmt = $db->prepare(
                    '
                SELECT id
                FROM inventarios
                WHERE id = :id
                  AND entorno_id = :entorno
                  AND activo = 1
                LIMIT 1
                '
                );

                $stmt->execute([
                    'id' =>
                    $sourceInventoryId,

                    'entorno' =>
                    $environmentId,
                ]);

                if (!$stmt->fetchColumn()) {
                    throw new RuntimeException(
                        'Inventario de origen no válido para el entorno.'
                    );
                }

                $stmt->execute([
                    'id' =>
                    $destinationInventoryId,

                    'entorno' =>
                    $environmentId,
                ]);

                if (!$stmt->fetchColumn()) {
                    throw new RuntimeException(
                        'Inventario de destino no válido para el entorno.'
                    );
                }

                /*
             * Obtenemos los tipos reales del catálogo.
             */
                $stmt = $db->prepare(
                    '
                SELECT id
                FROM tipos_movimiento_inventario
                WHERE codigo = :codigo
                  AND factor = :factor
                LIMIT 1
                '
                );

                $stmt->execute([
                    'codigo' =>
                    'TRANSFERENCIA_SALIDA',
                    'factor' => -1,
                ]);

                $exitTypeId =
                    (int) $stmt->fetchColumn();

                if ($exitTypeId <= 0) {
                    throw new RuntimeException(
                        'No existe el tipo de movimiento TRANSFERENCIA_SALIDA.'
                    );
                }

                $stmt->execute([
                    'codigo' =>
                    'TRANSFERENCIA_ENTRADA',
                    'factor' => 1,
                ]);

                $entryTypeId =
                    (int) $stmt->fetchColumn();

                if ($entryTypeId <= 0) {
                    throw new RuntimeException(
                        'No existe el tipo de movimiento TRANSFERENCIA_ENTRADA.'
                    );
                }

                $observations = trim(
                    (string) (
                        $data['observaciones']
                        ?? ''
                    )
                );

                /*
             * movement() ya contiene las reglas de:
             *
             * - producto activo
             * - lotes
             * - vencimientos
             * - stock disponible
             * - aislamiento por entorno
             * - auditoría
             *
             * Database::transaction soporta
             * transacciones anidadas mediante
             * SAVEPOINT.
             */

                $exitId = $this->movement(
                    [
                        'inventario_id' =>
                        $sourceInventoryId,

                        'producto_id' =>
                        $productId,

                        'lote_id' =>
                        $lotId,

                        'tipo_movimiento_id' =>
                        $exitTypeId,

                        'cantidad' =>
                        $quantity,

                        'referencia_tipo' =>
                        'TRANSFERENCIA',

                        'observaciones' =>
                        $observations !== ''
                            ? $observations
                            : 'Transferencia entre inventarios',
                    ],
                    $environmentId,
                    $createdBy
                );

                $entryId = $this->movement(
                    [
                        'inventario_id' =>
                        $destinationInventoryId,

                        'producto_id' =>
                        $productId,

                        'lote_id' =>
                        $lotId,

                        'tipo_movimiento_id' =>
                        $entryTypeId,

                        'cantidad' =>
                        $quantity,

                        'referencia_tipo' =>
                        'TRANSFERENCIA',

                        'observaciones' =>
                        $observations !== ''
                            ? $observations
                            : 'Transferencia entre inventarios',
                    ],
                    $environmentId,
                    $createdBy
                );

                /*
             * Enlazamos ambos movimientos.
             */
                $stmt = $db->prepare(
                    '
                UPDATE movimientos_inventario
                SET referencia_tipo =
                        :tipo,
                    referencia_id =
                        :referencia
                WHERE id = :id
                '
                );

                $stmt->execute([
                    'tipo' =>
                    'TRANSFERENCIA',

                    'referencia' =>
                    $entryId,

                    'id' =>
                    $exitId,
                ]);

                $stmt->execute([
                    'tipo' =>
                    'TRANSFERENCIA',

                    'referencia' =>
                    $exitId,

                    'id' =>
                    $entryId,
                ]);

                /*
             * Auditoría de alto nivel.
             *
             * Además de los dos MOVIMIENTO que
             * movement() ya registra, queda una
             * entrada que representa la operación
             * completa.
             */
                (new AuditService())->log(
                    $createdBy,
                    $environmentId,
                    'INVENTARIO',
                    'TRANSFERENCIA',
                    'movimientos_inventario',
                    $exitId,
                    null,
                    [
                        'movimiento_salida_id' =>
                        $exitId,

                        'movimiento_entrada_id' =>
                        $entryId,

                        'inventario_origen_id' =>
                        $sourceInventoryId,

                        'inventario_destino_id' =>
                        $destinationInventoryId,

                        'producto_id' =>
                        $productId,

                        'lote_id' =>
                        $lotId,

                        'cantidad' =>
                        $quantity,
                    ]
                );

                return [
                    'movimiento_salida_id' =>
                    $exitId,

                    'movimiento_entrada_id' =>
                    $entryId,
                ];
            }
        );
    }

    public function consumeClinical(
        array $data,
        int $environmentId,
        int $createdBy
    ): int {
        return Database::transaction(
            function (PDO $db) use (
                $data,
                $environmentId,
                $createdBy
            ): int {
                $inventoryId = (int) (
                    $data['inventario_id']
                    ?? 0
                );

                $productId = (int) (
                    $data['producto_id']
                    ?? 0
                );

                $lotId = !empty($data['lote_id'])
                    ? (int) $data['lote_id']
                    : null;

                $referenceType = strtoupper(
                    trim(
                        (string) (
                            $data['referencia_tipo']
                            ?? ''
                        )
                    )
                );

                $referenceId = (int) (
                    $data['referencia_id']
                    ?? 0
                );

                $quantityRaw =
                    $data['cantidad']
                    ?? null;

                if ($inventoryId <= 0) {
                    throw new RuntimeException(
                        'El inventario es obligatorio.'
                    );
                }

                if ($productId <= 0) {
                    throw new RuntimeException(
                        'El producto es obligatorio.'
                    );
                }

                if (
                    $quantityRaw === null
                    || $quantityRaw === ''
                    || !is_numeric($quantityRaw)
                ) {
                    throw new RuntimeException(
                        'La cantidad de consumo no es válida.'
                    );
                }

                $quantity = (float) $quantityRaw;

                if (
                    !is_finite($quantity)
                    || $quantity <= 0
                ) {
                    throw new RuntimeException(
                        'La cantidad de consumo debe ser mayor que cero.'
                    );
                }

                if ($referenceId <= 0) {
                    throw new RuntimeException(
                        'La referencia clínica es obligatoria.'
                    );
                }

                if (
                    !in_array(
                        $referenceType,
                        [
                            'MEDICAMENTO_APLICACION',
                            'VACUNACION',
                        ],
                        true
                    )
                ) {
                    throw new RuntimeException(
                        'Tipo de referencia clínica no válido.'
                    );
                }

                /*
             * Validamos que el producto físico realmente
             * represente el elemento clínico utilizado.
             */
                if (
                    $referenceType
                    === 'MEDICAMENTO_APLICACION'
                ) {
                    $this->assertMedicationProductMatch(
                        $db,
                        $productId,
                        $referenceId,
                        $environmentId
                    );
                } else {
                    $this->assertVaccineProductMatch(
                        $db,
                        $productId,
                        $referenceId,
                        $environmentId
                    );
                }

                $stmt = $db->prepare(
                    '
                SELECT id
                FROM tipos_movimiento_inventario
                WHERE codigo = :codigo
                  AND factor = -1
                LIMIT 1
                '
                );

                $stmt->execute([
                    'codigo' =>
                    'CONSUMO_CLINICO',
                ]);

                $movementTypeId =
                    (int) $stmt->fetchColumn();

                if ($movementTypeId <= 0) {
                    throw new RuntimeException(
                        'No existe el tipo de movimiento CONSUMO_CLINICO.'
                    );
                }

                return $this->movement(
                    [
                        'inventario_id' =>
                        $inventoryId,

                        'producto_id' =>
                        $productId,

                        'lote_id' =>
                        $lotId,

                        'tipo_movimiento_id' =>
                        $movementTypeId,

                        'cantidad' =>
                        $quantity,

                        'referencia_tipo' =>
                        $referenceType,

                        'referencia_id' =>
                        $referenceId,

                        'observaciones' =>
                        trim(
                            (string) (
                                $data['observaciones']
                                ?? 'Consumo clínico'
                            )
                        ),
                    ],
                    $environmentId,
                    $createdBy
                );
            }
        );
    }

    private function assertMedicationProductMatch(
        PDO $db,
        int $productId,
        int $applicationId,
        int $environmentId
    ): void {
        $stmt = $db->prepare(
            '
        SELECT
            ma.id,
            tm.presentacion_id,
            p.id AS producto_id

        FROM medicamento_aplicaciones ma

        INNER JOIN tratamiento_medicamentos tm
            ON tm.id =
               ma.tratamiento_medicamento_id

        INNER JOIN tratamientos t
            ON t.id =
               tm.tratamiento_id

        INNER JOIN eventos_clinicos ec
            ON ec.id =
               t.evento_clinico_id

        INNER JOIN animales a
            ON a.id =
               ec.animal_id

        INNER JOIN productos p
            ON p.id = :producto

        WHERE ma.id = :aplicacion

          AND a.entorno_id = :entorno

          AND tm.presentacion_id
              IS NOT NULL

          AND p.farmaco_presentacion_id
              = tm.presentacion_id

          AND p.activo = 1

          AND p.deleted_at IS NULL

          AND ma.anulado_at IS NULL

        LIMIT 1
        '
        );

        $stmt->execute([
            'producto' =>
            $productId,

            'aplicacion' =>
            $applicationId,

            'entorno' =>
            $environmentId,
        ]);

        if (!$stmt->fetch()) {
            throw new RuntimeException(
                'El producto no corresponde al medicamento aplicado o la referencia clínica no pertenece al entorno.'
            );
        }
    }

    private function assertVaccineProductMatch(
        PDO $db,
        int $productId,
        int $vaccinationId,
        int $environmentId
    ): void {
        $stmt = $db->prepare(
            '
        SELECT
            v.id,
            v.vacuna_id,
            p.id AS producto_id

        FROM vacunaciones v

        INNER JOIN eventos_clinicos ec
            ON ec.id =
               v.evento_clinico_id

        INNER JOIN animales a
            ON a.id =
               ec.animal_id

        INNER JOIN productos p
            ON p.id = :producto

        WHERE v.id = :vacunacion

          AND a.entorno_id = :entorno

          AND p.vacuna_id =
              v.vacuna_id

          AND p.activo = 1

          AND p.deleted_at IS NULL

          AND ec.anulado_at IS NULL

        LIMIT 1
        '
        );

        $stmt->execute([
            'producto' =>
            $productId,

            'vacunacion' =>
            $vaccinationId,

            'entorno' =>
            $environmentId,
        ]);

        if (!$stmt->fetch()) {
            throw new RuntimeException(
                'El producto no corresponde a la vacuna aplicada o la referencia clínica no pertenece al entorno.'
            );
        }
    }

    public function reverseMovement(
        int $movementId,
        int $environmentId,
        int $createdBy,
        ?string $reason = null
    ): int {
        return Database::transaction(
            function (PDO $db) use (
                $movementId,
                $environmentId,
                $createdBy,
                $reason
            ): int {
                if ($movementId <= 0) {
                    throw new RuntimeException(
                        'El movimiento a revertir es obligatorio.'
                    );
                }

                /*
             * Recuperamos el movimiento original
             * junto con su factor y entorno.
             */
                $stmt = $db->prepare(
                    '
                SELECT
                    mi.id,
                    mi.inventario_id,
                    mi.producto_id,
                    mi.lote_id,
                    mi.tipo_movimiento_id,
                    mi.cantidad,
                    mi.costo_unitario,
                    mi.referencia_tipo,
                    mi.referencia_id,

                    tm.codigo AS tipo_codigo,
                    tm.factor,

                    i.entorno_id

                FROM movimientos_inventario mi

                INNER JOIN tipos_movimiento_inventario tm
                    ON tm.id =
                       mi.tipo_movimiento_id

                INNER JOIN inventarios i
                    ON i.id =
                       mi.inventario_id

                WHERE mi.id = :movimiento

                LIMIT 1

                FOR UPDATE
                '
                );

                $stmt->execute([
                    'movimiento' =>
                    $movementId,
                ]);

                $original =
                    $stmt->fetch();

                if (!$original) {
                    throw new RuntimeException(
                        'Movimiento de inventario no encontrado.'
                    );
                }

                /*
             * Aislamiento por entorno.
             */
                if (
                    (int) $original['entorno_id']
                    !== $environmentId
                ) {
                    throw new RuntimeException(
                        'El movimiento no pertenece al entorno actual.'
                    );
                }

                /*
             * Una transferencia no se debe revertir
             * por un solo lado.
             */
                if (
                    in_array(
                        $original['tipo_codigo'],
                        [
                            'TRANSFERENCIA_ENTRADA',
                            'TRANSFERENCIA_SALIDA',
                        ],
                        true
                    )
                ) {
                    throw new RuntimeException(
                        'Las transferencias deben revertirse mediante el flujo de reverso de transferencias.'
                    );
                }

                /*
             * Un movimiento compensatorio tampoco
             * debe utilizarse como movimiento original
             * para crear cadenas de reversos.
             */
                if (
                    $original['referencia_tipo']
                    === 'REVERSO_INVENTARIO'
                ) {
                    throw new RuntimeException(
                        'No se puede revertir un movimiento que ya es un reverso.'
                    );
                }

                /*
             * Evitamos doble reverso.
             */
                $stmt = $db->prepare(
                    '
                SELECT id
                FROM movimientos_inventario
                WHERE referencia_tipo =
                      :tipo
                  AND referencia_id =
                      :referencia
                LIMIT 1
                '
                );

                $stmt->execute([
                    'tipo' =>
                    'REVERSO_INVENTARIO',

                    'referencia' =>
                    $movementId,
                ]);

                if ($stmt->fetchColumn()) {
                    throw new RuntimeException(
                        'El movimiento ya fue revertido.'
                    );
                }

                $factor =
                    (int) $original['factor'];

                /*
             * Movimiento negativo:
             * devolver existencias.
             *
             * Movimiento positivo:
             * retirar existencias.
             */
                $reverseCode =
                    $factor < 0
                    ? 'AJUSTE_POSITIVO'
                    : 'AJUSTE_NEGATIVO';

                $stmt = $db->prepare(
                    '
                SELECT id
                FROM tipos_movimiento_inventario
                WHERE codigo = :codigo
                  AND factor = :factor
                LIMIT 1
                '
                );

                $stmt->execute([
                    'codigo' =>
                    $reverseCode,

                    'factor' =>
                    $factor < 0
                        ? 1
                        : -1,
                ]);

                $reverseTypeId =
                    (int) $stmt->fetchColumn();

                if ($reverseTypeId <= 0) {
                    throw new RuntimeException(
                        'No existe el tipo de movimiento requerido para realizar el reverso.'
                    );
                }

                $reason = trim(
                    (string) $reason
                );

                if ($reason === '') {
                    $reason =
                        'Reverso del movimiento #'
                        . $movementId;
                }

                /*
             * Reutilizamos movement().
             *
             * Así conserva todas las reglas:
             * - entorno
             * - producto
             * - lote
             * - stock
             * - vencimiento
             * - auditoría
             */
                $reverseId =
                    $this->movement(
                        [
                            'inventario_id' =>
                            (int) $original['inventario_id'],

                            'producto_id' =>
                            (int) $original['producto_id'],

                            'lote_id' =>
                            $original['lote_id'] !== null
                                ? (int) $original['lote_id']
                                : null,

                            'tipo_movimiento_id' =>
                            $reverseTypeId,

                            'cantidad' =>
                            (float) $original['cantidad'],

                            'costo_unitario' =>
                            $original['costo_unitario'],

                            'referencia_tipo' =>
                            'REVERSO_INVENTARIO',

                            'referencia_id' =>
                            $movementId,

                            'observaciones' =>
                            $reason,
                        ],
                        $environmentId,
                        $createdBy,

                        /*
         * Una compensación histórica puede retirar
         * existencias de un lote que actualmente
         * ya se encuentra vencido.
         */
                        true
                    );

                /*
             * Auditoría adicional de alto nivel.
             */
                (new AuditService())
                    ->log(
                        $createdBy,
                        $environmentId,
                        'INVENTARIO',
                        'REVERSAR_MOVIMIENTO',
                        'movimientos_inventario',
                        $reverseId,
                        null,
                        [
                            'movimiento_original_id'
                            => $movementId,

                            'movimiento_reverso_id'
                            => $reverseId,

                            'motivo'
                            => $reason,
                        ]
                    );

                return $reverseId;
            }
        );
    }

    public function reverseTransfer(
        int $movementId,
        int $environmentId,
        int $createdBy,
        ?string $reason = null
    ): array {
        return Database::transaction(
            function (PDO $db) use (
                $movementId,
                $environmentId,
                $createdBy,
                $reason
            ): array {
                if ($movementId <= 0) {
                    throw new RuntimeException(
                        'El movimiento de transferencia es obligatorio.'
                    );
                }

                /*
             * Bloqueamos el primer movimiento.
             */
                $stmt = $db->prepare(
                    '
                SELECT
                    mi.id,
                    mi.inventario_id,
                    mi.producto_id,
                    mi.lote_id,
                    mi.tipo_movimiento_id,
                    mi.cantidad,
                    mi.costo_unitario,
                    mi.referencia_tipo,
                    mi.referencia_id,

                    tm.codigo AS tipo_codigo,
                    tm.factor,

                    i.entorno_id

                FROM movimientos_inventario mi

                INNER JOIN tipos_movimiento_inventario tm
                    ON tm.id = mi.tipo_movimiento_id

                INNER JOIN inventarios i
                    ON i.id = mi.inventario_id

                WHERE mi.id = :movimiento

                LIMIT 1

                FOR UPDATE
                '
                );

                $stmt->execute([
                    'movimiento' => $movementId,
                ]);

                $first = $stmt->fetch();

                if (!$first) {
                    throw new RuntimeException(
                        'Movimiento de transferencia no encontrado.'
                    );
                }

                if (
                    (int) $first['entorno_id']
                    !== $environmentId
                ) {
                    throw new RuntimeException(
                        'La transferencia no pertenece al entorno actual.'
                    );
                }

                if (
                    !in_array(
                        $first['tipo_codigo'],
                        [
                            'TRANSFERENCIA_SALIDA',
                            'TRANSFERENCIA_ENTRADA',
                        ],
                        true
                    )
                ) {
                    throw new RuntimeException(
                        'El movimiento indicado no pertenece a una transferencia.'
                    );
                }

                if (
                    $first['referencia_tipo']
                    !== 'TRANSFERENCIA'
                    || (int) $first['referencia_id'] <= 0
                ) {
                    throw new RuntimeException(
                        'La transferencia no posee una referencia válida a su movimiento relacionado.'
                    );
                }

                $pairId =
                    (int) $first['referencia_id'];

                $firstLockId =
                    min(
                        (int) $first['id'],
                        $pairId
                    );

                $secondLockId =
                    max(
                        (int) $first['id'],
                        $pairId
                    );

                $stmt = $db->prepare(
                    '
    SELECT
        mi.id,
        mi.inventario_id,
        mi.producto_id,
        mi.lote_id,
        mi.tipo_movimiento_id,
        mi.cantidad,
        mi.costo_unitario,
        mi.referencia_tipo,
        mi.referencia_id,

        tm.codigo AS tipo_codigo,
        tm.factor,

        i.entorno_id

    FROM movimientos_inventario mi

    INNER JOIN tipos_movimiento_inventario tm
        ON tm.id =
           mi.tipo_movimiento_id

    INNER JOIN inventarios i
        ON i.id =
           mi.inventario_id

    WHERE mi.id IN (
        :primero,
        :segundo
    )

    ORDER BY mi.id

    FOR UPDATE
    '
                );

                $stmt->execute([
                    'primero' =>
                    $firstLockId,

                    'segundo' =>
                    $secondLockId,
                ]);

                $lockedRows =
                    $stmt->fetchAll();

                if (count($lockedRows) !== 2) {
                    throw new RuntimeException(
                        'No se pudieron bloquear ambos movimientos de la transferencia.'
                    );
                }

                $lockedById = [];

                foreach ($lockedRows as $row) {
                    $lockedById[(int) $row['id']] = $row;
                }

                $first =
                    $lockedById[$movementId]
                    ?? null;

                $second =
                    $lockedById[$pairId]
                    ?? null;

                if (!$first || !$second) {
                    throw new RuntimeException(
                        'No se pudo reconstruir el par de transferencia.'
                    );
                }

                if (
                    (int) $second['entorno_id']
                    !== $environmentId
                ) {
                    throw new RuntimeException(
                        'Los movimientos de la transferencia no pertenecen al mismo entorno.'
                    );
                }

                /*
             * La relación debe ser simétrica:
             *
             * A -> B
             * B -> A
             */
                if (
                    $second['referencia_tipo']
                    !== 'TRANSFERENCIA'
                    || (int) $second['referencia_id']
                    !== (int) $first['id']
                ) {
                    throw new RuntimeException(
                        'La relación entre los movimientos de transferencia es inconsistente.'
                    );
                }

                /*
             * Debemos tener exactamente una salida
             * y una entrada.
             */
                if (
                    $first['tipo_codigo']
                    === 'TRANSFERENCIA_SALIDA'
                ) {
                    $exit = $first;
                    $entry = $second;
                } else {
                    $entry = $first;
                    $exit = $second;
                }

                if (
                    $exit['tipo_codigo']
                    !== 'TRANSFERENCIA_SALIDA'
                    || (int) $exit['factor'] !== -1
                    || $entry['tipo_codigo']
                    !== 'TRANSFERENCIA_ENTRADA'
                    || (int) $entry['factor'] !== 1
                ) {
                    throw new RuntimeException(
                        'El par de movimientos no representa una transferencia válida.'
                    );
                }

                /*
             * Integridad del par.
             */
                if (
                    (int) $exit['producto_id']
                    !== (int) $entry['producto_id']
                ) {
                    throw new RuntimeException(
                        'Los movimientos de la transferencia no corresponden al mismo producto.'
                    );
                }

                $exitLot =
                    $exit['lote_id'] !== null
                    ? (int) $exit['lote_id']
                    : null;

                $entryLot =
                    $entry['lote_id'] !== null
                    ? (int) $entry['lote_id']
                    : null;

                if ($exitLot !== $entryLot) {
                    throw new RuntimeException(
                        'Los movimientos de la transferencia no corresponden al mismo lote.'
                    );
                }

                $exitQuantity =
                    (float) $exit['cantidad'];

                $entryQuantity =
                    (float) $entry['cantidad'];

                if (
                    abs(
                        $exitQuantity
                            - $entryQuantity
                    ) > 0.0001
                ) {
                    throw new RuntimeException(
                        'Los movimientos de la transferencia no tienen la misma cantidad.'
                    );
                }

                if (
                    (int) $exit['inventario_id']
                    === (int) $entry['inventario_id']
                ) {
                    throw new RuntimeException(
                        'Una transferencia debe involucrar dos inventarios diferentes.'
                    );
                }

                /*
             * Evitamos reversar dos veces.
             *
             * Verificamos ambos movimientos originales.
             */
                $stmt = $db->prepare(
                    '
                SELECT id
                FROM movimientos_inventario
                WHERE referencia_tipo =
                      :tipo
                  AND referencia_id IN (
                      :salida,
                      :entrada
                  )
                LIMIT 1
                '
                );

                $stmt->execute([
                    'tipo' =>
                    'REVERSO_TRANSFERENCIA',

                    'salida' =>
                    (int) $exit['id'],

                    'entrada' =>
                    (int) $entry['id'],
                ]);

                if ($stmt->fetchColumn()) {
                    throw new RuntimeException(
                        'La transferencia ya fue revertida.'
                    );
                }

                /*
             * Obtenemos los tipos compensatorios.
             */
                $stmt = $db->prepare(
                    '
                SELECT id
                FROM tipos_movimiento_inventario
                WHERE codigo = :codigo
                  AND factor = :factor
                LIMIT 1
                '
                );

                $stmt->execute([
                    'codigo' =>
                    'AJUSTE_NEGATIVO',

                    'factor' => -1,
                ]);

                $negativeTypeId =
                    (int) $stmt->fetchColumn();

                if ($negativeTypeId <= 0) {
                    throw new RuntimeException(
                        'No existe el tipo AJUSTE_NEGATIVO requerido para revertir la transferencia.'
                    );
                }

                $stmt->execute([
                    'codigo' =>
                    'AJUSTE_POSITIVO',

                    'factor' => 1,
                ]);

                $positiveTypeId =
                    (int) $stmt->fetchColumn();

                if ($positiveTypeId <= 0) {
                    throw new RuntimeException(
                        'No existe el tipo AJUSTE_POSITIVO requerido para revertir la transferencia.'
                    );
                }

                $reason = trim(
                    (string) $reason
                );

                if ($reason === '') {
                    $reason =
                        'Reverso de transferencia #'
                        . $exit['id']
                        . ' / #'
                        . $entry['id'];
                }

                /*
             * IMPORTANTE:
             *
             * Primero retiramos del destino.
             *
             * Esto obliga a que realmente exista
             * el stock que fue transferido.
             *
             * movement() validará también el lote.
             */
                $destinationReverseId =
                    $this->movement(
                        [
                            'inventario_id' =>
                            (int)
                            $entry['inventario_id'],

                            'producto_id' =>
                            (int)
                            $entry['producto_id'],

                            'lote_id' =>
                            $entryLot,

                            'tipo_movimiento_id' =>
                            $negativeTypeId,

                            'cantidad' =>
                            $entryQuantity,

                            'costo_unitario' =>
                            $entry['costo_unitario'],

                            'referencia_tipo' =>
                            'REVERSO_TRANSFERENCIA',

                            'referencia_id' =>
                            (int)
                            $entry['id'],

                            'observaciones' =>
                            $reason,
                        ],
                        $environmentId,
                        $createdBy
                    );

                /*
             * Si el retiro anterior fue válido,
             * devolvemos la misma cantidad al origen.
             */
                $sourceReverseId =
                    $this->movement(
                        [
                            'inventario_id' =>
                            (int)
                            $exit['inventario_id'],

                            'producto_id' =>
                            (int)
                            $exit['producto_id'],

                            'lote_id' =>
                            $exitLot,

                            'tipo_movimiento_id' =>
                            $positiveTypeId,

                            'cantidad' =>
                            $exitQuantity,

                            'costo_unitario' =>
                            $exit['costo_unitario'],

                            'referencia_tipo' =>
                            'REVERSO_TRANSFERENCIA',

                            'referencia_id' =>
                            (int)
                            $exit['id'],

                            'observaciones' =>
                            $reason,
                        ],
                        $environmentId,
                        $createdBy
                    );

                /*
             * Auditoría de alto nivel.
             */
                (new AuditService())
                    ->log(
                        $createdBy,
                        $environmentId,
                        'INVENTARIO',
                        'REVERSAR_TRANSFERENCIA',
                        'movimientos_inventario',
                        (int) $exit['id'],
                        null,
                        [
                            'movimiento_salida_original_id'
                            => (int) $exit['id'],

                            'movimiento_entrada_original_id'
                            => (int) $entry['id'],

                            'movimiento_reverso_origen_id'
                            => $sourceReverseId,

                            'movimiento_reverso_destino_id'
                            => $destinationReverseId,

                            'inventario_origen_id'
                            => (int)
                            $exit['inventario_id'],

                            'inventario_destino_id'
                            => (int)
                            $entry['inventario_id'],

                            'producto_id'
                            => (int)
                            $exit['producto_id'],

                            'lote_id'
                            => $exitLot,

                            'cantidad'
                            => $exitQuantity,

                            'motivo'
                            => $reason,
                        ]
                    );

                return [
                    'movimiento_salida_original_id'
                    => (int) $exit['id'],

                    'movimiento_entrada_original_id'
                    => (int) $entry['id'],

                    'movimiento_reverso_origen_id'
                    => $sourceReverseId,

                    'movimiento_reverso_destino_id'
                    => $destinationReverseId,
                ];
            }
        );
    }

    public function reverseClinicalConsumption(
        string $referenceType,
        int $referenceId,
        int $environmentId,
        int $createdBy,
        string $reason
    ): array {
        return Database::transaction(
            function (PDO $db) use (
                $referenceType,
                $referenceId,
                $environmentId,
                $createdBy,
                $reason
            ): array {
                $referenceType =
                    strtoupper(
                        trim($referenceType)
                    );
                if (
                    !in_array(
                        $referenceType,
                        [
                            'MEDICAMENTO_APLICACION',
                            'VACUNACION',
                        ],
                        true
                    )
                ) {
                    throw new RuntimeException(
                        'Tipo de referencia clínica no válido.'
                    );
                }
                if ($referenceId <= 0) {
                    throw new RuntimeException(
                        'La referencia clínica es obligatoria.'
                    );
                }
                $stmt = $db->prepare(
                    '
                SELECT
                    mi.id
                FROM movimientos_inventario mi
                INNER JOIN inventarios i
                    ON i.id =
                       mi.inventario_id
                INNER JOIN tipos_movimiento_inventario tm
                    ON tm.id =
                       mi.tipo_movimiento_id
                WHERE mi.referencia_tipo =
                      :referencia_tipo
                  AND mi.referencia_id =
                      :referencia_id
                  AND tm.codigo =
                      "CONSUMO_CLINICO"
                  AND i.entorno_id =
                      :entorno
                ORDER BY mi.id
                '
                );
                $stmt->execute([
                    'referencia_tipo' =>
                    $referenceType,
                    'referencia_id' =>
                    $referenceId,
                    'entorno' =>
                    $environmentId,
                ]);

                $movementIds =
                    array_map(
                        'intval',
                        $stmt->fetchAll(
                            PDO::FETCH_COLUMN
                        )
                    );
                $reversalIds = [];
                foreach ($movementIds as $movementId) {
                    $check = $db->prepare(
                        '
                        SELECT id
                        FROM movimientos_inventario
                        WHERE referencia_tipo =
                            "REVERSO_INVENTARIO"
                        AND referencia_id =
                            :movimiento
                        LIMIT 1
                        '
                    );
                    $check->execute([
                        'movimiento' =>
                        $movementId,
                    ]);
                    if ($check->fetchColumn()) {
                        continue;
                    }
                    $reversalIds[] =
                        $this->reverseMovement(
                            $movementId,
                            $environmentId,
                            $createdBy,
                            $reason
                        );
                }
                return $reversalIds;
            }
        );
    }
}
