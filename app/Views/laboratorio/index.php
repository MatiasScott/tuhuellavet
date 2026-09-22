<link
    rel="stylesheet"
    href="<?= url('/assets/css/views/laboratorio.css') ?>">

<div class="page-heading">
    <div><span class="eyebrow">Diagnóstico</span>
        <h1>Laboratorio clínico</h1>
        <p>Solicitudes, resultados y documentos PDF.</p>
    </div>
    <button class="btn btn-primary" data-modal-open="lab-create">＋ Examen</button>
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
                    <th>Tipo</th>
                    <th>Documentos</th>
                    <th>Resultado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody><?php foreach ($exams as $x): ?><tr>
                        <td><?= e($x['fecha_solicitud']) ?></td>
                        <td><?= e($x['paciente']) ?></td>
                        <td><?= e($x['tipo']) ?></td>
                        <td>
                            <?php if (!empty($x['archivos'])): ?>
                                <div class="lab-document-actions">
                                    <?php foreach ($x['archivos'] as $file): ?>
                                        <button
                                            type="button"
                                            class="btn btn-secondary btn-sm"
                                            data-lab-document-url="<?= e(
                                                                        url('/laboratorio/archivo?id=' . (int) $file['id'])
                                                                    ) ?>">
                                            👁 Ver documento
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <span class="text-muted">
                                    Sin documento
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($x['resultado_resumen'] ?? 'Pendiente') ?></td>
                        <td>
                            <div class="lab-row-actions">
                                <button
                                    type="button"
                                    class="btn btn-secondary btn-sm"
                                    data-modal-open="lab-edit-<?= (int) $x['id'] ?>">
                                    ✎ Editar
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-danger btn-sm"
                                    data-modal-open="lab-delete-<?= (int) $x['id'] ?>">
                                    🗑 Eliminar
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<!-- ==========================================
     VISOR DE DOCUMENTOS DE LABORATORIO
========================================== -->

<div
    class="modal"
    id="lab-pdf-viewer"
    aria-hidden="true">
    <div class="modal-backdrop"></div>
    <div class="modal-dialog modal-xl lab-pdf-dialog">
        <div class="modal-header">
            <h2>Documento de laboratorio</h2>
            <button
                type="button"
                class="modal-close"
                data-modal-close
                aria-label="Cerrar visor">
                ×
            </button>
        </div>
        <div class="modal-body lab-pdf-body">
            <iframe
                id="lab-pdf-frame"
                title="Visor de documento clínico"
                loading="lazy"></iframe>
        </div>
    </div>
</div>

<?php foreach ($exams as $x): ?>
    <div
        class="modal"
        id="lab-edit-<?= (int) $x['id'] ?>">
        <div class="modal-backdrop"></div>
        <div class="modal-dialog modal-lg">
            <div class="modal-header">
                <h2>Editar examen de laboratorio</h2>
                <button
                    type="button"
                    class="modal-close"
                    data-modal-close>
                    ×
                </button>
            </div>
            <form
                method="POST"
                enctype="multipart/form-data"
                action="<?= url('/laboratorio/editar') ?>">
                <input type="hidden" name="examen_id" value="<?= (int) $x['id'] ?>">
                <?= csrf_field() ?>
                <div class="modal-body form-grid">
                    <label>
                        <span>Paciente</span>
                        <input
                            type="text"
                            value="<?= e($x['paciente']) ?>"
                            disabled>
                    </label>
                    <label>
                        <span>Tipo de examen *</span>
                        <select name="tipo_examen_id" required>
                            <?php foreach ($types as $t): ?>
                                <option
                                    value="<?= (int) $t['id'] ?>"
                                    <?= (int) $t['id'] === (int) $x['tipo_examen_id']
                                        ? 'selected'
                                        : '' ?>>
                                    <?= e($t['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Fecha de solicitud</span>
                        <input
                            type="datetime-local"
                            name="fecha_solicitud"
                            value="<?= e(
                                        !empty($x['fecha_solicitud'])
                                            ? date(
                                                'Y-m-d\TH:i',
                                                strtotime($x['fecha_solicitud'])
                                            )
                                            : ''
                                    ) ?>">
                    </label>
                    <label>
                        <span>Fecha de resultado</span>
                        <input
                            type="datetime-local"
                            name="fecha_resultado"
                            value="<?= e(
                                        !empty($x['fecha_resultado'])
                                            ? date(
                                                'Y-m-d\TH:i',
                                                strtotime($x['fecha_resultado'])
                                            )
                                            : ''
                                    ) ?>">
                    </label>
                    <label class="field-full">
                        <span>Resumen del resultado</span>
                        <textarea
                            name="resultado_resumen"
                            rows="4"><?= e($x['resultado_resumen'] ?? '') ?></textarea>
                    </label>

                    <label class="field-full">
                        <span>Adjuntar nuevo PDF o imagen</span>
                        <input
                            type="file"
                            name="archivo"
                            accept="application/pdf,image/jpeg,image/png,image/webp">
                        <small>
                            Si no seleccionas un archivo,
                            se conservarán los documentos existentes.
                        </small>
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
<?php endforeach; ?>
<?php foreach ($exams as $x): ?>
    <div
        class="modal"
        id="lab-delete-<?= (int) $x['id'] ?>">
        <div class="modal-backdrop"></div>
        <div class="modal-dialog">
            <div class="modal-header">
                <h2>Eliminar examen</h2>
                <button
                    type="button"
                    class="modal-close"
                    data-modal-close>
                    ×
                </button>
            </div>
            <form
                method="POST"
                action="<?= url('/laboratorio/eliminar') ?>">
                <?= csrf_field() ?>
                <input
                    type="hidden"
                    name="examen_id"
                    value="<?= (int) $x['id'] ?>">
                <div class="modal-body">
                    <p>
                        ¿Deseas eliminar el examen
                        <strong><?= e($x['tipo']) ?></strong>
                        del paciente
                        <strong><?= e($x['paciente']) ?></strong>?
                    </p>
                    <p class="text-muted">
                        Esta acción retirará el examen
                        del listado clínico.
                    </p>
                    <label class="field-full">
                        <span>Motivo de anulación *</span>
                        <textarea
                            name="motivo_anulacion"
                            rows="3"
                            maxlength="1000"
                            placeholder="Indica por qué se anula este examen."
                            required></textarea>
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
                        Confirmar eliminación
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endforeach; ?>

<div class="modal" id="lab-create">
    <div class="modal-backdrop"></div>
    <div class="modal-dialog modal-lg">
        <div class="modal-header">
            <h2>Nuevo examen</h2>
            <button class="modal-close" data-modal-close>×</button>
        </div>
        <form method="POST" enctype="multipart/form-data" action="<?= url('/laboratorio') ?>">
            <?= csrf_field() ?>
            <div class="modal-body form-grid">
                <label>
                    <span>Paciente</span>
                    <select name="animal_id" required>
                        <?php foreach ($patients as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= e($p['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Tipo</span>
                    <select name="tipo_examen_id" required>
                        <?php foreach ($types as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= e($t['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Fecha solicitud</span>
                    <input type="datetime-local" name="fecha_solicitud">
                </label>
                <label>
                    <span>Fecha resultado</span>
                    <input type="datetime-local" name="fecha_resultado">
                </label>
                <label class="field-full">
                    <span>Resumen resultado</span>
                    <textarea name="resultado_resumen"></textarea>
                </label>
                <label class="field-full">
                    <span>PDF / imagen</span>
                    <input type="file" name="archivo" accept="application/pdf,image/*">
                </label>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>
<script
    src="<?= url('/assets/js/views/laboratorio.js') ?>"
    defer></script>