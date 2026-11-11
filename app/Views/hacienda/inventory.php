<div class="page-heading"><div><span class="eyebrow">Hacienda Agusbella</span><h1>Inventario de insumos, fármacos y alimentos</h1><p>Catálogo exclusivo de Hacienda. Sin ventas ni facturación. Los consumos de raciones se confirman desde Alimentación.</p></div></div>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php if (can('inventario.crear')): ?>
<section class="card"><h2>Nuevo producto</h2>
    <form method="POST" action="<?= url('/inventario/productos') ?>"><?= csrf_field() ?>
        <div class="form-grid">
            <label><span>Código *</span><input name="codigo" required maxlength="80"></label>
            <label><span>Nombre *</span><input name="nombre" required maxlength="180"></label>
            <label><span>Categoría</span><select name="categoria_producto_id"><option value="">Sin categoría</option><?php foreach ($productCategories as $category): ?><option value="<?= (int) $category['id'] ?>"><?= e($category['nombre']) ?></option><?php endforeach; ?></select></label>
            <label><span>Unidad base *</span><select name="unidad_base_id" required><option value="">Seleccionar</option><?php foreach ($units as $unit): ?><option value="<?= (int) $unit['id'] ?>"><?= e($unit['nombre'] . ' (' . $unit['simbolo'] . ')') ?></option><?php endforeach; ?></select></label>
            <label><span>Costo de compra</span><input type="number" name="costo_compra" min="0" step="0.0001"></label>
            <label><span>Stock inicial</span><input type="number" name="cantidad_inicial" min="0" step="0.0001" value="0"></label>
            <label><span>Stock mínimo</span><input type="number" name="stock_minimo" min="0" step="0.0001"></label>
            <label><span>Stock máximo</span><input type="number" name="stock_maximo" min="0" step="0.0001"></label>
            <label><span>Stock máximo sin límite</span><input type="checkbox" name="stock_maximo_sin_limite" value="1" checked></label>
            <label><span>Controlar lotes</span><input type="checkbox" name="controla_lote" value="1"></label>
            <label><span>Controlar vencimiento</span><input type="checkbox" name="controla_vencimiento" value="1"></label>
            <label><span>Número de lote inicial</span><input name="numero_lote" maxlength="120"></label>
            <label><span>Fabricación</span><input type="date" name="fecha_fabricacion"></label>
            <label><span>Vencimiento</span><input type="date" name="fecha_vencimiento"></label>
            <label class="field-full"><span>Descripción</span><textarea name="descripcion"></textarea></label>
        </div><button class="btn btn-primary">Crear producto</button>
    </form>
</section>
<?php endif; ?>
<?php if (can('inventario.editar')): ?>
<section class="card"><h2>Editar productos de Hacienda</h2>
    <?php foreach ($products as $product): ?>
        <?php
        $productStock = array_values(array_filter($stock, static fn (array $row): bool => (int) $row['producto_id'] === (int) $product['id']));
        usort($productStock, static fn (array $a, array $b): int => (int) $a['inventario_id'] <=> (int) $b['inventario_id']);
        $limits = $productStock[0] ?? [];
        ?>
        <details class="soft-panel"><summary><?= e($product['codigo'] . ' · ' . $product['nombre']) ?></summary>
            <form method="POST" action="<?= url('/inventario/productos/' . $product['id'] . '/actualizar') ?>"><?= csrf_field() ?>
                <input type="hidden" name="unidad_base_id" value="<?= (int) $product['unidad_base_id'] ?>">
                <input type="hidden" name="controla_lote" value="<?= (int) $product['controla_lote'] ?>">
                <input type="hidden" name="controla_vencimiento" value="<?= (int) $product['controla_vencimiento'] ?>">
                <div class="form-grid">
                    <label><span>Código *</span><input name="codigo" maxlength="80" value="<?= e($product['codigo']) ?>" required></label>
                    <label><span>Nombre *</span><input name="nombre" maxlength="180" value="<?= e($product['nombre']) ?>" required></label>
                    <label><span>Categoría</span><select name="categoria_producto_id"><option value="">Sin categoría</option><?php foreach ($productCategories as $category): ?><option value="<?= (int) $category['id'] ?>" <?= (int) $product['categoria_producto_id'] === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['nombre']) ?></option><?php endforeach; ?></select></label>
                    <label><span>Costo de compra</span><input type="number" name="costo_compra" min="0" step="0.0001" value="<?= e($product['costo_compra'] ?? '') ?>"></label>
                    <label><span>Stock mínimo</span><input type="number" name="stock_minimo" min="0" step="0.0001" value="<?= e($limits['stock_minimo'] ?? '') ?>"></label>
                    <label><span>Stock máximo</span><input type="number" name="stock_maximo" min="0" step="0.0001" value="<?= e($limits['stock_maximo'] ?? '') ?>"></label>
                    <label><span>Stock máximo sin límite</span><input type="checkbox" name="stock_maximo_sin_limite" value="1" <?= !isset($limits['stock_maximo']) ? 'checked' : '' ?>></label>
                    <label class="field-full"><span>Descripción</span><textarea name="descripcion"><?= e($product['descripcion'] ?? '') ?></textarea></label>
                </div><button class="btn btn-primary">Actualizar producto</button>
            </form>
        </details>
    <?php endforeach; ?>
