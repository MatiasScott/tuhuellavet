<link
    rel="stylesheet"
    href="<?= asset(
        'css/views/consultas.css'
    ) ?>"
>


<div class="page-heading">

    <div>

        <span class="eyebrow">
            Clínica
        </span>

        <h1>
            Consultas externas
        </h1>

        <p>
            Historial de consultas
            médicas del entorno actual.
        </p>

    </div>


    <?php if (
        can('consultas.crear')
    ): ?>

        <button
            class="btn btn-primary"
            type="button"
            data-modal-open="
                consultation-create
            "
        >
            ＋ Nueva consulta
        </button>

    <?php endif; ?>

</div>


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


<section class="card">

    <form
        class="toolbar"
        method="GET"
        action="<?= url('/consultas') ?>"
    >

        <div class="search-box">

            🔎

            <input
                type="search"
                name="q"
                value="<?= e($search) ?>"
                placeholder="
                    Buscar paciente,
                    motivo, especie o raza
                "
            >

        </div>

        <button
            class="btn btn-secondary"
        >
            Buscar
        </button>

    </form>


    <?php if (
        empty($consultations)
    ): ?>

        <div class="empty-state">

            <div class="empty-icon">
                🩺
            </div>

            <h2>
                No existen consultas
            </h2>

            <p>
                Registra la primera
                atención clínica.
            </p>

        </div>

    <?php else: ?>

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

                <tbody>

                <?php foreach (
                    $consultations
                    as $consultation
                ): ?>

                    <tr>

                        <td>
                            <?= e(
                                date(
                                    'd/m/Y H:i',
                                    strtotime(
                                        $consultation[
                                            'fecha_evento'
                                        ]
                                    )
                                )
                            ) ?>
                        </td>

                        <td>

                            <div
                                class="entity-cell"
                            >

                                <div class="pet-avatar">
                                    🐾
                                </div>

                                <div>

                                    <strong>
                                        <?= e(
                                            $consultation[
                                                'paciente'
                                            ]
                                            ?: 'Sin nombre'
                                        ) ?>
                                    </strong>

                                    <small>
                                        <?= e(
                                            (
                                                $consultation[
                                                    'especie'
                                                ]
                                                ?? ''
                                            )
                                            . (
                                                $consultation[
                                                    'raza'
                                                ]
                                                    ? ' · '
                                                        . $consultation[
                                                            'raza'
                                                        ]
                                                    : ''
                                            )
                                        ) ?>
                                    </small>

                                </div>

                            </div>

                        </td>

                        <td>
                            <?= e(
                                $consultation[
                                    'motivo_consulta'
                                ]
                                ?: '—'
                            ) ?>
                        </td>

                        <td>
                            <?= e(
                                $consultation[
                                    'responsable'
                                ]
                            ) ?>
                        </td>

                        <td
                            class="
                                table-actions
                            "
                        >

                            <a
                                class="
                                    btn
                                    btn-small
                                    btn-secondary
                                "
                                href="<?= url(
                                    '/consultas/'
                                    . $consultation[
                                        'id'
                                    ]
                                ) ?>"
                            >
                                Ver
                            </a>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</section>


<?php if (
    can('consultas.crear')
): ?>

<div
    class="modal"
    id="consultation-create"
