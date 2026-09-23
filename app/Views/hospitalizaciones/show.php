<link rel="stylesheet" href="<?= asset('css/views/hospitalizaciones.css') ?>">
<?php $active = $hospitalization['estado_codigo'] === 'ACTIVA';
$eventId = (int)$hospitalization['evento_clinico_id']; ?>
<div class="page-heading">
    <div><span class="eyebrow">Ficha hospitalaria</span>
        <h1>🏥 <?= e($hospitalization['paciente']) ?></h1>
        <p><?= e($hospitalization['especie']) ?> · Ingreso <?= e(date('d/m/Y H:i', strtotime($hospitalization['fecha_ingreso']))) ?></p>
    </div>
    <div class="inline-actions">
        <span class="status-badge <?= $active ? 'status-success' : 'status-neutral' ?>"><?= e($hospitalization['estado_nombre']) ?></span>
        <?php if ($active): ?>
            <button class="btn btn-primary" data-modal-open="h-close">Alta / cerrar</button>
        <?php endif; ?>
    </div>
</div>
<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>
<div class="metric-grid">
    <div class="metric-card">
        <span>Peso</span>
        <strong><?= e($hospitalization['peso_actual'] ?? '—') ?><?= ($hospitalization['peso_actual'] !== null ? ' kg' : '') ?></strong>
    </div>
    <div class="metric-card">
        <span>Temperatura</span>
        <strong><?= !empty($signs) && $signs[0]['temperatura_c'] !== null ? e($signs[0]['temperatura_c']) . ' °C' : '—' ?></strong>
    </div>
    <div class="metric-card">
        <span>FC</span>
        <strong><?= !empty($signs) ? e($signs[0]['frecuencia_cardiaca'] ?? '—') : '—' ?></strong>
    </div>
    <div class="metric-card">
        <span>FR</span>
        <strong><?= !empty($signs) ? e($signs[0]['frecuencia_respiratoria'] ?? '—') : '—' ?></strong>
    </div>
    <div class="metric-card">
        <span>TLC</span>
        <strong><?= !empty($signs) ? e($signs[0]['tiempo_llenado_capilar_seg'] ?? '—') : '—' ?></strong>
    </div>
</div>
<section class="card mb-1">
    <div class="card-header">
        <div>
            <h2>Resumen</h2>
            <p><?= nl2br(e($hospitalization['motivo_ingreso'] ?? 'Sin motivo')) ?></p>
        </div>
    </div>
</section>
<section class="card mb-1" id="signos">
    <div class="card-header">
        <div>
            <h2>❤️ Signos clínicos</h2>
        </div>
        <?php if ($active): ?>
            <button class="btn btn-primary" data-modal-open="h-signs">＋ Control</button>
        <?php endif; ?>
    </div>
    <div class="table-wrap">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>T°</th>
                    <th>FC</th>
                    <th>FR</th>
                    <th>Dolor</th>
                    <th>Hidratación</th>
                </tr>
            </thead>
            <tbody><?php foreach ($signs as $s): ?><tr>
                        <td><?= e($s['fecha_hora']) ?></td>
                        <td><?= e($s['temperatura_c'] ?? '—') ?></td>
                        <td><?= e($s['frecuencia_cardiaca'] ?? '—') ?></td>
                        <td><?= e($s['frecuencia_respiratoria'] ?? '—') ?></td>
                        <td><?= e($s['nivel_dolor'] ?? '—') ?></td>
                        <td><?= e($s['hidratacion'] ?? '—') ?></td>
                    </tr><?php endforeach; ?></tbody>
        </table>
    </div>