</section>
<?php endif; ?>
<?php if (can('inventario.crear')): ?>
<section class="card"><h2>Entrada o salida operativa</h2>
    <form method="POST" action="<?= url('/inventario/movimientos') ?>"><?= csrf_field() ?>
        <div class="form-grid">
            <label><span>Inventario *</span><select name="inventario_id" required><?php foreach ($inventories as $inventory): ?><option value="<?= (int) $inventory['id'] ?>"><?= e($inventory['nombre']) ?></option><?php endforeach; ?></select></label>
            <label><span>Producto *</span><select name="producto_id" required><option value="">Seleccionar</option><?php foreach ($products as $product): ?><option value="<?= (int) $product['id'] ?>"><?= e($product['codigo'] . ' · ' . $product['nombre'] . ' (' . $product['unidad'] . ')') ?></option><?php endforeach; ?></select></label>
            <label><span>Tipo *</span><select name="tipo_movimiento_id" required><?php foreach ($movementTypes as $type): ?><option value="<?= (int) $type['id'] ?>"><?= e($type['nombre']) ?></option><?php endforeach; ?></select></label>
            <label><span>Cantidad *</span><input type="number" name="cantidad" min="0.0001" step="0.0001" required></label>
            <label><span>Lote producto</span><select name="lote_id"><option value="">Sin lote</option><?php foreach ($lots as $lot): ?><option value="<?= (int) $lot['id'] ?>"><?= e($lot['producto_id'] . ' · ' . $lot['numero_lote']) ?></option><?php endforeach; ?></select></label>
            <label><span>Observaciones</span><textarea name="observaciones"></textarea></label>
        </div><button class="btn btn-primary">Registrar movimiento</button>
    </form>
</section>
<section class="card"><h2>Nuevo lote de producto</h2>
    <form method="POST" action="<?= url('/inventario/lotes') ?>"><?= csrf_field() ?>
        <div class="form-grid">
            <label><span>Producto *</span><select name="producto_id" required><?php foreach ($products as $product): ?><option value="<?= (int) $product['id'] ?>"><?= e($product['nombre']) ?></option><?php endforeach; ?></select></label>
            <label><span>Número de lote *</span><input name="numero_lote" maxlength="120" required></label>
            <label><span>Fabricación</span><input type="date" name="fecha_fabricacion"></label>
            <label><span>Vencimiento</span><input type="date" name="fecha_vencimiento"></label>
        </div><button class="btn btn-primary">Guardar lote</button>
    </form>
</section>
<?php endif; ?>
<section class="card"><h2>Existencias</h2><div class="table-wrap"><table class="modern-table"><thead><tr><th>Inventario</th><th>Producto</th><th>Stock</th><th>Mínimo</th><th>Máximo</th></tr></thead><tbody>
<?php if (!$stock): ?><tr><td colspan="5">Sin existencias registradas.</td></tr><?php endif; ?>
<?php foreach ($stock as $row): ?><tr><td><?= e($row['inventario']) ?></td><td><?= e($row['producto']) ?></td><td><?= e($row['stock'] . ' ' . $row['simbolo']) ?></td><td><?= e($row['stock_minimo'] ?? '') ?></td><td><?= e($row['stock_maximo'] ?? '') ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<section class="card"><h2>Movimientos</h2><div class="table-wrap"><table class="modern-table"><thead><tr><th>Fecha</th><th>Producto</th><th>Lote</th><th>Tipo</th><th>Cantidad</th><th>Referencia</th></tr></thead><tbody>
<?php if (!$movements): ?><tr><td colspan="6">Sin movimientos.</td></tr><?php endif; ?>
<?php foreach ($movements as $row): ?><tr><td><?= e($row['fecha_movimiento']) ?></td><td><?= e($row['producto']) ?></td><td><?= e($row['numero_lote'] ?? '') ?></td><td><?= e($row['tipo']) ?></td><td><?= e($row['cantidad']) ?></td><td><?= e(($row['referencia_tipo'] ?? '') . ' ' . ($row['referencia_id'] ?? '')) ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
