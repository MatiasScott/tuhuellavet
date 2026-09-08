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
            Â·
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
        â† Volver al paciente
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

            </div>

        </div>

        <div class="clinical-text">

            <?= nl2br(
                e(
                    $consultation['motivo_consulta']
                        ?: 'Sin informaciÃ³n'
                )
            ) ?>

        </div>

    </section>


    <section class="card">

        <div class="card-header">

            <div>
                <h2>Anamnesis</h2>
            </div>

        </div>

        <div class="clinical-text">

            <?= nl2br(
                e(
                    $consultation['anamnesis']
                        ?: 'Sin informaciÃ³n'
                )
            ) ?>

        </div>

    </section>

</div>


<section class="card">

    <div class="card-header">

        <div>

            <h2>
                Examen clÃ­nico general
            </h2>

            <p>
                Signos y evaluaciÃ³n
                registrados.
            </p>

        </div>

    </div>


    <div
        class="
            clinical-data-grid
        ">

        <div>
            <span>AlimentaciÃ³n</span>
            <strong>
                <?= e(
                    $consultation['alimentacion']
                        ?: 'â€”'
                ) ?>
            </strong>
        </div>

        <div>
            <span>Historial reproductivo</span>
            <strong>
                <?= e(
                    $consultation['historial_reproductivo']
                        ?: 'â€”'
                ) ?>
            </strong>
        </div>

        <div>
            <span>Frecuencia cardiaca</span>
            <strong>
                <?= e(
                    $consultation['frecuencia_cardiaca']
                        ?: 'â€”'
                ) ?>
            </strong>
        </div>

        <div>
            <span>Frecuencia respiratoria</span>
            <strong>
                <?= e(
                    $consultation['frecuencia_respiratoria']
                        ?: 'â€”'
                ) ?>
            </strong>
        </div>

        <div>
            <span>Temperatura</span>
            <strong>
                <?= $consultation['temperatura_c'] !== null
                    ? e(
                        $consultation['temperatura_c']
                    ) . ' Â°C'
                    : 'â€”'
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
                    : 'â€”'
                ?>
            </strong>
        </div>

        <div>
            <span>Ganglios</span>
            <strong>
                <?= e(
                    $consultation['ganglios_linfaticos']
                        ?: 'â€”'
                ) ?>
            </strong>
        </div>

        <div>
            <span>CondiciÃ³n corporal</span>
            <strong>
                <?= e(
                    $consultation['condicion_corporal']
                        ?: 'â€”'
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
            VÃ³mitos
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
                DiagnÃ³sticos
            </h2>

        </div>

    </div>


    <?php if (
        empty($diagnoses)
    ): ?>

        <div class="compact-empty">
            No se registraron
            diagnÃ³sticos.
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
                ðŸ’Š Tratamientos
            </h2>

            <p>
                Tratamiento clÃ­nico
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
                ï¼‹ Agregar tratamiento
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
                                    ? 'ðŸ  Tratamiento en casa'
                                    : 'ðŸ¥ Tratamiento clÃ­nico'
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
                                            ðŸ’Š
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
                                                    : 'â€”'
                                                ?>

                                            </strong>

                                        </div>


                                        <div>

                                            <span>VÃ­a</span>

                                            <strong>

                                                <?= e(
                                                    $medication['via_nombre']
                                                        ?: 'â€”'
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
                                                            ?: 'â€”'
                                                        )
                                                ) ?>

                                            </strong>

                                        </div>


                                        <div>

                                            <span>DuraciÃ³n</span>

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
                                                    : 'â€”'
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

                                            ðŸ§® Calculado mediante fÃ³rmula:

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
                                            Registrar aplicaciÃ³n
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
                        PrescripciÃ³n
                    </span>

                    <h2>
                        Nuevo tratamiento
                    </h2>

                </div>

                <button
                    type="button"
                    class="modal-close"
                    data-modal-close>
                    Ã—
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
                                name="fecha_inicio">

                        </label>


                        <label class="field-full">

                            <span>
                                Instrucciones generales
                            </span>

                            <textarea
                                name="
                                instrucciones_generales
                            "
                                rows="3"></textarea>

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
                            ï¼‹ Medicamento
                        </button>

                    </div>


                    <div
                        id="
                        treatment-medications
                    "></div>


                    <label
                        class="field-full">

                        <span>
                            Observaciones
                        </span>

                        <textarea
                            name="observaciones"
                            rows="3"></textarea>

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
                    Tratamiento clÃ­nico
                </span>

                <h2>
                    Registrar aplicaciÃ³n
                </h2>

            </div>

            <button
                type="button"
                class="modal-close"
                data-modal-close>
                Ã—
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
                    Registrar aplicaciÃ³n
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
            ) ?>"></script>
