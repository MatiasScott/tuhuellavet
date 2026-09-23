<link rel="stylesheet" href="<?= url('/assets/css/views/catalogos.css') ?>">

<div class="page-heading">
    <div>
        <span class="eyebrow">Configuración clínica</span>

        <h1>Catálogos</h1>

        <p>
            Administra especies, razas, vacunas, fármacos, laboratorio,
            procedimientos, unidades de medida y servicios.
        </p>
    </div>
</div>


<?php if ($success): ?>
    <div class="alert alert-success">
        <?= e($success) ?>
    </div>
<?php endif; ?>


<?php if ($error): ?>
    <div class="alert alert-danger">
        <?= e($error) ?>
    </div>
<?php endif; ?>


<!-- ============================================================
     TARJETAS DE CATÁLOGOS
============================================================ -->

<div class="cards-grid">

    <!-- ========================================================
         ESPECIES
    ========================================================= -->

    <section class="card">

        <div class="card-header">
            <div>
                <h2>Especies</h2>

                <p>
                    Especies disponibles para el registro de pacientes.
                </p>
            </div>
        </div>


        <form
            method="POST"
            action="<?= url('/admin/catalogos/especie') ?>"
            class="form-stack">
            <?= csrf_field() ?>

            <label>
                <span>Categoría</span>

                <select
                    name="categoria_id"
                    required>
                    <option value="">
                        Seleccione una categoría
                    </option>

                    <?php foreach ($categories as $item): ?>
                        <option value="<?= (int)$item['id'] ?>">
                            <?= e($item['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>


            <label>
                <span>Código</span>

                <input
                    type="text"
                    name="codigo"
                    maxlength="50"
                    placeholder="Ej. CANINO"
                    required>
            </label>


            <label>
                <span>Nombre común</span>

                <input
                    type="text"
                    name="nombre"
                    maxlength="150"
                    placeholder="Ej. Perro"
                    required>
            </label>


            <label>
                <span>Nombre científico</span>

                <input
                    type="text"
                    name="nombre_cientifico"
                    maxlength="180"
                    placeholder="Ej. Canis lupus familiaris">
            </label>


            <button
                type="submit"
                class="btn btn-primary">
                Agregar especie
            </button>
        </form>


        <div class="catalog-card-footer">
            <button
                type="button"
                class="btn btn-secondary"
                data-catalog-modal-open="species">
                Ver registros

                <span class="catalog-count">
                    <?= count($adminSpecies) ?>
                </span>
            </button>
        </div>

    </section>


    <!-- ========================================================
         RAZAS
    ========================================================= -->

    <section class="card">

        <div class="card-header">
            <div>
                <h2>Razas</h2>

                <p>
                    Razas asociadas a las especies registradas.
                </p>
            </div>
        </div>


        <form
            method="POST"
            action="<?= url('/admin/catalogos/raza') ?>"
            class="form-stack">
            <?= csrf_field() ?>

            <label>
                <span>Especie</span>

                <select
                    name="especie_id"
                    required>
                    <option value="">
                        Seleccione una especie
                    </option>

                    <?php foreach ($species as $item): ?>
                        <option value="<?= (int)$item['id'] ?>">
                            <?= e($item['nombre_comun']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>


            <label>
                <span>Nombre</span>

                <input
                    type="text"
                    name="nombre"
                    maxlength="150"
                    placeholder="Ej. Labrador Retriever"
                    required>
            </label>


            <label>
                <span>Descripción</span>

                <textarea
                    name="descripcion"
                    placeholder="Descripción opcional de la raza"></textarea>
            </label>


            <button
                type="submit"
                class="btn btn-primary">
                Agregar raza
            </button>
        </form>


        <div class="catalog-card-footer">
            <button
                type="button"
                class="btn btn-secondary"
                data-catalog-modal-open="breeds">
                Ver registros

                <span class="catalog-count">
                    <?= count($breeds) ?>
                </span>
            </button>
        </div>

    </section>


    <!-- ========================================================
         VACUNAS
    ========================================================= -->

    <section class="card">

        <div class="card-header">
            <div>
                <h2>Vacunas</h2>

                <p>
                    Catálogo de vacunas disponibles para aplicación.
                </p>
            </div>
        </div>


        <form
            method="POST"
            action="<?= url('/admin/catalogos/vacuna') ?>"
            class="form-stack">
            <?= csrf_field() ?>

            <label>
                <span>Nombre</span>

                <input
                    type="text"
                    name="nombre"
                    maxlength="180"
                    placeholder="Ej. Vacuna antirrábica"
                    required>
            </label>


            <label>
                <span>Descripción</span>

                <textarea
                    name="descripcion"
                    placeholder="Descripción opcional de la vacuna"></textarea>
            </label>


            <button
                type="submit"
                class="btn btn-primary">
                Agregar vacuna
            </button>
        </form>


        <div class="catalog-card-footer">
            <button
                type="button"
                class="btn btn-secondary"
                data-catalog-modal-open="vaccines">
                Ver registros

                <span class="catalog-count">
                    <?= count($vaccines) ?>
                </span>
            </button>
        </div>

    </section>


    <!-- ========================================================
         FÁRMACOS
    ========================================================= -->

    <section class="card">

        <div class="card-header">
            <div>
                <h2>Fármacos</h2>

                <p>
                    Medicamentos utilizados en tratamientos clínicos.
                </p>
            </div>
        </div>


        <form
            method="POST"
            action="<?= url('/admin/catalogos/farmaco') ?>"
            class="form-stack">
            <?= csrf_field() ?>

            <label>
                <span>Nombre</span>

                <input
                    type="text"
                    name="nombre"
                    maxlength="180"
                    placeholder="Ej. Amoxicilina"
                    required>
            </label>


            <label>
                <span>Descripción</span>

                <textarea
                    name="descripcion"
                    placeholder="Descripción opcional del fármaco"></textarea>
            </label>


            <button
                type="submit"
                class="btn btn-primary">
                Agregar fármaco
            </button>
        </form>


        <div class="catalog-card-footer">
            <button
                type="button"
                class="btn btn-secondary"
                data-catalog-modal-open="drugs">
                Ver registros

                <span class="catalog-count">
                    <?= count($drugs) ?>
                </span>
            </button>
        </div>

    </section>


    <!-- ========================================================
         TIPOS DE EXAMEN
    ========================================================= -->

    <section class="card">

        <div class="card-header">
            <div>
                <h2>Tipos de examen</h2>

                <p>
                    Exámenes disponibles para solicitudes de laboratorio.
                </p>
            </div>
        </div>


        <form
            method="POST"
            action="<?= url('/admin/catalogos/laboratorio') ?>"
            class="form-stack">
            <?= csrf_field() ?>

            <label>
                <span>Nombre</span>

                <input
                    type="text"
                    name="nombre"
                    maxlength="180"
                    placeholder="Ej. Hemograma completo"
                    required>
            </label>


            <label>
                <span>Descripción</span>

                <textarea
                    name="descripcion"
                    placeholder="Descripción opcional del examen"></textarea>
            </label>


            <button
                type="submit"
                class="btn btn-primary">
                Agregar tipo de examen
            </button>
        </form>


        <div class="catalog-card-footer">
            <button
                type="button"
                class="btn btn-secondary"
                data-catalog-modal-open="lab-types">
                Ver registros

                <span class="catalog-count">
                    <?= count($labTypes) ?>
                </span>
            </button>
        </div>

    </section>


    <!-- ========================================================
         PROCEDIMIENTOS QUIRÚRGICOS
    ========================================================= -->

    <section class="card">

        <div class="card-header">
            <div>
                <h2>Procedimientos quirúrgicos</h2>

                <p>
                    Procedimientos disponibles para el módulo de cirugías.
                </p>
            </div>
        </div>


        <form
            method="POST"
            action="<?= url('/admin/catalogos/cirugia') ?>"
            class="form-stack">
            <?= csrf_field() ?>

            <label>
                <span>Nombre</span>

                <input
                    type="text"
                    name="nombre"
                    maxlength="180"
                    placeholder="Ej. Esterilización"
                    required>
            </label>


            <label>
                <span>Descripción</span>

                <textarea
                    name="descripcion"
                    placeholder="Descripción opcional del procedimiento"></textarea>
            </label>


            <button
                type="submit"
                class="btn btn-primary">
                Agregar procedimiento
            </button>
        </form>


        <div class="catalog-card-footer">
            <button
                type="button"
                class="btn btn-secondary"
                data-catalog-modal-open="procedures">
                Ver registros

                <span class="catalog-count">
                    <?= count($procedures) ?>
                </span>
            </button>
        </div>

    </section>


    <!-- ========================================================
         UNIDADES DE MEDIDA
    ========================================================= -->

    <section class="card">

        <div class="card-header">
            <div>
                <h2>Unidades de medida</h2>

                <p>
                    Unidades utilizadas en dosis, productos y tratamientos.
                </p>
            </div>
        </div>


        <form
            method="POST"
            action="<?= url('/admin/catalogos/unidad') ?>"
            class="form-stack">
            <?= csrf_field() ?>

            <label>
                <span>Código</span>

                <input
                    type="text"
                    name="codigo"
                    maxlength="30"
                    placeholder="Ej. MCG"
                    required>
            </label>


            <label>
                <span>Nombre</span>

                <input
                    type="text"
                    name="nombre"
                    maxlength="100"
                    placeholder="Ej. Microgramo"
                    required>
            </label>


            <label>
                <span>Símbolo</span>

                <input
                    type="text"
                    name="simbolo"
                    maxlength="30"
                    placeholder="Ej. µg"
                    required>
            </label>


            <label>
                <span>Categoría</span>

                <input
                    type="text"
                    name="categoria"
                    maxlength="60"
                    placeholder="Ej. MASA, VOLUMEN, DOSIS">
            </label>


            <button
                type="submit"
                class="btn btn-primary">
                Agregar unidad
            </button>
        </form>


        <div class="catalog-card-footer">
            <button
                type="button"
                class="btn btn-secondary"
                data-catalog-modal-open="units">
                Ver registros

                <span class="catalog-count">
                    <?= count($units) ?>
                </span>
            </button>
        </div>

    </section>


    <!-- ========================================================
         SERVICIOS
    ========================================================= -->

    <section class="card">

        <div class="card-header">
            <div>
                <h2>Servicios</h2>

                <p>
                    Servicios disponibles para ventas y facturación.
                </p>
            </div>
        </div>


        <form
            method="POST"
            action="<?= url('/admin/catalogos/servicio') ?>"
            class="form-stack">
            <?= csrf_field() ?>

            <label>
                <span>Código</span>

                <input
                    type="text"
                    name="codigo"
                    maxlength="80"
                    placeholder="Ej. CONSULTA_GENERAL"
                    required>
            </label>


            <label>
                <span>Nombre</span>

                <input
                    type="text"
                    name="nombre"
                    maxlength="180"
                    placeholder="Ej. Consulta general"
                    required>
            </label>


            <label>
                <span>Descripción</span>

                <textarea
                    name="descripcion"
                    placeholder="Ej. Consulta veterinaria general"></textarea>
            </label>


            <label>
                <span>Precio base</span>

                <input
                    type="number"
                    name="precio_base"
                    min="0"
                    step="0.01"
                    placeholder="Ej. 20.00">
            </label>


            <button
                type="submit"
                class="btn btn-primary">
                Agregar servicio
            </button>
        </form>


        <div class="catalog-card-footer">
            <button
                type="button"
                class="btn btn-secondary"
                data-catalog-modal-open="services">
                Ver registros

                <span class="catalog-count">
                    <?= count($services) ?>
                </span>
            </button>
        </div>

    </section>

</div>


<!-- ============================================================
     MODAL: ESPECIES
============================================================ -->

<div
    class="catalog-modal"
    data-catalog-modal="species"
    aria-hidden="true">
    <div
        class="catalog-modal-backdrop"
        data-catalog-modal-close></div>


    <div class="catalog-modal-dialog">

        <div class="catalog-modal-header">
            <div>
                <span class="eyebrow">Catálogo</span>

                <h2>Especies registradas</h2>

                <p>
                    <?= count($adminSpecies) ?> registro(s)
                </p>
            </div>


            <button
                type="button"
                class="catalog-modal-close"
                data-catalog-modal-close
                aria-label="Cerrar">
                ×
            </button>
        </div>


        <div class="catalog-modal-search">
            <input
                type="search"
                placeholder="Buscar por código, especie, categoría o estado..."
                data-catalog-search
                autocomplete="off">
        </div>


        <div class="catalog-modal-body">

            <div class="table-responsive">
                <table class="table">

                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Especie</th>
                            <th>Categoría</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>


                    <tbody data-catalog-rows>

                        <?php foreach ($adminSpecies as $item): ?>

                            <?php
                            $active = (int)$item['activo'] === 1;
                            ?>

                            <tr data-catalog-row>

                                <td>
                                    <?= e($item['codigo']) ?>
                                </td>


                                <td>
                                    <strong>
                                        <?= e($item['nombre_comun']) ?>
                                    </strong>

                                    <?php if (!empty($item['nombre_cientifico'])): ?>
                                        <div class="muted">
                                            <?= e($item['nombre_cientifico']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>


                                <td>
                                    <?= e($item['categoria_nombre'] ?? '—') ?>
                                </td>


                                <td>
                                    <span
                                        class="catalog-status <?= $active
                                                                    ? 'is-active'
                                                                    : 'is-inactive' ?>">
                                        <?= $active ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>


                                <td>
                                    <div class="catalog-actions">

                                        <button
                                            type="button"
                                            class="btn btn-secondary btn-sm"
                                            data-catalog-edit
                                            data-type="especie"
                                            data-id="<?= (int)$item['id'] ?>"
                                            data-categoria-id="<?= (int)$item['categoria_id'] ?>"
                                            data-codigo="<?= e($item['codigo']) ?>"
                                            data-nombre="<?= e($item['nombre_comun']) ?>"
                                            data-nombre-cientifico="<?= e(
                                                                        $item['nombre_cientifico'] ?? ''
                                                                    ) ?>"
                                            data-update-url="<?= e(
                                                                    url(
                                                                        '/admin/catalogos/especie/'
                                                                            . (int)$item['id']
                                                                            . '/actualizar'
                                                                    )
                                                                ) ?>">
                                            Editar
                                        </button>


                                        <form
                                            method="POST"
                                            action="<?= url(
                                                        '/admin/catalogos/especie/'
                                                            . (int)$item['id']
                                                            . '/estado'
                                                    ) ?>"
                                            class="catalog-status-form">
                                            <?= csrf_field() ?>

                                            <button
                                                type="submit"
                                                class="btn btn-sm <?= $active
                                                                        ? 'btn-danger'
                                                                        : 'btn-primary' ?>"
                                                data-confirm-status
                                                data-action="<?= $active
                                                                    ? 'desactivar'
                                                                    : 'activar' ?>"
                                                data-name="<?= e(
                                                                $item['nombre_comun']
                                                            ) ?>">
                                                <?= $active
                                                    ? 'Desactivar'
                                                    : 'Activar' ?>
                                            </button>
                                        </form>

                                    </div>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>
            </div>


            <div
                class="catalog-empty"
                data-catalog-empty
                hidden>
                No se encontraron especies con ese criterio.
            </div>

        </div>

    </div>
