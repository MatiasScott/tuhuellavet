<link
    rel="stylesheet"
    href="<?= asset(
        'css/views/vacunas.css'
    ) ?>"
>


<div class="page-heading">

    <div>

        <span class="eyebrow">
            Medicina preventiva
        </span>

        <h1>
            Vacunación
        </h1>

        <p>
            Registro y seguimiento
            del esquema de vacunación.
        </p>

    </div>


    <?php if (
        can('vacunas.crear')
    ): ?>

        <button
            type="button"
            class="btn btn-primary"
            data-modal-open="
                vaccination-create
            "
        >
            ＋ Nueva vacunación
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


<?php if (!empty($upcoming)): ?>

<section
    class="
        card
        preventive-alert-card
    "
>

    <div class="card-header">

        <div>

            <h2>
                ⏰ Próximas vacunaciones
            </h2>

            <p>
                Próximos 30 días
            </p>

        </div>

    </div>


    <div class="upcoming-preventive-grid">

        <?php foreach (
            array_slice(
                $upcoming,
                0,
                6
            )
            as $item
        ): ?>

            <article
                class="
                    upcoming-preventive-item
                "
            >

                <div
                    class="
                        preventive-icon
                    "
                >
                    💉
                </div>

                <div>

                    <strong>
                        <?= e(
                            $item[
                                'paciente'
                            ]
                            ?: 'Paciente'
                        ) ?>
                    </strong>

                    <span>
                        <?= e(
                            $item[
                                'vacuna'
                            ]
                        ) ?>
                    </span>

                </div>

                <time>
                    <?= e(
                        date(
                            'd/m/Y',
                            strtotime(
                                $item[
                                    'fecha_revacunacion'
                                ]
                            )
                        )
                    ) ?>
                </time>

            </article>

        <?php endforeach; ?>

    </div>

</section>

<?php endif; ?>


<section class="card">

    <form
        method="GET"
        class="toolbar"
        action="<?= url('/vacunas') ?>"
    >

        <div class="search-box">

            🔎

            <input
                name="q"
                type="search"
                value="<?= e($search) ?>"
                placeholder="
                    Buscar paciente,
                    vacuna, propietario o lote
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
        empty($vaccinations)
    ): ?>

        <div class="empty-state">

            <div class="empty-icon">
                💉
            </div>

            <h2>
                Sin vacunaciones
            </h2>

            <p>
                Todavía no existen
                vacunas registradas.
            </p>

        </div>

    <?php else: ?>

        <div class="table-wrap">

            <table class="modern-table">

                <thead>

                <tr>
                    <th>Fecha</th>
                    <th>Paciente</th>
                    <th>Vacuna</th>
                    <th>Dosis</th>
                    <th>Lote</th>
                    <th>Revacunación</th>
                    <th>Aplicada por</th>
                </tr>

                </thead>

                <tbody>

                <?php foreach (
                    $vaccinations
                    as $item
                ): ?>

                    <tr>

                        <td>
                            <?= e(
                                date(
                                    'd/m/Y H:i',
                                    strtotime(
                                        $item[
                                            'fecha_evento'
                                        ]
                                    )
                                )
                            ) ?>
                        </td>


                        <td>

                            <a
                                href="<?= url(
                                    '/pacientes/'
                                    . $item[
                                        'animal_id'
                                    ]
                                ) ?>"
                                class="entity-cell"
                            >

                                <div class="pet-avatar">
                                    🐾
                                </div>

                                <div>

                                    <strong>
                                        <?= e(
                                            $item[
                                                'paciente'
                                            ]
                                            ?: 'Sin nombre'
                                        ) ?>
                                    </strong>

                                    <small>
                                        <?= e(
                                            (
                                                $item[
                                                    'especie'
                                                ]
                                                ?? ''
                                            )
                                            . (
                                                $item[
                                                    'raza'
                                                ]
                                                    ? ' · '
                                                        . $item[
                                                            'raza'
                                                        ]
                                                    : ''
                                            )
                                        ) ?>
                                    </small>

                                </div>

                            </a>

                        </td>


                        <td>
                            <strong>
                                <?= e(
                                    $item[
                                        'vacuna'
                                    ]
                                ) ?>
                            </strong>
                        </td>


                        <td>

                            <?= $item['dosis']
                                !== null
                                ? e(
                                    $item['dosis']
                                )
                                    . ' '
                                    . e(
                                        $item[
                                            'unidad'
                                        ]
                                        ?? ''
                                    )
                                : '—'
                            ?>

                        </td>


                        <td>
                            <?= e(
                                $item['lote']
                                ?: '—'
                            ) ?>
                        </td>


                        <td>

                            <?php if (
                                $item[
                                    'fecha_revacunacion'
                                ]
                            ): ?>

                                <span
                                    class="
                                        status-badge
                                        status-info
                                    "
                                >
                                    <?= e(
                                        date(
                                            'd/m/Y',
                                            strtotime(
                                                $item[
                                                    'fecha_revacunacion'
                                                ]
                                            )
                                        )
                                    ) ?>
                                </span>

                            <?php else: ?>

                                —

                            <?php endif; ?>

                        </td>


                        <td>
                            <?= e(
                                $item[
                                    'aplicada_por_nombre'
                                ]
                            ) ?>
                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</section>

