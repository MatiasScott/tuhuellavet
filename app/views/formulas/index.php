<?php
ob_start();
?>
<?php

$pageTitle = 'Formulas medicas';
?>
<?php
$rowsSafe = isset($rows) && is_array($rows) ? $rows : [];
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$successSafe = isset($success) ? (string) $success : '';
$errorSafe = isset($error) ? (string) $error : '';
?>

<main class="container py-4">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 m-0">Formulas medicas</h1>
            <p class="text-muted m-0">Calcula dosis y protocolos con expresiones reutilizables y variables detectadas automaticamente.</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/dashboard')); ?>">Volver</a>
            <a class="btn btn-brand" href="<?php echo htmlspecialchars(url('/formulas/crear')); ?>">Nueva formula</a>
        </div>
    </div>

    <?php if ($successSafe !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($successSafe); ?></div><?php endif; ?>
    <?php if ($errorSafe !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($errorSafe); ?></div><?php endif; ?>

    <section class="card tvg-card p-3 tvg-surface-strong">
        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
            <h2 class="h6 m-0">Catalogo de formulas</h2>
            <input class="form-control tvg-search" type="search" placeholder="Buscar por nombre, categoria o expresion" data-table-search="#tabla-formulas">
        </div>
        <div class="table-responsive">
            <table id="tabla-formulas" class="table tvg-table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Categoria</th>
                    <th>Expresion</th>
                    <th>Variables</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rowsSafe as $row): ?>
                    <tr>
                        <td>#<?php echo (int) ($row['id'] ?? 0); ?></td>
                        <td>
                            <div class="fw-semibold"><?php echo htmlspecialchars((string) ($row['nombre'] ?? '')); ?></div>
                            <small class="text-muted"><?php echo htmlspecialchars((string) ($row['descripcion'] ?? '')); ?></small>
                        </td>
                        <td><span class="badge tvg-badge tvg-badge-muted"><?php echo htmlspecialchars((string) ($row['categoria'] ?? 'general')); ?></span></td>
                        <td><code><?php echo htmlspecialchars((string) ($row['expresion_formula'] ?? '')); ?></code></td>
                        <td><?php echo (int) ($row['total_variables'] ?? 0); ?></td>
                        <td>
                            <span class="badge tvg-badge <?php echo ((int) ($row['estado'] ?? 0) === 1) ? 'tvg-badge-success' : 'tvg-badge-muted'; ?>">
                                <?php echo ((int) ($row['estado'] ?? 0) === 1) ? 'Activa' : 'Inactiva'; ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars(url('/formulas/editar?id=' . (int) ($row['id'] ?? 0))); ?>">Editar</a>
                            <button class="btn btn-sm btn-outline-success" type="button" data-test-formula data-formula-id="<?php echo (int) ($row['id'] ?? 0); ?>" data-formula-nombre="<?php echo htmlspecialchars((string) ($row['nombre'] ?? '')); ?>">Probar</button>
                            <form class="d-inline" method="post" action="<?php echo htmlspecialchars(url('/formulas/toggle')); ?>">
                                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
                                <input type="hidden" name="id" value="<?php echo (int) ($row['id'] ?? 0); ?>">
                                <input type="hidden" name="estado" value="<?php echo ((int) ($row['estado'] ?? 0) === 1) ? 0 : 1; ?>">
                                <button class="btn btn-sm btn-outline-warning" type="submit"><?php echo ((int) ($row['estado'] ?? 0) === 1) ? 'Desactivar' : 'Activar'; ?></button>
                            </form>
                            <form class="d-inline" method="post" action="<?php echo htmlspecialchars(url('/formulas/eliminar')); ?>" onsubmit="return confirm('Eliminar formula?');">
                                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
                                <input type="hidden" name="id" value="<?php echo (int) ($row['id'] ?? 0); ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<div class="modal fade" id="formulaTestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Probar formula</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form id="formulaTestForm">
                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
                    <input type="hidden" name="id" value="">
                    <div id="formulaTestFields" class="row g-2"></div>
                </form>
                <div class="alert alert-info mt-3 mb-0 d-none" id="formulaTestResult"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-brand" id="formulaTestSubmit">Calcular</button>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo htmlspecialchars(asset('js/formulas.js')); ?>"></script>

<?php
$pageContent = ob_get_clean();
require BASE_PATH . '/app/views/layaouts/app.php';
