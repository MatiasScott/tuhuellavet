<div class="page-heading">
    <div>
        <span class="eyebrow">Prevención</span>

        <h1>Desparasitación</h1>

        <p>
            Control antiparasitario y próximas dosis.
        </p>
    </div>

    <button
        type="button"
        class="btn btn-primary"
        data-modal-open="dew-create">
        ＋ Desparasitación
    </button>
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


<section class="card">

    <div class="table-wrap">

        <table class="modern-table">

            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Paciente</th>
                    <th>Fármaco</th>
                    <th>Dosis</th>
                    <th>Próxima</th>
                    <th>Observaciones</th>
                    <th>Acciones</th>
                </tr>
            </thead>


            <tbody>

                <?php if (empty($dewormings)): ?>

                    <tr>
                        <td
                            colspan="7"
                            style="text-align:center;">
                            No existen desparasitaciones registradas.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($dewormings as $v): ?>

                        <tr>

                            <td>
                                <?= e(
                                    date(
                                        'd/m/Y',
                                        strtotime(
                                            $v['fecha_evento']
                                        )
                                    )
                                ) ?>
                            </td>


                            <td>

                                <strong>
                                    <?= e($v['paciente']) ?>
                                </strong>

                                <?php if (
                                    !empty($v['propietario_nombres'])
                                    || !empty($v['propietario_apellidos'])
                                ): ?>

                                    <div class="muted">

                                        <?= e(
                                            trim(
                                                (
                                                    $v['propietario_nombres']
                                                    ?? ''
                                                )
                                                    . ' '
                                                    . (
                                                        $v['propietario_apellidos']
                                                        ?? ''
                                                    )
                                            )
                                        ) ?>

                                    </div>

                                <?php endif; ?>

                            </td>


                            <td>
                                <?= e($v['farmaco']) ?>
                            </td>


                            <td>

                                <?= e(
                                    $v['dosis']
                                        ?? '—'
                                ) ?>

                                <?= e(
                                    $v['unidad']
                                        ?? ''
                                ) ?>

                            </td>


                            <td>

                                <?php if (
                                    !empty($v['proxima_desparasitacion'])
                                ): ?>

                                    <?= e(
                                        date(
                                            'd/m/Y',
                                            strtotime(
                                                $v['proxima_desparasitacion']
                                            )
                                        )
                                    ) ?>

                                <?php else: ?>

                                    —

                                <?php endif; ?>

                            </td>


                            <td>

                                <?php if (
                                    !empty($v['observaciones'])
                                ): ?>

                                    <?= e(
                                        $v['observaciones']
                                    ) ?>

                                <?php else: ?>

                                    —

                                <?php endif; ?>

                            </td>


                            <td>

                                <div class="catalog-actions">

                                    <button
                                        type="button"
                                        class="btn btn-secondary btn-sm"

                                        data-deworming-edit

                                        data-id="<?= (int)$v['id'] ?>"

                                        data-patient="<?= e(
                                                            $v['paciente']
                                                        ) ?>"

                                        data-drug-id="<?= (int)$v['farmaco_id'] ?>"

                                        data-dose="<?= e(
                                                        $v['dosis']
                                                            ?? ''
                                                    ) ?>"

                                        data-unit-id="<?= e(
                                                            $v['unidad_dosis_id']
                                                                ?? ''
                                                        ) ?>"

                                        data-next-date="<?= e(
                                                            $v['proxima_desparasitacion']
                                                                ?? ''
                                                        ) ?>"

                                        data-observations="<?= e(
                                                                $v['observaciones']
                                                                    ?? ''
                                                            ) ?>"

                                        data-event-date="<?= e(
                                                                date(
                                                                    'Y-m-d\TH:i',
                                                                    strtotime(
                                                                        $v['fecha_evento']
                                                                    )
                                                                )
                                                            ) ?>"

                                        data-update-url="<?= e(
                                                                url(
                                                                    '/desparasitaciones/'
                                                                        . (int)$v['id']
                                                                        . '/actualizar'
                                                                )
                                                            ) ?>">
                                        Editar
                                    </button>


                                    <button
                                        type="button"
                                        class="btn btn-danger btn-sm"

                                        data-deworming-cancel

                                        data-id="<?= (int)$v['id'] ?>"

                                        data-patient="<?= e(
                                                            $v['paciente']
                                                        ) ?>"

                                        data-drug="<?= e(
                                                        $v['farmaco']
                                                    ) ?>"

                                        data-cancel-url="<?= e(
                                                                url(
                                                                    '/desparasitaciones/'
                                                                        . (int)$v['id']
                                                                        . '/anular'
                                                                )
                                                            ) ?>">
                                        Anular
                                    </button>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</section>


<!--
|--------------------------------------------------------------------------
| MODAL CREAR
|--------------------------------------------------------------------------
-->

