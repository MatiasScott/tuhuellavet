<?php
ob_start();
?>
<?php

$pageTitle = 'Usuarios';
?>
<?php
$usuariosSafe = isset($usuarios) && is_array($usuarios) ? $usuarios : [];
$rolesSafe = isset($roles) && is_array($roles) ? $roles : [];
$empresasSafe = isset($empresas) && is_array($empresas) ? $empresas : [];
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$successSafe = isset($success) ? (string) $success : '';
$errorSafe = isset($error) ? (string) $error : '';
?>

<main class="container py-4">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <h1 class="h4 m-0">Administracion de usuarios</h1>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/dashboard')); ?>">Volver</a>
    </div>

    <?php if ($successSafe !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($successSafe); ?></div><?php endif; ?>
    <?php if ($errorSafe !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($errorSafe); ?></div><?php endif; ?>

    <section class="card tvg-card p-3 mb-4">
        <h2 class="h6 mb-3">Crear usuario</h2>
        <form method="post" action="<?php echo htmlspecialchars(url('/usuarios/crear')); ?>" class="row g-2">
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
            <div class="col-md-3"><input class="form-control" name="nombres" placeholder="Nombres" required></div>
            <div class="col-md-3"><input class="form-control" name="apellidos" placeholder="Apellidos"></div>
            <div class="col-md-3"><input class="form-control" type="email" name="email" placeholder="Email" required></div>
            <div class="col-md-3"><input class="form-control" name="telefono" placeholder="Telefono"></div>
            <div class="col-md-3"><input class="form-control" type="password" name="password" placeholder="Contrasena inicial" required></div>
            <div class="col-md-2 d-flex align-items-center"><label><input type="checkbox" name="estado" value="1" checked> Activo</label></div>
            <div class="col-md-2"><button class="btn btn-brand w-100" type="submit">Crear</button></div>
        </form>
    </section>

    <section class="card tvg-card p-3">
        <h2 class="h6 mb-3">Usuarios registrados</h2>
        <div class="table-responsive">
            <table class="table tvg-table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Telefono</th>
                        <th>Empresas</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuariosSafe as $u): ?>
                        <tr>
                            <td><?php echo (int) ($u['id'] ?? 0); ?></td>
                            <td><?php echo htmlspecialchars(trim((string) ($u['nombres'] ?? '') . ' ' . (string) ($u['apellidos'] ?? ''))); ?></td>
                            <td><?php echo htmlspecialchars((string) ($u['email'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($u['telefono'] ?? '')); ?></td>
                            <td><?php echo (int) ($u['total_empresas'] ?? 0); ?></td>
                            <td><span class="badge tvg-badge <?php echo ((int) ($u['estado'] ?? 0) === 1) ? 'tvg-badge-success' : 'tvg-badge-muted'; ?>"><?php echo ((int) ($u['estado'] ?? 0) === 1) ? 'Activo' : 'Inactivo'; ?></span></td>
                            <td class="text-end">
                                <details>
                                    <summary class="btn btn-sm btn-outline-primary">Gestionar</summary>
                                    <div class="mt-2 text-start" style="min-width:360px;">
                                        <form method="post" action="<?php echo htmlspecialchars(url('/usuarios/actualizar')); ?>" class="row g-2 mb-2">
                                            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
                                            <input type="hidden" name="id" value="<?php echo (int) ($u['id'] ?? 0); ?>">
                                            <div class="col-md-6"><input class="form-control form-control-sm" name="nombres" value="<?php echo htmlspecialchars((string) ($u['nombres'] ?? '')); ?>" required></div>
                                            <div class="col-md-6"><input class="form-control form-control-sm" name="apellidos" value="<?php echo htmlspecialchars((string) ($u['apellidos'] ?? '')); ?>"></div>
                                            <div class="col-md-6"><input class="form-control form-control-sm" type="email" name="email" value="<?php echo htmlspecialchars((string) ($u['email'] ?? '')); ?>" required></div>
                                            <div class="col-md-4"><input class="form-control form-control-sm" name="telefono" value="<?php echo htmlspecialchars((string) ($u['telefono'] ?? '')); ?>"></div>
                                            <div class="col-md-2 d-flex align-items-center"><label><input type="checkbox" name="estado" value="1" <?php echo ((int) ($u['estado'] ?? 0) === 1) ? 'checked' : ''; ?>> On</label></div>
                                            <div class="col-md-12"><button class="btn btn-sm btn-outline-primary" type="submit">Guardar datos</button></div>
                                        </form>

                                        <form method="post" action="<?php echo htmlspecialchars(url('/usuarios/reset-password')); ?>" class="row g-2 mb-2">
                                            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
                                            <input type="hidden" name="id" value="<?php echo (int) ($u['id'] ?? 0); ?>">
                                            <div class="col-md-8"><input class="form-control form-control-sm" type="password" name="new_password" placeholder="Nueva contrasena" required></div>
                                            <div class="col-md-4"><button class="btn btn-sm btn-outline-warning w-100" type="submit">Resetear clave</button></div>
                                        </form>

                                        <form method="post" action="<?php echo htmlspecialchars(url('/usuarios/asignaciones')); ?>" class="row g-2">
                                            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
                                            <input type="hidden" name="id" value="<?php echo (int) ($u['id'] ?? 0); ?>">
                                            <div class="col-md-6">
                                                <select class="form-select form-select-sm" name="rol_id" required>
                                                    <option value="">Rol para empresas...</option>
                                                    <?php foreach ($rolesSafe as $r): ?>
                                                        <option value="<?php echo (int) ($r['id'] ?? 0); ?>"><?php echo htmlspecialchars((string) ($r['nombre'] ?? '')); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <select class="form-select form-select-sm" name="empresa_ids[]" multiple size="4" required>
                                                    <?php foreach ($empresasSafe as $e): ?>
                                                        <option value="<?php echo (int) ($e['id'] ?? 0); ?>"><?php echo htmlspecialchars((string) ($e['nombre'] ?? '')); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-12"><button class="btn btn-sm btn-outline-dark" type="submit">Reemplazar asignaciones empresa/rol</button></div>
                                        </form>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php
$pageContent = ob_get_clean();
require BASE_PATH . "/app/views/layaouts/app.php";
?>
