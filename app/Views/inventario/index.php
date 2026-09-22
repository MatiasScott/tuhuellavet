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

    <!-- Formulario de producto -->

    <section class="card inventory-form-card">
        <div class="inventory-card-heading">
            <h3>Nuevo producto</h3>
            <p>Completa la información del producto.</p>
        </div>
        <form
            method="POST"
            action="<?= url('/inventario/productos') ?>">

            <?= csrf_field() ?>

            <div class="form-grid">
                <label>
                    <span>Código *</span>
                    <input
                        type="text"
                        name="codigo"
                        maxlength="80"
                        required>
                </label>
                <label>
                    <span>Nombre *</span>

                    <input
                        type="text"
                        name="nombre"
                        maxlength="180"
                        required>
                </label>
                <label>
                    <span>Categoría</span>
                    <select name="categoria_producto_id">
                        <option value="">
                            Sin categoría
                        </option>
                        <?php foreach ($productCategories as $category): ?>
                            <option value="<?= (int) $category['id'] ?>">
                                <?= e($category['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Unidad base *</span>
                    <select name="unidad_base_id" required>
                        <option value="">
                            Seleccione una unidad
                        </option>
                        <?php foreach ($units as $unit): ?>
                            <option value="<?= (int) $unit['id'] ?>">
                                <?= e($unit['simbolo'] . ' · ' . $unit['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="inventory-checkbox">
                    <input
                        type="checkbox"
                        name="controla_lote"
                        value="1">
                    <span>Controla lote</span>
                </label>
                <label class="inventory-checkbox">
                    <input
                        type="checkbox"
                        name="controla_vencimiento"
                        value="1">
                    <span>Controla vencimiento</span>
                </label>
                <label class="field-full">
                    <span>Descripción</span>
                    <textarea
                        name="descripcion"
                        rows="3"></textarea>
                </label>
            </div>
            <div class="form-grid">
                <label>
                    <span>Stock mínimo</span>
                    <input
                        type="number"
                        name="stock_minimo"
                        min="0"
                        step="0.0001"
                        placeholder="Ej. 5">
                </label>
                <label class="field-full">
                    <input
                        type="checkbox"
                        name="stock_maximo_sin_limite"
                        id="stock-maximo-sin-limite"
                        value="1"
                        checked>
                    Sin límite de stock máximo
                </label>
                <label id="stock-maximo-field" hidden>
                    <span>Stock máximo</span>
                    <input
                        type="number"
                        name="stock_maximo"
                        id="stock-maximo"
                        min="0"
                        step="0.0001"
                        placeholder="Ej. 100"
                        disabled>
                </label>
            </div>
            <?php /* Inventario de destino */ ?>
            <div class="form-grid">
                <label>
                    <span>Inventario</span>
                    <select name="inventario_id" required>
                        <option value="">Seleccione un inventario</option>
                        <?php foreach ($inventories as $inventory): ?>
                            <option value="<?= (int) $inventory['id'] ?>">
                                <?= htmlspecialchars(
                                    $inventory['nombre'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?php /* Datos del lote */ ?>
                <div
                    id="product-lot-fields"
                    class="form-grid field-full inventory-lot-fields"
                    hidden>
                    <label>
                        <span>Número de lote</span>
                        <input
                            type="text"
                            name="numero_lote"
                            id="product-lot-number"
                            maxlength="100">
                    </label>
                    <label>
                        <span>Fecha de fabricación</span>
                        <input
                            type="date"
                            name="fecha_fabricacion"
                            id="product-manufacture-date">
                    </label>
                    <label>
                        <span>Fecha de vencimiento</span>
                        <input
                            type="date"
                            name="fecha_vencimiento"
                            id="product-expiration-date">
                    </label>
                </div>

                <?php /* Existencias iniciales */ ?>
                <label>
                    <span>Cantidad inicial</span>
                    <input
                        type="number"
                        name="cantidad_inicial"
                        min="0"
                        step="0.0001"
                        value="0"
                        required>
                    <small>
                        Si es mayor que cero, se registrará
                        automáticamente un movimiento de entrada.
                    </small>
                </label>
            </div> <!-- .form-grid -->
            <div class="inventory-form-actions">
                <button
                    type="submit"
                    class="btn btn-primary">
                    ＋ Crear producto
                </button>
            </div>
        </form>
    </section>

    <!-- ==========================================
     REGISTRO DE LOTES
========================================== -->

    <section class="card inventory-form-card">
        <div class="inventory-card-heading">
            <h3>Agregar lote a producto existente</h3>
            <p>
                Registra un nuevo lote de un producto
                que ya existe en el catálogo.
            </p>
        </div>
        <form
            method="POST"
            action="<?= url('/inventario/lotes') ?>">
            <?= csrf_field() ?>
            <div class="form-grid">
                <!-- Producto -->
                <label class="field-full">
                    <span>Producto *</span>
                    <select
                        name="producto_id"
                        id="lot-product"
                        required>
                        <option value="">
                            Seleccione un producto
                        </option>
                        <?php foreach ($products as $product): ?>
                            <option
                                value="<?= (int) $product['id'] ?>"
                                data-controls-expiration="<?= (int) ($product['controla_vencimiento'] ?? 0) ?>">
                                <?= e($product['codigo'] . ' — ' . $product['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <!-- Número de lote -->
                <label>
                    <span>Número de lote *</span>
                    <input
                        type="text"
                        name="numero_lote"
                        maxlength="100"
                        placeholder="Ej. LOT-2026-001"
                        required>
                </label>

                <!-- Fecha de fabricación -->

                <label>
                    <span>Fecha de fabricación</span>
                    <input
                        type="date"
                        name="fecha_fabricacion"
                        id="lot-manufacture-date">
                </label>

                <!-- Fecha de vencimiento -->
                <label>
                    <span>Fecha de vencimiento</span>
                    <input
                        type="date"
                        name="fecha_vencimiento"
                        id="lot-expiration-date">
                    <small id="lot-expiration-help">
                        Obligatoria si el producto controla vencimiento.
                    </small>
                </label>
            </div>

            <!-- Acciones -->
            <div class="inventory-form-actions">
                <button
                    type="submit"
                    class="btn btn-primary">
                    ＋ Registrar lote
                </button>
            </div>
        </form>
    </section>

    <!-- Existencias -->

    <section class="card inventory-stock-section">
        <div class="inventory-card-heading">
            <h3>Existencias actuales</h3>
            <p>Productos asociados a los inventarios del entorno.</p>
        </div>
        <div class="cards-grid">
            <?php if (empty($stock)): ?>
                <div class="soft-panel">
                    <strong>Sin productos</strong>
                    <p>No hay existencias para mostrar.</p>
                </div>
            <?php else: ?>
                <?php foreach ($stock as $item): ?>
                    <div class="soft-panel">
                        <strong>
                            <?= e($item['producto'] ?? $item['nombre'] ?? '') ?>
                        </strong>
                        <p>
                            <?= e($item['stock'] ?? 0) ?>
                            <?= e($item['simbolo'] ?? '') ?>
                        </p>
                        <small>
                            <?= e($item['inventario'] ?? '') ?>
                        </small>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
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
     JAVASCRIPT
========================================== -->
<script src="<?= url('/assets/js/views/inventory.js') ?>" defer></script>