<?php if (
    can('vacunas.crear')
): ?>

<div
    class="modal"
    id="vaccination-create"
>

    <div class="modal-backdrop"></div>

    <div
        class="
            modal-dialog
            modal-xl
        "
    >

        <div class="modal-header">

            <div>

                <span class="eyebrow">
                    Medicina preventiva
                </span>

                <h2>
                    Registrar vacunación
                </h2>

            </div>

            <button
                type="button"
                class="modal-close"
                data-modal-close
            >
                ×
            </button>

        </div>


        <form
            action="<?= url('/vacunas') ?>"
            method="POST"
        >

            <?= csrf_field() ?>

            <div class="modal-body">


                <div class="preventive-section">

                    <h3>
                        🐾 Paciente
                    </h3>


                    <div class="form-grid">

                        <label class="field-full">

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
                                            $patient['id'] ?>"
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
                                type="datetime-local"
                                name="fecha_evento"
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


                <div class="preventive-section">

                    <h3>
                        ❤️ Examen clínico
                    </h3>


                    <div class="form-grid">

                        <label>
                            <span>Alimentación</span>
                            <input name="alimentacion">
                        </label>

                        <label>
                            <span>Historial reproductivo</span>
                            <input
                                name="
                                    historial_reproductivo
                                "
                            >
                        </label>

                        <label>
                            <span>Frecuencia cardiaca</span>
                            <input
                                type="number"
                                step="0.01"
                                name="
                                    frecuencia_cardiaca
                                "
                            >
                        </label>

                        <label>
                            <span>Frecuencia respiratoria</span>
                            <input
                                type="number"
                                step="0.01"
                                name="
                                    frecuencia_respiratoria
                                "
                            >
                        </label>

                        <label>
                            <span>Temperatura °C</span>
                            <input
                                type="number"
                                step="0.01"
                                name="temperatura_c"
                            >
                        </label>

                        <label>
                            <span>TLC (segundos)</span>
                            <input
                                type="number"
                                step="0.01"
                                name="
                                    tiempo_llenado_capilar_seg
                                "
                            >
                        </label>

                        <label>
                            <span>Ganglios linfáticos</span>
                            <input
                                name="
                                    ganglios_linfaticos
                                "
                            >
                        </label>

                        <label>
                            <span>Condición corporal</span>
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
                                <span>Vómitos</span>
                            </label>

                            <label>
                                <input
                                    type="checkbox"
                                    name="diarrea"
                                    value="1"
                                >
                                <span>Diarrea</span>
                            </label>

                            <label>
                                <input
                                    type="checkbox"
                                    name="tos"
                                    value="1"
                                >
                                <span>Tos</span>
                            </label>

                        </div>

                    </div>

                </div>


                <div class="preventive-section">

                    <h3>
                        💉 Vacuna
                    </h3>


                    <div class="form-grid">

                        <label class="field-full">

                            <span>
                                Vacuna *
                            </span>

                            <select
                                name="vacuna_id"
                                required
                            >

                                <option value="">
                                    Seleccionar
                                </option>

                                <?php foreach (
                                    $vaccines
                                    as $vaccine
                                ): ?>

                                    <option
                                        value="<?= (int)
                                            $vaccine[
                                                'id'
                                            ] ?>"
                                    >

                                        <?= e(
                                            $vaccine[
                                                'nombre'
                                            ]
                                        ) ?>

                                        <?= $vaccine[
                                            'laboratorio'
                                        ]
                                            ? ' · '
                                                . e(
                                                    $vaccine[
                                                        'laboratorio'
                                                    ]
                                                )
                                            : ''
                                        ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </label>


                        <label>

                            <span>
                                Dosis
                            </span>

                            <input
                                type="number"
                                name="dosis"
                                min="0.0001"
                                step="0.0001"
                            >

                        </label>


                        <label>

                            <span>
                                Unidad
                            </span>

                            <select
                                name="
                                    unidad_dosis_id
                                "
                            >

                                <option value="">
                                    Seleccionar
                                </option>

                                <?php foreach (
                                    $units
                                    as $unit
                                ): ?>

                                    <option
                                        value="<?= (int)
                                            $unit['id'] ?>"
                                    >
                                        <?= e(
                                            $unit['nombre']
                                        ) ?>

                                        <?= $unit[
                                            'simbolo'
                                        ]
                                            ? ' ('
                                                . e(
                                                    $unit[
                                                        'simbolo'
                                                    ]
                                                )
                                                . ')'
                                            : ''
                                        ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </label>


                        <label>

                            <span>
                                Lote
                            </span>

                            <input
                                name="lote"
                                maxlength="100"
                            >

                        </label>


                        <label>

                            <span>
                                Próxima vacunación
                            </span>

                            <input
                                type="date"
                                name="
                                    fecha_revacunacion
                                "
                            >

                        </label>


                        <label class="field-full">

                            <span>
                                Observaciones
                            </span>

                            <textarea
                                name="observaciones"
                                rows="3"
                            ></textarea>

                        </label>

                    </div>

                </div>

            </div>


            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-secondary"
                    data-modal-close
                >
                    Cancelar
                </button>

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Registrar vacunación
                </button>

            </div>

        </form>

    </div>

</div>

<?php endif; ?>


<script
    src="<?= asset(
        'js/views/vacunas.js'
    ) ?>"
></script>