</div>


<!-- ============================================================
     MODAL: RAZAS
============================================================ -->

<div
    class="catalog-modal"
    data-catalog-modal="breeds"
    aria-hidden="true">
    <div
        class="catalog-modal-backdrop"
        data-catalog-modal-close></div>


    <div class="catalog-modal-dialog">

        <div class="catalog-modal-header">
            <div>
                <span class="eyebrow">Catálogo</span>

                <h2>Razas registradas</h2>

                <p>
                    <?= count($breeds) ?> registro(s)
                </p>
            </div>


            <button
                type="button"
                class="catalog-modal-close"
                data-catalog-modal-close
                aria-label="Cerrar">
                ×
            </button>
        </div>


        <div class="catalog-modal-search">
            <input
                type="search"
                placeholder="Buscar por especie, raza o estado..."
                data-catalog-search
                autocomplete="off">
        </div>


        <div class="catalog-modal-body">

            <div class="table-responsive">
                <table class="table">

                    <thead>
                        <tr>
                            <th>Especie</th>
                            <th>Raza</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>


                    <tbody data-catalog-rows>

                        <?php foreach ($breeds as $item): ?>

                            <?php
                            $active = (int)$item['activo'] === 1;
                            ?>

                            <tr data-catalog-row>

                                <td>
                                    <?= e($item['especie_nombre'] ?? '—') ?>
                                </td>


                                <td>
                                    <strong>
                                        <?= e($item['nombre']) ?>
                                    </strong>

                                    <?php if (!empty($item['descripcion'])): ?>
                                        <div class="muted">
                                            <?= e($item['descripcion']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>


                                <td>
                                    <span
                                        class="catalog-status <?= $active
                                                                    ? 'is-active'
                                                                    : 'is-inactive' ?>">
                                        <?= $active ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>


                                <td>
                                    <div class="catalog-actions">

                                        <button
                                            type="button"
                                            class="btn btn-secondary btn-sm"
                                            data-catalog-edit
                                            data-type="raza"
                                            data-id="<?= (int)$item['id'] ?>"
                                            data-especie-id="<?= (int)$item['especie_id'] ?>"
                                            data-nombre="<?= e($item['nombre']) ?>"
                                            data-descripcion="<?= e(
                                                                    $item['descripcion'] ?? ''
                                                                ) ?>"
                                            data-update-url="<?= e(
                                                                    url(
                                                                        '/admin/catalogos/raza/'
                                                                            . (int)$item['id']
                                                                            . '/actualizar'
                                                                    )
                                                                ) ?>">
                                            Editar
                                        </button>


                                        <form
                                            method="POST"
                                            action="<?= url(
                                                        '/admin/catalogos/raza/'
                                                            . (int)$item['id']
                                                            . '/estado'
                                                    ) ?>"
                                            class="catalog-status-form">
                                            <?= csrf_field() ?>

                                            <button
                                                type="submit"
                                                class="btn btn-sm <?= $active
                                                                        ? 'btn-danger'
                                                                        : 'btn-primary' ?>"
                                                data-confirm-status
                                                data-action="<?= $active
                                                                    ? 'desactivar'
                                                                    : 'activar' ?>"
                                                data-name="<?= e($item['nombre']) ?>">
                                                <?= $active
                                                    ? 'Desactivar'
                                                    : 'Activar' ?>
                                            </button>
                                        </form>

                                    </div>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>
            </div>


            <div
                class="catalog-empty"
                data-catalog-empty
                hidden>
                No se encontraron razas con ese criterio.
            </div>

        </div>

    </div>
