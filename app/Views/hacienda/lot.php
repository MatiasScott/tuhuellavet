<div class="page-heading"><div><span class="eyebrow">Hacienda · Lote animal</span><h1><?= e($lot['nombre']) ?></h1><p><?= e($lot['codigo'] . ' · ' . $lot['especie']) ?></p></div><a class="btn btn-secondary" href="<?= url('/hacienda/lotes') ?>">Volver a lotes</a></div>
<?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
<section class="card"><h2>Ficha del lote</h2><p>Cantidad: <?= (int) $lot['cantidad'] ?></p><p>Identificación: <?= e($lot['metodo_identificacion']) ?></p><p>Origen: <?= e($lot['origen']) ?></p><p><?= e($lot['observaciones'] ?? '') ?></p></section>
<?php if (can('lotes_animales.editar')): ?>
<section class="card"><details><summary>Editar lote y cantidad de animales</summary>
    <form method="POST" action="<?= url('/hacienda/lotes/' . $lot['id'] . '/actualizar') ?>"><?= csrf_field() ?>
        <div class="form-grid">
            <label><span>Nombre *</span><input name="nombre" maxlength="150" value="<?= e($lot['nombre']) ?>" required></label>
            <label><span>Cantidad *</span><input type="number" name="cantidad" min="1" max="999999999" step="1" value="<?= (int) $lot['cantidad'] ?>" required></label>
            <label><span>Método de identificación *</span><input name="metodo_identificacion" maxlength="150" value="<?= e($lot['metodo_identificacion']) ?>" required></label>
            <label><span>Origen *</span><input name="origen" maxlength="150" value="<?= e($lot['origen']) ?>" required></label>
            <label class="field-full"><span>Observaciones</span><textarea name="observaciones"><?= e($lot['observaciones'] ?? '') ?></textarea></label>
        </div><button class="btn btn-primary">Guardar lote</button>
    </form>
</details></section>
<?php endif; ?>
<?php if (can('consultas.crear')): ?>
<section class="card"><h2>Nueva atención clínica del lote</h2>
    <form method="POST" action="<?= url('/hacienda/lotes/' . $lot['id'] . '/examenes') ?>"><?= csrf_field() ?>
        <div class="form-grid">
            <label><span>Motivo / título *</span><input name="titulo" maxlength="200" required></label>
            <label><span>Fecha y hora *</span><input type="datetime-local" name="fecha_evento" value="<?= date('Y-m-d\TH:i') ?>" required></label>
            <label><span>Temperatura pulmonar / cloacal °C</span><input type="number" name="temperatura_c" min="0" max="999.99" step="0.01"></label>
            <label><span>TLC (segundos)</span><input type="number" name="tiempo_llenado_capilar_seg" min="0" max="999.99" step="0.01"></label>
            <?php $examValues = []; require dirname(__DIR__) . '/consultas/physiological-fields.php'; ?>
            <label class="field-full"><span>Observaciones del examen</span><textarea name="examen_observaciones"></textarea></label>
        </div>
        <button class="btn btn-primary">Registrar atención</button>
    </form>
</section>
<?php endif; ?>
<?php if (can('consultas.ver')): ?>
<section class="card"><h2>Historial clínico del lote</h2>
    <?php if (!$history): ?><p>Sin atenciones registradas.</p><?php endif; ?>
    <?php foreach ($history as $examValues): ?>
        <article class="soft-panel"><h3><?= e($examValues['titulo']) ?></h3><p><?= e($examValues['fecha_evento']) ?></p>
            <?php require dirname(__DIR__) . '/consultas/physiological-summary.php'; ?>
            <p><?= e($examValues['observaciones'] ?? '') ?></p>
        </article>
    <?php endforeach; ?>
</section>
<?php endif; ?>