>

    <div
        class="modal-backdrop"
    ></div>


    <div
        class="
            modal-dialog
            modal-xl
        "
    >

        <div class="modal-header">

            <div>

                <span class="eyebrow">
                    Atención clínica
                </span>

                <h2>
                    Nueva consulta externa
                </h2>

            </div>

            <button
                class="modal-close"
                type="button"
                data-modal-close
            >
                ×
            </button>

        </div>


        <form
            method="POST"
            action="<?= url('/consultas') ?>"
        >

            <?= csrf_field() ?>


            <div class="modal-body">

                <div
                    class="
                        consultation-section
                    "
                >

                    <h3>
                        🐾 Paciente
                    </h3>

                    <div class="form-grid">

                        <label
                            class="field-full"
                        >

                            <span>
                                Paciente *
                            </span>

                            <select
                                name="animal_id"
                                required
                            >

                                <option value="">
                                    Seleccionar
                                </option>

                                <?php foreach (
                                    $patients
                                    as $patient
                                ): ?>

                                    <option
                                        value="<?= (int)
                                            $patient[
                                                'id'
                                            ] ?>"
                                    >
                                        <?= e(
                                            (
                                                $patient[
                                                    'nombre'
                                                ]
                                                ?: 'Sin nombre'
                                            )
                                            . ' · '
                                            . (
                                                $patient[
                                                    'especie'
                                                ]
                                                ?? ''
                                            )
                                            . (
                                                $patient[
                                                    'propietario_nombres'
                                                ]
                                                    ? ' · '
                                                        . trim(
                                                            $patient[
                                                                'propietario_nombres'
                                                            ]
                                                            . ' '
                                                            . (
                                                                $patient[
                                                                    'propietario_apellidos'
                                                                ]
                                                                ?? ''
                                                            )
                                                        )
                                                    : ''
                                            )
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </label>


                        <label>

                            <span>
                                Fecha y hora
                            </span>

                            <input
                                type="
                                    datetime-local
                                "
                                name="
                                    fecha_evento
                                "
                            >

                        </label>


                        <label>

                            <span>
                                Peso actual (kg)
                            </span>

                            <input
                                type="number"
                                name="peso_kg"
                                min="0.001"
                                step="0.001"
                            >

                        </label>

                    </div>

                </div>


                <div
                    class="
                        consultation-section
                    "
                >

                    <h3>
                        🩺 Motivo y anamnesis
                    </h3>

                    <div class="form-grid">

                        <label
                            class="field-full"
                        >

                            <span>
                                Motivo de consulta
                            </span>

                            <textarea
                                name="
                                    motivo_consulta
                                "
                                rows="2"
                            ></textarea>

                        </label>


                        <label
                            class="field-full"
                        >

                            <span>
                                Anamnesis
                            </span>

                            <textarea
                                name="anamnesis"
                                rows="3"
                            ></textarea>

                        </label>


                        <label
                            class="field-full"
                        >

                            <span>
                                Antecedentes
                            </span>

                            <textarea
                                name="antecedentes"
                                rows="3"
                            ></textarea>

                        </label>

                    </div>

                </div>


                <div
                    class="
                        consultation-section
                    "
                >

                    <h3>
                        ❤️ Examen clínico general
                    </h3>


                    <div class="form-grid">

                        <label>

                            <span>
                                Alimentación
                            </span>

                            <input
                                name="
                                    alimentacion
                                "
                            >

                        </label>


                        <label>

                            <span>
                                Historial reproductivo
                            </span>

                            <input
                                name="
                                    historial_reproductivo
                                "
                            >

                        </label>


                        <label>

                            <span>
                                Frecuencia cardiaca
                            </span>

                            <input
                                type="number"
                                name="
                                    frecuencia_cardiaca
                                "
                                step="0.01"
                            >

                        </label>


                        <label>

                            <span>
                                Frecuencia respiratoria
                            </span>

                            <input
                                type="number"
                                name="
                                    frecuencia_respiratoria
                                "
                                step="0.01"
                            >

                        </label>


                        <label>

                            <span>
                                Temperatura °C
                            </span>

                            <input
                                type="number"
                                name="
                                    temperatura_c
                                "
                                step="0.01"
                            >

                        </label>


                        <label>

                            <span>
                                TLC (segundos)
                            </span>

                            <input
                                type="number"
                                name="
                                    tiempo_llenado_capilar_seg
                                "
                                step="0.01"
                            >

                        </label>


                        <label>

                            <span>
                                Ganglios linfáticos
                            </span>

                            <input
                                name="
                                    ganglios_linfaticos
                                "
                            >

                        </label>


                        <label>

                            <span>
                                Condición corporal
                            </span>

                            <input
                                name="
                                    condicion_corporal
                                "
                            >

                        </label>


                        <div
                            class="
                                clinical-checkboxes
                                field-full
                            "
                        >

                            <label>

                                <input
                                    type="checkbox"
                                    name="vomitos"
                                    value="1"
                                >

                                <span>
                                    Vómitos
                                </span>

                            </label>


                            <label>

                                <input
                                    type="checkbox"
                                    name="diarrea"
                                    value="1"
                                >

                                <span>
                                    Diarrea
                                </span>

                            </label>


                            <label>

                                <input
                                    type="checkbox"
                                    name="tos"
                                    value="1"
                                >

                                <span>
                                    Tos
                                </span>

                            </label>

                        </div>


                        <label
                            class="field-full"
                        >

                            <span>
                                Observaciones del examen
                            </span>

                            <textarea
                                name="
                                    examen_observaciones
                                "
                                rows="3"
                            ></textarea>

                        </label>

                    </div>

                </div>


                <div
                    class="
                        consultation-section
                    "
                >

                    <h3>
                        🧠 Diagnósticos
                    </h3>

                    <div class="form-grid">


                        <label
                            class="field-full"
                        >

                            <span>
                                Diagnóstico diferencial
                            </span>

                            <textarea
                                name="
                                    diagnostico_diferencial
                                "
                                rows="2"
                            ></textarea>

                        </label>


                        <label
                            class="field-full"
                        >

                            <span>
                                Diagnóstico presuntivo
                            </span>

                            <textarea
                                name="
                                    diagnostico_presuntivo
                                "
                                rows="2"
                            ></textarea>

                        </label>


                        <label
                            class="field-full"
                        >

                            <span>
                                Diagnóstico definitivo
                            </span>

                            <textarea
                                name="
                                    diagnostico_definitivo
                                "
                                rows="2"
                            ></textarea>

                        </label>

                    </div>

                </div>


                <div
                    class="
                        consultation-section
                    "
                >

                    <h3>
                        📝 Recomendaciones
                    </h3>

                    <div class="form-grid">

                        <label
                            class="field-full"
                        >

                            <textarea
                                name="
                                    recomendaciones
                                "
                                rows="4"
                            ></textarea>

                        </label>

                    </div>

                </div>

            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="
                        btn
                        btn-secondary
                    "
                    data-modal-close
                >
                    Cancelar
                </button>

                <button
                    type="submit"
                    class="
                        btn
                        btn-primary
                    "
                >
                    Guardar consulta
                </button>

            </div>

        </form>

    </div>

</div>

<?php endif; ?>


<script
    src="<?= asset(
        'js/views/consultas.js'
    ) ?>"
></script>