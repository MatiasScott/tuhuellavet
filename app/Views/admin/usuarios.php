<div class="page-heading">
    <div><span class="eyebrow">Administración</span>
        <h1>Usuarios</h1>
        <p>Accesos y roles del entorno.</p>
    </div><button class="btn btn-primary" data-modal-open="user-create">＋ Usuario</button>
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
                    <th>Usuario</th>
                    <th>Correo</th>
                    <th>Roles</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody><?php foreach ($users as $u): ?><tr>
                        <td><?= e($u['nombres'] . ' ' . $u['apellidos']) ?></td>
                        <td><?= e($u['email']) ?></td>
                        <td><?= e($u['roles'] ?? '—') ?></td>
                        <td><?= $u['activo'] ? 'Activo' : 'Inactivo' ?></td>
                        <td>
                            <?php if (can('usuarios.editar')): ?>

                                <button
                                    type="button"
                                    class="btn btn-secondary btn-sm"
                                    data-edit-user="<?= (int) $u['id'] ?>"
                                    data-modal-open="user-edit">
                                    Editar
                                </button>

                            <?php endif; ?>
                            <?php if (
                                can('usuarios.editar') &&
                                (int) $u['activo'] === 1 &&
                                (int) ($u['acceso_entorno_activo'] ?? 0) === 1
                            ): ?>

                                <button
                                    type="button"
                                    class="btn btn-secondary btn-sm"
                                    data-resend-invitation="<?= (int) $u['id'] ?>"
                                    data-user-name="<?= e(
                                                        $u['nombres'] . ' ' . $u['apellidos']
                                                    ) ?>"
                                    data-user-email="<?= e($u['email']) ?>"
                                    data-modal-open="user-resend-invitation">
                                    <icon name="mail" size="sm" />
                                    Reenviar invitación
                                </button>

                            <?php endif; ?>
                        </td>
                    </tr><?php endforeach; ?></tbody>
        </table>
    </div>
</section>
<div class="modal" id="user-create">
    <div class="modal-backdrop"></div>
    <div class="modal-dialog">
        <div class="modal-header">
            <h2>Nuevo usuario</h2><button class="modal-close" data-modal-close>×</button>
        </div>
        <form method="POST" action="<?= url('/admin/usuarios') ?>">
            <?= csrf_field() ?>
            <div class="modal-body form-grid">
                <label>
                    <span>Nombres</span>
                    <input name="nombres" required>
                </label>
                <label>
                    <span>Apellidos</span>
                    <input name="apellidos" required>
                </label>
                <label class="field-full">
                    <span>Email</span>
                    <input type="email" name="email" required>
                </label>
                <label>
                    <span>Teléfono</span>
                    <input name="telefono">
                </label>
                <label>
                    <span>Rol</span>
                    <select name="rol_id">
                        <option value="">Sin rol</option><?php foreach ($roles as $r): ?><option value="<?= $r['id'] ?>"><?= e($r['nombre']) ?></option><?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="modal-footer"><button class="btn btn-primary">Crear</button></div>
        </form>
    </div>
</div>
<div class="modal" id="user-edit">

    <div class="modal-backdrop"></div>

    <div class="modal-dialog">

        <div class="modal-header">

            <h2>Editar usuario</h2>

            <button
                type="button"
                class="modal-close"
                data-modal-close>
                ×
            </button>

        </div>

        <form
            id="user-edit-form"
            method="POST"
            action=""
            data-action-template="<?= e(
                                        url('/admin/usuarios/__ID__/editar')
                                    ) ?>">

            <?= csrf_field() ?>

            <div class="modal-body form-grid">

                <label>
                    <span>Nombres</span>

                    <input
                        name="nombres"
                        id="edit-user-nombres"
                        required
                        maxlength="120">
                </label>

                <label>
                    <span>Apellidos</span>

                    <input
                        name="apellidos"
                        id="edit-user-apellidos"
                        required
                        maxlength="120">
                </label>

                <label class="field-full">

                    <span>Correo electrónico</span>

                    <input
                        type="email"
                        id="edit-user-email"
                        readonly>

                    <small>
                        La modificación del correo estará disponible
                        cuando implementemos su verificación.
                    </small>

                </label>

                <label>
                    <span>Teléfono</span>

                    <input
                        name="telefono"
                        id="edit-user-telefono"
                        maxlength="30">
                </label>

                <label id="edit-user-role-container">
                    <span>Rol del entorno</span>

                    <select
                        name="rol_id"
                        id="edit-user-role">

                        <option value="">
                            Mantener roles actuales
                        </option>

                        <?php foreach ($roles as $role): ?>

                            <?php
                            if (
                                !empty($role['es_global']) ||
                                $role['codigo'] === 'SUPER_ADMINISTRADOR'
                            ) {
                                continue;
                            }

                            if (
                                empty(auth_user()['is_super_admin']) &&
                                $role['codigo'] !== 'CLIENTE'
                            ) {
                                continue;
                            }
                            ?>

                            <option value="<?= (int) $role['id'] ?>">
                                <?= e($role['nombre']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </label>

                <label class="field-full" id="edit-user-active-container">

                    <span>Estado</span>

                    <select
                        name="activo"
                        id="edit-user-active">

                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>

                    </select>

                </label>

                <div
                    id="edit-user-error"
                    class="alert alert-danger"
                    hidden></div>

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
                    class="btn btn-primary"
                    id="edit-user-submit"
                    disabled>
                    Guardar cambios
                </button>

            </div>

        </form>

    </div>

</div>
<div class="modal" id="user-resend-invitation">

    <div class="modal-backdrop"></div>

    <div class="modal-dialog">

        <div class="modal-header">

            <h2>Reenviar invitación</h2>

            <button
                type="button"
                class="modal-close"
                data-modal-close>
                ×
            </button>

        </div>

        <form
            id="user-resend-form"
            method="POST"
            action=""
            data-action-template="<?= e(
                                        url('/admin/usuarios/__ID__/reenviar-invitacion')
                                    ) ?>">

            <?= csrf_field() ?>

            <div class="modal-body">

                <p>
                    Se enviará un nuevo enlace para establecer
                    la contraseña del siguiente usuario:
                </p>

                <div class="card" style="padding:16px">

                    <strong id="resend-user-name"></strong>

                    <p
                        id="resend-user-email"
                        style="margin-top:6px"></p>

                </div>

                <p style="margin-top:16px">

                    El enlace tendrá una vigencia de 60 minutos.

                    La contraseña actual no cambiará hasta
                    que el usuario complete el proceso.

                </p>

                <div
                    id="resend-user-error"
                    class="alert alert-danger"
                    hidden></div>

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
                    class="btn btn-primary"
                    id="resend-user-submit"
                    disabled>
                    Enviar invitación
                </button>

            </div>

        </form>

    </div>

</div>
<script
    src="<?= asset('js/views/usuarios.js') ?>"
    defer></script>