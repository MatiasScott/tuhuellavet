<?php
$labels = ['lotes' => 'Lotes animales', 'produccion' => 'Producción', 'genealogia' => 'Registro genealógico', 'alimentacion' => 'Alimentación'];
$permission = $module === 'lotes' ? 'lotes_animales' : $module;
$report = in_array($module, ['produccion', 'alimentacion'], true);
?>
<div class="page-heading"><div><span class="eyebrow">Hacienda Agusbella</span><h1><?= e($labels[$module]) ?></h1></div></div>
<nav class="inline-actions">
    <?php foreach ($labels as $key => $label): ?>
        <?php if (can(($key === 'lotes' ? 'lotes_animales' : $key) . '.ver')): ?>
            <a class="btn btn-secondary" href="<?= url('/hacienda/' . $key) ?>"><?= e($label) ?></a>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<?php if ($module === 'lotes'): ?>
    <section class="card">
        <label><span>Tipo de registro</span><select aria-label="Tipo de registro" onchange="if(this.value) location.href=this.value">
            <option value="">Por lotes</option><option value="<?= e(url('/pacientes')) ?>">Animal individual</option>
        </select></label>
        <p>Los lotes animales mantienen su cantidad, origen e historial, separados de los lotes de productos del inventario.</p>
    </section>
<?php endif; ?>
<?php if ($report): ?>
    <section class="card">
        <h2>Reporte y trazabilidad</h2>
        <form class="form-grid" method="GET">
            <label><span>Desde</span><input type="date" name="desde" value="<?= e($filters['desde'] ?? '') ?>"></label>
            <label><span>Hasta</span><input type="date" name="hasta" value="<?= e($filters['hasta'] ?? '') ?>"></label>
            <?php require __DIR__ . '/target-fields.php'; ?>
            <button class="btn btn-secondary">Filtrar</button>
            <?php if (can('reportes.exportar')): ?>
                <a class="btn btn-secondary" href="<?= e(url('/hacienda/' . $module . '/reporte.csv') . '?' . http_build_query(array_intersect_key($filters, array_flip(['desde', 'hasta', 'paciente_id', 'lote_id'])))) ?>">Exportar CSV</a>
            <?php endif; ?>
        </form>
        <p>Totales separados por animal/lote, producto, unidad y estado. Los consumos se filtran por fecha de confirmación; los planes, por fecha inicial. Una ración planificada no representa consumo real.</p>
        <div class="metric-grid">
            <?php foreach ($metrics as $metric): ?>
                <div class="metric-card"><span><?= e($metric['destino'] . ' · ' . $metric['tipo'] . ' ' . $metric['estado']) ?></span><strong><?= e($metric['cantidad'] . ' ' . $metric['unidad']) ?></strong></div>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
