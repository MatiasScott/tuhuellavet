<?php
ob_start();
?>
<?php

$pageTitle = 'Permisos';
?>
<?php
$permisosSafe = isset($permisos) && is_array($permisos) ? $permisos : [];
$modulosSafe = isset($modulos) && is_array($modulos) ? $modulos : [];
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$successSafe = isset($success) ? (string) $success : '';
$errorSafe = isset($error) ? (string) $error : '';
?>

<main class="container py-4">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <h1 class="h4 m-0">Permisos del sistema</h1>
        <div class="d-flex gap-2">
            <form method="post" action="<?php echo htmlspecialchars(url('/permisos/sincronizar')); ?>" class="m-0">
                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
                <button class="btn btn-outline-primary" type="submit">Sincronizar permisos</button>
            </form>
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/dashboard')); ?>">Volver</a>
        </div>
    </div>

    <?php if ($successSafe !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($successSafe); ?></div><?php endif; ?>
    <?php if ($errorSafe !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($errorSafe); ?></div><?php endif; ?>

    <section class="card tvg-card p-3 mb-4">
        <h2 class="h6">Crear permiso</h2>
        <p class="text-muted small mb-2">La creacion manual sigue disponible, pero se recomienda usar "Sincronizar permisos" para generar automaticamente modulo.ver/crear/editar/eliminar.</p>
        <form method="post" action="<?php echo htmlspecialchars(url('/permisos/crear')); ?>" class="row g-2">
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
            <div class="col-md-4">
                <select class="form-select" name="modulo_id" required>
                    <option value="">Modulo...</option>
                    <?php foreach ($modulosSafe as $m): ?>
                        <option value="<?php echo (int) ($m['id'] ?? 0); ?>"><?php echo htmlspecialchars((string) ($m['nombre'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3"><input class="form-control" name="nombre" placeholder="Nombre" required></div>
            <div class="col-md-3"><input class="form-control" name="slug" placeholder="Slug" required></div>
            <div class="col-md-2"><button class="btn btn-brand w-100" type="submit">Crear</button></div>
        </form>
    </section>

    <section class="card tvg-card p-3">
        <h2 class="h6 mb-3">Listado de permisos</h2>
        <div class="table-responsive">
            <table class="table tvg-table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Modulo</th>
                        <th>Nombre</th>
                        <th>Slug</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($permisosSafe as $p): ?>
                        <tr>
                            <td><?php echo (int) ($p['id'] ?? 0); ?></td>
                            <td><?php echo htmlspecialchars((string) ($p['modulo_nombre'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($p['nombre'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($p['slug'] ?? '')); ?></td>
                            <td class="text-end">
                                <details>
                                    <summary class="btn btn-sm btn-outline-primary">Editar</summary>
                                    <form method="post" action="<?php echo htmlspecialchars(url('/permisos/actualizar')); ?>" class="row g-2 mt-2 text-start" style="min-width:360px;">
                                        <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
                                        <input type="hidden" name="id" value="<?php echo (int) ($p['id'] ?? 0); ?>">
                                        <div class="col-md-4">
                                            <select class="form-select form-select-sm" name="modulo_id" required>
                                                <?php foreach ($modulosSafe as $m): ?>
                                                    <option value="<?php echo (int) ($m['id'] ?? 0); ?>" <?php echo ((int) ($p['modulo_id'] ?? 0) === (int) ($m['id'] ?? 0)) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($m['nombre'] ?? '')); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4"><input class="form-control form-control-sm" name="nombre" value="<?php echo htmlspecialchars((string) ($p['nombre'] ?? '')); ?>" required></div>
                                        <div class="col-md-4"><input class="form-control form-control-sm" name="slug" value="<?php echo htmlspecialchars((string) ($p['slug'] ?? '')); ?>" required></div>
                                        <div class="col-md-12"><button class="btn btn-sm btn-outline-primary" type="submit">Guardar</button></div>
                                    </form>
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
