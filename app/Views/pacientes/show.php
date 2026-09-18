<?php

/**
 * Vista: expediente clínico del paciente.
 *
 * Variables esperadas desde PacienteController::show():
 * $patient, $weights, $timeline, $owners, $species,
 * $breeds, $sexes, $success, $error.
 */

$canEdit = can('pacientes.editar');

$patientId = (int) $patient['id'];

$patientName = trim((string) ($patient['nombre'] ?? ''));

$ownerName = trim(
    (string) ($patient['propietario_nombres'] ?? '') . ' ' .
        (string) ($patient['propietario_apellidos'] ?? '')
);

$selectedSpeciesId = (int) ($patient['especie_id'] ?? 0);
$selectedBreedId = (int) ($patient['raza_id'] ?? 0);
$selectedSexId = (int) ($patient['sexo_id'] ?? 0);
$selectedOwnerId = (int) ($patient['propietario_entorno_id'] ?? 0);

$birthDate = $patient['fecha_nacimiento'] ?? '';

if ($birthDate !== null && $birthDate !== '') {
    $birthDate = substr((string) $birthDate, 0, 10);
}

$hasWeight = isset($patient['peso_actual'])
    && $patient['peso_actual'] !== null;

?>

<link
    rel="stylesheet"
    href="<?= asset('css/views/pacientes.css') ?>">

<!-- =====================================================
     ENCABEZADO
===================================================== -->

<div class="page-heading">

    <div>

        <span class="eyebrow">
            Expediente clínico
        </span>

        <h1>
            🐾 <?= e($patientName !== '' ? $patientName : 'Paciente') ?>
        </h1>

        <p>
            <?= e($patient['especie'] ?? '—') ?>

            <?php if (!empty($patient['raza'])): ?>
                · <?= e($patient['raza']) ?>
            <?php endif; ?>

            · <?= e($patient['sexo'] ?? '—') ?>
        </p>

    </div>

    <div class="inline-actions">

        <?php if ($canEdit): ?>

            <button
                type="button"
                class="btn btn-primary"
                data-modal-open="patient-edit">
                ✎ Editar paciente
            </button>

        <?php endif; ?>

        <a
            class="btn btn-secondary"
            href="<?= url('/pacientes') ?>">
            ← Pacientes
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
     INFORMACIÓN PRINCIPAL
===================================================== -->

<div class="metric-grid">

    <div class="metric-card">

        <span>Peso actual</span>

        <strong>
            <?= $hasWeight
                ? e($patient['peso_actual']) . ' kg'
                : '—' ?>
        </strong>

    </div>


    <div class="metric-card">

        <span>Propietario</span>

        <strong>
            <?= e($ownerName !== '' ? $ownerName : '—') ?>
        </strong>

    </div>


    <div class="metric-card">

        <span>Nacimiento</span>

        <strong>
            <?= e($birthDate ?: '—') ?>
        </strong>

    </div>


    <div class="metric-card">

        <span>Color</span>

        <strong>
            <?= e($patient['color'] ?: '—') ?>
        </strong>

    </div>

</div>


<!-- =====================================================
     EVOLUCIÓN DE PESO
===================================================== -->

<section class="card mb-1">

    <div class="card-header">

        <div>
            <h2>Evolución de peso</h2>
        </div>

        <?php if ($canEdit): ?>

            <button
                type="button"
                class="btn btn-primary"
                data-modal-open="weight-add">
                ＋ Peso
            </button>

        <?php endif; ?>

    </div>


    <div class="table-wrap">

        <table class="modern-table">

            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Peso</th>
                    <th>Origen</th>
                    <th>Registrado por</th>
                </tr>
            </thead>

            <tbody>

                <?php if (empty($weights)): ?>

                    <tr>
                        <td colspan="4">
                            No existen registros de peso.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($weights as $weight): ?>

                        <tr>

                            <td>
                                <?= e($weight['fecha_registro'] ?? '—') ?>
                            </td>

                            <td>
                                <?= e($weight['peso_kg'] ?? '—') ?> kg
                            </td>

                            <td>
                                <?= e($weight['origen'] ?? '—') ?>
                            </td>

                            <td>
                                <?= e($weight['registrado_por_nombre'] ?? '—') ?>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>


<!-- =====================================================
     HISTORIAL CLÍNICO
===================================================== -->