</div>


<!-- ============================================================
     MODAL: VACUNAS
============================================================ -->

<div
    class="catalog-modal"
    data-catalog-modal="vaccines"
    aria-hidden="true">
    <div
        class="catalog-modal-backdrop"
        data-catalog-modal-close></div>


    <div class="catalog-modal-dialog">

        <div class="catalog-modal-header">
            <div>
                <span class="eyebrow">Catálogo</span>

                <h2>Vacunas registradas</h2>

                <p>
                    <?= count($vaccines) ?> registro(s)
                </p>
            </div>


            <button
                type="button"
                class="catalog-modal-close"
                data-catalog-modal-close
                aria-label="Cerrar">
                ×
            </button>
        </div>


        <div class="catalog-modal-search">
            <input
                type="search"
                placeholder="Buscar vacuna, descripción o estado..."
                data-catalog-search
                autocomplete="off">
        </div>


        <div class="catalog-modal-body">

            <div class="table-responsive">
                <table class="table">

                    <thead>
                        <tr>
                            <th>Vacuna</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>


                    <tbody data-catalog-rows>

                        <?php foreach ($vaccines as $item): ?>

                            <?php
                            $active = (int)$item['activo'] === 1;
                            ?>

                            <tr data-catalog-row>

                                <td>
                                    <strong>
                                        <?= e($item['nombre']) ?>
                                    </strong>

                                    <?php if (!empty($item['descripcion'])): ?>
                                        <div class="muted">
                                            <?= e($item['descripcion']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>


                                <td>
                                    <span
                                        class="catalog-status <?= $active
                                                                    ? 'is-active'
                                                                    : 'is-inactive' ?>">
                                        <?= $active ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>


                                <td>
                                    <div class="catalog-actions">

                                        <button
                                            type="button"
                                            class="btn btn-secondary btn-sm"
                                            data-catalog-edit
                                            data-type="vacuna"
                                            data-id="<?= (int)$item['id'] ?>"
                                            data-nombre="<?= e($item['nombre']) ?>"
                                            data-descripcion="<?= e(
                                                                    $item['descripcion'] ?? ''
                                                                ) ?>"
                                            data-update-url="<?= e(
                                                                    url(
                                                                        '/admin/catalogos/vacuna/'
                                                                            . (int)$item['id']
                                                                            . '/actualizar'
                                                                    )
                                                                ) ?>">
                                            Editar
                                        </button>


                                        <form
                                            method="POST"
                                            action="<?= url(
                                                        '/admin/catalogos/vacuna/'
                                                            . (int)$item['id']
                                                            . '/estado'
                                                    ) ?>"
                                            class="catalog-status-form">
                                            <?= csrf_field() ?>

                                            <button
                                                type="submit"
                                                class="btn btn-sm <?= $active
                                                                        ? 'btn-danger'
                                                                        : 'btn-primary' ?>"
                                                data-confirm-status
                                                data-action="<?= $active
                                                                    ? 'desactivar'
                                                                    : 'activar' ?>"
                                                data-name="<?= e($item['nombre']) ?>">
                                                <?= $active
                                                    ? 'Desactivar'
                                                    : 'Activar' ?>
                                            </button>
                                        </form>

                                    </div>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>
            </div>


            <div
                class="catalog-empty"
                data-catalog-empty
                hidden>
                No se encontraron vacunas con ese criterio.
            </div>

        </div>

    </div>
