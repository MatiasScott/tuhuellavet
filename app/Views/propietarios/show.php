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
     DATOS FISCALES
===================================================== -->

<section class="card owner-fiscal-card">

    <div class="card-header">

        <div>
            <h2>Datos fiscales</h2>

            <p>
                Información disponible para facturación
                y documentos fiscales.
            </p>
        </div>

        <?php if ($canEdit): ?>
            <button
                type="button"
                class="btn btn-primary"
                data-modal-open="fiscal-create">
                ＋ Agregar dato fiscal
            </button>
        <?php endif; ?>

    </div>


    <?php if (empty($fiscalData)): ?>

        <div class="soft-panel owner-fiscal-empty">

            <strong>
                No hay datos fiscales registrados
            </strong>

            <p>
                Este propietario todavía no tiene información
                fiscal disponible para facturación.
            </p>

            <?php if ($canEdit): ?>
                <button
                    type="button"
                    class="btn btn-secondary btn-sm"
                    data-modal-open="fiscal-create">
                    Agregar datos fiscales
                </button>
            <?php endif; ?>

        </div>

    <?php else: ?>

        <div class="owner-fiscal-grid">

            <?php foreach ($fiscalData as $fiscal): ?>

                <?php
                $isPrincipal =
                    (int) ($fiscal['es_principal'] ?? 0) === 1;

                $isActive =
                    (int) ($fiscal['activo'] ?? 0) === 1;
                ?>

                <article
                    class="owner-fiscal-item <?= !$isActive ? 'is-inactive' : '' ?>">

                    <div class="owner-fiscal-item-header">

                        <div class="owner-fiscal-badges">

                            <?php if ($isPrincipal): ?>
                                <span class="status-badge status-success">
                                    Principal
                                </span>
                            <?php endif; ?>

                            <?php if ($isActive): ?>
                                <span class="status-badge status-neutral">
                                    Activo
                                </span>
                            <?php else: ?>
                                <span class="status-badge status-danger">
                                    Inactivo
                                </span>
                            <?php endif; ?>

                        </div>

                        <?php if ($canEdit): ?>

                            <div class="inline-actions">

                                <button
                                    type="button"
                                    class="btn btn-secondary btn-sm"
                                    data-modal-open="fiscal-edit-<?= (int) $fiscal['id'] ?>">
                                    Editar
                                </button>

                                <form
                                    method="POST"
                                    action="<?= url(
                                                '/propietarios/' .
                                                    $ownerId .
                                                    '/datos-fiscales/' .
                                                    (int) $fiscal['id'] .
                                                    '/estado'
                                            ) ?>"
                                    class="inline-form">

                                    <?= csrf_field() ?>

                                    <button
                                        type="submit"
                                        class="btn btn-secondary btn-sm">
                                        <?= $isActive
                                            ? 'Desactivar'
                                            : 'Activar' ?>
                                    </button>

                                </form>

                            </div>

                        <?php endif; ?>

                    </div>


                    <div class="owner-fiscal-main">

                        <span class="owner-fiscal-label">
                            Razón social
                        </span>

                        <strong>
                            <?= e(
                                $fiscal['razon_social']
                                    ?? '—'
                            ) ?>
                        </strong>

                    </div>


                    <div class="owner-fiscal-details">

                        <div>
                            <span>Identificación</span>

                            <strong>
                                <?= e(
                                    $fiscal['identificacion']
                                        ?? '—'
                                ) ?>
                            </strong>
                        </div>

                        <div>
                            <span>Correo</span>

                            <strong>
                                <?= e(
                                    $fiscal['email']
                                        ?: '—'
                                ) ?>
                            </strong>
                        </div>

                        <div>
                            <span>Teléfono</span>

                            <strong>
                                <?= e(
                                    $fiscal['telefono']
                                        ?: '—'
                                ) ?>
                            </strong>
                        </div>

                        <div>
                            <span>Dirección</span>

                            <strong>
                                <?= e(
                                    $fiscal['direccion']
                                        ?: '—'
                                ) ?>
                            </strong>
                        </div>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

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
                        <!-- PRIMER NOMBRE / NOMBRES -->
                        <label>
                            <span>Primer nombre / Nombres *</span>
                            <input
                                type="text"
                                name="nombres"
                                maxlength="120"
                                value="<?= e($owner['nombres'] ?? '') ?>"
                                required>
                        </label>

                        <!-- SEGUNDO NOMBRE -->
                        <label>
                            <span>Segundo nombre</span>
                            <input
                                type="text"
                                name="segundo_nombre"
                                maxlength="120"
                                value="<?= e($owner['segundo_nombre'] ?? '') ?>">
                        </label>

                        <!-- APELLIDO PATERNO -->
                        <label>
                            <span>Apellido paterno</span>
                            <input
                                type="text"
                                name="apellidos"
                                maxlength="120"
                                value="<?= e($owner['apellidos'] ?? '') ?>">
                        </label>

                        <!-- APELLIDO MATERNO -->
                        <label>
                            <span>Apellido materno</span>
                            <input
                                type="text"
                                name="apellido_materno"
                                maxlength="120"
                                value="<?= e($owner['apellido_materno'] ?? '') ?>">
                        </label>

                        <!-- TIPO IDENTIFICACIÓN -->
                        <label>
                            <span>Tipo de identificación *</span>

                            <select
                                name="tipo_identificacion_id"
                                data-owner-type
                                required>

                                <option value="">Seleccionar</option>

                                <?php foreach ($identificationTypes as $type): ?>
                                    <option
                                        value="<?= (int) $type['id'] ?>"
                                        data-code="<?= e($type['codigo']) ?>"
                                        <?= (int) ($owner['tipo_identificacion_id'] ?? 0)
                                            === (int) $type['id']
                                            ? 'selected'
                                            : '' ?>>
                                        <?= e($type['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>

                            </select>
                        </label>

                        <!-- NATURAL / JURÍDICA -->
                        <label
                            data-owner-natural-container
                            <?= (int) ($owner['tipo_identificacion_id'] ?? 0) === 2
                                ? ''
                                : 'hidden' ?>>

                            <span>Tipo de persona *</span>

                            <select
                                name="es_persona_natural"
                                data-owner-natural
                                <?= (int) ($owner['tipo_identificacion_id'] ?? 0) === 2
                                    ? 'required'
                                    : 'disabled' ?>>

                                <option value="">Seleccionar</option>

                                <option
                                    value="1"
                                    <?= (string) ($owner['es_persona_natural'] ?? '') === '1'
                                        ? 'selected'
                                        : '' ?>>
                                    Persona natural
                                </option>

                                <option
                                    value="0"
                                    <?= (string) ($owner['es_persona_natural'] ?? '') === '0'
                                        ? 'selected'
                                        : '' ?>>
                                    Persona jurídica
                                </option>

                            </select>
                        </label>

                        <!-- IDENTIFICACIÓN -->
                        <label>
                            <span>Identificación *</span>

                            <input
                                type="text"
                                name="identificacion"
                                maxlength="30"
                                value="<?= e($owner['identificacion'] ?? '') ?>"
                                autocomplete="off"
                                data-owner-field="identificacion"
                                required>

                            <span
                                class="owner-field-message"
                                data-message-for="identificacion"
                                aria-live="polite"></span>
                        </label>

                        <!-- CORREO -->
                        <label>
                            <span>Correo electrónico *</span>

                            <input
                                type="email"
                                name="email"
                                maxlength="180"
                                value="<?= e($owner['email'] ?? '') ?>"
                                autocomplete="email"
                                data-owner-field="email"
                                required>

                            <span
                                class="owner-field-message"
                                data-message-for="email"
                                aria-live="polite"></span>
                        </label>

                        <!-- CELULAR -->
                        <label>
                            <span>Celular</span>

                            <input
                                type="tel"
                                name="celular"
                                maxlength="30"
                                value="<?= e($owner['celular'] ?? '') ?>"
                                autocomplete="tel"
                                data-owner-field="celular">

                            <span
                                class="owner-field-message"
                                data-message-for="celular"
                                aria-live="polite"></span>
                        </label>

                        <!-- TELÉFONO -->
                        <label>
                            <span>Teléfono</span>

                            <input
                                type="tel"
                                name="telefono"
                                maxlength="30"
                                value="<?= e($owner['telefono'] ?? '') ?>">
                        </label>

                        <!-- DIRECCIÓN -->
                        <label class="field-full">
                            <span>Dirección</span>

                            <input
                                type="text"
                                name="direccion"
                                maxlength="255"
                                value="<?= e($owner['direccion'] ?? '') ?>">
                        </label>

                        <!-- REFERENCIA -->
                        <label class="field-full">
                            <span>Referencia</span>

                            <input
                                type="text"
                                name="referencia"
                                maxlength="250"
                                value="<?= e($owner['referencia'] ?? '') ?>">
                        </label>

                        <!-- PAÍS -->
                        <label>
                            <span>País</span>

                            <select
                                name="codigo_pais"
                                data-location-country
                                data-selected="<?= e($owner['codigo_pais'] ?? '') ?>"
                                data-provinces-url="<?= url('/propietarios/ubicacion/provincias') ?>">

                                <option value="">Seleccionar</option>

                                <?php foreach (($countries ?? []) as $country): ?>
                                    <option
                                        value="<?= e($country['codigo']) ?>"
                                        <?= ($owner['codigo_pais'] ?? '') === $country['codigo']
                                            ? 'selected'
                                            : '' ?>>
                                        <?= e($country['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>

                            </select>
                        </label>

                        <!-- PROVINCIA -->
                        <label>
                            <span>Provincia</span>

                            <select
                                name="codigo_provincia"
                                data-location-province
                                data-selected="<?= e($owner['codigo_provincia'] ?? '') ?>"
                                data-cantons-url="<?= url('/propietarios/ubicacion/cantones') ?>">

                                <option value="">Seleccionar provincia</option>

                            </select>
                        </label>

                        <!-- CANTÓN -->
                        <label>
                            <span>Cantón</span>

                            <select
                                name="codigo_canton"
                                data-location-canton
                                data-selected="<?= e($owner['codigo_canton'] ?? '') ?>"
                                data-parishes-url="<?= url('/propietarios/ubicacion/parroquias') ?>">

                                <option value="">Seleccionar cantón</option>

                            </select>
                        </label>

                        <!-- PARROQUIA -->
                        <label>
                            <span>Parroquia</span>

                            <select
                                name="codigo_parroquia"
                                data-location-parish
                                data-selected="<?= e($owner['codigo_parroquia'] ?? '') ?>">

                                <option value="">Seleccionar parroquia</option>

                            </select>
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
<?php if ($canEdit): ?>

    <!-- =================================================
         MODAL: CREAR DATO FISCAL
    ================================================== -->

    <div
        class="modal"
        id="fiscal-create">

        <div class="modal-backdrop"></div>

        <div class="modal-dialog modal-lg">

            <div class="modal-header">

                <div>
                    <h2>Agregar datos fiscales</h2>
                    <p>
                        Información de facturación de
                        <?= e($fullName) ?>.
                    </p>
                </div>

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
                action="<?= url(
                            '/propietarios/' .
                                $ownerId .
                                '/datos-fiscales'
                        ) ?>"
                data-fiscal-create-form>

                <?= csrf_field() ?>


                <div class="modal-body">

                    <label class="owner-fiscal-same">

                        <input
                            type="checkbox"
                            checked
                            data-copy-owner-fiscal>

                        <span>
                            <strong>
                                Usar datos del propietario
                            </strong>

                            <small>
                                Copiaremos identificación, nombre,
                                dirección, correo y teléfono.
                                Podrás modificarlos antes de guardar.
                            </small>
                        </span>

                    </label>


                    <div class="form-grid">

                        <label>

                            <span>
                                Tipo de identificación *
                            </span>

                            <select
                                name="tipo_identificacion_id"
                                required
                                data-fiscal-type>

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


                        <label>

                            <span>
                                Identificación *
                            </span>

                            <input
                                type="text"
                                name="identificacion"
                                maxlength="30"
                                value="<?= e(
                                            $owner['identificacion']
                                                ?? ''
                                        ) ?>"
                                required
                                data-fiscal-identification>

                        </label>


                        <label class="field-full">

                            <span>
                                Razón social *
                            </span>

                            <input
                                type="text"
                                name="razon_social"
                                maxlength="200"
                                value="<?= e($fullName) ?>"
                                required
                                data-fiscal-business-name>

                        </label>


                        <label class="field-full">

                            <span>Dirección</span>

                            <input
                                type="text"
                                name="direccion"
                                maxlength="255"
                                value="<?= e(
                                            $owner['direccion']
                                                ?? ''
                                        ) ?>"
                                data-fiscal-address>

                        </label>


                        <label>

                            <span>
                                Correo de facturación
                            </span>

                            <input
                                type="email"
                                name="email"
                                maxlength="180"
                                value="<?= e(
                                            $owner['email']
                                                ?? ''
                                        ) ?>"
                                data-fiscal-email>

                        </label>


                        <label>

                            <span>Teléfono</span>

                            <input
                                type="text"
                                name="telefono"
                                maxlength="30"
                                value="<?= e(
                                            $owner['celular']
                                                ?: ($owner['telefono'] ?? '')
                                        ) ?>"
                                data-fiscal-phone>

                        </label>


                        <div class="field-full">

                            <label class="owner-fiscal-same">

                                <input
                                    type="checkbox"
                                    name="es_principal"
                                    value="1"
                                    checked>

                                <span>
                                    <strong>
                                        Dato fiscal principal
                                    </strong>

                                    <small>
                                        Se seleccionará por defecto
                                        al realizar una venta.
                                    </small>
                                </span>

                            </label>

                        </div>

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
                        Guardar datos fiscales
                    </button>

                </div>

            </form>

        </div>

    </div>
    <!-- =================================================
         MODALES: EDITAR DATOS FISCALES
    ================================================== -->

    <?php foreach ($fiscalData as $fiscal): ?>

        <div
            class="modal"
            id="fiscal-edit-<?= (int) $fiscal['id'] ?>">

            <div class="modal-backdrop"></div>

            <div class="modal-dialog modal-lg">

                <div class="modal-header">

                    <div>
                        <h2>Editar datos fiscales</h2>

                        <p>
                            <?= e(
                                $fiscal['razon_social']
                                    ?? ''
                            ) ?>
                        </p>
                    </div>

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
                    action="<?= url(
                                '/propietarios/' .
                                    $ownerId .
                                    '/datos-fiscales/' .
                                    (int) $fiscal['id'] .
                                    '/actualizar'
                            ) ?>">

                    <?= csrf_field() ?>


                    <div class="modal-body">

                        <div class="form-grid">

                            <label>

                                <span>
                                    Tipo de identificación *
                                </span>

                                <select
                                    name="tipo_identificacion_id"
                                    required>

                                    <option value="">
                                        Seleccionar
                                    </option>

                                    <?php foreach ($identificationTypes as $type): ?>

                                        <option
                                            value="<?= (int) $type['id'] ?>"
                                            <?= (int) $fiscal['tipo_identificacion_id']
                                                === (int) $type['id']
                                                ? 'selected'
                                                : '' ?>>

                                            <?= e($type['nombre']) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </label>


                            <label>

                                <span>
                                    Identificación *
                                </span>

                                <input
                                    type="text"
                                    name="identificacion"
                                    maxlength="30"
                                    value="<?= e(
                                                $fiscal['identificacion']
                                                    ?? ''
                                            ) ?>"
                                    required>

                            </label>


                            <label class="field-full">

                                <span>
                                    Razón social *
                                </span>

                                <input
                                    type="text"
                                    name="razon_social"
                                    maxlength="200"
                                    value="<?= e(
                                                $fiscal['razon_social']
                                                    ?? ''
                                            ) ?>"
                                    required>

                            </label>


                            <label class="field-full">

                                <span>Dirección</span>

                                <input
                                    type="text"
                                    name="direccion"
                                    maxlength="255"
                                    value="<?= e(
                                                $fiscal['direccion']
                                                    ?? ''
                                            ) ?>">

                            </label>


                            <label>

                                <span>
                                    Correo de facturación
                                </span>

                                <input
                                    type="email"
                                    name="email"
                                    maxlength="180"
                                    value="<?= e(
                                                $fiscal['email']
                                                    ?? ''
                                            ) ?>">

                            </label>


                            <label>

                                <span>Teléfono</span>

                                <input
                                    type="text"
                                    name="telefono"
                                    maxlength="30"
                                    value="<?= e(
                                                $fiscal['telefono']
                                                    ?? ''
                                            ) ?>">

                            </label>


                            <div class="field-full">

                                <label class="owner-fiscal-same">

                                    <input
                                        type="checkbox"
                                        name="es_principal"
                                        value="1"
                                        <?= (int) ($fiscal['es_principal'] ?? 0) === 1
                                            ? 'checked'
                                            : '' ?>>

                                    <span>
                                        <strong>
                                            Dato fiscal principal
                                        </strong>

                                        <small>
                                            Si seleccionas esta opción,
                                            los demás dejarán de ser
                                            principales.
                                        </small>
                                    </span>

                                </label>

                            </div>

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

    <?php endforeach; ?>

