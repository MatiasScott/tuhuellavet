<?php
ob_start();
?>
<?php

$pageTitle = 'Propietarios';
?>
<?php
$rowsSafe = isset($rows) && is_array($rows) ? $rows : [];
$successSafe = isset($success) ? (string) $success : '';
$errorSafe = isset($error) ? (string) $error : '';
?>

<main class="container py-4">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 m-0">Propietarios</h1>
            <p class="text-muted m-0">Gestion de clientes con vista limpia y acciones rapidas.</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/dashboard')); ?>">Volver</a>
            <a class="btn btn-brand" href="<?php echo htmlspecialchars(url('/propietarios/crear')); ?>">Nuevo propietario</a>
        </div>
    </div>

    <?php if ($successSafe !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($successSafe); ?></div><?php endif; ?>
    <?php if ($errorSafe !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($errorSafe); ?></div><?php endif; ?>

    <section class="card tvg-card p-3 tvg-surface-strong">
        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
            <h2 class="h6 m-0">Listado de propietarios</h2>
            <input class="form-control tvg-search" type="search" placeholder="Buscar por nombre, celular o correo" data-table-search="#tabla-propietarios">
        </div>
        <div class="table-responsive">
            <table id="tabla-propietarios" class="table tvg-table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Nombre</th>
                    <th>Contacto</th>
                    <th>Estado</th>
                    <th>Animales</th>
                    <th class="text-end">Acciones</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rowsSafe as $row): ?>
                    <?php
                    $fotoRaw = trim((string) ($row['foto'] ?? ''));
                    $fotoUrl = $fotoRaw !== '' ? url('/' . ltrim($fotoRaw, '/')) : '';
                    $nombreCompleto = trim((string) ($row['nombres'] ?? '') . ' ' . (string) ($row['apellidos'] ?? ''));
                    ?>
                    <tr>
                        <td>
                            <?php if ($fotoUrl !== ''): ?>
                                <img class="tvg-avatar" src="<?php echo htmlspecialchars($fotoUrl); ?>" alt="Foto propietario">
                            <?php else: ?>
                                <span class="tvg-avatar tvg-avatar-fallback"><?php echo htmlspecialchars(strtoupper(substr($nombreCompleto, 0, 1))); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-semibold"><?php echo htmlspecialchars($nombreCompleto); ?></div>
                            <small class="text-muted">ID #<?php echo (int) ($row['id'] ?? 0); ?></small>
                        </td>
                        <td>
                            <div><?php echo htmlspecialchars((string) ($row['celular'] ?? '')); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars((string) ($row['email'] ?? '')); ?></small>
                        </td>
                        <td>
                            <span class="badge tvg-badge <?php echo ((int) ($row['portal_cliente_activo'] ?? 0) === 1) ? 'tvg-badge-success' : 'tvg-badge-muted'; ?>">
                                <?php echo ((int) ($row['portal_cliente_activo'] ?? 0) === 1) ? 'Portal activo' : 'Portal inactivo'; ?>
                            </span>
                        </td>
                        <td><?php echo (int) ($row['total_animales'] ?? 0); ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars(url('/propietarios/editar?id=' . (int) ($row['id'] ?? 0))); ?>">Editar</a>
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
