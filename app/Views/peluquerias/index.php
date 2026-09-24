<div class="page-heading">
    <div>
        <span class="eyebrow">Servicios</span>

        <h1>Peluquería</h1>

        <p>
            Servicios realizados y próximas peluquerías.
        </p>
    </div>

    <button
        type="button"
        class="btn btn-primary"
        data-modal-open="groom-create">
        ＋ Peluquería
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



<!--
|--------------------------------------------------------------------------
| HISTORIAL
|--------------------------------------------------------------------------
-->

<section class="card">

    <div class="table-wrap">

        <table class="modern-table">

            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Paciente</th>
                    <th>Servicio</th>
                    <th>Próxima</th>
                    <th>Observaciones</th>
                    <th>Responsable</th>
                    <th>Acciones</th>
                </tr>
            </thead>


            <tbody>

                <?php if (empty($groomings)): ?>

                    <tr>
                        <td
                            colspan="7"
                            style="text-align:center;">
                            No existen servicios de peluquería registrados.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php foreach ($groomings as $v): ?>

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
                                <?= e($v['servicio']) ?>
                            </td>


                            <td>

                                <?php if (
                                    !empty($v['proxima_peluqueria'])
                                ): ?>

                                    <?= e(
                                        date(
                                            'd/m/Y',
                                            strtotime(
                                                $v['proxima_peluqueria']
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

                                    <?= e($v['observaciones']) ?>

                                <?php else: ?>

                                    —

                                <?php endif; ?>

                            </td>


                            <td>
                                <?= e(
                                    $v['responsable_nombre']
                                        ?? '—'
                                ) ?>
                            </td>


                            <td>

                                <div class="catalog-actions">

                                    <button
                                        type="button"
                                        class="btn btn-secondary btn-sm"

                                        data-grooming-edit

                                        data-id="<?= (int)$v['evento_clinico_id'] ?>"

                                        data-patient="<?= e(
                                                            $v['paciente']
                                                        ) ?>"

                                        data-service-id="<?= (int)$v['servicio_id'] ?>"

                                        data-next-date="<?= e(
                                                            $v['proxima_peluqueria']
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
                                                                    '/peluquerias/'
                                                                        . (int)$v['evento_clinico_id']
                                                                        . '/actualizar'
                                                                )
                                                            ) ?>">
                                        Editar
                                    </button>


                                    <button
                                        type="button"
                                        class="btn btn-danger btn-sm"

                                        data-grooming-cancel

                                        data-id="<?= (int)$v['evento_clinico_id'] ?>"

                                        data-patient="<?= e(
                                                            $v['paciente']
                                                        ) ?>"

                                        data-service="<?= e(
                                                            $v['servicio']
                                                        ) ?>"

                                        data-cancel-url="<?= e(
                                                                url(
                                                                    '/peluquerias/'
                                                                        . (int)$v['evento_clinico_id']
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
    id="groom-create">

    <div
        class="modal-backdrop"
        data-modal-close></div>


    <div class="modal-dialog modal-lg">

        <div class="modal-header">

            <h2>
                Registrar peluquería
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
                        '/peluquerias'
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

                    <span>
                        Fecha de ingreso
                    </span>

                    <input
                        type="datetime-local"
                        name="fecha_evento"
                        value="<?= e(
                                    date('Y-m-d\TH:i')
                                ) ?>"
                        required>

                </label>


                <label>

                    <span>Servicio</span>

                    <select
                        name="servicio_id"
                        required>

                        <?php foreach ($services as $service): ?>

                            <option
                                value="<?= (int)$service['id'] ?>">

                                <?= e($service['nombre']) ?>

                                <?php if (
                                    $service['precio_base'] !== null
                                    && $service['precio_base'] !== ''
                                ): ?>

                                    — $<?= e(
                                            number_format(
                                                (float)$service['precio_base'],
                                                2
                                            )
                                        ) ?>

                                <?php endif; ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </label>


                <label>

                    <span>
                        Próxima peluquería
                    </span>

                    <input
                        type="date"
                        name="proxima_peluqueria">

                </label>


                <label class="field-full">

                    <span>Observaciones</span>

                    <textarea
                        name="observaciones"
                        placeholder="Detalles del servicio realizado, indicaciones u observaciones"></textarea>

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
    id="groom-edit">

    <div
        class="modal-backdrop"
        data-modal-close></div>


    <div class="modal-dialog modal-lg">

        <div class="modal-header">

            <div>

                <h2>
                    Editar peluquería
                </h2>

                <p
                    class="muted"
                    id="groom-edit-patient"></p>

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
            id="groom-edit-form">

            <?= csrf_field() ?>


            <div class="modal-body form-grid">

                <label>

                    <span>
                        Fecha de ingreso
                    </span>

                    <input
                        type="datetime-local"
                        name="fecha_evento"
                        id="groom-edit-date"
                        required>

                </label>


                <label>

                    <span>Servicio</span>

                    <select
                        name="servicio_id"
                        id="groom-edit-service"
                        required>

                        <?php foreach ($services as $service): ?>

                            <option
                                value="<?= (int)$service['id'] ?>">
                                <?= e($service['nombre']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </label>


                <label>

                    <span>
                        Próxima peluquería
                    </span>

                    <input
                        type="date"
                        name="proxima_peluqueria"
                        id="groom-edit-next-date">

                </label>


                <label class="field-full">

                    <span>Observaciones</span>

                    <textarea
                        name="observaciones"
                        id="groom-edit-observations"></textarea>

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
    id="groom-cancel">

    <div
        class="modal-backdrop"
        data-modal-close></div>


    <div class="modal-dialog">

        <div class="modal-header">

            <div>

                <h2>
                    Anular peluquería
                </h2>

                <p
                    class="muted"
                    id="groom-cancel-description"></p>

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
            id="groom-cancel-form">

            <?= csrf_field() ?>


            <div class="modal-body">

                <div class="alert alert-warning">

                    La atención de peluquería permanecerá
                    registrada en el historial clínico
                    como un evento anulado.

                </div>


                <label>

                    <span>
                        Motivo de anulación
                    </span>

                    <textarea
                        name="motivo"
                        id="groom-cancel-reason"
                        required
                        placeholder="Indica por qué se anula esta atención"></textarea>

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
                    Anular peluquería
                </button>

            </div>

        </form>

    </div>
</div>

<script src="<?= url('assets/js/views/peluquerias.js') ?>"></script>
<script src="<?= url('assets/js/views/vacunas.js') ?>"></script>