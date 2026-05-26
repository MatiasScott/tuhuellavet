<?php

$pageTitle = 'Vacunas';
require BASE_PATH . '/app/views/layaouts/header.php';
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

<?php require BASE_PATH . '/app/views/layaouts/sidebar.php'; ?>
<section class="col-12 col-lg-9 col-xl-10">
<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 m-0">Vacunas</h1>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/dashboard')); ?>">Volver</a>
    </div>

    <?php if ($successSafe !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($successSafe); ?></div><?php endif; ?>
    <?php if ($errorSafe !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($errorSafe); ?></div><?php endif; ?>

    <section class="card tvg-card p-3 mb-4">
        <h2 class="h6">Nueva vacuna en catalogo</h2>
        <form method="post" action="<?php echo htmlspecialchars(url('/vacunas/catalogo')); ?>" class="row g-2">
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
            <div class="col-md-5"><input class="form-control" name="nombre" placeholder="Nombre vacuna" required></div>
            <div class="col-md-5"><input class="form-control" name="descripcion" placeholder="Descripcion"></div>
            <div class="col-md-1 d-flex align-items-center"><label><input type="checkbox" name="estado" value="1" checked> Activa</label></div>
            <div class="col-md-1"><button class="btn btn-brand w-100" type="submit">Guardar</button></div>
        </form>
    </section>

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
            <table class="table table-hover mb-0">
                <thead><tr><th>ID</th><th>Nombre</th><th>Descripcion</th><th>Activa</th></tr></thead>
                <tbody>
                <?php foreach ($catalogoSafe as $v): ?>
                    <tr>
                        <td><?php echo (int) ($v['id'] ?? 0); ?></td>
                        <td><?php echo htmlspecialchars((string) ($v['nombre'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($v['descripcion'] ?? '')); ?></td>
                        <td><?php echo ((int) ($v['estado'] ?? 0) === 1) ? 'Si' : 'No'; ?></td>
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

</section>
<?php require BASE_PATH . '/app/views/layaouts/footer.php'; ?>