<div
    class="modal"
    id="dew-create">
    <div class="modal-backdrop"></div>

    <div class="modal-dialog modal-lg">

        <div class="modal-header">

            <h2>
                Registrar desparasitación
            </h2>

            <button
                type="button"
                class="modal-close"
                data-modal-close>
                ×
            </button>

        </div>


        <form
            method="POST"
            action="<?= url(
                        '/desparasitaciones'
                    ) ?>">

            <?= csrf_field() ?>


            <div class="modal-body form-grid">

                <label class="field-full">

                    <span>Paciente</span>

                    <select
                        name="animal_id"
                        required>

                        <?php foreach ($patients as $p): ?>

                            <option
                                value="<?= (int)$p['id'] ?>">
                                <?= e($p['nombre']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </label>


                <label>

                    <span>Fármaco</span>

                    <select
                        name="farmaco_id"
                        required>

                        <?php foreach ($drugs as $x): ?>

                            <option
                                value="<?= (int)$x['id'] ?>">
                                <?= e($x['nombre']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </label>


                <label>

                    <span>Dosis</span>

                    <input
                        type="number"
                        step="any"
                        name="dosis">

                </label>


                <label>

                    <span>Unidad</span>

                    <select name="unidad_dosis_id">

                        <option value="">
                            —
                        </option>

                        <?php foreach ($units as $u): ?>

                            <option
                                value="<?= (int)$u['id'] ?>">
                                <?= e($u['simbolo']) ?>
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
                        name="proxima_desparasitacion">

                </label>


                <label>

                    <span>Peso kg</span>

                    <input
                        type="number"
                        step=".001"
                        min="0.001"
                        name="peso_kg">

                </label>


                <label class="field-full">

                    <span>Observaciones</span>

                    <textarea
                        name="observaciones"></textarea>

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
                    Guardar
                </button>

            </div>

        </form>

    </div>
</div>


<!--
|--------------------------------------------------------------------------
| MODAL EDITAR
|--------------------------------------------------------------------------
-->

<div
    class="modal"
    id="dew-edit">
    <div
        class="modal-backdrop"
        data-modal-close></div>


    <div class="modal-dialog modal-lg">

        <div class="modal-header">

            <div>

                <h2>
                    Editar desparasitación
                </h2>

                <p
                    class="muted"
                    id="dew-edit-patient"></p>

            </div>


            <button
                type="button"
                class="modal-close"
                data-modal-close>
                ×
            </button>

        </div>


        <form
            method="POST"
            id="dew-edit-form">

            <?= csrf_field() ?>


            <div class="modal-body form-grid">

                <label>

                    <span>
                        Fecha de aplicación
                    </span>

                    <input
                        type="datetime-local"
                        name="fecha_evento"
                        id="dew-edit-date">

                </label>


                <label>

                    <span>Fármaco</span>

                    <select
                        name="farmaco_id"
                        id="dew-edit-drug"
                        required>

                        <?php foreach ($drugs as $x): ?>

                            <option
                                value="<?= (int)$x['id'] ?>">
                                <?= e($x['nombre']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </label>


                <label>

                    <span>Dosis</span>

                    <input
                        type="number"
                        step="any"
                        name="dosis"
                        id="dew-edit-dose">

                </label>


                <label>

                    <span>Unidad</span>

                    <select
                        name="unidad_dosis_id"
                        id="dew-edit-unit">

                        <option value="">
                            —
                        </option>

                        <?php foreach ($units as $u): ?>

                            <option
                                value="<?= (int)$u['id'] ?>">
                                <?= e($u['simbolo']) ?>
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
                        name="proxima_desparasitacion"
                        id="dew-edit-next-date">

                </label>


                <label class="field-full">

                    <span>Observaciones</span>

                    <textarea
                        name="observaciones"
                        id="dew-edit-observations"></textarea>

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
                    Guardar cambios
                </button>

            </div>

        </form>

    </div>
</div>


<!--
|--------------------------------------------------------------------------
| MODAL ANULAR
|--------------------------------------------------------------------------
-->

<div
    class="modal"
    id="dew-cancel">
    <div
        class="modal-backdrop"
        data-modal-close></div>


    <div class="modal-dialog">

        <div class="modal-header">

            <div>

                <h2>
                    Anular desparasitación
                </h2>

                <p
                    class="muted"
                    id="dew-cancel-description"></p>

            </div>


            <button
                type="button"
                class="modal-close"
                data-modal-close>
                ×
            </button>

        </div>


        <form
            method="POST"
            id="dew-cancel-form">

            <?= csrf_field() ?>


            <div class="modal-body">

                <div class="alert alert-warning">

                    La desparasitación permanecerá
                    registrada en el historial clínico
                    como un evento anulado.

                    Los recordatorios pendientes
                    asociados serán cancelados.

                </div>


                <label>

                    <span>
                        Motivo de anulación
                    </span>

                    <textarea
                        name="motivo_anulacion"
                        id="dew-cancel-reason"
                        required
                        placeholder="Indica por qué se anula esta desparasitación"></textarea>

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
                    class="btn btn-danger">
                    Anular desparasitación
                </button>

            </div>

        </form>

    </div>
</div>
<script src="<?= url('assets/js/views/vacunas.js') ?>"></script>