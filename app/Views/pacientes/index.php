<link
    rel="stylesheet"
    href="<?= asset(
        'css/views/pacientes.css'
    ) ?>"
>


<div class="page-heading">

    <div>

        <span class="eyebrow">
            Clínica
        </span>

        <h1>
            Pacientes
        </h1>

        <p>
            Expedientes de animales
            registrados en
            <?= e(
                active_environment()[
                    'nombre'
                ]
                ?? 'este entorno'
            ) ?>.
        </p>

    </div>


    <?php if (
        can('pacientes.crear')
    ): ?>

        <button
            class="btn btn-primary"
            type="button"
            data-modal-open="
                patient-create
            "
        >
            ＋ Nuevo paciente
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
        action="<?= url(
            '/pacientes'
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
                    propietario,
                    especie o raza
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
        empty($patients)
    ): ?>

        <div class="empty-state">

            <div class="empty-icon">
                🐾
            </div>

            <h2>
                Aún no existen pacientes
            </h2>

            <p>
                Registra el primer animal
                de este entorno.
            </p>

        </div>

    <?php else: ?>

        <div class="patient-grid">

            <?php foreach (
                $patients
                as $patient
            ): ?>

                <article
                    class="patient-card"
                >

                    <div
                        class="
                            patient-card-photo
                        "
                    >

                        <?php if (
                            !empty(
                                $patient[
                                    'foto_principal_path'
                                ]
                            )
                        ): ?>

                            <img
                                src="<?= e(
                                    patient_photo_url(
                                        $patient[
                                            'foto_principal_path'
                                        ]
                                    )
                                ) ?>"
                                alt="<?= e(
                                    $patient[
                                        'nombre'
                                    ]
                                    ?: 'Paciente'
                                ) ?>"
                            >

                        <?php else: ?>

                            <span>
                                <?= (
                                    $patient[
                                        'especie'
                                    ] ?? ''
                                ) === 'Gato'
                                    ? '🐱'
                                    : '🐾'
                                ?>
                            </span>

                        <?php endif; ?>

                    </div>


                    <div
                        class="
                            patient-card-body
                        "
                    >

                        <div>

                            <span
                                class="
                                    patient-species
                                "
                            >
                                <?= e(
                                    $patient[
                                        'especie'
                                    ]
                                ) ?>
                            </span>

                            <h3>

                                <?= e(
                                    $patient[
                                        'nombre'
                                    ]
                                    ?: 'Sin nombre'
                                ) ?>

                            </h3>

                            <p>

                                <?= e(
                                    $patient[
                                        'raza'
                                    ]
                                    ?: 'Sin raza registrada'
                                ) ?>

                            </p>

                        </div>


                        <div
                            class="
                                patient-meta
                            "
                        >

                            <span>
                                ⚖️
                                <?= $patient[
                                    'peso_actual'
                                ] !== null
                                    ? e(
                                        $patient[
                                            'peso_actual'
                                        ]
                                    )
                                        . ' kg'
                                    : 'Sin peso'
                                ?>
                            </span>

                            <span>
                                👤
                                <?= e(
                                    trim(
                                        (
                                            $patient[
                                                'propietario_nombres'
                                            ] ?? ''
                                        )
                                        . ' '
                                        . (
                                            $patient[
                                                'propietario_apellidos'
                                            ] ?? ''
                                        )
                                    )
                                    ?: 'Sin propietario'
                                ) ?>
                            </span>

                        </div>


                        <a
                            href="<?= url(
                                '/pacientes/'
                                . $patient[
                                    'id'
                                ]
                            ) ?>"
                            class="
                                btn
                                btn-secondary
                                btn-block
                            "
                        >
                            Ver expediente
                        </a>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</section>


<?php if (
    can('pacientes.crear')
): ?>

<div
    class="modal"
    id="patient-create"