</section>
<section class="card mb-1" id="fluidoterapia">
    <div class="card-header">
        <div>
            <h2>💧 Fluidoterapia</h2>
        </div>
        <?php if ($active): ?>
            <button class="btn btn-primary" data-modal-open="h-fluid">＋ Fluidoterapia</button>
        <?php endif; ?>
    </div>
    <div class="cards-grid">
        <?php foreach ($fluidTherapies as $f): ?>
            <div class="soft-panel">
                <strong><?= e($f['categoria_nombre'] ?? 'Fluidoterapia') ?></strong>
                <p>Total <?= e($f['volumen_total_ml'] ?? '—') ?> ml · <?= e($f['velocidad_ml_hora'] ?? '—') ?> ml/h</p>
                <?php if (!empty($f['formula_nombre'])): ?>
                    <small>🧮 <?= e($f['formula_nombre']) ?> = <?= e($f['formula_resultado']) ?> <?= e($f['formula_unidad'] ?? '') ?></small>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<section class="card mb-1" id="tratamientos">
    <div class="card-header">
        <div>
            <h2>💊 Tratamientos</h2>
        </div><?php if ($active): ?>
            <button class="btn btn-primary" data-modal-open="h-treatment">＋ Tratamiento</button>
        <?php endif; ?>
    </div>
    <?php foreach ($treatments as $t): ?>
        <div class="soft-panel mb-1">
            <strong><?= e($t['tipo_nombre']) ?></strong>
            <p><?= e($t['instrucciones_generales'] ?? '') ?></p>
            <?php foreach ($t['medications'] as $m): ?>
                <?php
                $applications = $m['applications'] ?? [];
                ?>

                <div class="treatment-medication-item">

                    <div class="treatment-medication-main">

                        <div class="treatment-medication-info">
                            <strong>
                                💊 <?= e($m['farmaco_nombre']) ?>
                            </strong>

                            <div>
                                <?php if ($m['dosis_cantidad'] !== null): ?>
                                    <span>
                                        Dosis:
                                        <strong>
                                            <?= e($m['dosis_cantidad']) ?>
                                            <?= e($m['dosis_unidad'] ?? '') ?>
                                        </strong>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if (!empty($m['instrucciones'])): ?>
                                <small>
                                    <?= e($m['instrucciones']) ?>
                                </small>
                            <?php endif; ?>
                        </div>

                        <?php if ($active): ?>
                            <button
                                type="button"
                                class="btn btn-secondary btn-sm
                                data-modal-open=" h-apply-medication""
                                data-apply-med="<?= (int)$m['id'] ?>"
                                data-med-name="<?= e($m['farmaco_nombre']) ?>"
                                data-med-dose="<?= e($m['dosis_cantidad'] ?? '') ?>"
                                data-med-unit-id="<?= (int)($m['dosis_unidad_id'] ?? 0) ?>"
                                data-med-unit="<?= e($m['dosis_unidad'] ?? '') ?>">
                                Aplicar
                            </button>
                        <?php endif; ?>

                    </div>

                    <?php if (!empty($applications)): ?>

                        <div class="medication-applications">

                            <strong class="medication-applications-title">
                                Aplicaciones
                            </strong>

                            <?php foreach ($applications as $application): ?>
                                <?php
                                $isCancelled =
                                    (int)($application['esta_anulado'] ?? 0) === 1;

                                $quantity = $application['cantidad_aplicada'];

                                if ($quantity !== null) {
                                    $quantity = rtrim(
                                        rtrim(
                                            number_format(
                                                (float)$quantity,
                                                6,
                                                '.',
                                                ''
                                            ),
                                            '0'
                                        ),
                                        '.'
                                    );
                                }
                                ?>

                                <div
                                    class="medication-application-item <?= $isCancelled
                                                                            ? 'is-cancelled'
                                                                            : '' ?>">

                                    <div class="medication-application-header">

                                        <div>
                                            <strong>
                                                <?= e($quantity ?? '—') ?>

                                                <?php if (!empty($application['unidad'])): ?>
                                                    <?= e($application['unidad']) ?>
                                                <?php endif; ?>
                                            </strong>

                                            <?php if (!empty($application['fecha_hora'])): ?>
                                                <span class="text-muted">
                                                    ·
                                                    <?= e(
                                                        date(
                                                            'd/m/Y H:i',
                                                            strtotime(
                                                                $application['fecha_hora']
                                                            )
                                                        )
                                                    ) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($active && !$isCancelled): ?>

                                            <button
                                                type="button"
                                                class="btn btn-danger btn-sm"
                                                data-modal-open="h-cancel-application"
                                                data-cancel-application="<?= (int)$application['id'] ?>"
                                                data-cancel-medication="<?= e($m['farmaco_nombre']) ?>"
                                                data-cancel-quantity="<?= e($quantity ?? '') ?>"
                                                data-cancel-unit="<?= e($application['unidad'] ?? '') ?>">
                                                Anular
                                            </button>

                                        <?php endif; ?>

                                    </div>

                                    <?php if (!empty($application['aplicado_por_nombre'])): ?>
                                        <small>
                                            Aplicado por:
                                            <?= e($application['aplicado_por_nombre']) ?>
                                        </small>
                                    <?php endif; ?>

                                    <?php if (!empty($application['observaciones'])): ?>
                                        <small>
                                            Observación:
                                            <?= e($application['observaciones']) ?>
                                        </small>
                                    <?php endif; ?>

                                    <?php if ($isCancelled): ?>

                                        <div class="medication-application-cancelled">

                                            <small>
                                                <strong>
                                                    Aplicación anulada
                                                </strong>

                                                <?php if (!empty($application['anulado_at'])): ?>
                                                    ·
                                                    <?= e(
                                                        date(
                                                            'd/m/Y H:i',
                                                            strtotime(
                                                                $application['anulado_at']
                                                            )
                                                        )
                                                    ) ?>
                                                <?php endif; ?>
                                            </small>

                                            <?php if (!empty($application['anulado_por_nombre'])): ?>
                                                <small>
                                                    Anulada por:
                                                    <?= e($application['anulado_por_nombre']) ?>
                                                </small>
                                            <?php endif; ?>

                                            <?php if (!empty($application['motivo_anulacion'])): ?>
                                                <small>
                                                    Motivo:
                                                    <?= e($application['motivo_anulacion']) ?>
                                                </small>
                                            <?php endif; ?>

                                        </div>

                                    <?php endif; ?>

                                </div>

                            <?php endforeach; ?>

                        </div>

                    <?php endif; ?>

                </div>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</section>