<section class="card">

    <div class="card-header">

        <div>

            <h2>Historial clínico</h2>

            <p>
                Consultas, vacunas, desparasitación,
                hospitalización, laboratorio y cirugía.
            </p>

        </div>

    </div>


    <div class="stack">

        <?php if (empty($timeline)): ?>

            <div class="soft-panel">
                Todavía no existen eventos clínicos registrados.
            </div>

        <?php else: ?>

            <?php foreach ($timeline as $event): ?>

                <article class="soft-panel">

                    <div class="inline-actions">

                        <strong>
                            <?= e(
                                $event['tipo_nombre']
                                    ?? $event['titulo']
                                    ?? 'Evento clínico'
                            ) ?>
                        </strong>

                        <span class="status-badge status-neutral">

                            <?php
                            $eventDate = $event['fecha_evento'] ?? null;

                            echo $eventDate
                                ? e(date('d/m/Y H:i', strtotime($eventDate)))
                                : '—';
                            ?>

                        </span>

                    </div>


                    <p>
                        <?= e($event['titulo'] ?? '') ?>
                    </p>


                    <?php if (
                        ($event['tipo_codigo'] ?? '')
                        === 'HOSPITALIZACION'
                    ): ?>

                        <a
                            class="btn btn-secondary btn-sm"
                            href="<?= url('/hospitalizaciones/' . $event['id']) ?>">
                            Ver hospitalización
                        </a>

                    <?php endif; ?>

                </article>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</section>


<!-- =====================================================
     MODAL: EDITAR PACIENTE
===================================================== -->

<?php if ($canEdit): ?>

    <div class="modal" id="patient-edit">

        <div class="modal-backdrop"></div>

        <div class="modal-dialog modal-xl">

            <div class="modal-header">

                <h2>Editar paciente</h2>

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
                action="<?= url('/pacientes/' . $patientId) ?>"
                id="patient-edit-form">

                <?= csrf_field() ?>

                <input
                    type="hidden"
                    name="_method"
                    value="PUT">


                <div class="modal-body">

                    <div class="form-grid">


                        <!-- Nombre -->

                        <label>

                            <span>Nombre</span>

                            <input
                                type="text"
                                name="nombre"
                                value="<?= e($patient['nombre'] ?? '') ?>">

                        </label>


                        <!-- Código -->

                        <label>

                            <span>Código</span>

                            <input
                                type="text"
                                name="codigo"
                                value="<?= e($patient['codigo'] ?? '') ?>">

                        </label>


                        <!-- Propietario -->

                        <label>

                            <span>Propietario</span>

                            <select name="propietario_entorno_id">

                                <option value="">
                                    Sin propietario
                                </option>

                                <?php foreach ($owners as $owner): ?>

                                    <option
                                        value="<?= (int) $owner['id'] ?>"
                                        <?= $selectedOwnerId === (int) $owner['id']
                                            ? 'selected'
                                            : '' ?>>

                                        <?= e(trim(
                                            ($owner['nombres'] ?? '') . ' ' .
                                                ($owner['apellidos'] ?? '')
                                        )) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </label>


                        <!-- Especie -->

                        <label>

                            <span>Especie *</span>

                            <select
                                name="especie_id"
                                id="patient-edit-species"
                                required>

                                <option value="">
                                    Seleccionar especie
                                </option>

                                <?php foreach ($species as $speciesItem): ?>

                                    <option
                                        value="<?= (int) $speciesItem['id'] ?>"
                                        <?= $selectedSpeciesId === (int) $speciesItem['id']
                                            ? 'selected'
                                            : '' ?>>

                                        <?= e($speciesItem['nombre_comun']) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </label>


                        <!-- Raza -->

                        <label>

                            <span>Raza</span>

                            <select
                                name="raza_id"
                                id="patient-edit-breed">

                                <option value="">
                                    Sin raza
                                </option>

                                <?php foreach ($breeds as $breed): ?>

                                    <option
                                        value="<?= (int) $breed['id'] ?>"
                                        data-species-id="<?= (int) $breed['especie_id'] ?>"
                                        <?= $selectedBreedId === (int) $breed['id']
                                            ? 'selected'
                                            : '' ?>>

                                        <?= e($breed['nombre']) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </label>


                        <!-- Sexo -->

                        <label>

                            <span>Sexo</span>

                            <select name="sexo_id">

                                <option value="">
                                    Seleccionar
                                </option>

                                <?php foreach ($sexes as $sex): ?>

                                    <option
                                        value="<?= (int) $sex['id'] ?>"
                                        <?= $selectedSexId === (int) $sex['id']
                                            ? 'selected'
                                            : '' ?>>

                                        <?= e($sex['nombre']) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </label>


                        <!-- Fecha nacimiento -->

                        <label>

                            <span>Fecha de nacimiento</span>

                            <input
                                type="date"
                                name="fecha_nacimiento"
                                value="<?= e($birthDate) ?>">

                        </label>


                        <!-- Fecha aproximada -->

                        <label>

                            <span>Fecha aproximada</span>

                            <input
                                type="checkbox"
                                name="fecha_nacimiento_aproximada"
                                value="1"
                                <?= !empty($patient['fecha_nacimiento_aproximada'])
                                    ? 'checked'
                                    : '' ?>>

                        </label>


                        <!-- Color -->

                        <label>

                            <span>Color</span>

                            <input
                                type="text"
                                name="color"
                                value="<?= e($patient['color'] ?? '') ?>">

                        </label>


                        <!-- Microchip -->

                        <label>

                            <span>Microchip</span>

                            <input
                                type="text"
                                name="microchip"
                                value="<?= e($patient['microchip'] ?? '') ?>">

                        </label>


                        <!-- Arete -->

                        <label>

                            <span>Arete</span>

                            <input
                                type="text"
                                name="arete"
                                value="<?= e($patient['arete'] ?? '') ?>">

                        </label>


                        <!-- Observaciones -->

                        <label class="field-full">

                            <span>Observaciones</span>

                            <textarea
                                name="observaciones"><?= e($patient['observaciones'] ?? '') ?></textarea>

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


