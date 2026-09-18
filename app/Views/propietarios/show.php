<?php

/**
 * Ficha individual del propietario.
 *
 * Variables:
 * $owner
 * $patients
 * $identificationTypes
 * $success
 * $error
 */

$ownerId = (int) $owner['id'];

$fullName = trim(
    (string) ($owner['nombres'] ?? '') . ' ' .
        (string) ($owner['apellidos'] ?? '')
);

$canEdit = can('propietarios.editar');

?>

<link
    rel="stylesheet"
    href="<?= asset('css/views/propietarios.css') ?>">


<!-- =====================================================
     ENCABEZADO
===================================================== -->

<div class="page-heading">

    <div>

        <span class="eyebrow">
            Clientes / Propietarios
        </span>

        <h1>
            <?= e($fullName) ?>
        </h1>

        <p>
            Expediente individual del propietario.
        </p>

    </div>


    <div class="inline-actions">

        <?php if ($canEdit): ?>

            <button
                type="button"
                class="btn btn-primary"
                data-modal-open="owner-edit">
                ✎ Editar propietario
            </button>

        <?php endif; ?>


        <a
            href="<?= url('/propietarios') ?>"
            class="btn btn-secondary">
            ← Propietarios
        </a>

    </div>

</div>


<!-- =====================================================
     MENSAJES
===================================================== -->

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


<!-- =====================================================
     DATOS GENERALES
===================================================== -->

<section class="card">

    <div class="card-header">

        <div>

            <h2>Información personal</h2>

            <p>
                Datos generales y de contacto.
            </p>

        </div>

    </div>


    <div class="metric-grid">

        <div class="metric-card">

            <span>Nombres</span>

            <strong>
                <?= e($owner['nombres'] ?? '—') ?>
            </strong>

        </div>


        <div class="metric-card">

            <span>Apellidos</span>

            <strong>
                <?= e($owner['apellidos'] ?: '—') ?>
            </strong>

        </div>


        <div class="metric-card">

            <span>Identificación</span>

            <strong>
                <?= e($owner['identificacion'] ?: '—') ?>
            </strong>

        </div>


        <div class="metric-card">

            <span>Correo electrónico</span>

            <strong>
                <?= e($owner['email'] ?: '—') ?>
            </strong>

        </div>


        <div class="metric-card">

            <span>Celular</span>

            <strong>
                <?= e($owner['celular'] ?: '—') ?>
            </strong>

        </div>


        <div class="metric-card">

            <span>Teléfono</span>

            <strong>
                <?= e($owner['telefono'] ?: '—') ?>
            </strong>

        </div>

    </div>


    <div class="soft-panel">

        <strong>Dirección</strong>

        <p>
            <?= e($owner['direccion'] ?: 'No registrada') ?>
        </p>

    </div>

</section>


<!-- =====================================================
     PACIENTES DEL PROPIETARIO
===================================================== -->

<section class="card">

    <div class="card-header">

        <div>

            <h2>Pacientes asociados</h2>

            <p>
                Animales registrados a nombre de este propietario.
            </p>

        </div>

        <span class="status-badge status-neutral">

            <?= count($patients) ?> paciente(s)

        </span>

    </div>


    <div class="table-wrap">

        <table class="modern-table">

            <thead>

                <tr>

                    <th>Paciente</th>

                    <th>Especie</th>

                    <th>Raza</th>

                    <th>Sexo</th>

                    <th>Acciones</th>

                </tr>

            </thead>


            <tbody>

                <?php if (empty($patients)): ?>

                    <tr>

                        <td colspan="5">

                            Este propietario todavía no tiene pacientes registrados.

                        </td>

                    </tr>

                <?php else: ?>

                    <?php foreach ($patients as $patient): ?>

                        <tr>

                            <td>

                                <strong>
                                    <?= e($patient['nombre'] ?? '—') ?>
                                </strong>

                                <small>
                                    <?= e($patient['codigo'] ?? '') ?>
                                </small>

                            </td>


                            <td>
                                <?= e($patient['especie'] ?? '—') ?>
                            </td>


                            <td>
                                <?= e($patient['raza'] ?: '—') ?>
                            </td>


                            <td>
                                <?= e($patient['sexo'] ?: '—') ?>
                            </td>


                            <td>

                                <?php if (can('pacientes.ver')): ?>

                                    <a
                                        class="btn btn-secondary btn-sm"
                                        href="<?= url(
                                                    '/pacientes/' .
                                                        (int) $patient['id']
                                                ) ?>">
                                        Ver expediente
                                    </a>

                                <?php else: ?>

                                    —

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>


