<?php
$activeTab = ($_GET['tab'] ?? 'productos') === 'movimientos'
    ? 'movimientos'
    : 'productos';
?>
<link rel="stylesheet" href="<?= url('/assets/css/views/inventario.css') ?>">

<!-- ==========================================
     ENCABEZADO
========================================== -->

<div class="page-heading">
    <div>
        <span class="eyebrow">Gestión</span>
        <h1>Inventario</h1>
        <p>Administración de productos, existencias y movimientos.</p>
    </div>
</div>


<!-- ==========================================
     MENSAJES
========================================== -->

<?php if (!empty($success)): ?>
    <div class="alert alert-success">
        <?= e($success) ?>
    </div>
<?php endif; ?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger">
        <?= e($error) ?>
    </div>
<?php endif; ?>


<!-- ==========================================
     NAVEGACIÓN
========================================== -->

<div
    class="inventory-tabs"
    data-active-tab="<?= e($activeTab) ?>">
    <button
        type="button"
        class="inventory-tab <?= $activeTab === 'productos' ? 'is-active' : '' ?>"
        data-inventory-tab="productos"
        aria-selected="<?= $activeTab === 'productos' ? 'true' : 'false' ?>">
        <span class="inventory-tab-icon">▣</span>
        <span>
            <strong>Productos</strong>
            <small>Catálogo y existencias</small>
        </span>
    </button>

    <button
        type="button"
        class="inventory-tab <?= $activeTab === 'movimientos' ? 'is-active' : '' ?>"
        data-inventory-tab="movimientos"
        aria-selected="<?= $activeTab === 'movimientos' ? 'true' : 'false' ?>">
        <span class="inventory-tab-icon">⇄</span>
        <span>
            <strong>Movimientos</strong>
            <small>Entradas, salidas y ajustes</small>
        </span>
    </button>
</div>


<!-- ==========================================
     PESTAÑA: PRODUCTOS
========================================== -->

