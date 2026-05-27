<?php
ob_start();
?>
<?php

$pageTitle = 'Roles';
?>
<?php
$rolesSafe = isset($roles) && is_array($roles) ? $roles : [];
$permisosSafe = isset($permisos) && is_array($permisos) ? $permisos : [];
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$successSafe = isset($success) ? (string) $success : '';
$errorSafe = isset($error) ? (string) $error : '';
?>

<main class="container py-4">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <h1 class="h4 m-0">Roles y asignacion de permisos</h1>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/dashboard')); ?>">Volver</a>
    </div>

    <?php if ($successSafe !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($successSafe); ?></div><?php endif; ?>
    <?php if ($errorSafe !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($errorSafe); ?></div><?php endif; ?>

    <section class="card tvg-card p-3 mb-4">
        <h2 class="h6">Crear rol</h2>
        <form method="post" action="<?php echo htmlspecialchars(url('/roles/crear')); ?>" class="row g-2">
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
            <div class="col-md-4"><input class="form-control" name="nombre" placeholder="Nombre del rol" required></div>
            <div class="col-md-6"><input class="form-control" name="descripcion" placeholder="Descripcion"></div>
            <div class="col-md-2"><button class="btn btn-brand w-100" type="submit">Crear</button></div>
        </form>
    </section>

    <section class="card tvg-card p-3">
        <h2 class="h6 mb-3">Roles existentes</h2>
        <div class="table-responsive">
            <table class="table tvg-table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Rol</th>
                        <th>Descripcion</th>
                        <th>Permisos</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rolesSafe as $r): ?>
                        <?php $selected = isset($r['permiso_ids']) && is_array($r['permiso_ids']) ? $r['permiso_ids'] : []; ?>
                        <tr>
                            <td><?php echo (int) ($r['id'] ?? 0); ?></td>
                            <td><?php echo htmlspecialchars((string) ($r['nombre'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($r['descripcion'] ?? '')); ?></td>
                            <td><?php echo (int) ($r['total_permisos'] ?? 0); ?></td>
                            <td class="text-end">
                                <details>
                                    <summary class="btn btn-sm btn-outline-primary">Gestionar</summary>
                                    <div class="mt-2 text-start" style="min-width:420px;">
                                        <form method="post" action="<?php echo htmlspecialchars(url('/roles/actualizar')); ?>" class="row g-2 mb-2">
                                            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
                                            <input type="hidden" name="id" value="<?php echo (int) ($r['id'] ?? 0); ?>">
                                            <div class="col-md-4"><input class="form-control form-control-sm" name="nombre" value="<?php echo htmlspecialchars((string) ($r['nombre'] ?? '')); ?>" required></div>
                                            <div class="col-md-8"><input class="form-control form-control-sm" name="descripcion" value="<?php echo htmlspecialchars((string) ($r['descripcion'] ?? '')); ?>"></div>
                                            <div class="col-md-12"><button class="btn btn-sm btn-outline-primary" type="submit">Guardar rol</button></div>
                                        </form>

                                        <form method="post" action="<?php echo htmlspecialchars(url('/roles/duplicar')); ?>" class="row g-2 mb-2">
                                            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
                                            <input type="hidden" name="id" value="<?php echo (int) ($r['id'] ?? 0); ?>">
                                            <div class="col-md-8"><input class="form-control form-control-sm" name="new_name" placeholder="Nuevo nombre del duplicado" required></div>
                                            <div class="col-md-4"><button class="btn btn-sm btn-outline-secondary w-100" type="submit">Duplicar rol</button></div>
                                        </form>

                                        <form method="post" action="<?php echo htmlspecialchars(url('/roles/permisos')); ?>" class="row g-2">
                                            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
                                            <input type="hidden" name="id" value="<?php echo (int) ($r['id'] ?? 0); ?>">
                                            <div class="col-md-12">
                                                <select class="form-select form-select-sm" name="permiso_ids[]" multiple size="8">
                                                    <?php foreach ($permisosSafe as $p): ?>
                                                        <?php $pid = (int) ($p['id'] ?? 0); ?>
                                                        <option value="<?php echo $pid; ?>" <?php echo in_array($pid, $selected, true) ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars((string) ($p['modulo_nombre'] ?? 'Modulo')); ?> - <?php echo htmlspecialchars((string) ($p['nombre'] ?? 'Permiso')); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-12"><button class="btn btn-sm btn-outline-dark" type="submit">Guardar permisos del rol</button></div>
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
