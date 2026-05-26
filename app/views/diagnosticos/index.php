<?php
ob_start();
?>
<?php

$pageTitle = 'Diagnosticos';
?>
<?php
$catalogoSafe = isset($catalogo) && is_array($catalogo) ? $catalogo : [];
$consultasSafe = isset($consultas) && is_array($consultas) ? $consultas : [];
$asignacionesSafe = isset($asignaciones) && is_array($asignaciones) ? $asignaciones : [];
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$successSafe = isset($success) ? (string) $success : '';
$errorSafe = isset($error) ? (string) $error : '';
?>

<main class="container py-4">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <h1 class="h4 m-0">Diagnosticos</h1>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/dashboard')); ?>">Volver</a>
            <button class="btn btn-brand" type="button" data-bs-toggle="modal" data-bs-target="#diagnosticoModal" data-mode="create">Nuevo diagnostico</button>
        </div>
    </div>

    <?php if ($successSafe !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($successSafe); ?></div><?php endif; ?>
    <?php if ($errorSafe !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($errorSafe); ?></div><?php endif; ?>

    <section class="card tvg-card p-3 mb-4">
        <h2 class="h6">Aplicar diagnostico a consulta</h2>
        <form method="post" action="<?php echo htmlspecialchars(url('/diagnosticos/asignar')); ?>" class="row g-2">
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
            <div class="col-md-6">
                <select class="form-select" name="consulta_id" required>
                    <option value="">Consulta...</option>
                    <?php foreach ($consultasSafe as $c): ?>
                        <option value="<?php echo (int) ($c['id'] ?? 0); ?>">#<?php echo (int) ($c['id'] ?? 0); ?> - <?php echo htmlspecialchars((string) ($c['animal'] ?? '')); ?> - <?php echo htmlspecialchars((string) ($c['fecha_consulta'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <select class="form-select" name="diagnostico_id" required>
                    <option value="">Diagnostico...</option>
                    <?php foreach ($catalogoSafe as $d): ?>
                        <option value="<?php echo (int) ($d['id'] ?? 0); ?>"><?php echo htmlspecialchars((string) ($d['nombre'] ?? '')); ?> (<?php echo htmlspecialchars((string) ($d['tipo'] ?? '')); ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><button class="btn btn-brand w-100" type="submit">Aplicar</button></div>
            <div class="col-md-12"><input class="form-control" name="observacion" placeholder="Observacion de aplicacion"></div>
        </form>
    </section>

    <section class="card tvg-card p-3 mb-4">
        <h2 class="h6">Catalogo actual</h2>
        <div class="table-responsive">
            <table class="table tvg-table table-hover mb-0">
                <thead><tr><th>ID</th><th>Codigo</th><th>Nombre</th><th>Tipo</th><th>Activo</th><th class="text-end">Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($catalogoSafe as $d): ?>
                    <tr>
                        <td><?php echo (int) ($d['id'] ?? 0); ?></td>
                        <td><?php echo htmlspecialchars((string) ($d['codigo'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($d['nombre'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($d['tipo'] ?? '')); ?></td>
                        <td><span class="badge tvg-badge <?php echo ((int) ($d['estado'] ?? 0) === 1) ? 'tvg-badge-success' : 'tvg-badge-muted'; ?>"><?php echo ((int) ($d['estado'] ?? 0) === 1) ? 'Activo' : 'Inactivo'; ?></span></td>
                        <td class="text-end">
                            <button
                                class="btn btn-sm btn-outline-primary"
                                type="button"
                                data-bs-toggle="modal"
                                data-bs-target="#diagnosticoModal"
                                data-mode="edit"
                                data-id="<?php echo (int) ($d['id'] ?? 0); ?>"
                                data-codigo="<?php echo htmlspecialchars((string) ($d['codigo'] ?? '')); ?>"
                                data-nombre="<?php echo htmlspecialchars((string) ($d['nombre'] ?? '')); ?>"
                                data-tipo="<?php echo htmlspecialchars((string) ($d['tipo'] ?? '')); ?>"
                                data-descripcion="<?php echo htmlspecialchars((string) ($d['descripcion'] ?? '')); ?>"
                                data-estado="<?php echo (int) ($d['estado'] ?? 0); ?>"
                            >Editar</button>
                            <form class="d-inline" method="post" action="<?php echo htmlspecialchars(url('/diagnosticos/catalogo/eliminar')); ?>" onsubmit="return confirm('Eliminar diagnostico de catalogo?');">
                                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
                                <input type="hidden" name="id" value="<?php echo (int) ($d['id'] ?? 0); ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Eliminar</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card tvg-card p-3">
        <h2 class="h6">Diagnosticos aplicados</h2>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>ID</th><th>Consulta</th><th>Paciente</th><th>Diagnostico</th><th>Tipo</th><th>Observacion</th><th>Usuario</th></tr></thead>
                <tbody>
                <?php foreach ($asignacionesSafe as $a): ?>
                    <tr>
                        <td><?php echo (int) ($a['id'] ?? 0); ?></td>
                        <td>#<?php echo (int) ($a['consulta_id'] ?? 0); ?></td>
                        <td><?php echo htmlspecialchars((string) ($a['animal'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($a['diagnostico'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($a['tipo'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($a['observacion'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($a['usuario'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<div class="modal fade" id="diagnosticoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Diagnostico</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form method="post" action="<?php echo htmlspecialchars(url('/diagnosticos/catalogo')); ?>" id="diagnosticoModalForm">
                <div class="modal-body row g-2">
                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
                    <input type="hidden" name="id" value="0">
                    <div class="col-md-3"><input class="form-control" name="codigo" placeholder="Codigo"></div>
                    <div class="col-md-5"><input class="form-control" name="nombre" placeholder="Nombre" required></div>
                    <div class="col-md-4">
                        <select class="form-select" name="tipo" required>
                            <option value="diferencial">Diferencial</option>
                            <option value="preventivo">Preventivo</option>
                            <option value="definitivo">Definitivo</option>
                        </select>
                    </div>
                    <div class="col-md-9"><input class="form-control" name="descripcion" placeholder="Descripcion"></div>
                    <div class="col-md-3 d-flex align-items-center"><label><input type="checkbox" name="estado" value="1" checked> Activo</label></div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-brand" type="submit">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function () {
    var modal = document.getElementById('diagnosticoModal');
    var form = document.getElementById('diagnosticoModalForm');
    if (!modal || !form) {
        return;
    }

    modal.addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        if (!btn) {
            return;
        }

        var mode = btn.getAttribute('data-mode') || 'create';
        form.action = mode === 'edit' ? '<?php echo htmlspecialchars(url('/diagnosticos/catalogo/actualizar')); ?>' : '<?php echo htmlspecialchars(url('/diagnosticos/catalogo')); ?>';
        form.querySelector('[name="id"]').value = btn.getAttribute('data-id') || '0';
        form.querySelector('[name="codigo"]').value = btn.getAttribute('data-codigo') || '';
        form.querySelector('[name="nombre"]').value = btn.getAttribute('data-nombre') || '';
        form.querySelector('[name="tipo"]').value = btn.getAttribute('data-tipo') || 'diferencial';
        form.querySelector('[name="descripcion"]').value = btn.getAttribute('data-descripcion') || '';
        form.querySelector('[name="estado"]').checked = (btn.getAttribute('data-estado') || '1') === '1';

        if (mode === 'create') {
            form.querySelector('[name="id"]').value = '0';
            form.querySelector('[name="codigo"]').value = '';
            form.querySelector('[name="nombre"]').value = '';
            form.querySelector('[name="tipo"]').value = 'diferencial';
            form.querySelector('[name="descripcion"]').value = '';
            form.querySelector('[name="estado"]').checked = true;
        }
    });
})();
</script>


<?php
$pageContent = ob_get_clean();
require BASE_PATH . "/app/views/layaouts/app.php";
?>