<?php if (can($permission . ($module === 'genealogia' ? '.editar' : '.crear'))): ?>
    <section class="card">
        <h2><?= $module === 'genealogia' ? 'Registrar o actualizar linaje' : 'Nuevo registro' ?></h2>
        <form method="POST" action="<?= url('/hacienda/' . $module) ?>">
            <?= csrf_field() ?>
            <div class="form-grid">
                <?php if ($module === 'lotes'): ?>
                    <label><span>Código *</span><input name="codigo" maxlength="80" required></label>
                    <label><span>Nombre *</span><input name="nombre" maxlength="150" required></label>
                    <label><span>Especie *</span><select name="especie_id" required><option value="">Seleccionar</option><?php foreach ($species as $speciesRow): ?><option value="<?= (int) $speciesRow['id'] ?>"><?= e($speciesRow['nombre_comun']) ?></option><?php endforeach; ?></select></label>
                    <label><span>Cantidad de animales *</span><input type="number" name="cantidad" min="1" max="999999999" step="1" required></label>
                    <label><span>Método de identificación *</span><input name="metodo_identificacion" maxlength="150" required></label>
                    <label><span>Origen *</span><input name="origen" maxlength="150" required></label>
                <?php elseif ($module === 'genealogia'): ?>
                    <?php foreach (['paciente_id' => 'Animal *', 'padre_id' => 'Padre', 'madre_id' => 'Madre'] as $field => $label): ?>
                        <label><span><?= e($label) ?></span><select name="<?= e($field) ?>" <?= $field === 'paciente_id' ? 'required' : '' ?>><option value="">Sin seleccionar</option>
                            <?php foreach ($patients as $patient): ?><option value="<?= (int) $patient['id'] ?>"><?= e($patient['nombre'] ?: $patient['codigo'] ?: ('Animal #' . $patient['id'])) ?></option><?php endforeach; ?>
                        </select></label>
                    <?php endforeach; ?>
                    <label><span>Generación *</span><input type="number" name="generacion" value="1" min="1" step="1" required></label>
                    <p class="field-full">Se verifica especie, sexo, ciclos y linaje compartido conocido. La ausencia de ancestros registrados no garantiza ausencia de consanguinidad.</p>
                <?php else: ?>
                    <?php $savedFilters = $filters; $filters = []; require __DIR__ . '/target-fields.php'; $filters = $savedFilters; ?>
                    <p class="field-full">Seleccione únicamente un animal individual o un lote.</p>
                    <label><span><?= $module === 'produccion' ? 'Tipo de producto *' : 'Tipo de alimento *' ?></span><input name="<?= $module === 'produccion' ? 'tipo_producto' : 'tipo_alimento' ?>" maxlength="<?= $module === 'produccion' ? 100 : 150 ?>" required></label>
                    <label><span>Cantidad total *</span><input type="number" name="<?= $module === 'produccion' ? 'cantidad' : 'cantidad_consumida' ?>" min="0.01" max="99999999.99" step="0.01" required></label>
                    <label><span>Unidad *</span><select name="unidad_id" required><option value="">Seleccionar</option><?php foreach ($units as $unit): ?><option value="<?= (int) $unit['id'] ?>"><?= e($unit['nombre'] . ' (' . $unit['simbolo'] . ')') ?></option><?php endforeach; ?></select></label>
                    <?php if ($module === 'produccion'): ?>
                        <label><span>Fecha y hora *</span><input type="datetime-local" name="fecha_registro" value="<?= date('Y-m-d\TH:i') ?>" required></label>
                    <?php else: ?>
                        <label><span>Frecuencia</span><input name="frecuencia" maxlength="100" placeholder="Diario, dos veces al día…"></label>
                        <label><span>Fecha inicial *</span><input type="date" name="fecha_inicio" value="<?= date('Y-m-d') ?>" required></label>
                        <label><span>Fecha final</span><input type="date" name="fecha_fin"></label>
                        <label><span>Inventario para el consumo</span><select name="inventario_id"><option value="">Sin vincular</option><?php foreach ($inventories as $inventory): ?><option value="<?= (int) $inventory['id'] ?>"><?= e($inventory['nombre']) ?></option><?php endforeach; ?></select></label>
                        <label><span>Producto del inventario</span><select name="producto_id"><option value="">Sin vincular</option><?php foreach ($products as $product): ?><option value="<?= (int) $product['id'] ?>"><?= e($product['nombre'] . ' (' . $product['unidad'] . ')') ?></option><?php endforeach; ?></select></label>
                        <label><span>Lote del producto</span><select name="lote_producto_id"><option value="">Sin lote</option><?php foreach ($productLots as $productLot): ?><option value="<?= (int) $productLot['id'] ?>"><?= e($productLot['producto_id'] . ' · ' . $productLot['numero_lote']) ?></option><?php endforeach; ?></select></label>
                        <p class="field-full">Para confirmar un consumo se requieren producto e inventario. La cantidad total se descuenta una sola vez, en la unidad base del producto, no se multiplica por días ni animales. Puede vincular el inventario antes de confirmar mediante «Editar ración».</p>
                    <?php endif; ?>
                <?php endif; ?>
                <label class="field-full"><span>Observaciones</span><textarea name="observaciones" maxlength="10000"></textarea></label>
            </div>
            <button class="btn btn-primary"><?= $module === 'alimentacion' ? 'Planificar ración' : 'Guardar' ?></button>
        </form>
    </section>