<section
    class="inventory-panel"
    id="inventory-panel-productos"
    data-inventory-panel="productos"
    <?= $activeTab !== 'productos' ? 'hidden' : '' ?>>
    <div class="inventory-section-heading">
        <div>
            <h2>Gestión de productos</h2>
            <p>Registra productos y consulta sus existencias.</p>
        </div>
    </div>

    <!-- ==========================================
        PRODUCTOS REGISTRADOS
    ========================================== -->

    <section class="card inventory-stock-section">

        <div class="inventory-card-heading inventory-card-heading--actions">
            <div>
                <span class="eyebrow">Inventario</span>
                <h3>Productos registrados</h3>
                <p>Consulta, busca y administra los productos disponibles en el inventario.</p>
            </div>

            <div class="inventory-heading-actions">
                <button type="button" class="btn btn-secondary" data-stock-open>
                    Existencias actuales
                </button>
                <button type="button" class="btn btn-primary" data-product-create-open>
                    + Nuevo producto
                </button>
            </div>
        </div>

        <div class="inventory-toolbar">
            <div class="inventory-search">
                <span class="inventory-search-icon">⌕</span>
                <input
                    type="search"
                    id="inventory-product-search"
                    placeholder="Buscar por código, producto, descripción o unidad..."
                    autocomplete="off">
                <button
                    type="button"
                    class="inventory-search-clear"
                    id="inventory-product-search-clear"
                    aria-label="Limpiar búsqueda"
                    hidden>×</button>
            </div>
            <div class="inventory-search-result">
                <span id="inventory-product-count"><?= count($products) ?> productos</span>
            </div>
        </div>

        <div class="table-wrap">

            <table class="modern-table">

                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Producto</th>
                        <th>Unidad</th>
                        <th>Precio</th>
                        <th>Impuesto</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if (empty($products)): ?>

                        <tr>
                            <td colspan="6">
                                No existen productos registrados.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($products as $product): ?>

                            <?php
                            $minimumStock = '';
                            $maximumStock = '';

                            foreach ($stock as $stockItem) {
                                if (
                                    (int)$stockItem['producto_id']
                                    ===
                                    (int)$product['id']
                                ) {
                                    $minimumStock =
                                        $stockItem['stock_minimo']
                                        ?? '';

                                    $maximumStock =
                                        $stockItem['stock_maximo']
                                        ?? '';

                                    break;
                                }
                            }
                            ?>

                            <tr
                                data-product-row
                                data-search="<?= e(strtolower(
                                                    ($product['codigo'] ?? '') . ' ' .
                                                        ($product['nombre'] ?? '') . ' ' .
                                                        ($product['descripcion'] ?? '') . ' ' .
                                                        ($product['unidad'] ?? '')
                                                )) ?>">

                                <td>
                                    <?= e($product['codigo']) ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= e($product['nombre']) ?>
                                    </strong>

                                    <?php if (!empty($product['descripcion'])): ?>

                                        <div class="muted">
                                            <?= e($product['descripcion']) ?>
                                        </div>

                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= e($product['unidad'] ?? '') ?>
                                </td>

                                <td>
                                    <?php if ($product['precio_venta'] !== null): ?>

                                        $<?= number_format(
                                                (float)$product['precio_venta'],
                                                2
                                            ) ?>

                                    <?php else: ?>

                                        <span class="muted">
                                            Sin configurar
                                        </span>

                                    <?php endif; ?>
                                </td>

                                <td>

                                    <?php if ($product['impuesto_tarifa_id'] !== null): ?>

                                        <strong>
                                            <?= e(
                                                $product['impuesto_nombre']
                                                    ?? 'Impuesto'
                                            ) ?>
                                        </strong>

                                        <div class="muted">

                                            <?= number_format(
                                                (float)(
                                                    $product['impuesto_porcentaje']
                                                    ?? 0
                                                ),
                                                2
                                            ) ?>%

                                            ·

                                            <?= (int)$product['precio_incluye_impuesto'] === 1
                                                ? 'Incluido'
                                                : 'No incluido' ?>

                                        </div>

                                    <?php else: ?>

                                        <span class="muted">
                                            Sin configurar
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <td>

                                    <button
                                        type="button"
                                        class="btn btn-secondary btn-sm"

                                        data-product-edit

                                        data-id="<?= (int)$product['id'] ?>"

                                        data-codigo="<?= e(
                                                            $product['codigo']
                                                        ) ?>"

                                        data-nombre="<?= e(
                                                            $product['nombre']
                                                        ) ?>"

                                        data-descripcion="<?= e(
                                                                $product['descripcion']
                                                                    ?? ''
                                                            ) ?>"

                                        data-categoria-id="<?= e(
                                                                $product['categoria_producto_id']
                                                                    ?? ''
                                                            ) ?>"

                                        data-unidad-id="<?= e(
                                                            $product['unidad_base_id']
                                                                ?? ''
                                                        ) ?>"

                                        data-precio="<?= e(
                                                            $product['precio_venta']
                                                                ?? ''
                                                        ) ?>"

                                        data-impuesto-tarifa-id="<?= e(
                                                                        $product['impuesto_tarifa_id']
                                                                            ?? ''
                                                                    ) ?>"

                                        data-precio-incluye-impuesto="<?= (int)(
                                                                            $product['precio_incluye_impuesto']
                                                                            ?? 0
                                                                        ) ?>"

                                        data-controla-lote="<?= (int)(
                                                                $product['controla_lote']
                                                                ?? 0
                                                            ) ?>"

                                        data-controla-vencimiento="<?= (int)(
                                                                        $product['controla_vencimiento']
                                                                        ?? 0
                                                                    ) ?>"

                                        data-stock-minimo="<?= e(
                                                                $minimumStock
                                                            ) ?>"

                                        data-stock-maximo="<?= e(
                                                                $maximumStock
                                                            ) ?>"

                                        data-update-url="<?= e(
                                                                url(
                                                                    '/inventario/productos/'
                                                                        . (int)$product['id']
                                                                        . '/actualizar'
                                                                )
                                                            ) ?>">

                                        Editar

                                    </button>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>

                </tbody>

            </table>

        </div>

        <div class="inventory-empty-search" id="inventory-product-empty" hidden>
            <strong>No encontramos productos</strong>
            <p>Prueba con otro código, nombre o término de búsqueda.</p>
        </div>

    </section>

</section>


<!-- ==========================================
     PESTAÑA: MOVIMIENTOS
========================================== -->

