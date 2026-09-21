<link rel="stylesheet" href="<?= asset('css/views/consultas.css') ?>">
<div class="page-heading">
    <div><span class="eyebrow">Atención clínica</span>
        <h1>Consultas externas</h1>
        <p>Anamnesis, examen clínico, diagnóstico y tratamiento.</p>
    </div><?php if (can('consultas.crear')): ?><button class="btn btn-primary" data-modal-open="consult-create">＋ Nueva consulta</button><?php endif; ?>
</div><?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?><section class="card">
    <form class="toolbar">
        <div class="search-box">🔎<input name="q" value="<?= e($search) ?>" placeholder="Buscar..."></div><button class="btn btn-secondary">Buscar</button>
    </form>
    <div class="table-wrap">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Paciente</th>
                    <th>Motivo</th>
                    <th>Responsable</th>
                    <th></th>
                </tr>
            </thead>
            <tbody><?php foreach ($consultations as $c): ?><tr>
                        <td><?= e(date('d/m/Y H:i', strtotime($c['fecha_evento']))) ?></td>
                        <td>
                            <strong>
                                <?= e($c['paciente_nombre'] ?? 'Paciente no disponible') ?>
                            </strong>
                        </td>
                        <td><?= e($c['motivo_consulta'] ?? '—') ?></td>
                        <td><?= e($c['responsable_nombre'] ?? '') ?></td>
                        <td>
                            <a
                                class="btn btn-secondary btn-sm"
                                href="<?= url('/consultas/' . (int) $c['evento_id']) ?>">
                                Ver
                            </a>
                        </td>
                    </tr><?php endforeach; ?></tbody>
        </table>
    </div>
</section>
<div class="modal" id="consult-create">
    <div class="modal-backdrop"></div>
    <div class="modal-dialog modal-xl">
        <div class="modal-header">
            <h2>Nueva consulta</h2><button class="modal-close" data-modal-close>×</button>
        </div>
        <form method="POST" action="<?= url('/consultas') ?>"><?= csrf_field() ?><div class="modal-body">
                <div class="form-grid">
                    <label class="field-full">
                        <span>Paciente *</span>
                        <select name="animal_id" required>
                            <option value="">Seleccionar</option>
                            <?php foreach ($patients as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= e($p['nombre'] . ' · ' . $p['especie']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="field-full">
                        <span>Motivo</span>
                        <textarea name="motivo_consulta"></textarea>
                    </label>
                    <label class="field-full">
                        <span>Anamnesis</span>
                        <textarea name="anamnesis"></textarea>
                    </label>
                    <label class="field-full">
                        <span>Antecedentes</span>
                        <textarea name="antecedentes"></textarea>
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
                        <span>TLC s</span>
                        <input type="number" step=".01" name="tiempo_llenado_capilar_seg">
                    </label>
                    <label>
                        <span>Peso kg</span>
                        <input type="number" step=".001" name="peso_kg">
                    </label>
                    <label>
                        <span>Condición corporal</span>
                        <input name="condicion_corporal">
                    </label>
                    <label class="field-full">
                        <span>Recomendaciones</span>
                        <textarea name="recomendaciones"></textarea>
                    </label>
                </div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary">Registrar consulta</button></div>
        </form>
    </div>
</div>