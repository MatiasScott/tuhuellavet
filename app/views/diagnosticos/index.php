<?php

$pageTitle = 'Diagnosticos';
require BASE_PATH . '/app/views/layaouts/header.php';
?>
<?php
$catalogoSafe = isset($catalogo) && is_array($catalogo) ? $catalogo : [];
$consultasSafe = isset($consultas) && is_array($consultas) ? $consultas : [];
$asignacionesSafe = isset($asignaciones) && is_array($asignaciones) ? $asignaciones : [];
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$successSafe = isset($success) ? (string) $success : '';
$errorSafe = isset($error) ? (string) $error : '';
?>

<?php require BASE_PATH . '/app/views/layaouts/sidebar.php'; ?>
<section class="col-12 col-lg-9 col-xl-10">
<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 m-0">Diagnosticos</h1>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/dashboard')); ?>">Volver</a>
    </div>

    <?php if ($successSafe !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($successSafe); ?></div><?php endif; ?>
    <?php if ($errorSafe !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($errorSafe); ?></div><?php endif; ?>

    <section class="card tvg-card p-3 mb-4">
        <h2 class="h6">Nuevo diagnostico en catalogo</h2>
        <form method="post" action="<?php echo htmlspecialchars(url('/diagnosticos/catalogo')); ?>" class="row g-2">
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
            <div class="col-md-2"><input class="form-control" name="codigo" placeholder="Codigo"></div>
            <div class="col-md-4"><input class="form-control" name="nombre" placeholder="Nombre" required></div>
            <div class="col-md-3">
                <select class="form-select" name="tipo" required>
                    <option value="diferencial">Diferencial</option>
                    <option value="preventivo">Preventivo</option>
                    <option value="definitivo">Definitivo</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-center"><label><input type="checkbox" name="estado" value="1" checked> Activo</label></div>
            <div class="col-md-10"><input class="form-control" name="descripcion" placeholder="Descripcion"></div>
            <div class="col-md-2"><button class="btn btn-brand w-100" type="submit">Guardar</button></div>
        </form>
    </section>

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
            <table class="table table-hover mb-0">
                <thead><tr><th>ID</th><th>Codigo</th><th>Nombre</th><th>Tipo</th><th>Activo</th></tr></thead>
                <tbody>
                <?php foreach ($catalogoSafe as $d): ?>
                    <tr>
                        <td><?php echo (int) ($d['id'] ?? 0); ?></td>
                        <td><?php echo htmlspecialchars((string) ($d['codigo'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($d['nombre'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($d['tipo'] ?? '')); ?></td>
                        <td><?php echo ((int) ($d['estado'] ?? 0) === 1) ? 'Si' : 'No'; ?></td>
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

</section>
<?php require BASE_PATH . '/app/views/layaouts/footer.php'; ?>