<!-- =====================================================
     MODAL: REGISTRAR PESO
===================================================== -->

<?php if ($canEdit): ?>

    <div class="modal" id="weight-add">

        <div class="modal-backdrop"></div>

        <div class="modal-dialog">

            <div class="modal-header">

                <h2>Registrar peso</h2>

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
                action="<?= url('/pacientes/' . $patientId . '/peso') ?>">

                <?= csrf_field() ?>


                <div class="modal-body form-grid">

                    <label>

                        <span>Peso kg</span>

                        <input
                            type="number"
                            step="0.001"
                            min="0.001"
                            name="peso_kg"
                            required>

                    </label>


                    <label>

                        <span>Observación</span>

                        <input
                            type="text"
                            name="observacion">

                    </label>

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
                        Guardar peso
                    </button>

                </div>

            </form>

        </div>

    </div>

<?php endif; ?>


<!-- =====================================================
     FILTRO DE RAZAS SEGÚN ESPECIE
===================================================== -->

<?php if ($canEdit): ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {

            const speciesSelect = document.getElementById(
                'patient-edit-species'
            );

            const breedSelect = document.getElementById(
                'patient-edit-breed'
            );

            if (!speciesSelect || !breedSelect) {
                return;
            }

            const allBreedOptions = Array.from(
                breedSelect.options
            ).filter(function(option) {
                return option.value !== '';
            });

            function filterBreeds() {

                const speciesId = speciesSelect.value;

                const previousBreedId = breedSelect.value;

                /*
                 * Reconstruimos las opciones en lugar de depender
                 * únicamente de option.hidden, ya que algunos
                 * navegadores muestran opciones ocultas en select.
                 */

                breedSelect.replaceChildren();

                const emptyOption = document.createElement('option');

                emptyOption.value = '';
                emptyOption.textContent = 'Sin raza';

                breedSelect.appendChild(emptyOption);

                allBreedOptions.forEach(function(option) {

                    if (option.dataset.speciesId === speciesId) {

                        breedSelect.appendChild(
                            option.cloneNode(true)
                        );

                    }

                });

                /*
                 * Mantener la raza seleccionada solamente
                 * si pertenece a la especie actual.
                 */

                const selectedOption = Array.from(
                    breedSelect.options
                ).find(function(option) {

                    return option.value === previousBreedId;

                });

                breedSelect.value = selectedOption ?
                    previousBreedId :
                    '';

            }

            speciesSelect.addEventListener(
                'change',
                filterBreeds
            );

            filterBreeds();

        });
    </script>

<?php endif; ?>