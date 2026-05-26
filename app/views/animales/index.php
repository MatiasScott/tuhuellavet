<?php
ob_start();
?>
<?php

$pageTitle = 'Animales';
?>
<?php
$rowsSafe = isset($rows) && is_array($rows) ? $rows : [];
$successSafe = isset($success) ? (string) $success : '';
$errorSafe = isset($error) ? (string) $error : '';
?>

<main class="container py-4">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 m-0">Pacientes</h1>
            <p class="text-muted m-0">Historial y control de animales registrados por empresa.</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/dashboard')); ?>">Volver</a>
            <a class="btn btn-brand" href="<?php echo htmlspecialchars(url('/animales/crear')); ?>">Nuevo paciente</a>
        </div>
    </div>

    <?php if ($successSafe !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($successSafe); ?></div><?php endif; ?>
    <?php if ($errorSafe !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($errorSafe); ?></div><?php endif; ?>

    <section class="card tvg-card p-3 tvg-surface-strong">
        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
            <h2 class="h6 m-0">Listado de pacientes</h2>
            <input class="form-control tvg-search" type="search" placeholder="Buscar por nombre, propietario o especie" data-table-search="#tabla-animales">
        </div>
        <div class="table-responsive">
            <table id="tabla-animales" class="table tvg-table table-hover mb-0">
                <thead>
                <tr>
                    <th>Paciente</th>
                    <th>Nombre</th>
                    <th>Propietario</th>
                    <th>Especie/Raza</th>
                    <th>Peso</th>
                    <th>Edad</th>
                    <th class="text-end">Acciones</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rowsSafe as $row): ?>
                    <?php
                    $fotoRaw = trim((string) ($row['foto'] ?? ''));
                    $fotoUrl = $fotoRaw !== '' ? url('/' . ltrim($fotoRaw, '/')) : '';
                    $nombrePaciente = (string) ($row['nombre'] ?? '');
                    ?>
                    <tr>
                        <td>
                            <?php if ($fotoUrl !== ''): ?>
                                <img class="tvg-avatar" src="<?php echo htmlspecialchars($fotoUrl); ?>" alt="Foto paciente">
                            <?php else: ?>
                                <span class="tvg-avatar tvg-avatar-fallback"><?php echo htmlspecialchars(strtoupper(substr($nombrePaciente, 0, 1))); ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="fw-semibold"><?php echo htmlspecialchars($nombrePaciente); ?></div>
                            <small class="text-muted">ID #<?php echo (int) ($row['id'] ?? 0); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars((string) ($row['propietario_nombre'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) (($row['especie'] ?? '') . ' / ' . ($row['raza'] ?? ''))); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row['peso_actual'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row['edad_anios'] ?? '')); ?> anos</td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars(url('/animales/editar?id=' . (int) ($row['id'] ?? 0))); ?>">Editar</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<script src="<?php echo htmlspecialchars(asset('js/animales/index.js')); ?>"></script>


<?php
$pageContent = ob_get_clean();
require BASE_PATH . "/app/views/layaouts/app.php";
?>
