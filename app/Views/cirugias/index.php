<div class="page-heading">
    <div>
        <span class="eyebrow">Procedimientos</span>
        <h1>Cirugías</h1>
        <p>Procedimiento, anestesia, documentos y seguimiento.</p>
    </div>
    <button class="btn btn-primary" data-modal-open="surg-create">
        ＋ Cirugía
    </button>
</div>
<?php if ($success): ?>
    <div class="alert alert-success">
        <?= e($success) ?></div><?php endif; ?><?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?>
    </div><?php endif; ?>
<section class="card">
    <div class="table-wrap">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Paciente</th>
                    <th>Procedimiento</th>
                    <th>Médico</th>
                    <th></th>
                </tr>
            </thead>
            <tbody><?php foreach ($surgeries as $x): ?>
                    <tr>
                        <td>
                            <?= e($x['fecha_inicio']) ?>
                        </td>
                        <td>
                            <?= e($x['paciente']) ?>
                        </td>
                        <td>
                            <?= e($x['procedimiento']) ?>
                        </td>
                        <td>
                            <?= e($x['medico']) ?>
                        </td>
                        <td>
                            <a
                                class="btn btn-secondary btn-sm"
                                href="<?= url(
                                            '/cirugias/'
                                                . (int) $x['evento_clinico_id']
                                        ) ?>">
                                Ver ficha
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<div class="modal" id="surg-create">
    <div class="modal-backdrop">

    </div>
    <div class="modal-dialog modal-xl">
        <div class="modal-header">
            <h2>Nueva cirugía</h2>
            <button class="modal-close" data-modal-close>
                ×
            </button>
        </div>
        <form method="POST" enctype="multipart/form-data" action="<?= url('/cirugias') ?>">
            <?= csrf_field() ?><div class="modal-body form-grid">
                <label><span>Paciente</span>
                    <select name="animal_id" required><?php foreach ($patients as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= e($p['nombre']) ?>
                            </option><?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Procedimiento</span>
                    <select name="procedimiento_quirurgico_id" required>
                        <?php foreach ($procedures as $p): ?>
                            <option value="<?= $p['id'] ?>">
                                <?= e($p['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>
                    <span>Fecha inicio</span><input type="datetime-local" name="fecha_inicio"></label><label><span>Anestesia</span><select name="tipo_anestesia_id">
                        <option value="">—</option><?php foreach ($anesthesiaTypes as $a): ?><option value="<?= $a['id'] ?>"><?= e($a['nombre']) ?></option><?php endforeach; ?>
                    </select></label><label class="field-full"><span>Diagnóstico preoperatorio</span><textarea name="diagnostico_preoperatorio"></textarea></label><label class="field-full"><span>Descripción procedimiento</span><textarea name="descripcion_procedimiento"></textarea></label><label class="field-full"><span>Protocolo anestésico</span><textarea name="protocolo_anestesia"></textarea></label><label class="field-full"><span>Documento PDF</span><input type="file" name="archivo" accept="application/pdf,image/*"></label>
            </div>
            <div class="modal-footer"><button class="btn btn-primary">Guardar</button></div>
        </form>
    </div>
</div>