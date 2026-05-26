<?php
ob_start();
?>
<?php

$pageTitle = 'Vacunas';
?>
<?php
$catalogoSafe = isset($catalogo) && is_array($catalogo) ? $catalogo : [];
$animalesSafe = isset($animales) && is_array($animales) ? $animales : [];
$consultasSafe = isset($consultas) && is_array($consultas) ? $consultas : [];
$aplicacionesSafe = isset($aplicaciones) && is_array($aplicaciones) ? $aplicaciones : [];
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$successSafe = isset($success) ? (string) $success : '';
$errorSafe = isset($error) ? (string) $error : '';
?>

<main class="container py-4">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <h1 class="h4 m-0">Vacunas</h1>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/dashboard')); ?>">Volver</a>
            <button class="btn btn-brand" type="button" data-bs-toggle="modal" data-bs-target="#vacunaCatalogoModal" data-mode="create">Nueva vacuna</button>
        </div>
    </div>

    <?php if ($successSafe !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($successSafe); ?></div><?php endif; ?>
    <?php if ($errorSafe !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($errorSafe); ?></div><?php endif; ?>

    <section class="card tvg-card p-3 mb-4">
        <h2 class="h6">Registrar aplicacion</h2>
        <form method="post" action="<?php echo htmlspecialchars(url('/vacunas/aplicar')); ?>" class="row g-2">
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
            <div class="col-md-3">
                <select class="form-select" name="animal_id" required>
                    <option value="">Paciente...</option>
                    <?php foreach ($animalesSafe as $a): ?>
                        <option value="<?php echo (int) ($a['id'] ?? 0); ?>"><?php echo htmlspecialchars((string) ($a['nombre'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="catalogo_vacuna_id" required>
                    <option value="">Vacuna...</option>
                    <?php foreach ($catalogoSafe as $v): ?>
                        <option value="<?php echo (int) ($v['id'] ?? 0); ?>"><?php echo htmlspecialchars((string) ($v['nombre'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="consulta_id">
                    <option value="">Consulta opcional...</option>
                    <?php foreach ($consultasSafe as $c): ?>
                        <option value="<?php echo (int) ($c['id'] ?? 0); ?>">#<?php echo (int) ($c['id'] ?? 0); ?> - <?php echo htmlspecialchars((string) ($c['animal'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3"><input class="form-control" type="date" name="fecha_aplicacion"></div>
            <div class="col-md-3"><input class="form-control" type="date" name="proxima_aplicacion"></div>
            <div class="col-md-3"><input class="form-control" name="dosis" placeholder="Dosis"></div>
            <div class="col-md-3"><input class="form-control" name="laboratorio" placeholder="Laboratorio"></div>
            <div class="col-md-3"><input class="form-control" name="lote" placeholder="Lote"></div>
            <div class="col-md-9"><input class="form-control" name="observaciones" placeholder="Observaciones"></div>
            <div class="col-md-3"><button class="btn btn-brand w-100" type="submit">Registrar</button></div>
        </form>
    </section>

    <section class="card tvg-card p-3 mb-4">
        <h2 class="h6">Catalogo</h2>
        <div class="table-responsive">
            <table class="table tvg-table table-hover mb-0">
                <thead><tr><th>ID</th><th>Nombre</th><th>Descripcion</th><th>Activa</th><th class="text-end">Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($catalogoSafe as $v): ?>
                    <tr>
                        <td><?php echo (int) ($v['id'] ?? 0); ?></td>
                        <td><?php echo htmlspecialchars((string) ($v['nombre'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($v['descripcion'] ?? '')); ?></td>
                        <td><span class="badge tvg-badge <?php echo ((int) ($v['estado'] ?? 0) === 1) ? 'tvg-badge-success' : 'tvg-badge-muted'; ?>"><?php echo ((int) ($v['estado'] ?? 0) === 1) ? 'Activa' : 'Inactiva'; ?></span></td>
                        <td class="text-end">
                            <button
                                class="btn btn-sm btn-outline-primary"
                                type="button"
                                data-bs-toggle="modal"
                                data-bs-target="#vacunaCatalogoModal"
                                data-mode="edit"
                                data-id="<?php echo (int) ($v['id'] ?? 0); ?>"
                                data-nombre="<?php echo htmlspecialchars((string) ($v['nombre'] ?? '')); ?>"
                                data-descripcion="<?php echo htmlspecialchars((string) ($v['descripcion'] ?? '')); ?>"
                                data-estado="<?php echo (int) ($v['estado'] ?? 0); ?>"
                            >Editar</button>
                            <form class="d-inline" method="post" action="<?php echo htmlspecialchars(url('/vacunas/catalogo/eliminar')); ?>" onsubmit="return confirm('Eliminar vacuna del catalogo?');">
                                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
                                <input type="hidden" name="id" value="<?php echo (int) ($v['id'] ?? 0); ?>">
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
        <h2 class="h6">Aplicaciones registradas</h2>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>ID</th><th>Paciente</th><th>Vacuna</th><th>Fecha</th><th>Proxima</th><th>Dosis</th><th>Lote</th><th>Usuario</th></tr></thead>
                <tbody>
                <?php foreach ($aplicacionesSafe as $r): ?>
                    <tr>
                        <td><?php echo (int) ($r['id'] ?? 0); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['animal'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['vacuna'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['fecha_aplicacion'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['proxima_aplicacion'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['dosis'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['lote'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['usuario'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<div class="modal fade" id="vacunaCatalogoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Vacuna de catalogo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form method="post" action="<?php echo htmlspecialchars(url('/vacunas/catalogo')); ?>" id="vacunaCatalogoForm">
                <div class="modal-body row g-2">
                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
                    <input type="hidden" name="id" value="0">
                    <div class="col-md-7"><input class="form-control" name="nombre" placeholder="Nombre vacuna" required></div>
                    <div class="col-md-5 d-flex align-items-center"><label><input type="checkbox" name="estado" value="1" checked> Activa</label></div>
                    <div class="col-12"><textarea class="form-control" name="descripcion" placeholder="Descripcion"></textarea></div>
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
    var modal = document.getElementById('vacunaCatalogoModal');
    var form = document.getElementById('vacunaCatalogoForm');
    if (!modal || !form) {
        return;
    }

    modal.addEventListener('show.bs.modal', function (event) {
        var btn = event.relatedTarget;
        if (!btn) {
            return;
        }

        var mode = btn.getAttribute('data-mode') || 'create';
        form.action = mode === 'edit' ? '<?php echo htmlspecialchars(url('/vacunas/catalogo/actualizar')); ?>' : '<?php echo htmlspecialchars(url('/vacunas/catalogo')); ?>';

        form.querySelector('[name="id"]').value = btn.getAttribute('data-id') || '0';
        form.querySelector('[name="nombre"]').value = btn.getAttribute('data-nombre') || '';
        form.querySelector('[name="descripcion"]').value = btn.getAttribute('data-descripcion') || '';
        form.querySelector('[name="estado"]').checked = (btn.getAttribute('data-estado') || '1') === '1';

        if (mode === 'create') {
            form.querySelector('[name="id"]').value = '0';
            form.querySelector('[name="nombre"]').value = '';
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
