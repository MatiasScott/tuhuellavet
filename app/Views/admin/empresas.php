<div class="page-heading">
    <div><span class="eyebrow">Administración</span>
        <h1>Empresas</h1>
        <p>Organizaciones y entornos vinculados.</p>
    </div><button class="btn btn-primary" data-modal-open="company-create">＋ Empresa</button>
</div><?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?><?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?><section class="card">
    <div class="table-wrap">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Empresa</th>
                    <th>RUC/ID</th>
                    <th>Entornos</th>
                    <th>Contacto</th>
                </tr>
            </thead>
            <tbody><?php foreach ($companies as $c): ?><tr>
                        <td><?= e($c['nombre']) ?></td>
                        <td><?= e($c['identificacion_fiscal'] ?? '—') ?></td>
                        <td><?= e($c['entornos_count']) ?></td>
                        <td><?= e($c['email'] ?? '—') ?></td>
                    </tr><?php endforeach; ?></tbody>
        </table>
    </div>
</section>
<div class="modal" id="company-create">
    <div class="modal-backdrop"></div>
    <div class="modal-dialog">
        <div class="modal-header">
            <h2>Nueva empresa</h2><button class="modal-close" data-modal-close>×</button>
        </div>
        <form method="POST" action="<?= url('/admin/empresas') ?>">
            <?= csrf_field() ?>
            <div class="modal-body form-grid">
                <label>
                    <span>Nombre</span>
                    <input name="nombre" required>
                </label>
                <label>
                    <span>Nombre comercial</span>
                    <input name="nombre_comercial">
                </label>
                <label>
                    <span>Razón social</span>
                    <input name="razon_social">
                </label>
                <label>
                    <span>Identificación fiscal</span>
                    <input name="identificacion_fiscal">
                </label>
                <label>
                    <span>Email</span>
                    <input type="email" name="email">
                </label>
                <label>
                    <span>Teléfono</span>
                    <input name="telefono">
                </label>
            </div>
            <div class="modal-footer"><button class="btn btn-primary">Crear</button></div>
        </form>
    </div>
</div>
<section class="card mt-1">
    <div class="card-header">
        <h2>Crear entorno</h2>
    </div>
    <form method="POST" action="<?= url('/admin/entornos') ?>" class="form-grid">
        <?= csrf_field() ?>
        <label>
            <span>Empresa</span>
            <select name="empresa_id">
                <?php foreach ($companies as $c): ?>
                    <option value="<?= $c['id'] ?>"><?= e($c['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Tipo</span>
            <select name="tipo_entorno_id">
                <?php foreach ($environmentTypes as $t): ?>
                    <option value="<?= $t['id'] ?>"><?= e($t['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Nombre</span>
            <input name="nombre" required>
        </label>
        <label>
            <span>Código</span>
            <input name="codigo" required>
        </label>
        <label>
            <span><input type="checkbox" name="es_productivo" value="1"> Productivo</span>
        </label>
        <label>
            <span><input type="checkbox" name="permite_facturacion_real" value="1"> Facturación real</span>
        </label>
        <label class="field-full">
            <span>Descripción</span>
            <textarea name="descripcion"></textarea>
        </label>
        <div><button class="btn btn-primary">Crear entorno</button></div>
    </form>
</section>