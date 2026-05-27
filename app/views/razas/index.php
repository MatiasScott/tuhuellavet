<?php
ob_start();
?>
<?php
$pageTitle = 'Razas';
$razasSafe = isset($razas) && is_array($razas) ? $razas : [];
$especiesSafe = isset($especies) && is_array($especies) ? $especies : [];
$canToggleSafe = isset($canToggle) ? (bool) $canToggle : false;
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$successSafe = isset($success) ? (string) $success : '';
$errorSafe = isset($error) ? (string) $error : '';
?>

<main class="container py-4">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <h1 class="h4 m-0">Catalogo de razas</h1>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/dashboard')); ?>">Volver</a>
    </div>

    <?php if ($successSafe !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($successSafe); ?></div><?php endif; ?>
    <?php if ($errorSafe !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($errorSafe); ?></div><?php endif; ?>
    <?php if (!$canToggleSafe): ?><div class="alert alert-info">La tabla actual de razas no tiene columna estado. Se mantiene el esquema existente sin duplicaciones.</div><?php endif; ?>

    <section class="card tvg-card p-3 mb-4">
        <h2 class="h6">Crear raza</h2>
        <form method="post" action="<?php echo htmlspecialchars(url('/razas/crear')); ?>" class="row g-2">
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
            <div class="col-md-4">
                <select class="form-select" name="especie_id" required>
                    <option value="">Especie...</option>
                    <?php foreach ($especiesSafe as $e): ?>
                        <option value="<?php echo (int) ($e['id'] ?? 0); ?>"><?php echo htmlspecialchars((string) ($e['nombre'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6"><input class="form-control" name="nombre" placeholder="Nombre de raza" required></div>
            <div class="col-md-2"><button class="btn btn-brand w-100" type="submit">Crear</button></div>
        </form>
    </section>

    <section class="card tvg-card p-3">
        <h2 class="h6 mb-3">Razas registradas</h2>
        <div class="table-responsive">
            <table class="table tvg-table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Especie</th>
                        <th>Nombre</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($razasSafe as $r): ?>
                    <tr>
                        <td><?php echo (int) ($r['id'] ?? 0); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['especie_nombre'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['nombre'] ?? '')); ?></td>
                        <td class="text-end">
                            <details>
                                <summary class="btn btn-sm btn-outline-primary">Editar</summary>
                                <div class="mt-2 text-start" style="min-width:360px;">
                                    <form method="post" action="<?php echo htmlspecialchars(url('/razas/actualizar')); ?>" class="row g-2 mb-2">
                                        <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
                                        <input type="hidden" name="id" value="<?php echo (int) ($r['id'] ?? 0); ?>">
                                        <div class="col-md-6">
                                            <select class="form-select form-select-sm" name="especie_id" required>
                                                <?php foreach ($especiesSafe as $e): ?>
                                                    <option value="<?php echo (int) ($e['id'] ?? 0); ?>" <?php echo ((int) ($r['especie_id'] ?? 0) === (int) ($e['id'] ?? 0)) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($e['nombre'] ?? '')); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6"><input class="form-control form-control-sm" name="nombre" value="<?php echo htmlspecialchars((string) ($r['nombre'] ?? '')); ?>" required></div>
                                        <div class="col-md-12"><button class="btn btn-sm btn-outline-primary" type="submit">Guardar</button></div>
                                    </form>
                                    <?php if ($canToggleSafe): ?>
                                        <form method="post" action="<?php echo htmlspecialchars(url('/razas/estado')); ?>">
                                            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
                                            <input type="hidden" name="id" value="<?php echo (int) ($r['id'] ?? 0); ?>">
                                            <input type="hidden" name="estado" value="0">
                                            <button class="btn btn-sm btn-outline-warning" type="submit">Desactivar</button>
                                        </form>
                                    <?php endif; ?>
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