<section class="card mb-1" id="evoluciones">
    <div class="card-header">
        <div>
            <h2>📋 Evoluciones</h2>
        </div>
        <?php if ($active): ?>
            <button class="btn btn-primary" data-modal-open="h-evo">＋ Evolución</button>
        <?php endif; ?>
    </div>
    <div class="stack">
        <?php foreach ($evolutions as $x): ?>
            <div class="soft-panel">
                <strong><?= e($x['fecha_hora']) ?> · <?= e($x['registrado_por_nombre']) ?></strong>
                <p><?= nl2br(e($x['evolucion'])) ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<?php if ($active): ?>
    <div class="modal" id="h-signs">
        <div class="modal-backdrop"></div>
        <div class="modal-dialog modal-lg">
            <div class="modal-header">
                <h2>Nuevo control</h2><button class="modal-close" data-modal-close>×</button>
            </div>
            <form method="POST" action="<?= url('/hospitalizaciones/' . $eventId . '/signos') ?>">
                <?= csrf_field() ?>
                <div class="modal-body form-grid">
                    <label>
                        <span>Fecha</span>
                        <input type="datetime-local" name="fecha_hora">
                    </label>
                    <label>
                        <span>Peso kg</span>
                        <input type="number" step=".001" name="peso_kg">
                    </label>
                    <label>
                        <span>Temperatura</span>
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
                    <label>
                        <span>Dolor</span>
                        <input name="nivel_dolor">
                    </label>
                    <label>
                        <span>Hidratación</span>
                        <input name="hidratacion">
                    </label>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
    <div class="modal" id="h-evo">
        <div class="modal-backdrop"></div>
        <div class="modal-dialog">
            <div class="modal-header">
                <h2>Nueva evolución</h2>
                <button class="modal-close" data-modal-close>×</button>
            </div>
            <form method="POST" action="<?= url('/hospitalizaciones/' . $eventId . '/evoluciones') ?>">
                <?= csrf_field() ?>
                <div class="modal-body form-grid">
                    <label class="field-full">
                        <span>Evolución</span>
                        <textarea name="evolucion" required></textarea>
                    </label>
                    <label class="field-full">
                        <span>Observaciones</span>
                        <textarea name="observaciones"></textarea>
                    </label>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
    <div class="modal" id="h-fluid">
        <div class="modal-backdrop"></div>
        <div class="modal-dialog modal-lg">
            <div class="modal-header">
                <h2>Fluidoterapia</h2>
                <button class="modal-close" data-modal-close>×</button>
            </div>
            <form method="POST" action="<?= url('/hospitalizaciones/' . $eventId . '/fluidoterapia') ?>">
                <?= csrf_field() ?>
                <div class="modal-body form-grid">
                    <label>
                        <span>Categoría</span>
                        <select name="categoria_mantenimiento_id">
                            <option value="">—</option><?php foreach ($maintenanceCategories as $c): ?><option value="<?= $c['id'] ?>"><?= e($c['nombre']) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Inicio</span>
                        <input type="datetime-local" name="fecha_inicio">
                    </label>
                    <label>
                        <span>Mantenimiento ml</span>
                        <input type="number" step=".001" name="mantenimiento_ml">
                    </label>
                    <label>
                        <span>Rehidratación ml</span>
                        <input type="number" step=".001" name="rehidratacion_ml">
                    </label>
                    <label>
                        <span>% deshidratación</span>
                        <input type="number" step=".01" name="porcentaje_deshidratacion">
                    </label>
                    <label>
                        <span>Total ml</span>
                        <input type="number" step=".001" name="volumen_total_ml">
                    </label>
                    <label>
                        <span>Velocidad ml/h</span>
                        <input type="number" step=".001" name="velocidad_ml_hora">
                    </label>
                    <div class="field-full formula-fluid-block">
                        <span class="field-label">Fórmula médica</span>
                        <div class="inline-actions">
                            <select id="fluid-formula-version">
                                <option value="">Seleccionar fórmula</option>
                                <?php foreach ($formulas as $formula): ?>
                                    <option value="<?= (int)$formula['formula_version_id'] ?>"><?= e($formula['nombre']) ?> · v<?= e($formula['numero_version']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-secondary" id="fluid-formula-load">Cargar fórmula</button>
                        </div>
                        <input type="hidden" name="formula_ejecucion_id" id="fluid-formula-execution-id">
                        <div id="fluid-formula-panel" class="formula-calculator-panel" hidden>
                            <h3 id="fluid-formula-name">Fórmula</h3>
                            <div id="fluid-formula-variables" class="form-grid"></div>
                            <div id="fluid-formula-result" class="formula-calculator-result" hidden>
                                <span>Resultado</span>
                                <strong id="fluid-formula-result-value"></strong>
                            </div>
                            <button type="button" class="btn btn-primary" id="fluid-formula-calculate">Calcular</button>
                        </div>
                    </div>
                    <label class="field-full">
                        <span>Observaciones</span>
                        <textarea name="observaciones"></textarea>
                    </label>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
    <div class="modal" id="h-treatment">
        <div class="modal-backdrop"></div>
        <div class="modal-dialog modal-xl">
            <div class="modal-header">
                <div>
                    <h2>Tratamiento hospitalario</h2>
                    <p class="text-muted">
                        Agrega uno o varios medicamentos
                        al tratamiento.
                    </p>
                </div>
                <button type="button" class="modal-close" data-modal-close>
                    ×
                </button>
            </div>
            <form method="POST" action="<?= url('/hospitalizaciones/' . $eventId . '/tratamientos') ?>">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="form-grid">
                        <label>
                            <span>Tipo</span>
                            <select name="tipo_tratamiento_id" required>
                                <?php foreach (
                                    $treatmentTypes as $t
                                ): ?>
                                    <option
                                        value="<?= (int)$t['id'] ?>">
                                        <?= e($t['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field-full">
                            <span>
                                Instrucciones generales
                            </span>
                            <textarea
                                name="instrucciones_generales"
                                placeholder="Indicaciones generales del tratamiento..."></textarea>
                        </label>
                    </div>
                    <div class="hospital-medications-header">
                        <div>
                            <h3>
                                💊 Medicamentos
                            </h3>
                            <p class="text-muted">
                                Registra cada medicamento
                                con su dosis y unidad.
                            </p>
                        </div>
                        <button type="button" class="btn btn-secondary" id="hospital-add-medication">
                            ＋ Agregar medicamento
                        </button>
                    </div>
                    <div id="hospital-medications-container" class="hospital-medications-list">
                        <div class="hospital-medication-row" data-medication-row>
                            <div class="form-grid">
                                <label>
                                    <span>Fármaco</span>
                                    <select name="medicamentos[0][farmaco_id]" data-medication-field="farmaco_id" required>
                                        <option value="">
                                            Seleccionar
                                        </option>
                                        <?php foreach (
                                            $drugs as $d
                                        ): ?>
                                            <option
                                                value="<?= (int)$d['id'] ?>">
                                                <?= e($d['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>
                                    <span>Dosis</span>
                                    <input
                                        type="number"
                                        step="any"
                                        min="0"
                                        name="medicamentos[0][dosis_cantidad]"
                                        data-medication-field="dosis_cantidad"
                                        required>
                                </label>
                                <label>
                                    <span>Unidad</span>
                                    <select name="medicamentos[0][dosis_unidad_id]" data-medication-field="dosis_unidad_id" required>
                                        <option value="">
                                            Seleccionar
                                        </option>
                                        <?php foreach (
                                            $units as $u
                                        ): ?>
                                            <option
                                                value="<?= (int)$u['id'] ?>">
                                                <?= e(
                                                    $u['simbolo']
                                                ) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <div class="hospital-medication-actions">
                                    <button
                                        type="button"
                                        class="btn btn-danger btn-sm"
                                        data-remove-medication
                                        hidden>
                                        Eliminar
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-modal-close>
                        Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Guardar tratamiento
                    </button>
                </div>
            </form>
        </div>
    </div>
    <div class="modal" id="h-apply-medication">
        <div class="modal-backdrop"></div>

        <div class="modal-dialog">

            <div class="modal-header">
                <div>
                    <h2>Aplicar medicamento</h2>

                    <p
                        class="text-muted"
                        id="apply-medication-name">
                    </p>
                </div>

                <button
                    type="button"
                    class="modal-close"
                    data-modal-close>
                    ×
                </button>
            </div>

            <form
                method="POST"
                id="apply-medication-form"
                action="">

                <?= csrf_field() ?>

                <div class="modal-body form-grid">

                    <div class="field-full soft-panel">

                        <span class="text-muted">
                            Dosis prescrita
                        </span>

                        <strong
                            id="apply-medication-prescribed-dose">
                            —
                        </strong>

                    </div>

                    <label>
                        <span>
                            Cantidad aplicada
                        </span>

                        <input
                            type="number"
                            step="any"
                            min="0.000001"
                            name="cantidad_aplicada"
                            id="apply-medication-quantity"
                            required>
                    </label>

                    <label>
                        <span>
                            Unidad
                        </span>

                        <select
                            name="unidad_id"
                            id="apply-medication-unit"
                            required>

                            <option value="">
                                Seleccionar
                            </option>

                            <?php foreach ($units as $u): ?>
                                <option
                                    value="<?= (int)$u['id'] ?>">
                                    <?= e($u['simbolo']) ?>
                                </option>
                            <?php endforeach; ?>

                        </select>
                    </label>

                    <label class="field-full">
                        <span>
                            Observaciones
                        </span>

                        <textarea
                            name="observaciones"
                            rows="3"
                            placeholder="Observaciones de la aplicación..."></textarea>
                    </label>

                </div>

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-modal-close>
                        Cancelar
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary">
                        Registrar aplicación
                    </button>

                </div>

            </form>

        </div>
    </div>
    <div class="modal" id="h-cancel-application">

        <div class="modal-backdrop"></div>

        <div class="modal-dialog">

            <div class="modal-header">

                <div>
                    <h2>Anular aplicación</h2>

                    <p class="text-muted">
                        Esta acción quedará registrada
                        en el historial clínico.
                    </p>
                </div>

                <button
                    type="button"
                    class="modal-close"
                    data-modal-close>
                    ×
                </button>

            </div>

            <form
                method="POST"
                id="cancel-application-form"
                action="">

                <?= csrf_field() ?>

                <div class="modal-body form-grid">

                    <div class="field-full soft-panel">

                        <strong id="cancel-application-medication">
                            Medicamento
                        </strong>

                        <div
                            class="text-muted"
                            id="cancel-application-dose">
                        </div>

                    </div>

                    <label class="field-full">

                        <span>
                            Motivo de anulación *
                        </span>

                        <textarea
                            name="motivo"
                            id="cancel-application-reason"
                            rows="4"
                            required
                            placeholder="Indica por qué se anula esta aplicación..."></textarea>

                    </label>

                </div>

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-modal-close>
                        Cancelar
                    </button>

                    <button
                        type="submit"
                        class="btn btn-danger">
                        Confirmar anulación
                    </button>

                </div>

            </form>

        </div>

    </div>
    <div class="modal" id="h-close">
        <div class="modal-backdrop"></div>
        <div class="modal-dialog">
            <div class="modal-header">
                <h2>Cerrar hospitalización</h2>
                <button class="modal-close" data-modal-close>×</button>
            </div>
            <form method="POST" action="<?= url('/hospitalizaciones/' . $eventId . '/cerrar') ?>">
                <?= csrf_field() ?>
                <div class="modal-body form-grid">
                    <label>
                        <span>Resultado</span>
                        <select name="estado_codigo">
                            <option>ALTA</option>
                            <option>TRASLADO</option>
                            <option>FALLECIDO</option>
                            <option>CANCELADA</option>
                        </select>
                    </label>
                    <label>
                        <span>Salida</span>
                        <input type="datetime-local" name="fecha_salida">
                    </label>
                    <label class="field-full">
                        <span>Observaciones</span>
                        <textarea name="observaciones_alta"></textarea>
                    </label>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary">Confirmar</button>
                </div>
            </form>
        </div>
    </div><?php endif; ?>
<script>
    window.HospitalizationData = <?= json_encode([
                                        'eventId' => $eventId,
                                        'patientId' => (int)$hospitalization['animal_id'],
                                        'csrf' => csrf_token(),
                                        'baseUrl' => rtrim($_ENV['APP_URL'] ?? '', '/'),
                                        'isAcademic' => ((active_environment()['tipo_codigo'] ?? '') === 'ACADEMICO'),
                                        'applicationUrlBase' => url('/hospitalizaciones/' . $eventId . '/medicamentos'),
                                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="<?= url('assets/js/views/hospitalizaciones.js') ?>"></script>