<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {

        const form = document.querySelector(
            '[data-fiscal-create-form]'
        );

        if (!form) {
            return;
        }

        const copyCheckbox = form.querySelector(
            '[data-copy-owner-fiscal]'
        );

        const fields = {
            type: form.querySelector(
                '[data-fiscal-type]'
            ),
            identification: form.querySelector(
                '[data-fiscal-identification]'
            ),
            businessName: form.querySelector(
                '[data-fiscal-business-name]'
            ),
            address: form.querySelector(
                '[data-fiscal-address]'
            ),
            email: form.querySelector(
                '[data-fiscal-email]'
            ),
            phone: form.querySelector(
                '[data-fiscal-phone]'
            )
        };

        const ownerData = {
            type: <?= json_encode(
                        (string) ($owner['tipo_identificacion_id'] ?? '')
                    ) ?>,

            identification: <?= json_encode(
                                (string) ($owner['identificacion'] ?? '')
                            ) ?>,

            businessName: <?= json_encode(
                                $fullName
                            ) ?>,

            address: <?= json_encode(
                            (string) ($owner['direccion'] ?? '')
                        ) ?>,

            email: <?= json_encode(
                        (string) ($owner['email'] ?? '')
                    ) ?>,

            phone: <?= json_encode(
                        (string) (
                            $owner['celular']
                            ?: ($owner['telefono'] ?? '')
                        )
                    ) ?>
        };


        function copyOwnerData() {

            if (!copyCheckbox.checked) {
                return;
            }

            fields.type.value =
                ownerData.type;

            fields.identification.value =
                ownerData.identification;

            fields.businessName.value =
                ownerData.businessName;

            fields.address.value =
                ownerData.address;

            fields.email.value =
                ownerData.email;

            fields.phone.value =
                ownerData.phone;
        }


        copyCheckbox.addEventListener(
            'change',
            function() {

                if (copyCheckbox.checked) {
                    copyOwnerData();
                }

            }
        );


        copyOwnerData();

    });
</script>

<script
    src="<?= url('/assets/js/views/propietarios-validacion.js') ?>"
    defer></script>
<script
    src="<?= url('/assets/js/views/propietarios-formulario.js') ?>"
    defer></script>
<script
    src="<?= url('/assets/js/views/propietarios-ubicacion.js') ?>"
    defer></script>