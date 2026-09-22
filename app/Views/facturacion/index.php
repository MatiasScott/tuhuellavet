<div class="page-heading">
    <div><span class="eyebrow">Finanzas</span>
        <h1>Ventas y facturación</h1>
        <p>Ventas clínicas y cola de emisión Contífico.</p>
    </div>
    <?php if (can('ventas.crear')): ?>
        <button class="btn btn-primary" data-modal-open="sale-create">＋ Venta</button>
    <?php endif; ?>
</div>
<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>
<section class="card mb-1">
    <div class="table-wrap">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Cliente</th>
                    <th>Paciente</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody><?php foreach ($sales as $s): ?><tr>
                        <td><?= e($s['fecha']) ?></td>
                        <td><?= e(trim(($s['propietario_nombres'] ?? '') . ' ' . ($s['propietario_apellidos'] ?? '')) ?: '—') ?></td>
                        <td><?= e($s['paciente'] ?? '—') ?></td>
                        <td>$<?= number_format((float)$s['total'], 2) ?></td>
                        <td><?= e($s['estado']) ?><?= !empty($s['es_simulada']) ? ' · SIMULADA' : '' ?></td>
                        <td>
                            <?php if (can('facturacion.facturar') && !$s['es_simulada']): ?>
                                <form method="POST" action="<?= url('/facturacion/' . $s['id'] . '/emitir') ?>">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-secondary btn-sm">Emitir</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<section class="card">
    <div class="card-header">
        <h2>Documentos fiscales</h2>
    </div>
    <div class="table-wrap">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Venta</th>
                    <th>Estado</th>
                    <th>Contífico estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($documents as $d): ?><tr>
                        <td>#<?= $d['id'] ?></td>
                        <td>#<?= $d['venta_id'] ?> · $<?= number_format((float)$d['total'], 2) ?></td>
                        <td><?= e($d['estado']) ?></td>
                        <td><?= e($d['contifico_estado'] ?? 'Pendiente') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<div class="modal" id="sale-create">
    <div class="modal-backdrop"></div>
    <div class="modal-dialog modal-xl">
        <div class="modal-header">
            <h2>Nueva venta</h2>
            <button class="modal-close" data-modal-close>×</button>
        </div>
        <form method="POST" action="<?= url('/facturacion') ?>">
            <?= csrf_field() ?>
            <div class="modal-body form-grid">
                <label>
                    <span>Propietario</span>
                    <select name="propietario_entorno_id">
                        <option value="">—</option>
                        <?php foreach ($owners as $o): ?>
                            <option value="<?= $o['id'] ?>">
                                <?= e(trim($o['nombres'] . ' ' . ($o['apellidos'] ?? ''))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Paciente</span>
                    <select name="animal_id">
                        <option value="">—</option>
                        <?php foreach ($patients as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= e($p['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Servicio</span>
                    <select name="items[0][servicio_id]">
                        <option value="">—</option>
                        <?php foreach ($services as $x): ?>
                            <option value="<?= $x['id'] ?>"><?= e($x['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Descripción</span>
                    <input name="items[0][descripcion]" value="Servicio veterinario">
                </label>
                <label>
                    <span>Cantidad</span>
                    <input type="number" step="any" name="items[0][cantidad]" value="1">
                </label>
                <label>
                    <span>Precio unitario</span>
                    <input type="number" step=".01" name="items[0][precio_unitario]" required>
                </label>
                <label>
                    <span>Descuento</span>
                    <input type="number" step=".01" name="items[0][descuento]" value="0">
                </label>
                <label>
                    <span>Impuesto</span>
                    <input type="number" step=".01" name="items[0][impuesto]" value="0">
                </label>
            </div>
            <div class="modal-footer"><button class="btn btn-primary">Registrar venta</button></div>
        </form>
    </div>
</div>