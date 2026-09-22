<link
    rel="stylesheet"
    href="<?= asset(
                'css/views/consultas.css'
            ) ?>">
<link
    rel="stylesheet"
    href="<?= asset(
                'css/views/tratamientos.css'
            ) ?>">
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

<div class="page-heading">
    <div>
        <span class="eyebrow">
            Consulta externa
        </span>
        <h1>
            <?= e(
                $consultation['paciente_nombre']
                    ?: 'Paciente'
            ) ?>
        </h1>
        <p>
            <?= e(
                date(
                    'd/m/Y H:i',
                    strtotime(
                        $consultation['fecha_evento']
                    )
                )
            ) ?>
            .
            <?= e(
                $consultation['responsable_nombre']
            ) ?>
        </p>
    </div>
    <a
        href="<?= url(
                    '/pacientes/'
                        . $consultation['animal_id']
                ) ?>"
        class="
            btn
            btn-secondary
        ">
        → Volver al paciente
    </a>
</div>

<div
    class="
        consultation-detail-grid
    ">
    <section class="card">
        <div class="card-header">
            <div>
                <h2>
                    Motivo de consulta
                </h2>
                <?php if (can('consultas.editar')): ?>
                    <button type="button" class="btn btn-secondary btn-sm" data-modal-open="consult-motivo">Editar</button>
                <?php endif; ?>
            </div>
        </div>
        <div class="clinical-text">
            <?= nl2br(
                e(
                    $consultation['motivo_consulta']
                        ?: 'Sin información'
                )
            ) ?>
        </div>
    </section>
    <section class="card">
        <div class="card-header">
            <div>
                <h2>Anamnesis</h2>
                <?php if (can('consultas.editar')): ?>
                    <button type="button" class="btn btn-secondary btn-sm" data-modal-open="consult-anamnesis">Editar</button>
                <?php endif; ?>
            </div>
        </div>
        <div class="clinical-text">
            <?= nl2br(
                e(
                    $consultation['anamnesis']
                        ?: 'Sin información'
                )
            ) ?>
        </div>
    </section>
</div>

<section class="card">
    <div class="card-header">
        <h2>Antecedentes</h2>
        <?php if (can('consultas.editar')): ?>
            <button type="button" class="btn btn-secondary btn-sm" data-modal-open="consult-antecedentes">Editar</button>
        <?php endif; ?>
    </div>
    <div class="clinical-text"><?= nl2br(e($consultation['antecedentes'] ?: 'Sin información')) ?></div>
</section>
<section class="card">
    <div class="card-header">
        <div>
            <h2>
                Examen clínico general
            </h2>
            <?php if (can('consultas.editar')): ?>
                <button type="button" class="btn btn-secondary btn-sm" data-modal-open="consult-examen">Editar</button>
            <?php endif; ?>
            <p>
                Signos y evaluación
                registrados.
            </p>
        </div>
    </div>
    <div
        class="
            clinical-data-grid
        ">
        <div>
            <span>Alimentación</span>
            <strong>
                <?= e(
                    $consultation['alimentacion']
                        ?: '—'
                ) ?>
            </strong>
        </div>
        <div>
            <span>Historial reproductivo</span>
            <strong>
                <?= e(
                    $consultation['historial_reproductivo']
                        ?: '—'
                ) ?>
            </strong>
        </div>
        <div>
            <span>Frecuencia cardiaca</span>
            <strong>
                <?= e(
                    $consultation['frecuencia_cardiaca']
                        ?: '—'
                ) ?>
            </strong>
        </div>
        <div>
            <span>Frecuencia respiratoria</span>
            <strong>
                <?= e(
                    $consultation['frecuencia_respiratoria']
                        ?: '—'
                ) ?>
            </strong>
        </div>
        <div>
            <span>Temperatura</span>
            <strong>
                <?= $consultation['temperatura_c'] !== null
                    ? e(
                        $consultation['temperatura_c']
                    ) . ' °C'
                    : '—'
                ?>
            </strong>
        </div>
        <div>
            <span>TLC</span>
            <strong>
                <?= $consultation['tiempo_llenado_capilar_seg'] !== null
                    ? e(
                        $consultation['tiempo_llenado_capilar_seg']
                    ) . ' s'
                    : '—'
                ?>
            </strong>
        </div>
        <div>
            <span>Ganglios</span>
            <strong>
                <?= e(
                    $consultation['ganglios_linfaticos']
                        ?: '—'
                ) ?>
            </strong>
        </div>
        <div>
            <span>Condición corporal</span>
            <strong>
                <?= e(
                    $consultation['condicion_corporal']
                        ?: '—'
                ) ?>
            </strong>
        </div>
    </div>
    <div
        class="
            clinical-symptoms
        ">
        <span
            class="<?= !empty($consultation['vomitos'])
                        ? 'symptom-positive'
                        : 'symptom-negative'
                    ?>">
            Vómitos
        </span>
        <span
            class="<?= !empty($consultation['diarrea'])
                        ? 'symptom-positive'
                        : 'symptom-negative'
                    ?>">
            Diarrea
        </span>
        <span
            class="<?= !empty($consultation['tos'])
                        ? 'symptom-positive'
                        : 'symptom-negative'
                    ?>">
            Tos
        </span>
    </div>