</div>


<!-- ============================================================
     MODAL: FÁRMACOS
============================================================ -->

<div
    class="catalog-modal"
    data-catalog-modal="drugs"
    aria-hidden="true">
    <div
        class="catalog-modal-backdrop"
        data-catalog-modal-close></div>


    <div class="catalog-modal-dialog">

        <div class="catalog-modal-header">
            <div>
                <span class="eyebrow">Catálogo</span>

                <h2>Fármacos registrados</h2>

                <p>
                    <?= count($drugs) ?> registro(s)
                </p>
            </div>


            <button
                type="button"
                class="catalog-modal-close"
                data-catalog-modal-close
                aria-label="Cerrar">
                ×
            </button>
        </div>


        <div class="catalog-modal-search">
            <input
                type="search"
                placeholder="Buscar fármaco, descripción o estado..."
                data-catalog-search
                autocomplete="off">
        </div>


        <div class="catalog-modal-body">

            <div class="table-responsive">
                <table class="table">

                    <thead>
                        <tr>
                            <th>Fármaco</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>


                    <tbody data-catalog-rows>

                        <?php foreach ($drugs as $item): ?>

                            <?php
                            $active = (int)$item['activo'] === 1;
                            ?>

                            <tr data-catalog-row>

                                <td>
                                    <strong>
                                        <?= e($item['nombre']) ?>
                                    </strong>

                                    <?php if (!empty($item['descripcion'])): ?>
                                        <div class="muted">
                                            <?= e($item['descripcion']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>


                                <td>
                                    <span
                                        class="catalog-status <?= $active
                                                                    ? 'is-active'
                                                                    : 'is-inactive' ?>">
                                        <?= $active ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>


                                <td>
                                    <div class="catalog-actions">

                                        <button
                                            type="button"
                                            class="btn btn-secondary btn-sm"
                                            data-catalog-edit
                                            data-type="farmaco"
                                            data-id="<?= (int)$item['id'] ?>"
                                            data-nombre="<?= e($item['nombre']) ?>"
                                            data-descripcion="<?= e(
                                                                    $item['descripcion'] ?? ''
                                                                ) ?>"
                                            data-update-url="<?= e(
                                                                    url(
                                                                        '/admin/catalogos/farmaco/'
                                                                            . (int)$item['id']
                                                                            . '/actualizar'
                                                                    )
                                                                ) ?>">
                                            Editar
                                        </button>


                                        <form
                                            method="POST"
                                            action="<?= url(
                                                        '/admin/catalogos/farmaco/'
                                                            . (int)$item['id']
                                                            . '/estado'
                                                    ) ?>"
                                            class="catalog-status-form">
                                            <?= csrf_field() ?>

                                            <button
                                                type="submit"
                                                class="btn btn-sm <?= $active
                                                                        ? 'btn-danger'
                                                                        : 'btn-primary' ?>"
                                                data-confirm-status
                                                data-action="<?= $active
                                                                    ? 'desactivar'
                                                                    : 'activar' ?>"
                                                data-name="<?= e($item['nombre']) ?>">
                                                <?= $active
                                                    ? 'Desactivar'
                                                    : 'Activar' ?>
                                            </button>
                                        </form>

                                    </div>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>
            </div>


            <div
                class="catalog-empty"
                data-catalog-empty
                hidden>
                No se encontraron fármacos con ese criterio.
            </div>

        </div>

    </div>
