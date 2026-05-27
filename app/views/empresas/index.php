<?php
ob_start();
?>
<?php
$pageTitle = 'Empresas';
$empresasSafe = isset($empresas) && is_array($empresas) ? $empresas : [];
$hasColorColumnsSafe = isset($hasColorColumns) ? (bool) $hasColorColumns : false;
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$successSafe = isset($success) ? (string) $success : '';
$errorSafe = isset($error) ? (string) $error : '';
?>

<main class="container py-4">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <h1 class="h4 m-0">Empresas</h1>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/dashboard')); ?>">Volver</a>
    </div>

    <?php if ($successSafe !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($successSafe); ?></div><?php endif; ?>
    <?php if ($errorSafe !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($errorSafe); ?></div><?php endif; ?>
    <?php if (!$hasColorColumnsSafe): ?><div class="alert alert-info">La tabla empresas actual no incluye colores. Se reutiliza el esquema vigente sin crear columnas nuevas.</div><?php endif; ?>

    <section class="card tvg-card p-3 mb-4">
        <h2 class="h6">Crear empresa</h2>
        <form method="post" action="<?php echo htmlspecialchars(url('/empresas/crear')); ?>" enctype="multipart/form-data" class="row g-2">
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
            <div class="col-md-4"><input class="form-control" name="nombre" placeholder="Nombre" required></div>
            <div class="col-md-2">
                <select class="form-select" name="tipo" required>
                    <option value="veterinaria">Veterinaria</option>
                    <option value="hacienda">Hacienda</option>
                </select>
            </div>
            <div class="col-md-3"><input class="form-control" name="telefono" placeholder="Telefono"></div>
            <div class="col-md-3"><input class="form-control" type="email" name="email" placeholder="Email"></div>
            <div class="col-md-8"><input class="form-control" name="direccion" placeholder="Direccion"></div>
            <div class="col-md-2"><input class="form-control" type="file" name="logo" accept=".jpg,.jpeg,.png,.webp"></div>
            <div class="col-md-2 d-flex align-items-center"><label><input type="checkbox" name="estado" value="1" checked> Activa</label></div>
            <div class="col-md-2"><button class="btn btn-brand w-100" type="submit">Crear</button></div>
        </form>
    </section>

    <section class="card tvg-card p-3">
        <h2 class="h6 mb-3">Empresas registradas</h2>
        <div class="table-responsive">
            <table class="table tvg-table table-hover mb-0 align-middle">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Logo</th>
                    <th>Nombre</th>
                    <th>Tipo</th>
                    <th>Contacto</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($empresasSafe as $e): ?>
                    <?php $logo = trim((string) ($e['logo'] ?? '')); ?>
                    <?php $logoUrl = $logo !== '' ? url('/imagen/ver?path=' . rawurlencode($logo)) : ''; ?>
                    <tr>
                        <td><?php echo (int) ($e['id'] ?? 0); ?></td>
                        <td>
                            <?php if ($logoUrl !== ''): ?>
                                <img src="<?php echo htmlspecialchars($logoUrl); ?>" alt="Logo" style="width:40px;height:40px;object-fit:cover;border-radius:8px;">
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars((string) ($e['nombre'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($e['tipo'] ?? '')); ?></td>
                        <td>
                            <div><?php echo htmlspecialchars((string) ($e['telefono'] ?? '')); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars((string) ($e['email'] ?? '')); ?></small>
                        </td>
                        <td><span class="badge tvg-badge <?php echo ((int) ($e['estado'] ?? 0) === 1) ? 'tvg-badge-success' : 'tvg-badge-muted'; ?>"><?php echo ((int) ($e['estado'] ?? 0) === 1) ? 'Activa' : 'Inactiva'; ?></span></td>
                        <td class="text-end">
                            <details>
                                <summary class="btn btn-sm btn-outline-primary">Editar</summary>
                                <form method="post" action="<?php echo htmlspecialchars(url('/empresas/actualizar')); ?>" enctype="multipart/form-data" class="row g-2 mt-2 text-start" style="min-width:420px;">
                                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
                                    <input type="hidden" name="id" value="<?php echo (int) ($e['id'] ?? 0); ?>">
                                    <div class="col-md-6"><input class="form-control form-control-sm" name="nombre" value="<?php echo htmlspecialchars((string) ($e['nombre'] ?? '')); ?>" required></div>
                                    <div class="col-md-6">
                                        <select class="form-select form-select-sm" name="tipo" required>
                                            <option value="veterinaria" <?php echo ((string) ($e['tipo'] ?? '') === 'veterinaria') ? 'selected' : ''; ?>>Veterinaria</option>
                                            <option value="hacienda" <?php echo ((string) ($e['tipo'] ?? '') === 'hacienda') ? 'selected' : ''; ?>>Hacienda</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6"><input class="form-control form-control-sm" name="telefono" value="<?php echo htmlspecialchars((string) ($e['telefono'] ?? '')); ?>"></div>
                                    <div class="col-md-6"><input class="form-control form-control-sm" type="email" name="email" value="<?php echo htmlspecialchars((string) ($e['email'] ?? '')); ?>"></div>
                                    <div class="col-md-8"><input class="form-control form-control-sm" name="direccion" value="<?php echo htmlspecialchars((string) ($e['direccion'] ?? '')); ?>"></div>
                                    <div class="col-md-4"><input class="form-control form-control-sm" type="file" name="logo" accept=".jpg,.jpeg,.png,.webp"></div>
                                    <div class="col-md-4 d-flex align-items-center"><label><input type="checkbox" name="estado" value="1" <?php echo ((int) ($e['estado'] ?? 0) === 1) ? 'checked' : ''; ?>> Activa</label></div>
                                    <div class="col-md-8"><button class="btn btn-sm btn-outline-primary" type="submit">Guardar cambios</button></div>
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
