<div class="page-heading">
    <div><span class="eyebrow">Seguridad</span>
        <h1>Roles y permisos</h1>
        <p>Matriz dinámica de accesos por módulo.</p>
    </div>
</div>
<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>
<section class="card">
    <form method="GET" class="toolbar"><select name="rol_id" onchange="this.form.submit()">
            <?php foreach ($roles as $r): ?>
                <option value="<?= $r['id'] ?>" <?= $selectedRole == $r['id'] ? 'selected' : '' ?>><?= e($r['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
    <form method="POST" action="<?= url('/admin/roles/' . $selectedRole . '/permisos') ?>"><?= csrf_field() ?><div class="table-wrap">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Módulo</th>
                        <th>Acción</th>
                        <th>Permitir</th>
                    </tr>
                </thead>
                <tbody><?php foreach ($permissions as $p): ?><tr>
                            <td><?= e($p['modulo']) ?></td>
                            <td><?= e($p['accion']) ?></td>
                            <td><input type="checkbox" name="permisos[]" value="<?= $p['id'] ?>" <?= in_array((int)$p['id'], $selectedPermissions, true) ? 'checked' : '' ?>></td>
                        </tr><?php endforeach; ?></tbody>
            </table>
        </div>
        <div class="mt-1"><button class="btn btn-primary">Guardar permisos</button></div>
    </form>
</section>
<section class="card mt-1">
    <div class="card-header">
        <h2>Crear rol</h2>
    </div>
    <form method="POST" action="<?= url('/admin/roles') ?>" class="form-grid">
        <?= csrf_field() ?>
        <label>
            <span>Código</span>
            <input name="codigo" required>
        </label>
        <label>
            <span>Nombre</span>
            <input name="nombre" required>
        </label>
        <label class="field-full">
            <span>Descripción</span>
            <input name="descripcion">
        </label>
        <div><button class="btn btn-primary">Crear rol</button></div>
    </form>
</section>