<!-- =====================================================
     MODAL: EDITAR PROPIETARIO
===================================================== -->

<?php if ($canEdit): ?>

    <div
        class="modal"
        id="owner-edit">

        <div class="modal-backdrop"></div>


        <div class="modal-dialog modal-lg">

            <div class="modal-header">

                <h2>Editar propietario</h2>

                <button
                    type="button"
                    class="modal-close"
                    data-modal-close
                    aria-label="Cerrar">
                    ×
                </button>

            </div>


            <form
                method="POST"
                action="<?= url('/propietarios/' . $ownerId) ?>"
                class="owner-validation-form"
                data-validation-url="<?= url('/propietarios/validar') ?>"
                data-owner-id="<?= $ownerId ?>">

                <?= csrf_field() ?>

                <input
                    type="hidden"
                    name="_method"
                    value="PUT">


                <div class="modal-body">

                    <div class="form-grid">


                        <!-- Nombres -->

                        <label>

                            <span>Nombres *</span>

                            <input
                                type="text"
                                name="nombres"
                                value="<?= e($owner['nombres'] ?? '') ?>"
                                required>

                        </label>


                        <!-- Apellidos -->

                        <label>

                            <span>Apellidos</span>

                            <input
                                type="text"
                                name="apellidos"
                                value="<?= e($owner['apellidos'] ?? '') ?>">

                        </label>


                        <!-- Tipo identificación -->

                        <label>

                            <span>Tipo de identificación</span>

                            <select name="tipo_identificacion_id">

                                <option value="">
                                    Seleccionar
                                </option>

                                <?php foreach ($identificationTypes as $type): ?>

                                    <option
                                        value="<?= (int) $type['id'] ?>"
                                        <?= (int) ($owner['tipo_identificacion_id'] ?? 0)
                                            === (int) $type['id']
                                            ? 'selected'
                                            : '' ?>>

                                        <?= e($type['nombre']) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </label>


                        <!-- Identificación -->

                        <label>
                            <span>Identificación</span>

                            <input
                                type="text"
                                name="identificacion"
                                maxlength="30"
                                value="<?= e($owner['identificacion'] ?? '') ?>"
                                data-owner-field="identificacion">

                            <span
                                class="owner-field-message"
                                data-message-for="identificacion"
                                aria-live="polite"></span>
                        </label>


                        <!-- Correo -->

                        <label>

                            <span>Correo electrónico</span>

                            <input
                                type="email"
                                name="email"
                                value="<?= e($owner['email'] ?? '') ?>"
                                data-owner-field="email">

                        </label>


                        <!-- Celular -->

                        <label>

                            <span>Celular</span>

                            <input
                                type="tel"
                                name="celular"
                                value="<?= e($owner['celular'] ?? '') ?>"
                                data-owner-field="celular">

                        </label>


                        <!-- Teléfono -->

                        <label>

                            <span>Teléfono</span>

                            <input
                                type="tel"
                                name="telefono"
                                value="<?= e($owner['telefono'] ?? '') ?>"
                                data-owner-field="telefono">

                        </label>


                        <!-- Dirección -->

                        <label class="field-full">

                            <span>Dirección</span>

                            <input
                                type="text"
                                name="direccion"
                                value="<?= e($owner['direccion'] ?? '') ?>">

                        </label>


                    </div>

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
                        Guardar cambios
                    </button>

                </div>

            </form>

        </div>

    </div>

<?php endif; ?>