<?php endif; ?>
<section class="card">
    <h2>Historial</h2>
    <div class="table-wrap"><table class="modern-table"><thead><tr>
        <?php if ($module === 'lotes'): ?><th>Código / nombre</th><th>Especie</th><th>Cantidad</th><th>Identificación</th><th>Origen</th>
        <?php elseif ($module === 'genealogia'): ?><th>Animal</th><th>Padre</th><th>Madre</th><th>Generación</th>
        <?php else: ?><th>Fecha</th><th>Tipo / destino</th><th>Producto / alimento</th><th>Cantidad</th><th>Estado / trazabilidad</th><?php endif; ?>
        <th>Observaciones</th>
    </tr></thead><tbody>
        <?php if (!$rows): ?><tr><td colspan="6">Sin registros para esta selección.</td></tr><?php endif; ?>
        <?php foreach ($rows as $row): ?><tr>
            <?php if ($module === 'lotes'): ?>
                <td><a href="<?= url('/hacienda/lotes/' . $row['id']) ?>"><?= e($row['codigo'] . ' / ' . $row['nombre']) ?></a></td><td><?= e($row['especie']) ?></td><td><?= (int) $row['cantidad'] ?></td><td><?= e($row['metodo_identificacion']) ?></td><td><?= e($row['origen']) ?></td>
            <?php elseif ($module === 'genealogia'): ?>
                <td><?= e($row['paciente'] ?? '') ?></td><td><?= e($row['padre'] ?? 'Desconocido') ?></td><td><?= e($row['madre'] ?? 'Desconocida') ?></td><td><?= (int) $row['generacion'] ?></td>
            <?php else: ?>
                <td><?= e($row['fecha_reporte']) ?><?php if (!empty($row['fecha_fin'])): ?><br>Hasta <?= e($row['fecha_fin']) ?><?php endif; ?></td>
                <td><?= e($row['tipo_registro'] . ' #' . ($row['paciente_id'] ?? $row['lote_id']) . ' / ' . $row['destino']) ?></td>
                <td><?= e($row['tipo_producto'] ?? $row['tipo_alimento']) ?></td><td><?= e(($row['cantidad'] ?? $row['cantidad_consumida']) . ' ' . $row['unidad']) ?></td>
                <td><?php if ($module === 'alimentacion'): ?>
                    <strong><?= e($row['estado']) ?></strong><p><?= e($row['frecuencia'] ?? '') ?></p>
                    <p><?= e(($row['inventario'] ?? '') . ' / ' . ($row['producto'] ?? '') . ' / ' . ($row['numero_lote'] ?? 'Sin lote')) ?></p>
                    <?php if ($row['movimiento_id']): ?><p>Movimiento #<?= (int) $row['movimiento_id'] ?> · <?= e($row['confirmado_at']) ?></p><?php endif; ?>
                    <?php if ($row['estado'] === 'PLANIFICADA' && can('alimentacion.editar')): ?>
                        <details><summary>Editar ración</summary>
                            <form method="POST" action="<?= url('/hacienda/alimentacion/' . $row['id'] . '/actualizar') ?>"><?= csrf_field() ?>
                                <label><span>Cantidad total</span><input type="number" name="cantidad_consumida" min="0.01" max="99999999.99" step="0.01" value="<?= e($row['cantidad_consumida']) ?>" required></label>
                                <label><span>Inventario</span><select name="inventario_id"><option value="">Sin vincular</option><?php foreach ($inventories as $inventory): ?><option value="<?= (int) $inventory['id'] ?>" <?= (int) $row['inventario_id'] === (int) $inventory['id'] ? 'selected' : '' ?>><?= e($inventory['nombre']) ?></option><?php endforeach; ?></select></label>
                                <label><span>Producto</span><select name="producto_id"><option value="">Sin vincular</option><?php foreach ($products as $product): ?><option value="<?= (int) $product['id'] ?>" <?= (int) $row['producto_id'] === (int) $product['id'] ? 'selected' : '' ?>><?= e($product['nombre'] . ' (' . $product['unidad'] . ')') ?></option><?php endforeach; ?></select></label>
                                <label><span>Lote producto</span><select name="lote_producto_id"><option value="">Sin lote</option><?php foreach ($productLots as $productLot): ?><option value="<?= (int) $productLot['id'] ?>" <?= (int) $row['lote_producto_id'] === (int) $productLot['id'] ? 'selected' : '' ?>><?= e($productLot['producto_id'] . ' · ' . $productLot['numero_lote']) ?></option><?php endforeach; ?></select></label>
                                <button class="btn btn-secondary">Guardar plan</button>
                            </form>
                        </details>
                    <?php endif; ?>
                    <?php if ($row['estado'] === 'PLANIFICADA' && $row['producto_id'] && can('alimentacion.editar') && can('inventario.editar')): ?>
                        <form method="POST" action="<?= url('/hacienda/alimentacion/' . $row['id'] . '/confirmar') ?>"><?= csrf_field() ?><button class="btn btn-primary">Confirmar consumo real</button></form>
                    <?php endif; ?>
                <?php else: ?>Registrada<?php endif; ?></td>
            <?php endif; ?>
            <td><?= e($row['observaciones'] ?? '') ?></td>
        </tr><?php endforeach; ?>
    </tbody></table></div>
</section>
