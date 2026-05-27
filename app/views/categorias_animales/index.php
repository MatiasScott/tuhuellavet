<?php
ob_start();
?>
<?php
$pageTitle = 'Categorias animales';
$categoriasSafe = isset($categorias) && is_array($categorias) ? $categorias : [];
$canToggleSafe = isset($canToggle) ? (bool) $canToggle : false;
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$successSafe = isset($success) ? (string) $success : '';
$errorSafe = isset($error) ? (string) $error : '';
?>

<main class="container py-4">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <h1 class="h4 m-0">Categorias de animales</h1>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/dashboard')); ?>">Volver</a>
    </div>

    <?php if ($successSafe !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($successSafe); ?></div><?php endif; ?>
    <?php if ($errorSafe !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($errorSafe); ?></div><?php endif; ?>
    <?php if (!$canToggleSafe): ?><div class="alert alert-info">La tabla actual de categorias_animales no tiene columna estado. Se mantiene el esquema existente sin duplicaciones.</div><?php endif; ?>

    <section class="card tvg-card p-3 mb-4">
        <h2 class="h6">Crear categoria</h2>
        <form method="post" action="<?php echo htmlspecialchars(url('/categorias-animales/crear')); ?>" class="row g-2">
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
            <div class="col-md-10"><input class="form-control" name="nombre" placeholder="Nombre de categoria" required></div>
            <div class="col-md-2"><button class="btn btn-brand w-100" type="submit">Crear</button></div>
        </form>
    </section>

    <section class="card tvg-card p-3">
        <h2 class="h6 mb-3">Categorias registradas</h2>
        <div class="table-responsive">
            <table class="table tvg-table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($categoriasSafe as $c): ?>
                    <tr>
                        <td><?php echo (int) ($c['id'] ?? 0); ?></td>
                        <td><?php echo htmlspecialchars((string) ($c['nombre'] ?? '')); ?></td>
                        <td class="text-end">
                            <details>
                                <summary class="btn btn-sm btn-outline-primary">Editar</summary>
                                <div class="mt-2 text-start" style="min-width:320px;">
                                    <form method="post" action="<?php echo htmlspecialchars(url('/categorias-animales/actualizar')); ?>" class="row g-2 mb-2">
                                        <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
                                        <input type="hidden" name="id" value="<?php echo (int) ($c['id'] ?? 0); ?>">
                                        <div class="col-md-12"><input class="form-control form-control-sm" name="nombre" value="<?php echo htmlspecialchars((string) ($c['nombre'] ?? '')); ?>" required></div>
                                        <div class="col-md-12"><button class="btn btn-sm btn-outline-primary" type="submit">Guardar</button></div>
                                    </form>
                                    <?php if ($canToggleSafe): ?>
                                        <form method="post" action="<?php echo htmlspecialchars(url('/categorias-animales/estado')); ?>">
                                            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
                                            <input type="hidden" name="id" value="<?php echo (int) ($c['id'] ?? 0); ?>">
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
