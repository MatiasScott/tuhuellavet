<link
    rel="stylesheet"
    href="<?= asset(
        'css/views/propietarios.css'
    ) ?>"
>
<div class="page-heading">

    <div>

        <span class="eyebrow">
            Clientes
        </span>

        <h1>
            Propietarios
        </h1>

        <p>
            Administra clientes,
            sus animales y su acceso
            al portal.
        </p>

    </div>

    <?php if (
        can('propietarios.crear')
    ): ?>

        <button
            class="btn btn-primary"
            type="button"
            data-modal-open="owner-create"
        >
            ＋ Nuevo propietario
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


<?php

$temporaryPassword
    = \App\Core\Session::pullFlash(
        'temporary_password'
    );

?>


<?php if ($temporaryPassword): ?>

    <div class="temporary-password-card">

        <div>

            <strong>
                🔐 Contraseña temporal
            </strong>

            <p>
                Entrega esta contraseña
                al cliente. El sistema
                solicitará cambiarla
                al primer ingreso.
            </p>

        </div>

        <code>
            <?= e(
                $temporaryPassword
            ) ?>
        </code>

    </div>

<?php endif; ?>


<section class="card">

    <form
        class="toolbar"
        method="GET"
        action="<?= url(
            '/propietarios'
        ) ?>"
    >

        <div class="search-box">

            🔎

            <input
                type="search"
                name="q"
                value="<?= e($search) ?>"
                placeholder="
                    Buscar propietario
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
        empty($owners)
    ): ?>

        <div class="empty-state">

            <div class="empty-icon">
                👥
            </div>

            <h2>
                No existen propietarios
            </h2>

            <p>
                Registra el primer cliente.
            </p>

        </div>

    <?php else: ?>

        <div class="table-wrap">

            <table class="modern-table">

                <thead>

                <tr>

                    <th>
                        Propietario
                    </th>

                    <th>
                        Contacto
                    </th>

                    <th>
                        Identificación
                    </th>

                    <th>
                        Animales
                    </th>

                    <th>
                        Portal
                    </th>

                    <th></th>

                </tr>

                </thead>

                <tbody>

                <?php foreach (
                    $owners
                    as $owner
                ): ?>

                    <tr>

                        <td>

                            <div
                                class="entity-cell"
                            >

                                <div class="avatar">

                                    <?= e(
                                        strtoupper(
                                            substr(
                                                $owner[
                                                    'nombres'
                                                ],
                                                0,
                                                1
                                            )
                                        )
                                    ) ?>

                                </div>

                                <div>

                                    <strong>

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

                                    </strong>

                                    <small>

                                        <?= e(
                                            $owner[
                                                'email'
                                            ]
                                            ?? 'Sin correo'
                                        ) ?>

                                    </small>

                                </div>

                            </div>

                        </td>


                        <td>

                            <?= e(
                                $owner[
                                    'celular'
                                ]
                                ?: (
                                    $owner[
                                        'telefono'
                                    ]
                                    ?: '—'
                                )
                            ) ?>

                        </td>


                        <td>

                            <?= e(
                                $owner[
                                    'identificacion'
                                ]
                                ?: '—'
                            ) ?>

                        </td>


                        <td>

                            <span
                                class="
                                    status-badge
                                    status-info
                                "
                            >

                                <?= (int)
                                    $owner[
                                        'animales_count'
                                    ] ?>

                            </span>

                        </td>


                        <td>

                            <?php if (
                                $owner[
                                    'usuario_id'
                                ]
                            ): ?>

                                <span
                                    class="
                                        status-badge
                                        status-success
                                    "
                                >
                                    Activo
                                </span>

                            <?php else: ?>

                                <span
                                    class="
                                        status-badge
                                        status-neutral
                                    "
                                >
                                    Sin acceso
                                </span>

                            <?php endif; ?>

                        </td>


                        <td class="table-actions">

                            <?php if (
                                can(
                                    'propietarios.editar'
                                )
                            ): ?>

                                <button
                                    type="button"
                                    class="
                                        btn
                                        btn-small
                                        btn-secondary
                                    "
                                    data-owner-edit
                                    data-id="<?= (int)
                                        $owner['id'] ?>"
                                    data-tipo="<?= (int)
                                        ($owner[
                                            'tipo_identificacion_id'
                                        ] ?? 0) ?>"
                                    data-identificacion="<?= e(
                                        $owner[
                                            'identificacion'
                                        ] ?? ''
                                    ) ?>"
                                    data-nombres="<?= e(
                                        $owner[
                                            'nombres'
                                        ]
                                    ) ?>"
                                    data-apellidos="<?= e(
                                        $owner[
                                            'apellidos'
                                        ] ?? ''
                                    ) ?>"
                                    data-email="<?= e(
                                        $owner[
                                            'email'
                                        ] ?? ''
                                    ) ?>"
                                    data-telefono="<?= e(
                                        $owner[
                                            'telefono'
                                        ] ?? ''
                                    ) ?>"
                                    data-celular="<?= e(
                                        $owner[
                                            'celular'
                                        ] ?? ''
                                    ) ?>"
                                    data-direccion="<?= e(
                                        $owner[
                                            'direccion'
                                        ] ?? ''
                                    ) ?>"
                                >
                                    Editar
                                </button>

                            <?php endif; ?>


                            <?php if (
                                can(
                                    'propietarios.eliminar'
                                )
                            ): ?>

                                <form
                                    method="POST"
                                    action="<?= url(
                                        '/propietarios/'
                                        . $owner[
                                            'id'
                                        ]
                                    ) ?>"
                                    class="inline-form"
                                    data-confirm-form="
                                        ¿Deseas retirar este propietario del entorno?
                                    "
                                >

                                    <?= csrf_field() ?>

                                    <input
                                        type="hidden"
                                        name="_method"
                                        value="DELETE"
                                    >

                                    <button
                                        class="
                                            btn
                                            btn-small
                                            btn-danger-soft
                                        "
                                    >
                                        Eliminar
                                    </button>

                                </form>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</section>


