<?php
ob_start();
?>
<?php

$pageTitle = 'Consultas externas';
?>
<?php
$rowsSafe = isset($rows) && is_array($rows) ? $rows : [];
$successSafe = isset($success) ? (string) $success : '';
$errorSafe = isset($error) ? (string) $error : '';
?>

<main class="container py-4">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 m-0">Consultas externas</h1>
            <p class="text-muted m-0">Seguimiento clinico cronologico por paciente y veterinario.</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/dashboard')); ?>">Volver</a>
            <a class="btn btn-brand" href="<?php echo htmlspecialchars(url('/consultas/crear')); ?>">Nueva consulta</a>
        </div>
    </div>

    <?php if ($successSafe !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($successSafe); ?></div><?php endif; ?>
    <?php if ($errorSafe !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($errorSafe); ?></div><?php endif; ?>

    <section class="card tvg-card p-3 tvg-surface-strong">
        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
            <h2 class="h6 m-0">Historial de consultas</h2>
            <input class="form-control tvg-search" type="search" placeholder="Buscar por paciente, veterinario o estado" data-table-search="#tabla-consultas">
        </div>
        <div class="table-responsive">
            <table id="tabla-consultas" class="table tvg-table table-hover mb-0">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Fecha</th>
                    <th>Paciente</th>
                    <th>Veterinario</th>
                    <th>Motivo</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rowsSafe as $row): ?>
                    <tr>
                        <td><?php echo (int) ($row['id'] ?? 0); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row['fecha_consulta'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row['animal'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row['veterinario'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row['motivo_consulta'] ?? '')); ?></td>
                        <td>
                            <span class="badge tvg-badge tvg-badge-muted"><?php echo htmlspecialchars((string) ($row['estado'] ?? '')); ?></span>
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars(url('/consultas/editar?id=' . (int) ($row['id'] ?? 0))); ?>">Editar</a>
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
