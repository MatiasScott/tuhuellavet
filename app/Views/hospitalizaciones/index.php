<link rel="stylesheet" href="<?= asset('css/views/hospitalizaciones.css') ?>">
<div class="page-heading">
    <div><span class="eyebrow">Atención clínica</span>
        <h1>Hospitalización</h1>
        <p>Seguimiento de pacientes hospitalizados.</p>
    </div>
    <button class="btn btn-primary" data-modal-open="h-create">＋ Nueva hospitalización</button>
</div>
<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>
<section class="card">
    <form class="toolbar">
        <div class="search-box">
            🔎<input name="q" value="<?= e($search) ?>" placeholder="Buscar paciente...">
        </div>
        <button class="btn btn-secondary">Buscar</button>
    </form>
    <div class="cards-grid">
        <?php foreach ($hospitalizations as $h): ?>
            <a class="soft-panel" href="<?= url('/hospitalizaciones/' . $h['evento_clinico_id']) ?>">
                <div class="inline-actions">
                    <strong>🏥 <?= e($h['paciente']) ?></strong>
                    <span class="status-badge <?= ($h['estado_codigo'] === 'ACTIVA' ? 'status-success' : 'status-neutral') ?>"><?= e($h['estado_nombre']) ?></span>
                </div>
                <p class="text-muted"><?= e($h['especie']) ?><?= !empty($h['raza']) ? ' · ' . e($h['raza']) : '' ?></p>
                <p><?= e($h['motivo_ingreso'] ?? 'Sin motivo registrado') ?></p>
                <small>Ingreso: <?= e(date('d/m/Y H:i', strtotime($h['fecha_ingreso']))) ?></small>
            </a><?php endforeach; ?>
    </div>
</section>
<div class="modal" id="h-create">
    <div class="modal-backdrop"></div>
    <div class="modal-dialog modal-xl">
        <div class="modal-header">
            <h2>Nueva hospitalización</h2>
            <button class="modal-close" data-modal-close>×</button>
        </div>
        <form method="POST" action="<?= url('/hospitalizaciones') ?>">
            <?= csrf_field() ?>
            <div class="modal-body form-grid">
                <label class="field-full">
                    <span>Paciente</span>
                    <select name="animal_id" required>
                        <?php foreach ($patients as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= e($p['nombre'] . ' · ' . $p['especie']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Ingreso</span>
                    <input type="datetime-local" name="fecha_ingreso">
                </label>
                <label>
                    <span>Peso kg</span>
                    <input type="number" step=".001" name="peso_kg">
                </label>
                <label class="field-full">
                    <span>Motivo</span>
                    <textarea name="motivo_ingreso"></textarea>
                </label>
                <label class="field-full">
                    <span>Impresión clínica</span>
                    <textarea name="impresion_clinica_ingreso"></textarea>
                </label>
                <label class="field-full">
                    <span>Indicaciones generales</span>
                    <textarea name="indicaciones_generales"></textarea>
                </label>
                <label>
                    <span>Temperatura °C</span>
                    <input type="number" step=".01" name="temperatura_c">
                </label>
                <label>
                    <span>FC</span>
                    <input type="number" name="frecuencia_cardiaca">
                </label>
                <label>
                    <span>FR</span>
                    <input type="number" name="frecuencia_respiratoria">
                </label>
                <label>
                    <span>TLC</span>
                    <input type="number" step=".01" name="tiempo_llenado_capilar_seg">
                </label>
            </div>
            <div class="modal-footer"><button class="btn btn-primary">Ingresar paciente</button></div>
        </form>
    </div>
</div>