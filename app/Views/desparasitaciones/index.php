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
            Desparasitación
        </h1>

        <p>
            Registro y seguimiento
            antiparasitario.
        </p>

    </div>


    <?php if (
        can(
            'desparasitacion.crear'
        )
    ): ?>

        <button
            type="button"
            class="btn btn-primary"
            data-modal-open="
                deworming-create
            "
        >
            ＋ Nueva desparasitación
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

<section class="card preventive-alert-card">

    <div class="card-header">

        <div>

            <h2>
                ⏰ Próximas desparasitaciones
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
                    class="preventive-icon"
                >
                    💊
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
                                'farmaco'
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
                                    'proxima_desparasitacion'
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
        class="toolbar"
        method="GET"
        action="<?= url(
            '/desparasitaciones'
        ) ?>"
    >

        <div class="search-box">

            🔎

            <input
                type="search"
                name="q"
                value="<?= e($search) ?>"
                placeholder="
                    Buscar paciente,
                    fármaco o propietario
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
        empty($dewormings)
    ): ?>

        <div class="empty-state">

            <div class="empty-icon">
                💊
            </div>

            <h2>
                Sin desparasitaciones
            </h2>

        </div>

    <?php else: ?>

        <div class="table-wrap">

            <table class="modern-table">

                <thead>

                <tr>
                    <th>Fecha</th>
                    <th>Paciente</th>
                    <th>Fármaco</th>
                    <th>Dosis</th>
                    <th>Próxima</th>
                    <th>Aplicada por</th>
                </tr>

                </thead>

                <tbody>

                <?php foreach (
                    $dewormings
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
                                class="text-link"
                            >
                                <?= e(
                                    $item[
                                        'paciente'
                                    ]
                                    ?: 'Sin nombre'
                                ) ?>
                            </a>

                        </td>

                        <td>
                            <?= e(
                                $item[
                                    'farmaco'
                                ]
                            ) ?>
                        </td>

                        <td>

                            <?= $item['dosis']
                                !== null
                                ? e(
                                    $item[
                                        'dosis'
                                    ]
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

                            <?= $item[
                                'proxima_desparasitacion'
                            ]
                                ? e(
                                    date(
                                        'd/m/Y',
                                        strtotime(
                                            $item[
                                                'proxima_desparasitacion'
                                            ]
                                        )
                                    )
                                )
                                : '—'
                            ?>

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
    can(
        'desparasitacion.crear'
    )
): ?>

<div
    class="modal"
    id="deworming-create"
>

    <div class="modal-backdrop"></div>

    <div
        class="
            modal-dialog
            modal-lg
        "
    >

        <div class="modal-header">

            <div>

                <span class="eyebrow">
                    Medicina preventiva
                </span>

                <h2>
                    Nueva desparasitación
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
            method="POST"
            action="<?= url(
                '/desparasitaciones'
            ) ?>"
        >

            <?= csrf_field() ?>


            <div class="modal-body">

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


                    <label class="field-full">

                        <span>
                            Fármaco *
                        </span>

                        <select
                            name="farmaco_id"
                            required
                        >

                            <option value="">
                                Seleccionar
                            </option>

                            <?php foreach (
                                $drugs
                                as $drug
                            ): ?>

                                <option
                                    value="<?= (int)
                                        $drug['id'] ?>"
                                >
                                    <?= e(
                                        $drug[
                                            'nombre'
                                        ]
                                    ) ?>
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
                            Próxima desparasitación
                        </span>

                        <input
                            type="date"
                            name="
                                proxima_desparasitacion
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


            <div class="modal-footer">

                <button
                    class="btn btn-secondary"
                    type="button"
                    data-modal-close
                >
                    Cancelar
                </button>

                <button
                    class="btn btn-primary"
                    type="submit"
                >
                    Registrar
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