</section>
<section class="card">
    <div class="card-header">
        <div>
            <h2>
                Diagnósticos
            </h2>
            <?php if (can('consultas.editar')): ?>
                <button type="button" class="btn btn-secondary btn-sm" data-modal-open="consult-diagnostico">Agregar diagnóstico</button>
            <?php endif; ?>
        </div>
    </div>
    <?php if (
        empty($diagnoses)
    ): ?>
        <div class="compact-empty">
            No se registraron
            Diagnósticos.
        </div>
    <?php else: ?>
        <div
            class="
                diagnosis-list
            ">
            <?php foreach (
                $diagnoses
                as $diagnosis
            ): ?>
                <div
                    class="
                        diagnosis-item
                    ">
                    <span
                        class="
                            diagnosis-type
                        ">
                        <?= e(
                            $diagnosis['tipo']
                        ) ?>
                    </span>
                    <p>
                        <?= nl2br(
                            e(
                                $diagnosis['descripcion']
                            )
                        ) ?>
                    </p>
                    <small>
                        Registrado por
                        <?= e(
                            $diagnosis['ingresado_por_nombre']
                        ) ?>
                    </small>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="card treatment-section">
    <div class="card-header">
        <div>
            <h2>
                🧮 Tratamientos
            </h2>
            <p>
                Tratamiento clínico
                y tratamiento para casa.
            </p>
        </div>
        <?php if (
            can('tratamientos.crear')
        ): ?>
            <button
                class="btn btn-primary"
                type="button"
                data-modal-open="
                    treatment-create
                ">
                <span aria-hidden="true">+</span> Agregar tratamiento
            </button>
        <?php endif; ?>
    </div>
    <?php if (
        empty($treatments)
    ): ?>
        <div class="compact-empty">
            No existen tratamientos
            registrados en esta consulta.
        </div>
    <?php else: ?>
        <div class="treatment-list">
            <?php foreach (
                $treatments
                as $treatment
            ): ?>
                <article
                    class="treatment-card">
                    <div
                        class="
                            treatment-card-header
                        ">
                        <div>
                            <span
                                class="
                                    treatment-type
                                ">
                                <?= e(
                                    $treatment['tipo_nombre']
                                ) ?>
                            </span>
                            <h3>
                                <?= $treatment['tipo_codigo'] === 'CASA'
                                    ? '💊 Tratamiento en casa'
                                    : '🏥 Tratamiento clínico'
                                ?>
                            </h3>
                        </div>
                        <small>
                            Indicado por
                            <?= e(
                                $treatment['indicado_por_nombre']
                            ) ?>
                        </small>
                    </div>
                    <?php if (
                        $treatment['instrucciones_generales']
                    ): ?>
                        <p class="clinical-text">
                            <?= nl2br(
                                e(
                                    $treatment['instrucciones_generales']
                                )
                            ) ?>
                        </p>
                    <?php endif; ?>
                    <?php if (
                        empty($treatment['medications'])
                    ): ?>
                        <div class="compact-empty">
                            Sin medicamentos registrados.
                        </div>
                    <?php else: ?>
                        <div
                            class="
                                medication-list
                            ">
                            <?php foreach (
                                $treatment['medications']
                                as $medication
                            ): ?>
                                <div
                                    class="
                                        medication-card
                                    ">
                                    <div
                                        class="
                                            medication-main
                                        ">
                                        <div
                                            class="
                                                medication-icon
                                            ">
                                            🧮
                                        </div>
                                        <div>
                                            <strong>
                                                <?= e(
                                                    $medication['farmaco_nombre']
                                                ) ?>
                                            </strong>

                                            <span>
                                                <?= e(
                                                    $medication['nombre_comercial']
                                                        ?: (
                                                            $medication['forma_farmaceutica']
                                                            ?: ''
                                                        )
                                                ) ?>
                                            </span>
                                        </div>
                                    </div>
                                    <div
                                        class="
                                            medication-prescription
                                        ">
                                        <div>
                                            <span>Dosis</span>
                                            <strong>
                                                <?= $medication['dosis_cantidad'] !== null
                                                    ? e(
                                                        $medication['dosis_cantidad']
                                                    )
                                                    . ' '
                                                    . e(
                                                        $medication['dosis_unidad']
                                                            ?? ''
                                                    )
                                                    : '—'
                                                ?>
                                            </strong>
                                        </div>
                                        <div>
                                            <span>vía</span>
                                            <strong>
                                                <?= e(
                                                    $medication['via_nombre']
                                                        ?: '—'
                                                ) ?>
                                            </strong>
                                        </div>
                                        <div>
                                            <span>Frecuencia</span>
                                            <strong>
                                                <?= e(
                                                    $medication['frecuencia_nombre']
                                                        ?: (
                                                            $medication['frecuencia_texto']
                                                            ?: '—'
                                                        )
                                                ) ?>
                                            </strong>
                                        </div>
                                        <div>
                                            <span>Duración</span>
                                            <strong>
                                                <?= $medication['duracion_cantidad'] !== null
                                                    ? e(
                                                        $medication['duracion_cantidad']
                                                    )
                                                    . ' '
                                                    . e(
                                                        $medication['duracion_unidad']
                                                            ?? ''
                                                    )
                                                    : '—'
                                                ?>
                                            </strong>
                                        </div>
                                    </div>
                                    <?php if (
                                        $medication['formula_ejecucion_id']
                                    ): ?>
                                        <div
                                            class="
                                                formula-result-small
                                            ">
                                            🧮 Calculado mediante Fórmula:
                                            <strong>
                                                <?= e(
                                                    $medication['formula_resultado']
                                                ) ?>

                                                <?= e(
                                                    $medication['formula_unidad']
                                                        ?? ''
                                                ) ?>
                                            </strong>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (
                                        can(
                                            'tratamientos.editar'
                                        )
                                        &&
                                        $treatment['tipo_codigo'] === 'CLINICO'
                                    ): ?>
                                        <button
                                            type="button"
                                            class="
                                                btn
                                                btn-small
                                                btn-secondary
                                            "
                                            data-medication-apply
                                            data-event="<?= (int)
                                                        $consultation['evento_id'] ?>"
                                            data-medication="<?= (int)
                                                                $medication['id'] ?>"
                                            data-dose="<?= e(
                                                            $medication['dosis_cantidad']
                                                                ?? ''
                                                        ) ?>"
                                            data-unit="<?= e(
                                                            $medication['dosis_unidad_id']
                                                                ?? ''
                                                        ) ?>">
                                            Registrar aplicación
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>