<?php if (
    can('propietarios.crear')
): ?>

<div
    class="modal"
    id="owner-create"
>

    <div class="modal-backdrop"></div>

    <div class="modal-dialog modal-lg">

        <div class="modal-header">

            <div>

                <span class="eyebrow">
                    Nuevo cliente
                </span>

                <h2>
                    Crear propietario
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
                '/propietarios'
            ) ?>"
        >

            <?= csrf_field() ?>


            <div class="modal-body">

                <div class="form-grid">

                    <label>

                        <span>
                            Tipo identificación
                        </span>

                        <select
                            name="
                                tipo_identificacion_id
                            "
                        >

                            <option value="">
                                Seleccionar
                            </option>

                            <?php foreach (
                                $identificationTypes
                                as $type
                            ): ?>

                                <option
                                    value="<?= (int)
                                        $type['id'] ?>"
                                >
                                    <?= e(
                                        $type['nombre']
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </label>


                    <label>

                        <span>
                            Identificación
                        </span>

                        <input
                            name="identificacion"
                            maxlength="30"
                        >

                    </label>


                    <label>

                        <span>
                            Nombres *
                        </span>

                        <input
                            name="nombres"
                            required
                        >

                    </label>


                    <label>

                        <span>
                            Apellidos
                        </span>

                        <input
                            name="apellidos"
                        >

                    </label>


                    <label>

                        <span>
                            Correo
                        </span>

                        <input
                            type="email"
                            name="email"
                        >

                    </label>


                    <label>

                        <span>
                            Celular
                        </span>

                        <input
                            name="celular"
                        >

                    </label>


                    <label>

                        <span>
                            Teléfono
                        </span>

                        <input
                            name="telefono"
                        >

                    </label>


                    <label class="field-full">

                        <span>
                            Dirección
                        </span>

                        <input
                            name="direccion"
                        >

                    </label>

                </div>


                <label
                    class="
                        access-option
                        field-full
                    "
                >

                    <input
                        type="checkbox"
                        name="crear_acceso"
                        value="1"
                    >

                    <div>

                        <strong>
                            Crear acceso al portal
                        </strong>

                        <span>
                            Se creará o vinculará
                            automáticamente un usuario
                            con rol Cliente.
                        </span>

                    </div>

                </label>

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
                    Guardar propietario
                </button>

            </div>

        </form>

    </div>

</div>

<?php endif; ?>


<div
    class="modal"
    id="owner-edit"
>

    <div class="modal-backdrop"></div>

    <div class="modal-dialog modal-lg">

        <div class="modal-header">

            <div>

                <span class="eyebrow">
                    Cliente
                </span>

                <h2>
                    Editar propietario
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
            id="owner-edit-form"
        >

            <?= csrf_field() ?>

            <input
                type="hidden"
                name="_method"
                value="PUT"
            >

            <div class="modal-body">

                <div class="form-grid">

                    <label>

                        <span>
                            Tipo identificación
                        </span>

                        <select
                            name="
                                tipo_identificacion_id
                            "
                            id="edit-owner-type"
                        >

                            <option value="">
                                Seleccionar
                            </option>

                            <?php foreach (
                                $identificationTypes
                                as $type
                            ): ?>

                                <option
                                    value="<?= (int)
                                        $type['id'] ?>"
                                >
                                    <?= e(
                                        $type['nombre']
                                    ) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </label>

                    <label>
                        <span>Identificación</span>
                        <input
                            name="identificacion"
                            id="edit-owner-identification"
                        >
                    </label>

                    <label>
                        <span>Nombres *</span>
                        <input
                            name="nombres"
                            id="edit-owner-names"
                            required
                        >
                    </label>

                    <label>
                        <span>Apellidos</span>
                        <input
                            name="apellidos"
                            id="edit-owner-lastnames"
                        >
                    </label>

                    <label>
                        <span>Correo</span>
                        <input
                            type="email"
                            name="email"
                            id="edit-owner-email"
                        >
                    </label>

                    <label>
                        <span>Celular</span>
                        <input
                            name="celular"
                            id="edit-owner-mobile"
                        >
                    </label>

                    <label>
                        <span>Teléfono</span>
                        <input
                            name="telefono"
                            id="edit-owner-phone"
                        >
                    </label>

                    <label class="field-full">
                        <span>Dirección</span>
                        <input
                            name="direccion"
                            id="edit-owner-address"
                        >
                    </label>

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
                    Guardar cambios
                </button>

            </div>

        </form>

    </div>

</div>


<script
    src="<?= asset(
        'js/views/propietarios.js'
    ) ?>"
></script>