<section
    class="inventory-panel"
    id="inventory-panel-movimientos"
    data-inventory-panel="movimientos"
    <?= $activeTab !== 'movimientos' ? 'hidden' : '' ?>>
    <div class="inventory-section-heading">
        <div>
            <h2>Gestión de movimientos</h2>
            <p>Registra entradas, salidas y ajustes de inventario.</p>
        </div>
    </div>
    <!-- Formulario de movimiento -->
    <section class="card inventory-form-card">
        <div class="inventory-card-heading">
            <h3>Registrar movimiento</h3>
            <p>Selecciona el producto y completa los datos del movimiento.</p>
        </div>
        <form
            method="POST"
            action="<?= url('/inventario/movimientos') ?>">
            <?= csrf_field() ?>
            <div class="form-grid">
                <label>
                    <span>Inventario *</span>
                    <select name="inventario_id" required>
                        <?php foreach ($inventories as $inventory): ?>
                            <option value="<?= (int) $inventory['id'] ?>">
                                <?= e($inventory['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Producto *</span>
                    <select
                        name="producto_id"
                        id="movement-product"
                        required>
                        <option value="">
                            Seleccione un producto
                        </option>
                        <?php foreach ($products as $product): ?>
                            <option
                                value="<?= (int) $product['id'] ?>"
                                data-controls-lot="<?= (int) ($product['controla_lote'] ?? 0) ?>"
                                data-controls-expiration="<?= (int) ($product['controla_vencimiento'] ?? 0) ?>">
                                <?= e($product['codigo'] . ' — ' . $product['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Tipo de movimiento *</span>
                    <select
                        name="tipo_movimiento_id"
                        required>
                        <?php foreach ($movementTypes as $type): ?>
                            <option value="<?= (int) $type['id'] ?>">
                                <?= e($type['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Cantidad *</span>
                    <input
                        type="number"
                        name="cantidad"
                        min="0.0001"
                        step="0.0001"
                        required>
                </label>
                <!-- Selector de lote -->
                <label
                    class="field-full"
                    id="movement-lot-field"
                    hidden>
                    <span>Lote</span>
                    <select
                        name="lote_id"
                        id="movement-lot">
                        <option value="">
                            Seleccione un lote
                        </option>
                        <?php foreach ($lots as $lot): ?>
                            <option
                                value="<?= (int) $lot['id'] ?>"
                                data-product-id="<?= (int) $lot['producto_id'] ?>"
                                data-expiration="<?= e($lot['fecha_vencimiento'] ?? '') ?>"
                                hidden>
                                <?= e($lot['numero_lote']) ?>
                                —
                                Vence:
                                <?= e($lot['fecha_vencimiento'] ?? 'Sin fecha') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small id="movement-lot-help"></small>
                </label>
                <label class="field-full">
                    <span>Observaciones</span>
                    <textarea
                        name="observaciones"
                        rows="3"></textarea>
                </label>
            </div>
            <div class="inventory-form-actions">
                <button
                    type="submit"
                    class="btn btn-primary">
                    Guardar movimiento
                </button>
            </div>
        </form>
    </section>

    <!-- Historial -->

    <section class="card inventory-history-section">
        <div class="inventory-card-heading">
            <h3>Historial de movimientos</h3>
            <p>Últimos movimientos registrados.</p>
        </div>
        <div class="table-wrap">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Producto</th>
                        <th>Tipo</th>
                        <th>Cantidad</th>
                        <th>Usuario</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($movements)): ?>
                        <tr>
                            <td colspan="5">
                                No existen movimientos registrados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($movements as $movement): ?>
                            <tr>
                                <td>
                                    <?= e($movement['fecha_movimiento']) ?>
                                </td>
                                <td>
                                    <?= e($movement['producto']) ?>
                                </td>
                                <td>
                                    <?= e($movement['tipo']) ?>
                                </td>
                                <td>
                                    <?= e($movement['cantidad']) ?>
                                </td>
                                <td>
                                    <?= e($movement['usuario']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</section>

<!-- ==========================================
     MODAL CREAR PRODUCTO
========================================== -->
<div class="catalog-modal" id="product-create-modal" aria-hidden="true">
    <div class="catalog-modal-backdrop" data-product-create-close></div>
    <div class="catalog-modal-dialog catalog-modal-dialog--large" role="dialog" aria-modal="true" aria-labelledby="product-create-title">
        <div class="catalog-modal-header">
            <div>
                <span class="eyebrow">Inventario</span>
                <h2 id="product-create-title">Nuevo producto</h2>
                <p>Registra la información general, comercial y de inventario.</p>
            </div>
            <button type="button" class="catalog-modal-close" data-product-create-close aria-label="Cerrar">×</button>
        </div>
        <div class="catalog-modal-body">
            <form method="POST" action="<?= url('/inventario/productos') ?>" id="product-create-form">
                <?= csrf_field() ?>

                <div class="inventory-form-section">
                    <div class="inventory-form-section-heading">
                        <strong>Información general</strong>
                        <span>Identificación y clasificación del producto.</span>
                    </div>
                    <div class="form-grid">
                        <label><span>Código *</span><input type="text" name="codigo" maxlength="80" required placeholder="Ej. VET-001"></label>
                        <label><span>Nombre *</span><input type="text" name="nombre" maxlength="180" required placeholder="Nombre del producto"></label>
                        <label>
                            <span>Categoría</span>
                            <select name="categoria_producto_id">
                                <option value="">Sin categoría</option>
                                <?php foreach ($productCategories as $category): ?>
                                    <option value="<?= (int)$category['id'] ?>"><?= e($category['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span>Unidad base *</span>
                            <select name="unidad_base_id" required>
                                <option value="">Seleccione una unidad</option>
                                <?php foreach ($units as $unit): ?>
                                    <option value="<?= (int)$unit['id'] ?>"><?= e($unit['simbolo'] . ' · ' . $unit['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field-full">
                            <span>Descripción</span>
                            <textarea name="descripcion" rows="3" placeholder="Descripción opcional del producto"></textarea>
                        </label>
                    </div>
                </div>

                <div class="inventory-form-section">
                    <div class="inventory-form-section-heading">
                        <strong>Información comercial</strong>
                        <span>Precio de venta e impuesto aplicable.</span>
                    </div>
                    <div class="form-grid">
                        <label><span>Precio de venta</span><input type="number" name="precio_venta" min="0" step="0.0001" placeholder="Ej. 12.50"></label>
                        <label>
                            <span>Impuesto</span>
                            <select name="impuesto_tarifa_id" id="product-create-tax">
                                <option value="">Sin configurar</option>
                                <?php foreach ($taxRates as $taxRate): ?>
                                    <option value="<?= (int)$taxRate['id'] ?>">
                                        <?= e($taxRate['impuesto_nombre'] . ' · ' . $taxRate['nombre'] . ' · ' . number_format((float)$taxRate['porcentaje'], 2) . '%') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="inventory-checkbox field-full">
                            <input type="checkbox" name="precio_incluye_impuesto" id="product-create-price-tax" value="1">
                            <span>El precio de venta incluye impuesto</span>
                        </label>
                    </div>
                </div>

                <div class="inventory-form-section">
                    <div class="inventory-form-section-heading">
                        <strong>Control de inventario</strong>
                        <span>Define lotes, vencimientos y niveles mínimos y máximos.</span>
                    </div>
                    <div class="form-grid">
                        <label class="inventory-checkbox">
                            <input type="checkbox" name="controla_lote" id="product-create-controls-lot" value="1">
                            <span>Controla lote</span>
                        </label>
                        <label class="inventory-checkbox">
                            <input type="checkbox" name="controla_vencimiento" id="product-create-controls-expiration" value="1">
                            <span>Controla vencimiento</span>
                        </label>
                        <label><span>Stock mínimo</span><input type="number" name="stock_minimo" min="0" step="0.0001" value="0"></label>
                        <label class="inventory-checkbox">
                            <input type="checkbox" name="stock_maximo_sin_limite" id="stock-maximo-sin-limite" value="1" checked>
                            <span>Sin límite de stock máximo</span>
                        </label>
                        <label id="stock-maximo-field" hidden>
                            <span>Stock máximo</span>
                            <input type="number" name="stock_maximo" id="stock-maximo" min="0" step="0.0001" disabled>
                        </label>
                        <label>
                            <span>Inventario *</span>
                            <select name="inventario_id" required>
                                <option value="">Seleccione un inventario</option>
                                <?php foreach ($inventories as $inventory): ?>
                                    <option value="<?= (int)$inventory['id'] ?>"><?= e($inventory['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label><span>Cantidad inicial</span><input type="number" name="cantidad_inicial" min="0" step="0.0001" value="0" required></label>
                    </div>
                </div>

                <div id="product-lot-fields" class="inventory-lot-fields" hidden>
                    <div class="inventory-form-section-heading field-full">
                        <strong>Datos del lote inicial</strong>
                        <span>Completa estos datos cuando el producto controle lotes.</span>
                    </div>
                    <label><span>Número de lote</span><input type="text" name="numero_lote" id="product-lot-number" maxlength="100"></label>
                    <label><span>Fecha de fabricación</span><input type="date" name="fecha_fabricacion" id="product-manufacture-date"></label>
                    <label><span>Fecha de vencimiento</span><input type="date" name="fecha_vencimiento" id="product-expiration-date"></label>
                </div>

                <div class="inventory-form-actions">
                    <button type="button" class="btn btn-secondary" data-product-create-close>Cancelar</button>
                    <button type="submit" class="btn btn-primary">+ Crear producto</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==========================================
     MODAL EXISTENCIAS ACTUALES
========================================== -->
<div class="catalog-modal" id="stock-modal" aria-hidden="true">
    <div class="catalog-modal-backdrop" data-stock-close></div>
    <div class="catalog-modal-dialog catalog-modal-dialog--wide" role="dialog" aria-modal="true" aria-labelledby="stock-modal-title">
        <div class="catalog-modal-header">
            <div>
                <span class="eyebrow">Inventario</span>
                <h2 id="stock-modal-title">Existencias actuales</h2>
                <p>Consulta el stock disponible y sus niveles mínimos y máximos.</p>
            </div>
            <button type="button" class="catalog-modal-close" data-stock-close aria-label="Cerrar">×</button>
        </div>
        <div class="catalog-modal-body">
            <div class="inventory-toolbar">
                <div class="inventory-search">
                    <span class="inventory-search-icon">⌕</span>
                    <input type="search" id="inventory-stock-search" placeholder="Buscar por código, producto o inventario..." autocomplete="off">
                    <button type="button" class="inventory-search-clear" id="inventory-stock-search-clear" aria-label="Limpiar búsqueda" hidden>×</button>
                </div>
                <div class="inventory-search-result"><span id="inventory-stock-count"><?= count($stock) ?> registros</span></div>
            </div>

            <div class="table-wrap">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Inventario</th>
                            <th>Existencia</th>
                            <th>Mínimo</th>
                            <th>Máximo</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($stock)): ?>
                            <tr>
                                <td colspan="7">No existen existencias registradas.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($stock as $item): ?>
                                <?php
                                $currentStock = (float)($item['stock'] ?? 0);
                                $minimumStockValue = $item['stock_minimo'] !== null ? (float)$item['stock_minimo'] : null;
                                $isLowStock = $minimumStockValue !== null && $currentStock <= $minimumStockValue;
                                ?>
                                <tr
                                    data-stock-row
                                    data-search="<?= e(strtolower(
                                                        ($item['codigo'] ?? '') . ' ' .
                                                            ($item['producto'] ?? '') . ' ' .
                                                            ($item['inventario'] ?? '') . ' ' .
                                                            ($item['simbolo'] ?? '')
                                                    )) ?>">
                                    <td><?= e($item['codigo'] ?? '') ?></td>
                                    <td><strong><?= e($item['producto'] ?? '') ?></strong></td>
                                    <td><?= e($item['inventario'] ?? '') ?></td>
                                    <td><strong><?= e((string)($item['stock'] ?? '0')) ?> <?= e($item['simbolo'] ?? '') ?></strong></td>
                                    <td><?= $item['stock_minimo'] !== null ? e((string)$item['stock_minimo']) : '—' ?></td>
                                    <td><?= $item['stock_maximo'] !== null ? e((string)$item['stock_maximo']) : 'Sin límite' ?></td>
                                    <td>
                                        <?php if ($isLowStock): ?>
                                            <span class="inventory-status inventory-status--warning">Stock bajo</span>
                                        <?php else: ?>
                                            <span class="inventory-status inventory-status--ok">Disponible</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="inventory-empty-search" id="inventory-stock-empty" hidden>
                <strong>No encontramos existencias</strong>
                <p>Prueba con otro código, producto o inventario.</p>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================
     MODAL EDITAR PRODUCTO
========================================== -->

<div
    class="catalog-modal"
    id="product-edit-modal"
    aria-hidden="true">

    <div
        class="catalog-modal-backdrop"
        data-product-edit-close>
    </div>

    <div class="catalog-modal-dialog">

        <div class="catalog-modal-header">

            <div>
                <span class="eyebrow">
                    Inventario
                </span>

                <h2>Editar producto</h2>

                <p>
                    Modifica la información general y
                    comercial del producto.
                </p>
            </div>

            <button
                type="button"
                class="catalog-modal-close"
                data-product-edit-close>
                ×
            </button>

        </div>

        <div class="catalog-modal-body">

            <form
                method="POST"
                id="product-edit-form">

                <?= csrf_field() ?>

                <div class="form-grid">

                    <label>
                        <span>Código *</span>

                        <input
                            type="text"
                            name="codigo"
                            id="product-edit-code"
                            maxlength="80"
                            required>
                    </label>


                    <label>
                        <span>Nombre *</span>

                        <input
                            type="text"
                            name="nombre"
                            id="product-edit-name"
                            maxlength="180"
                            required>
                    </label>


                    <label>
                        <span>Categoría</span>

                        <select
                            name="categoria_producto_id"
                            id="product-edit-category">

                            <option value="">
                                Sin categoría
                            </option>

                            <?php foreach ($productCategories as $category): ?>

                                <option value="<?= (int)$category['id'] ?>">
                                    <?= e($category['nombre']) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>
                    </label>


                    <label>
                        <span>Unidad base *</span>

                        <select
                            name="unidad_base_id"
                            id="product-edit-unit"
                            required>

                            <?php foreach ($units as $unit): ?>

                                <option value="<?= (int)$unit['id'] ?>">
                                    <?= e(
                                        $unit['simbolo']
                                            . ' · '
                                            . $unit['nombre']
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>
                    </label>


                    <label>
                        <span>Precio de venta</span>

                        <input
                            type="number"
                            name="precio_venta"
                            id="product-edit-price"
                            min="0"
                            step="0.0001">
                    </label>


                    <label>
                        <span>Impuesto</span>

                        <select
                            name="impuesto_tarifa_id"
                            id="product-edit-tax">

                            <option value="">
                                Sin configurar
                            </option>

                            <?php foreach ($taxRates as $taxRate): ?>

                                <option value="<?= (int)$taxRate['id'] ?>">

                                    <?= e(
                                        $taxRate['impuesto_nombre']
                                            . ' · '
                                            . $taxRate['nombre']
                                            . ' · '
                                            . number_format(
                                                (float)$taxRate['porcentaje'],
                                                2
                                            )
                                            . '%'
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>
                    </label>


                    <label class="inventory-checkbox">

                        <input
                            type="checkbox"
                            name="precio_incluye_impuesto"
                            id="product-edit-price-tax"
                            value="1">

                        <span>
                            El precio de venta incluye impuesto
                        </span>

                    </label>


                    <label class="inventory-checkbox">

                        <input
                            type="checkbox"
                            name="controla_lote"
                            id="product-edit-controls-lot"
                            value="1">

                        <span>
                            Controla lote
                        </span>

                    </label>


                    <label class="inventory-checkbox">

                        <input
                            type="checkbox"
                            name="controla_vencimiento"
                            id="product-edit-controls-expiration"
                            value="1">

                        <span>
                            Controla vencimiento
                        </span>

                    </label>


                    <label>
                        <span>Stock mínimo</span>

                        <input
                            type="number"
                            name="stock_minimo"
                            id="product-edit-minimum-stock"
                            min="0"
                            step="0.0001">
                    </label>


                    <label class="inventory-checkbox">

                        <input
                            type="checkbox"
                            name="stock_maximo_sin_limite"
                            id="product-edit-unlimited-stock"
                            value="1">

                        <span>
                            Sin límite de stock máximo
                        </span>

                    </label>


                    <label id="product-edit-maximum-field">

                        <span>Stock máximo</span>

                        <input
                            type="number"
                            name="stock_maximo"
                            id="product-edit-maximum-stock"
                            min="0"
                            step="0.0001">

                    </label>


                    <label class="field-full">

                        <span>Descripción</span>

                        <textarea
                            name="descripcion"
                            id="product-edit-description"
                            rows="3"></textarea>

                    </label>

                </div>


                <div class="inventory-form-actions">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-product-edit-close>

                        Cancelar

                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary">

                        Guardar cambios

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- ==========================================
     JAVASCRIPT
========================================== -->
<script src="<?= url('/assets/js/views/inventory.js') ?>" defer></script>