</div>


<!-- ============================================================
     MODAL: TIPOS DE EXAMEN
============================================================ -->

<div
    class="catalog-modal"
    data-catalog-modal="lab-types"
    aria-hidden="true">
    <div
        class="catalog-modal-backdrop"
        data-catalog-modal-close></div>


    <div class="catalog-modal-dialog">

        <div class="catalog-modal-header">
            <div>
                <span class="eyebrow">Catálogo</span>

                <h2>Tipos de examen registrados</h2>

                <p>
                    <?= count($labTypes) ?> registro(s)
                </p>
            </div>


            <button
                type="button"
                class="catalog-modal-close"
                data-catalog-modal-close
                aria-label="Cerrar">
                ×
            </button>
        </div>


        <div class="catalog-modal-search">
            <input
                type="search"
                placeholder="Buscar examen, descripción o estado..."
                data-catalog-search
                autocomplete="off">
        </div>


        <div class="catalog-modal-body">

            <div class="table-responsive">
                <table class="table">

                    <thead>
                        <tr>
                            <th>Examen</th>
                            <th>Descripción</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>


                    <tbody data-catalog-rows>

                        <?php foreach ($labTypes as $item): ?>

                            <?php
                            $active = (int)$item['activo'] === 1;
                            ?>

                            <tr data-catalog-row>

                                <td>
                                    <?= e($item['nombre']) ?>
                                </td>


                                <td>
                                    <?= e($item['descripcion'] ?? '—') ?>
                                </td>


                                <td>
                                    <span
                                        class="catalog-status <?= $active
                                                                    ? 'is-active'
                                                                    : 'is-inactive' ?>">
                                        <?= $active ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>


                                <td>
                                    <div class="catalog-actions">

                                        <button
                                            type="button"
                                            class="btn btn-secondary btn-sm"
                                            data-catalog-edit
                                            data-type="laboratorio"
                                            data-id="<?= (int)$item['id'] ?>"
                                            data-nombre="<?= e($item['nombre']) ?>"
                                            data-descripcion="<?= e(
                                                                    $item['descripcion'] ?? ''
                                                                ) ?>"
                                            data-update-url="<?= e(
                                                                    url(
                                                                        '/admin/catalogos/laboratorio/'
                                                                            . (int)$item['id']
                                                                            . '/actualizar'
                                                                    )
                                                                ) ?>">
                                            Editar
                                        </button>


                                        <form
                                            method="POST"
                                            action="<?= url(
                                                        '/admin/catalogos/laboratorio/'
                                                            . (int)$item['id']
                                                            . '/estado'
                                                    ) ?>"
                                            class="catalog-status-form">
                                            <?= csrf_field() ?>

                                            <button
                                                type="submit"
                                                class="btn btn-sm <?= $active
                                                                        ? 'btn-danger'
                                                                        : 'btn-primary' ?>"
                                                data-confirm-status
                                                data-action="<?= $active
                                                                    ? 'desactivar'
                                                                    : 'activar' ?>"
                                                data-name="<?= e($item['nombre']) ?>">
                                                <?= $active
                                                    ? 'Desactivar'
                                                    : 'Activar' ?>
                                            </button>
                                        </form>

                                    </div>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>
            </div>


            <div
                class="catalog-empty"
                data-catalog-empty
                hidden>
                No se encontraron tipos de examen con ese criterio.
            </div>

        </div>

    </div>