>

    <div
        class="modal-backdrop"
    ></div>

    <div
        class="
            modal-dialog
            modal-lg
        "
    >

        <div class="modal-header">

            <div>

                <span class="eyebrow">
                    Nuevo paciente
                </span>

                <h2>
                    Crear ficha animal
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
            action="<?= url(
                '/pacientes'
            ) ?>"
            method="POST"
            enctype="
                multipart/form-data
            "
        >

            <?= csrf_field() ?>


            <div class="modal-body">

                <div class="form-grid">


                    <label>

                        <span>
                            Propietario
                        </span>

                        <select
                            name="
                                propietario_entorno_id
                            "
                        >

                            <option value="">
                                Sin propietario
                            </option>

                            <?php foreach (
                                $owners
                                as $owner
                            ): ?>

                                <option
                                    value="<?= (int)
                                        $owner[
                                            'propietario_entorno_id'
                                        ] ?>"
                                >

                                    <?= e(
                                        trim(
                                            $owner[
                                                'nombres'
                                            ]
                                            . ' '
                                            . (
                                                $owner[
                                                    'apellidos'
                                                ]
                                                ?? ''
                                            )
                                        )
                                    ) ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </label>


                    <label>

                        <span>
                            Nombre
                        </span>

                        <input
                            name="nombre"
                            maxlength="150"
                        >

                    </label>


                    <label>

                        <span>
                            Especie *
                        </span>

                        <select
                            name="especie_id"
                            id="
                                create-species
                            "
                            required
                        >

                            <option value="">
                                Seleccionar
                            </option>

                            <?php foreach (
                                $species
                                as $item
                            ): ?>

                                <option
                                    value="<?= (int)
                                        $item['id'] ?>"
                                >
                                    <?= e(
                                        $item[
                                            'nombre_comun'
                                        ]
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </label>


                    <label>

                        <span>
                            Raza
                        </span>

                        <select
                            name="raza_id"
                            id="
                                create-breed
                            "
                        >

                            <option value="">
                                Seleccionar
                            </option>

                            <?php foreach (
                                $breeds
                                as $breed
                            ): ?>

                                <option
                                    value="<?= (int)
                                        $breed['id'] ?>"
                                    data-species="<?= (int)
                                        $breed[
                                            'especie_id'
                                        ] ?>"
                                >
                                    <?= e(
                                        $breed['nombre']
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </label>


                    <label>

                        <span>
                            Sexo
                        </span>

                        <select
                            name="sexo_id"
                        >

                            <option value="">
                                Seleccionar
                            </option>

                            <?php foreach (
                                $sexes
                                as $sex
                            ): ?>

                                <option
                                    value="<?= (int)
                                        $sex['id'] ?>"
                                >
                                    <?= e(
                                        $sex['nombre']
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </label>


                    <label>

                        <span>
                            Color
                        </span>

                        <input
                            name="color"
                            maxlength="120"
                        >

                    </label>


                    <label>

                        <span>
                            Fecha nacimiento
                        </span>

                        <input
                            type="date"
                            name="
                                fecha_nacimiento
                            "
                            id="
                                create-birthdate
                            "
                        >

                        <small
                            id="
                                create-age-preview
                            "
                            class="
                                field-helper
                            "
                        ></small>

                    </label>


                    <label
                        class="
                            checkbox-field
                        "
                    >

                        <input
                            type="checkbox"
                            name="
                                fecha_nacimiento_aproximada
                            "
                            value="1"
                        >

                        <span>
                            Fecha aproximada
                        </span>

                    </label>


                    <label>

                        <span>
                            Peso inicial (kg)
                        </span>

                        <input
                            type="number"
                            name="peso_kg"
                            min="0.001"
                            step="0.001"
                        >

                    </label>


                    <label>

                        <span>
                            Código interno
                        </span>

                        <input
                            name="codigo"
                            maxlength="80"
                        >

                    </label>


                    <label>

                        <span>
                            Microchip
                        </span>

                        <input
                            name="microchip"
                            maxlength="100"
                        >

                    </label>


                    <label>

                        <span>
                            Arete
                        </span>

                        <input
                            name="arete"
                            maxlength="100"
                        >

                    </label>


                    <label
                        class="field-full"
                    >

                        <span>
                            Fotografía
                        </span>

                        <input
                            type="file"
                            name="foto"
                            accept="
                                image/jpeg,
                                image/png,
                                image/webp
                            "
                        >

                        <small
                            class="
                                field-helper
                            "
                        >
                            JPG, PNG o WEBP.
                            Máximo 5 MB.
                        </small>

                    </label>


                    <label
                        class="field-full"
                    >

                        <span>
                            Observaciones
                        </span>

                        <textarea
                            name="
                                observaciones
                            "
                            rows="3"
                        ></textarea>

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
                    Crear paciente
                </button>

            </div>

        </form>

    </div>

</div>

<?php endif; ?>


<script
    src="<?= asset(
        'js/views/pacientes.js'
    ) ?>"
></script>