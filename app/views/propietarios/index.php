<?php

$pageTitle = 'Propietarios';
require BASE_PATH . '/app/views/layaouts/header.php';
?>
<?php
$rowsSafe = isset($rows) && is_array($rows) ? $rows : [];
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$successSafe = isset($success) ? (string) $success : '';
$errorSafe = isset($error) ? (string) $error : '';
?>

<?php require BASE_PATH . '/app/views/layaouts/sidebar.php'; ?>
<section class="col-12 col-lg-9 col-xl-10">
<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 m-0">Propietarios / Clientes</h1>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/dashboard')); ?>">Volver</a>
    </div>

    <?php if ($successSafe !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($successSafe); ?></div><?php endif; ?>
    <?php if ($errorSafe !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($errorSafe); ?></div><?php endif; ?>

    <section class="card tvg-card p-3 mb-4">
        <h2 class="h6">Nuevo propietario</h2>
        <form method="post" action="<?php echo htmlspecialchars(url('/propietarios')); ?>" enctype="multipart/form-data" class="row g-2">
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
            <div class="col-md-3"><input class="form-control" name="nombres" placeholder="Nombres" required></div>
            <div class="col-md-3"><input class="form-control" name="apellidos" placeholder="Apellidos"></div>
            <div class="col-md-2"><input class="form-control" name="identificacion" placeholder="Identificacion"></div>
            <div class="col-md-2"><input class="form-control" name="celular" placeholder="Celular"></div>
            <div class="col-md-2"><input class="form-control" type="email" name="email" placeholder="Correo"></div>
            <div class="col-md-4"><input class="form-control" name="direccion" placeholder="Direccion"></div>
            <div class="col-md-3"><input class="form-control" name="telefono" placeholder="Telefono"></div>
            <div class="col-md-3"><input class="form-control" type="file" name="foto" accept=".jpg,.jpeg,.png,.webp"></div>
            <div class="col-md-2 d-flex align-items-center"><label class="form-check-label"><input class="form-check-input me-1" type="checkbox" name="portal_cliente_activo" value="1">Portal activo</label></div>
            <div class="col-md-2"><button class="btn btn-brand w-100" type="submit">Guardar</button></div>
        </form>
    </section>

    <section class="card tvg-card p-3">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Contacto</th>
                    <th>Portal</th>
                    <th>Animales</th>
                    <th>Foto</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rowsSafe as $row): ?>
                    <tr>
                        <td><?php echo (int) ($row['id'] ?? 0); ?></td>
                        <td><?php echo htmlspecialchars(trim((string) ($row['nombres'] ?? '') . ' ' . (string) ($row['apellidos'] ?? ''))); ?></td>
                        <td>
                            <div><?php echo htmlspecialchars((string) ($row['celular'] ?? '')); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars((string) ($row['email'] ?? '')); ?></small>
                        </td>
                        <td><?php echo ((int) ($row['portal_cliente_activo'] ?? 0) === 1) ? 'Si' : 'No'; ?></td>
                        <td><?php echo (int) ($row['total_animales'] ?? 0); ?></td>
                        <td><small class="text-muted"><?php echo htmlspecialchars((string) ($row['foto'] ?? '')); ?></small></td>
                        <td><a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars(url('/propietarios/editar?id=' . (int) ($row['id'] ?? 0))); ?>">Editar</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

</section>
<?php require BASE_PATH . '/app/views/layaouts/footer.php'; ?>
