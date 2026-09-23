<div class="page-heading">
    <div>
        <span class="eyebrow">Prevención</span>
        <h1>Vacunación</h1>
        <p>Aplicaciones y próximas revacunaciones.</p>
    </div>
    <button class="btn btn-primary" data-modal-open="vac-create">＋ Vacuna</button>
</div>
<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>
<section class="card">
    <div class="table-wrap">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Paciente</th>
                    <th>Vacuna</th>
                    <th>Dosis</th>
                    <th>Casa Comercial</th>
                    <th>Lote</th>
                    <th>Próxima</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vaccinations as $v): ?>
                    <tr>
                        <td>
                            <?= e(date('d/m/Y', strtotime($v['fecha_evento']))) ?>
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
                                    <?= e(trim(($v['propietario_nombres'] ?? '') . ' ' . ($v['propietario_apellidos'] ?? ''))) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= e($v['vacuna']) ?>
                        </td>
                        <td>
                            <?= e($v['dosis'] ?? '—') ?>

                            <?= e($v['unidad'] ?? '') ?>
                        </td>
                        <td>
                            <?= e($v['casa_comercial'] ?? '—') ?>
                        </td>
                        <td>
                            <?= e($v['lote'] ?? '—') ?>
                        </td>
                        <td>
                            <?php if (!empty($v['fecha_revacunacion'])): ?>
                                <?= e(date('d/m/Y', strtotime($v['fecha_revacunacion']))) ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="catalog-actions">
                                <button
                                    type="button"
                                    class="btn btn-secondary btn-sm"
                                    data-vaccination-edit
                                    data-id="<?= (int)$v['id'] ?>"
                                    data-patient="<?= e($v['paciente']) ?>"
                                    data-vaccine-id="<?= (int)$v['vacuna_id'] ?>"
                                    data-dose="<?= e($v['dosis'] ?? '') ?>"
                                    data-unit-id="<?= e($v['unidad_dosis_id'] ?? '') ?>"
                                    data-lot="<?= e($v['lote'] ?? '') ?>"
                                    data-commercial-house="<?= e($v['casa_comercial'] ?? '') ?>"
                                    data-revaccination="<?= e($v['fecha_revacunacion'] ?? '') ?>"
                                    data-observations="<?= e($v['observaciones'] ?? '') ?>"
                                    data-event-date="<?= e(date('Y-m-d\TH:i', strtotime($v['fecha_evento']))) ?>"
                                    data-update-url="<?= e(url('/vacunas/' . (int)$v['id'] . '/actualizar')) ?>">
                                    Editar
                                </button>
                                <button type="button" class="btn btn-danger btn-sm" data-vaccination-cancel data-id="<?= (int)$v['id'] ?>" data-patient="<?= e($v['paciente']) ?>" data-vaccine="<?= e($v['vacuna']) ?>" data-cancel-url="<?= e(url('/vacunas/' . (int)$v['id'] . '/anular')) ?>">
                                    Anular
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<div class="modal" id="vac-create">
    <div class="modal-backdrop"></div>
    <div class="modal-dialog modal-lg">
        <div class="modal-header">
            <h2>Registrar vacunación</h2>
            <button class="modal-close" data-modal-close>×</button>
        </div>
        <form method="POST" action="<?= url('/vacunas') ?>">
            <?= csrf_field() ?>
            <div class="modal-body form-grid">
                <label class="field-full">
                    <span>Paciente</span>
                    <select name="animal_id" required>
                        <?php foreach ($patients as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= e($p['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Vacuna</span>
                    <select name="vacuna_id" required>
                        <?php foreach ($vaccines as $x): ?>
                            <option value="<?= $x['id'] ?>"><?= e($x['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Dosis</span>
                    <input type="number" step="any" name="dosis">
                </label>
                <label>
                    <span>Unidad</span>
                    <select name="unidad_dosis_id">
                        <option value="">—</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= e($u['simbolo']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Lote</span>
                    <input name="lote">
                </label>
                <label>
                    <span>Casa Comercial</span>

                    <input type="text" name="casa_comercial" maxlength="180" placeholder="Ej. Zoetis, MSD, Boehringer">
                </label>
                <label>
                    <span>Fecha revacunación</span>
                    <input type="date" name="fecha_revacunacion">
                </label>
                <label>
                    <span>Peso kg</span>
                    <input type="number" step=".001" name="peso_kg">
                </label>
                <label class="field-full">
                    <span>Observaciones</span>
                    <textarea name="observaciones"></textarea>
                </label>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<div
    class="modal"
    id="vac-edit">
    <div
        class="modal-backdrop"
        data-modal-close></div>

    <div class="modal-dialog modal-lg">

        <div class="modal-header">

            <div>
                <h2>Editar vacunación</h2>

                <p
                    class="muted"
                    id="vac-edit-patient"></p>
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
            id="vac-edit-form">
            <?= csrf_field() ?>


            <div class="modal-body form-grid">

                <label>
                    <span>Fecha de aplicación</span>

                    <input
                        type="datetime-local"
                        name="fecha_evento"
                        id="vac-edit-date">
                </label>


                <label>
                    <span>Vacuna</span>

                    <select
                        name="vacuna_id"
                        id="vac-edit-vaccine"
                        required>
                        <?php foreach ($vaccines as $x): ?>

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
                        id="vac-edit-dose">
                </label>


                <label>
                    <span>Unidad</span>

                    <select
                        name="unidad_dosis_id"
                        id="vac-edit-unit">
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
                    <span>Lote</span>

                    <input
                        type="text"
                        name="lote"
                        id="vac-edit-lot">
                </label>


                <label>
                    <span>Casa Comercial</span>

                    <input
                        type="text"
                        name="casa_comercial"
                        id="vac-edit-commercial-house"
                        maxlength="180">
                </label>


                <label>
                    <span>Fecha revacunación</span>

                    <input
                        type="date"
                        name="fecha_revacunacion"
                        id="vac-edit-revaccination">
                </label>


                <label class="field-full">
                    <span>Observaciones</span>

                    <textarea
                        name="observaciones"
                        id="vac-edit-observations"></textarea>
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

<div
    class="modal"
    id="vac-cancel">
    <div
        class="modal-backdrop"
        data-modal-close></div>

    <div class="modal-dialog">

        <div class="modal-header">

            <div>
                <h2>Anular vacunación</h2>

                <p
                    class="muted"
                    id="vac-cancel-description"></p>
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
            id="vac-cancel-form">
            <?= csrf_field() ?>


            <div class="modal-body">

                <div class="alert alert-warning">
                    La vacunación permanecerá en el historial
                    como registro anulado. También se cancelarán
                    los recordatorios pendientes asociados.
                </div>


                <label>
                    <span>Motivo de anulación</span>

                    <textarea
                        name="motivo_anulacion"
                        id="vac-cancel-reason"
                        required
                        placeholder="Indica por qué se anula esta vacunación"></textarea>
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
                    Anular vacunación
                </button>

            </div>

        </form>

    </div>
</div>

<script src="<?= url('assets/js/views/vacunas.js') ?>"></script>