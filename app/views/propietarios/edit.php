<?php

$pageTitle = 'Editar propietario';
require BASE_PATH . '/app/views/layaouts/header.php';
?>
<?php
$rowSafe = isset($row) && is_array($row) ? $row : [];
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$errorSafe = isset($error) ? (string) $error : '';
?>

<?php require BASE_PATH . '/app/views/layaouts/sidebar.php'; ?>
<section class="col-12 col-lg-9 col-xl-10">
<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 m-0">Editar propietario #<?php echo (int) ($rowSafe['id'] ?? 0); ?></h1>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/propietarios')); ?>">Volver</a>
    </div>

    <?php if ($errorSafe !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($errorSafe); ?></div><?php endif; ?>

    <section class="card tvg-card p-3">
        <form method="post" action="<?php echo htmlspecialchars(url('/propietarios/actualizar')); ?>" enctype="multipart/form-data" class="row g-2">
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
            <input type="hidden" name="id" value="<?php echo (int) ($rowSafe['id'] ?? 0); ?>">
            <div class="col-md-3"><input class="form-control" name="nombres" value="<?php echo htmlspecialchars((string) ($rowSafe['nombres'] ?? '')); ?>" required></div>
            <div class="col-md-3"><input class="form-control" name="apellidos" value="<?php echo htmlspecialchars((string) ($rowSafe['apellidos'] ?? '')); ?>"></div>
            <div class="col-md-2"><input class="form-control" name="identificacion" value="<?php echo htmlspecialchars((string) ($rowSafe['identificacion'] ?? '')); ?>"></div>
            <div class="col-md-2"><input class="form-control" name="celular" value="<?php echo htmlspecialchars((string) ($rowSafe['celular'] ?? '')); ?>"></div>
            <div class="col-md-2"><input class="form-control" type="email" name="email" value="<?php echo htmlspecialchars((string) ($rowSafe['email'] ?? '')); ?>"></div>
            <div class="col-md-4"><input class="form-control" name="direccion" value="<?php echo htmlspecialchars((string) ($rowSafe['direccion'] ?? '')); ?>"></div>
            <div class="col-md-3"><input class="form-control" name="telefono" value="<?php echo htmlspecialchars((string) ($rowSafe['telefono'] ?? '')); ?>"></div>
            <div class="col-md-3"><input class="form-control" type="file" name="foto" accept=".jpg,.jpeg,.png,.webp"></div>
            <div class="col-md-2 d-flex align-items-center"><label class="form-check-label"><input class="form-check-input me-1" type="checkbox" name="portal_cliente_activo" value="1" <?php echo ((int) ($rowSafe['portal_cliente_activo'] ?? 0) === 1) ? 'checked' : ''; ?>>Portal activo</label></div>
            <div class="col-md-2"><button class="btn btn-brand w-100" type="submit">Actualizar</button></div>
            <div class="col-12"><small class="text-muted">Foto actual: <?php echo htmlspecialchars((string) ($rowSafe['foto'] ?? '')); ?></small></div>
        </form>
    </section>
</main>

</section>
<?php require BASE_PATH . '/app/views/layaouts/footer.php'; ?>