</div>


<!-- ============================================================
     MODAL: PROCEDIMIENTOS QUIRÚRGICOS
============================================================ -->

<div
    class="catalog-modal"
    data-catalog-modal="procedures"
    aria-hidden="true">
    <div
        class="catalog-modal-backdrop"
        data-catalog-modal-close></div>


    <div class="catalog-modal-dialog">

        <div class="catalog-modal-header">
            <div>
                <span class="eyebrow">Catálogo</span>

                <h2>Procedimientos quirúrgicos</h2>

                <p>
                    <?= count($procedures) ?> registro(s)
                </p>
            </div>


            <button
                type="button"
                class="catalog-modal-close"
                data-catalog-modal-close
                aria-label="Cerrar">
                ×
            </button>
        </div>


        <div class="catalog-modal-search">
            <input
                type="search"
                placeholder="Buscar procedimiento, descripción o estado..."
                data-catalog-search
                autocomplete="off">
        </div>


        <div class="catalog-modal-body">

            <div class="table-responsive">
                <table class="table">

                    <thead>
                        <tr>
                            <th>Procedimiento</th>
                            <th>Descripción</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>


                    <tbody data-catalog-rows>

                        <?php foreach ($procedures as $item): ?>

                            <?php
                            $active = (int)$item['activo'] === 1;
                            ?>

                            <tr data-catalog-row>

                                <td>
                                    <?= e($item['nombre']) ?>
                                </td>


                                <td>
                                    <?= e($item['descripcion'] ?? '—') ?>
                                </td>


                                <td>
                                    <span
                                        class="catalog-status <?= $active
                                                                    ? 'is-active'
                                                                    : 'is-inactive' ?>">
                                        <?= $active ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>


                                <td>
                                    <div class="catalog-actions">

                                        <button
                                            type="button"
                                            class="btn btn-secondary btn-sm"
                                            data-catalog-edit
                                            data-type="cirugia"
                                            data-id="<?= (int)$item['id'] ?>"
                                            data-nombre="<?= e($item['nombre']) ?>"
                                            data-descripcion="<?= e(
                                                                    $item['descripcion'] ?? ''
                                                                ) ?>"
                                            data-update-url="<?= e(
                                                                    url(
                                                                        '/admin/catalogos/cirugia/'
                                                                            . (int)$item['id']
                                                                            . '/actualizar'
                                                                    )
                                                                ) ?>">
                                            Editar
                                        </button>


                                        <form
                                            method="POST"
                                            action="<?= url(
                                                        '/admin/catalogos/cirugia/'
                                                            . (int)$item['id']
                                                            . '/estado'
                                                    ) ?>"
                                            class="catalog-status-form">
                                            <?= csrf_field() ?>

                                            <button
                                                type="submit"
                                                class="btn btn-sm <?= $active
                                                                        ? 'btn-danger'
                                                                        : 'btn-primary' ?>"
                                                data-confirm-status
                                                data-action="<?= $active
                                                                    ? 'desactivar'
                                                                    : 'activar' ?>"
                                                data-name="<?= e($item['nombre']) ?>">
                                                <?= $active
                                                    ? 'Desactivar'
                                                    : 'Activar' ?>
                                            </button>
                                        </form>

                                    </div>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>
            </div>


            <div
                class="catalog-empty"
                data-catalog-empty
                hidden>
                No se encontraron procedimientos con ese criterio.
            </div>

        </div>

    </div>
</div>


<!-- ============================================================
     MODAL: UNIDADES DE MEDIDA
============================================================ -->

