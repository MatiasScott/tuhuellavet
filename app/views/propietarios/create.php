<?php
ob_start();
?>
<?php

$pageTitle = 'Nuevo propietario';
?>
<?php
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$errorSafe = isset($error) ? (string) $error : '';
?>

<main class="container py-4">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 m-0">Nuevo propietario</h1>
            <p class="text-muted m-0">Registra informacion principal y datos de contacto del cliente.</p>
        </div>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/propietarios')); ?>">Ver listado</a>
    </div>

    <?php if ($errorSafe !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($errorSafe); ?></div><?php endif; ?>

    <section class="card tvg-card tvg-surface-strong p-4">
        <form method="post" action="<?php echo htmlspecialchars(url('/propietarios')); ?>" enctype="multipart/form-data" class="row g-3">
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">

            <div class="col-12">
                <h2 class="h6 m-0">Datos personales</h2>
            </div>
            <div class="col-md-6">
                <label class="form-label">Nombres</label>
                <input class="form-control" name="nombres" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Apellidos</label>
                <input class="form-control" name="apellidos">
            </div>
            <div class="col-md-4">
                <label class="form-label">Identificacion</label>
                <input class="form-control" name="identificacion">
            </div>
            <div class="col-md-4">
                <label class="form-label">Celular</label>
                <input class="form-control" name="celular">
            </div>
            <div class="col-md-4">
                <label class="form-label">Telefono</label>
                <input class="form-control" name="telefono">
            </div>

            <div class="col-12 mt-2">
                <h2 class="h6 m-0">Contacto y portal</h2>
            </div>
            <div class="col-md-6">
                <label class="form-label">Correo</label>
                <input class="form-control" type="email" name="email">
            </div>
            <div class="col-md-6">
                <label class="form-label">Direccion</label>
                <input class="form-control" name="direccion">
            </div>
            <div class="col-md-6">
                <label class="form-label">Foto</label>
                <input class="form-control" type="file" name="foto" accept=".jpg,.jpeg,.png,.webp">
            </div>
            <div class="col-md-6 d-flex align-items-end">
                <label class="form-check-label">
                    <input class="form-check-input me-1" type="checkbox" name="portal_cliente_activo" value="1">
                    Activar acceso portal cliente
                </label>
            </div>

            <div class="col-12 d-flex justify-content-end gap-2 mt-3">
                <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/propietarios')); ?>">Cancelar</a>
                <button class="btn btn-brand" type="submit">Guardar propietario</button>
            </div>
        </form>
    </section>
</main>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../layaouts/app.php';