<section class="card">
    <div class="card-header">
        <div>
            <h2>Recomendaciones</h2>
            <?php if (can('consultas.editar')): ?>
                <button type="button" class="btn btn-secondary btn-sm" data-modal-open="consult-recomendaciones">Editar</button>
            <?php endif; ?>
        </div>
    </div>

    <div class="clinical-text">
        <?= nl2br(
            e(
                $consultation['recomendaciones']
                    ?: 'Sin recomendaciones.'
            )
        ) ?>
    </div>
</section>
<?php if (can('consultas.editar')): ?>
    <div class="modal" id="consult-motivo">
        <div class="modal-backdrop"></div>
        <div class="modal-dialog modal-xl">
            <div class="modal-header">
                <h2>Editar Motivo de consulta</h2>
                <button type="button" class="modal-close" data-modal-close aria-label="Cerrar">×</button>
            </div>
            <form method="POST" action="<?= url('/consultas/' . (int) $consultation['evento_id'] . '') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="_method" value="PUT">
                <div class="modal-body">
                    <div class="form-grid">
                        <label class="field-full"><span>Motivo de consulta</span><textarea name="motivo_consulta" rows="5"><?= e($consultation['motivo_consulta'] ?? '') ?></textarea></label>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-modal-close>Cancelar</button><button type="submit" class="btn btn-primary">Guardar</button></div>
            </form>
        </div>
    </div>
    <div class="modal" id="consult-anamnesis">
        <div class="modal-backdrop"></div>
        <div class="modal-dialog modal-xl">
            <div class="modal-header">
                <h2>Editar Anamnesis</h2>
                <button type="button" class="modal-close" data-modal-close aria-label="Cerrar">×</button>
            </div>
            <form method="POST" action="<?= url('/consultas/' . (int) $consultation['evento_id'] . '') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="_method" value="PUT">
                <div class="modal-body">
                    <div class="form-grid">
                        <label class="field-full"><span>Anamnesis</span><textarea name="anamnesis" rows="5"><?= e($consultation['anamnesis'] ?? '') ?></textarea></label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-modal-close>Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="consult-antecedentes">
        <div class="modal-backdrop"></div>
        <div class="modal-dialog modal-xl">
            <div class="modal-header">
                <h2>Editar Antecedentes</h2>
                <button type="button" class="modal-close" data-modal-close aria-label="Cerrar">×</button>
            </div>
            <form method="POST" action="<?= url('/consultas/' . (int) $consultation['evento_id'] . '') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="_method" value="PUT">
                <div class="modal-body">
                    <div class="form-grid">
                        <label class="field-full">
                            <span>Antecedentes</span>
                            <textarea name="antecedentes" rows="5"><?= e($consultation['antecedentes'] ?? '') ?></textarea>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-modal-close>Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="consult-recomendaciones">
        <div class="modal-backdrop"></div>
        <div class="modal-dialog modal-xl">
            <div class="modal-header">
                <h2>Editar Recomendaciones</h2>
                <button type="button" class="modal-close" data-modal-close aria-label="Cerrar">×</button>
            </div>
            <form method="POST" action="<?= url('/consultas/' . (int) $consultation['evento_id'] . '') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="_method" value="PUT">
                <div class="modal-body">
                    <div class="form-grid">
                        <label class="field-full">
                            <span>Recomendaciones</span>
                            <textarea name="recomendaciones" rows="5"><?= e($consultation['recomendaciones'] ?? '') ?>
                        </textarea>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-modal-close>Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="consult-examen">
        <div class="modal-backdrop"></div>
        <div class="modal-dialog modal-xl">
            <div class="modal-header">
                <h2>Editar examen clínico</h2>
                <button type="button" class="modal-close" data-modal-close aria-label="Cerrar">×</button>
            </div>
            <form method="POST" action="<?= url('/consultas/' . (int) $consultation['evento_id'] . '/examen') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="_method" value="PUT">
                <div class="modal-body">
                    <div class="form-grid">
                        <label class="field-full">
                            <span>Alimentación</span>
                            <textarea name="alimentacion" rows="2"><?= e($consultation['alimentacion'] ?? '') ?></textarea>
                        </label>
                        <label class="field-full">
                            <span>Historial reproductivo</span>
                            <textarea name="historial_reproductivo" rows="2"><?= e($consultation['historial_reproductivo'] ?? '') ?></textarea>
                        </label>
                        <label class="field-full">
                            <span>Ganglios linfáticos</span>
                            <textarea name="ganglios_linfaticos" rows="2"><?= e($consultation['ganglios_linfaticos'] ?? '') ?></textarea>
                        </label>
                        <label class="field-full">
                            <span>Condición corporal</span>
                            <textarea name="condicion_corporal" rows="2"><?= e($consultation['condicion_corporal'] ?? '') ?></textarea>
                        </label>
                        <label class="field-full">
                            <span>Observaciones del examen</span>
                            <textarea name="examen_observaciones" rows="2"><?= e($consultation['examen_observaciones'] ?? '') ?></textarea>
                        </label>
                        <label>
                            <span>Frecuencia cardiaca</span>
                            <input type="number" name="frecuencia_cardiaca" step="any" min="0" value="<?= e($consultation['frecuencia_cardiaca'] ?? '') ?>">
                        </label>
                        <label>
                            <span>Frecuencia respiratoria</span>
                            <input type="number" name="frecuencia_respiratoria" step="any" min="0" value="<?= e($consultation['frecuencia_respiratoria'] ?? '') ?>">
                        </label>
                        <label>
                            <span>Temperatura °C</span>
                            <input type="number" name="temperatura_c" step="any" min="0" value="<?= e($consultation['temperatura_c'] ?? '') ?>">
                        </label>
                        <label>
                            <span>TLC (segundos)</span>
                            <input type="number" name="tiempo_llenado_capilar_seg" step="any" min="0" value="<?= e($consultation['tiempo_llenado_capilar_seg'] ?? '') ?>">
                        </label>
                        <label>
                            <span>Vómitos</span>
                            <select name="vomitos">
                                <option value="0" <?= empty($consultation['vomitos']) ? 'selected' : '' ?>>No</option>
                                <option value="1" <?= !empty($consultation['vomitos']) ? 'selected' : '' ?>>Sí</option>
                            </select>
                        </label>
                        <label>
                            <span>Diarrea</span>
                            <select name="diarrea">
                                <option value="0" <?= empty($consultation['diarrea']) ? 'selected' : '' ?>>No</option>
                                <option value="1" <?= !empty($consultation['diarrea']) ? 'selected' : '' ?>>Sí</option>
                            </select>
                        </label>
                        <label>
                            <span>Tos</span>
                            <select name="tos">
                                <option value="0" <?= empty($consultation['tos']) ? 'selected' : '' ?>>No</option>
                                <option value="1" <?= !empty($consultation['tos']) ? 'selected' : '' ?>>Sí</option>
                            </select>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-modal-close>Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal" id="consult-diagnostico">
        <div class="modal-backdrop"></div>
        <div class="modal-dialog modal-xl">
            <div class="modal-header">
                <h2>Agregar diagnóstico</h2>
                <button type="button" class="modal-close" data-modal-close aria-label="Cerrar">×</button>
            </div>
            <form method="POST" action="<?= url('/consultas/' . (int) $consultation['evento_id'] . '/diagnosticos') ?>">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="form-grid">
                        <label><span>Tipo de diagnóstico *</span><select name="tipo_codigo" required>
                                <option value="">Seleccionar</option>
                                <option value="DIFERENCIAL">Diferencial</option>
                                <option value="PRESUNTIVO">Presuntivo</option>
                                <option value="DEFINITIVO">Definitivo</option>
                            </select>
                        </label>
                        <label class="field-full">
                            <span>Descripción *</span>
                            <textarea name="descripcion" rows="4" required></textarea>
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-modal-close>Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<?php if (
    can('tratamientos.crear')
): ?>
    <div
        class="modal"
        id="treatment-create">
        <div class="modal-backdrop"></div>
        <div
            class="
            modal-dialog
            modal-xl
        ">
            <div class="modal-header">
                <div>
                    <span class="eyebrow">
                        Prescripción
                    </span>
                    <h2>
                        Nuevo tratamiento
                    </h2>
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
                action="<?= url(
                            '/consultas/'
                                . $consultation['evento_id']
                                . '/tratamientos'
                        ) ?>"
                id="treatment-form">
                <?= csrf_field() ?>

                <div class="modal-body">
                    <div class="form-grid">
                        <label>
                            <span>
                                Tipo *
                            </span>
                            <select
                                name="
                                tipo_tratamiento_id
                            "
                                required>
                                <option value="">
                                    Seleccionar
                                </option>
                                <?php foreach (
                                    $treatmentTypes
                                    as $type
                                ): ?>
                                    <option
                                        value="<?= (int)
                                                $type['id'] ?>">
                                        <?= e(
                                            $type['nombre']
                                        ) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span>
                                Fecha inicio
                            </span>
                            <input
                                type="datetime-local"
                                name="fecha_inicio"
                                required>
                        </label>
                        <label class="field-full">
                            <span>
                                Instrucciones generales
                            </span>

                            <textarea
                                name="instrucciones_generales"
                                rows="3">
                            </textarea>
                        </label>
                    </div>
                    <div
                        class="
                        treatment-medications-header
                    ">
                        <div>
                            <h3>
                                Medicamentos
                            </h3>
                            <p>
                                Agrega uno o varios
                                medicamentos.
                            </p>
                        </div>
                        <button
                            type="button"
                            class="
                            btn
                            btn-secondary
                        "
                            id="
                            add-treatment-medication
                        ">
                            + Medicamento
                        </button>
                    </div>
                    <div
                        id="
                        treatment-medications
                    ">
                    </div>
                    <label
                        class="field-full">
                        <span>
                            Observaciones
                        </span>
                        <textarea
                            name="observaciones"
                            rows="3">
                        </textarea>
                    </label>
                </div>
                <div class="modal-footer">
                    <button
                        type="button"
                        class="
                        btn
                        btn-secondary
                    "
                        data-modal-close>
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        class="
                        btn
                        btn-primary
                    ">
                        Guardar tratamiento
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<div
    class="modal"
    id="medication-apply">
    <div class="modal-backdrop"></div>
    <div class="modal-dialog">
        <div class="modal-header">
            <div>
                <span class="eyebrow">
                    Tratamiento clínico
                </span>
                <h2>
                    Registrar aplicación
                </h2>
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
            id="medication-apply-form">
            <?= csrf_field() ?>
            <div class="modal-body">
                <div class="form-grid">
                    <label>
                        <span>
                            Cantidad aplicada *
                        </span>
                        <input
                            type="number"
                            step="0.000001"
                            min="0.000001"
                            name="
                                cantidad_aplicada
                            "
                            id="
                                applied-quantity
                            "
                            required>
                    </label>
                    <label>
                        <span>
                            Unidad
                        </span>
                        <select
                            name="unidad_id"
                            id="applied-unit">
                            <option value="">
                                Seleccionar
                            </option>
                            <?php foreach (
                                $units
                                as $unit
                            ): ?>
                                <option
                                    value="<?= (int)
                                            $unit['id'] ?>">
                                    <?= e(
                                        $unit['nombre']
                                    ) ?>

                                    <?= $unit['simbolo']
                                        ? ' ('
                                        . e(
                                            $unit['simbolo']
                                        )
                                        . ')'
                                        : ''
                                    ?>
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
                            rows="3"></textarea>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button
                    type="button"
                    class="
                        btn
                        btn-secondary
                    "
                    data-modal-close>
                    Cancelar
                </button>

                <button
                    type="submit"
                    class="
                        btn
                        btn-primary
                    ">
                    Registrar aplicación
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    window.TreatmentData = <?= json_encode(
                                [
                                    'eventId'
                                    => (int)
                                    $consultation['evento_id'],

                                    'patientId'
                                    => (int)
                                    $consultation['animal_id'],

                                    'drugs'
                                    => $drugs,

                                    'presentations'
                                    => $presentations,

                                    'routes'
                                    => $routes,

                                    'frequencies'
                                    => $frequencies,

                                    'units'
                                    => $units,

                                    'timeUnits'
                                    => $timeUnits,

                                    'formulas'
                                    => $formulas,

                                    'csrf'
                                    => csrf_token(),

                                    'baseUrl'
                                    => rtrim(
                                        $_ENV['APP_URL']
                                            ?? '',
                                        '/'
                                    ),
                                ],
                                JSON_UNESCAPED_UNICODE
                                    | JSON_UNESCAPED_SLASHES
                            ) ?>;
</script>
<script
    src="<?= asset(
                'js/views/tratamientos.js'
            ) ?>">
</script>