<div
    class="catalog-modal"
    data-catalog-modal="units"
    aria-hidden="true">
    <div
        class="catalog-modal-backdrop"
        data-catalog-modal-close></div>


    <div class="catalog-modal-dialog">

        <div class="catalog-modal-header">
            <div>
                <span class="eyebrow">Catálogo</span>

                <h2>Unidades de medida</h2>

                <p>
                    <?= count($units) ?> registro(s)
                </p>
            </div>


            <button
                type="button"
                class="catalog-modal-close"
                data-catalog-modal-close
                aria-label="Cerrar">
                ×
            </button>
        </div>


        <div class="catalog-modal-search">
            <input
                type="search"
                placeholder="Buscar código, unidad, símbolo, categoría o estado..."
                data-catalog-search
                autocomplete="off">
        </div>


        <div class="catalog-modal-body">

            <div class="table-responsive">
                <table class="table">

                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Nombre</th>
                            <th>Símbolo</th>
                            <th>Categoría</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>


                    <tbody data-catalog-rows>

                        <?php foreach ($units as $unit): ?>

                            <?php
                            $active = (int)$unit['activo'] === 1;
                            ?>

                            <tr data-catalog-row>

                                <td>
                                    <?= e($unit['codigo']) ?>
                                </td>


                                <td>
                                    <?= e($unit['nombre']) ?>
                                </td>


                                <td>
                                    <?= e($unit['simbolo']) ?>
                                </td>


                                <td>
                                    <?= e($unit['categoria'] ?? '—') ?>
                                </td>


                                <td>
                                    <span
                                        class="catalog-status <?= $active
                                                                    ? 'is-active'
                                                                    : 'is-inactive' ?>">
                                        <?= $active ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>


                                <td>
                                    <div class="catalog-actions">

                                        <button
                                            type="button"
                                            class="btn btn-secondary btn-sm"
                                            data-catalog-edit
                                            data-type="unidad"
                                            data-id="<?= (int)$unit['id'] ?>"
                                            data-codigo="<?= e($unit['codigo']) ?>"
                                            data-nombre="<?= e($unit['nombre']) ?>"
                                            data-simbolo="<?= e($unit['simbolo']) ?>"
                                            data-categoria="<?= e(
                                                                $unit['categoria'] ?? ''
                                                            ) ?>"
                                            data-update-url="<?= e(
                                                                    url(
                                                                        '/admin/catalogos/unidad/'
                                                                            . (int)$unit['id']
                                                                            . '/actualizar'
                                                                    )
                                                                ) ?>">
                                            Editar
                                        </button>


                                        <form
                                            method="POST"
                                            action="<?= url(
                                                        '/admin/catalogos/unidad/'
                                                            . (int)$unit['id']
                                                            . '/estado'
                                                    ) ?>"
                                            class="catalog-status-form">
                                            <?= csrf_field() ?>

                                            <button
                                                type="submit"
                                                class="btn btn-sm <?= $active
                                                                        ? 'btn-danger'
                                                                        : 'btn-primary' ?>"
                                                data-confirm-status
                                                data-action="<?= $active
                                                                    ? 'desactivar'
                                                                    : 'activar' ?>"
                                                data-name="<?= e($unit['nombre']) ?>">
                                                <?= $active
                                                    ? 'Desactivar'
                                                    : 'Activar' ?>
                                            </button>
                                        </form>

                                    </div>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>
            </div>


            <div
                class="catalog-empty"
                data-catalog-empty
                hidden>
                No se encontraron unidades con ese criterio.
            </div>

        </div>

    </div>
</div>


<!-- ============================================================
     MODAL: SERVICIOS
============================================================ -->

<div
    class="catalog-modal"
    data-catalog-modal="services"
    aria-hidden="true">
    <div
        class="catalog-modal-backdrop"
        data-catalog-modal-close></div>


    <div class="catalog-modal-dialog">

        <div class="catalog-modal-header">
            <div>
                <span class="eyebrow">
                    Catálogo comercial
                </span>

                <h2>Servicios registrados</h2>

                <p>
                    <?= count($services) ?> registro(s)
                </p>
            </div>


            <button
                type="button"
                class="catalog-modal-close"
                data-catalog-modal-close
                aria-label="Cerrar">
                ×
            </button>
        </div>


        <div class="catalog-modal-search">
            <input
                type="search"
                placeholder="Buscar código, servicio, descripción o estado..."
                data-catalog-search
                autocomplete="off">
        </div>


        <div class="catalog-modal-body">

            <div class="table-responsive">
                <table class="table">

                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Servicio</th>
                            <th>Precio</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>


                    <tbody data-catalog-rows>

                        <?php foreach ($services as $service): ?>

                            <?php
                            $active = (int)$service['activo'] === 1;
                            ?>

                            <tr data-catalog-row>

                                <td>
                                    <?= e($service['codigo']) ?>
                                </td>


                                <td>
                                    <strong>
                                        <?= e($service['nombre']) ?>
                                    </strong>

                                    <?php if (!empty($service['descripcion'])): ?>
                                        <div class="muted">
                                            <?= e($service['descripcion']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>


                                <td>
                                    <?php if ($service['precio_base'] !== null): ?>

                                        $<?= number_format(
                                                (float)$service['precio_base'],
                                                2
                                            ) ?>

                                    <?php else: ?>

                                        —

                                    <?php endif; ?>
                                </td>


                                <td>
                                    <span
                                        class="catalog-status <?= $active
                                                                    ? 'is-active'
                                                                    : 'is-inactive' ?>">
                                        <?= $active ? 'Activo' : 'Inactivo' ?>
                                    </span>
                                </td>


                                <td>
                                    <div class="catalog-actions">

                                        <button
                                            type="button"
                                            class="btn btn-secondary btn-sm"
                                            data-catalog-edit
                                            data-type="servicio"
                                            data-id="<?= (int)$service['id'] ?>"
                                            data-codigo="<?= e($service['codigo']) ?>"
                                            data-nombre="<?= e($service['nombre']) ?>"
                                            data-descripcion="<?= e(
                                                                    $service['descripcion'] ?? ''
                                                                ) ?>"
                                            data-precio="<?= e(
                                                                $service['precio_base'] ?? ''
                                                            ) ?>"
                                            data-update-url="<?= e(
                                                                    url(
                                                                        '/admin/catalogos/servicio/'
                                                                            . (int)$service['id']
                                                                            . '/actualizar'
                                                                    )
                                                                ) ?>">
                                            Editar
                                        </button>


                                        <form
                                            method="POST"
                                            action="<?= url(
                                                        '/admin/catalogos/servicio/'
                                                            . (int)$service['id']
                                                            . '/estado'
                                                    ) ?>"
                                            class="catalog-status-form">
                                            <?= csrf_field() ?>

                                            <button
                                                type="submit"
                                                class="btn btn-sm <?= $active
                                                                        ? 'btn-danger'
                                                                        : 'btn-primary' ?>"
                                                data-confirm-status
                                                data-action="<?= $active
                                                                    ? 'desactivar'
                                                                    : 'activar' ?>"
                                                data-name="<?= e(
                                                                $service['nombre']
                                                            ) ?>">
                                                <?= $active
                                                    ? 'Desactivar'
                                                    : 'Activar' ?>
                                            </button>
                                        </form>

                                    </div>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>
            </div>


            <div
                class="catalog-empty"
                data-catalog-empty
                hidden>
                No se encontraron servicios con ese criterio.
            </div>

        </div>

    </div>
</div>


<!-- ============================================================
     MODAL ÚNICO DE EDICIÓN
============================================================ -->

<div
    class="catalog-modal"
    id="catalog-edit-modal"
    aria-hidden="true">
    <div
        class="catalog-modal-backdrop"
        data-catalog-edit-close></div>


    <div class="catalog-modal-dialog catalog-edit-dialog">

        <div class="catalog-modal-header">

            <div>
                <span class="eyebrow">
                    Administración de catálogo
                </span>

                <h2>
                    Editar registro
                </h2>

                <p>
                    Actualiza la información del registro seleccionado.
                </p>
            </div>


            <button
                type="button"
                class="catalog-modal-close"
                data-catalog-edit-close
                aria-label="Cerrar">
                ×
            </button>

        </div>


        <div class="catalog-modal-body">

            <form
                method="POST"
                id="catalog-edit-form"
                class="form-stack">
                <?= csrf_field() ?>


                <!-- CATEGORÍA DE ESPECIE -->

                <div
                    data-edit-field="categoria-especie"
                    hidden>
                    <label>
                        <span>Categoría</span>

                        <select
                            name="categoria_id"
                            id="catalog-edit-categoria-especie">
                            <option value="">
                                Seleccione una categoría
                            </option>

                            <?php foreach ($categories as $category): ?>
                                <option
                                    value="<?= (int)$category['id'] ?>">
                                    <?= e($category['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>


                <!-- ESPECIE DE LA RAZA -->

                <div
                    data-edit-field="especie"
                    hidden>
                    <label>
                        <span>Especie</span>

                        <select
                            name="especie_id"
                            id="catalog-edit-especie">
                            <option value="">
                                Seleccione una especie
                            </option>

                            <?php foreach ($species as $speciesItem): ?>
                                <option
                                    value="<?= (int)$speciesItem['id'] ?>">
                                    <?= e($speciesItem['nombre_comun']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                </div>


                <!-- CÓDIGO -->

                <div
                    data-edit-field="codigo"
                    hidden>
                    <label>
                        <span>Código</span>

                        <input
                            type="text"
                            name="codigo"
                            id="catalog-edit-codigo"
                            maxlength="80">
                    </label>
                </div>


                <!-- NOMBRE -->

                <div
                    data-edit-field="nombre"
                    hidden>
                    <label>
                        <span>Nombre</span>

                        <input
                            type="text"
                            name="nombre"
                            id="catalog-edit-nombre"
                            maxlength="180">
                    </label>
                </div>


                <!-- NOMBRE CIENTÍFICO -->

                <div
                    data-edit-field="nombre-cientifico"
                    hidden>
                    <label>
                        <span>Nombre científico</span>

                        <input
                            type="text"
                            name="nombre_cientifico"
                            id="catalog-edit-nombre-cientifico"
                            maxlength="180"
                            placeholder="Ej. Canis lupus familiaris">
                    </label>
                </div>


                <!-- DESCRIPCIÓN -->

                <div
                    data-edit-field="descripcion"
                    hidden>
                    <label>
                        <span>Descripción</span>

                        <textarea
                            name="descripcion"
                            id="catalog-edit-descripcion"></textarea>
                    </label>
                </div>


                <!-- SÍMBOLO -->

                <div
                    data-edit-field="simbolo"
                    hidden>
                    <label>
                        <span>Símbolo</span>

                        <input
                            type="text"
                            name="simbolo"
                            id="catalog-edit-simbolo"
                            maxlength="30"
                            placeholder="Ej. mg">
                    </label>
                </div>


                <!-- CATEGORÍA DE UNIDAD -->

                <div
                    data-edit-field="categoria-unidad"
                    hidden>
                    <label>
                        <span>Categoría</span>

                        <input
                            type="text"
                            name="categoria"
                            id="catalog-edit-categoria-unidad"
                            maxlength="60"
                            placeholder="Ej. MASA, VOLUMEN, DOSIS">
                    </label>
                </div>


                <!-- PRECIO -->

                <div
                    data-edit-field="precio"
                    hidden>
                    <label>
                        <span>Precio base</span>

                        <input
                            type="number"
                            name="precio_base"
                            id="catalog-edit-precio"
                            min="0"
                            step="0.01">
                    </label>
                </div>


                <!-- ACCIONES -->

                <div class="catalog-edit-actions">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-catalog-edit-close>
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
</div>
<script src="<?= url('/assets/js/views/catalogos.js') ?>" defer></script>