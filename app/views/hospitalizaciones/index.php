<?php

$pageTitle = 'Hospitalizacion y Fluidoterapia';
require BASE_PATH . '/app/views/layaouts/header.php';
?>
<?php
$animalesSafe = isset($animales) && is_array($animales) ? $animales : [];
$consultasSafe = isset($consultas) && is_array($consultas) ? $consultas : [];
$tamanosSafe = isset($tamanos) && is_array($tamanos) ? $tamanos : [];
$hospitalizacionesSafe = isset($hospitalizaciones) && is_array($hospitalizaciones) ? $hospitalizaciones : [];
$fluidoterapiaSafe = isset($fluidoterapia) && is_array($fluidoterapia) ? $fluidoterapia : [];
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$successSafe = isset($success) ? (string) $success : '';
$errorSafe = isset($error) ? (string) $error : '';
?>

<?php require BASE_PATH . '/app/views/layaouts/sidebar.php'; ?>
<section class="col-12 col-lg-9 col-xl-10">
<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 m-0">Hospitalizacion y Fluidoterapia</h1>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/dashboard')); ?>">Volver</a>
    </div>

    <?php if ($successSafe !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($successSafe); ?></div><?php endif; ?>
    <?php if ($errorSafe !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($errorSafe); ?></div><?php endif; ?>

    <section class="card tvg-card p-3 mb-4">
        <h2 class="h6">Tamanos de animal</h2>
        <form method="post" action="<?php echo htmlspecialchars(url('/hospitalizaciones/tamanos')); ?>" class="row g-2 mb-3">
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
            <div class="col-md-4"><input class="form-control" name="nombre" placeholder="Nombre" required></div>
            <div class="col-md-2"><input class="form-control" type="number" step="0.01" name="peso_min" placeholder="Peso min"></div>
            <div class="col-md-2"><input class="form-control" type="number" step="0.01" name="peso_max" placeholder="Peso max"></div>
            <div class="col-md-2 d-flex align-items-center"><label><input type="checkbox" name="estado" value="1" checked> Activo</label></div>
            <div class="col-md-2"><button class="btn btn-brand w-100" type="submit">Guardar</button></div>
        </form>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>ID</th><th>Nombre</th><th>Peso min</th><th>Peso max</th><th>Activo</th></tr></thead>
                <tbody>
                <?php foreach ($tamanosSafe as $t): ?>
                    <tr>
                        <td><?php echo (int) ($t['id'] ?? 0); ?></td>
                        <td><?php echo htmlspecialchars((string) ($t['nombre'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($t['peso_min'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($t['peso_max'] ?? '')); ?></td>
                        <td><?php echo ((int) ($t['estado'] ?? 0) === 1) ? 'Si' : 'No'; ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card tvg-card p-3 mb-4">
        <h2 class="h6">Nueva hospitalizacion</h2>
        <form method="post" action="<?php echo htmlspecialchars(url('/hospitalizaciones')); ?>" class="row g-2">
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
            <div class="col-md-4">
                <select class="form-select" name="animal_id" required>
                    <option value="">Paciente...</option>
                    <?php foreach ($animalesSafe as $a): ?>
                        <option value="<?php echo (int) ($a['id'] ?? 0); ?>"><?php echo htmlspecialchars((string) ($a['nombre'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <select class="form-select" name="consulta_id">
                    <option value="">Consulta opcional...</option>
                    <?php foreach ($consultasSafe as $c): ?>
                        <option value="<?php echo (int) ($c['id'] ?? 0); ?>">#<?php echo (int) ($c['id'] ?? 0); ?> - <?php echo htmlspecialchars((string) ($c['animal'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4"><input class="form-control" type="datetime-local" name="fecha_ingreso"></div>
            <div class="col-md-3">
                <select class="form-select" name="estado">
                    <option value="activa">Activa</option>
                    <option value="alta">Alta</option>
                    <option value="traslado">Traslado</option>
                    <option value="cancelada">Cancelada</option>
                </select>
            </div>
            <div class="col-md-4"><input class="form-control" name="motivo" placeholder="Motivo"></div>
            <div class="col-md-5"><input class="form-control" name="observaciones" placeholder="Observaciones"></div>
            <div class="col-md-2"><button class="btn btn-brand w-100" type="submit">Registrar</button></div>
        </form>
    </section>

    <section class="card tvg-card p-3 mb-4">
        <h2 class="h6">Hospitalizaciones</h2>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>ID</th><th>Paciente</th><th>Ingreso</th><th>Salida</th><th>Estado</th><th>Motivo</th><th>Usuario</th></tr></thead>
                <tbody>
                <?php foreach ($hospitalizacionesSafe as $h): ?>
                    <tr>
                        <td><?php echo (int) ($h['id'] ?? 0); ?></td>
                        <td><?php echo htmlspecialchars((string) ($h['animal'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($h['fecha_ingreso'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($h['fecha_salida'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($h['estado'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($h['motivo'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($h['usuario'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h3 class="h6 mt-4">Actualizar estado</h3>
        <form method="post" action="<?php echo htmlspecialchars(url('/hospitalizaciones/estado')); ?>" class="row g-2">
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
            <div class="col-md-3">
                <select class="form-select" name="hospitalizacion_id" required>
                    <option value="">Hospitalizacion...</option>
                    <?php foreach ($hospitalizacionesSafe as $h): ?>
                        <option value="<?php echo (int) ($h['id'] ?? 0); ?>">#<?php echo (int) ($h['id'] ?? 0); ?> - <?php echo htmlspecialchars((string) ($h['animal'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="estado" required>
                    <option value="activa">Activa</option>
                    <option value="alta">Alta</option>
                    <option value="traslado">Traslado</option>
                    <option value="cancelada">Cancelada</option>
                </select>
            </div>
            <div class="col-md-3"><input class="form-control" type="datetime-local" name="fecha_salida" placeholder="Fecha salida"></div>
            <div class="col-md-3"><button class="btn btn-brand w-100" type="submit">Actualizar</button></div>
        </form>
    </section>

    <section class="card tvg-card p-3 mb-4">
        <h2 class="h6">Registrar fluidoterapia</h2>
        <form method="post" action="<?php echo htmlspecialchars(url('/hospitalizaciones/fluidoterapia')); ?>" class="row g-2">
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
            <div class="col-md-3">
                <select class="form-select" name="hospitalizacion_id" required>
                    <option value="">Hospitalizacion...</option>
                    <?php foreach ($hospitalizacionesSafe as $h): ?>
                        <option value="<?php echo (int) ($h['id'] ?? 0); ?>">#<?php echo (int) ($h['id'] ?? 0); ?> - <?php echo htmlspecialchars((string) ($h['animal'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="tamano_animal_id">
                    <option value="">Tamano...</option>
                    <?php foreach ($tamanosSafe as $t): ?>
                        <option value="<?php echo (int) ($t['id'] ?? 0); ?>"><?php echo htmlspecialchars((string) ($t['nombre'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><input class="form-control" type="number" step="0.01" name="mantenimiento" placeholder="Mantenimiento"></div>
            <div class="col-md-2"><input class="form-control" type="number" step="0.01" name="rehidratacion" placeholder="Rehidratacion"></div>
            <div class="col-md-2"><input class="form-control" name="formula" placeholder="Formula"></div>
            <div class="col-md-4"><input class="form-control" name="formulas_medicas" placeholder="Formula medica/resultante"></div>
            <div class="col-md-4"><input class="form-control" name="signos_clinicos" placeholder="Signos clinicos"></div>
            <div class="col-md-4"><input class="form-control" name="observaciones_fluidoterapia" placeholder="Observaciones"></div>
            <div class="col-md-2"><button class="btn btn-brand w-100" type="submit">Guardar</button></div>
        </form>
    </section>

    <section class="card tvg-card p-3">
        <h2 class="h6">Historial fluidoterapia</h2>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>ID</th><th>Hospitalizacion</th><th>Paciente</th><th>Tamano</th><th>Mantenimiento</th><th>Rehidratacion</th><th>Formula</th><th>Resultado</th></tr></thead>
                <tbody>
                <?php foreach ($fluidoterapiaSafe as $f): ?>
                    <tr>
                        <td><?php echo (int) ($f['id'] ?? 0); ?></td>
                        <td>#<?php echo (int) ($f['hospitalizacion_id'] ?? 0); ?></td>
                        <td><?php echo htmlspecialchars((string) ($f['animal'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($f['tamano'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($f['mantenimiento'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($f['rehidratacion'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($f['formula'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($f['formulas_medicas'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

</section>
<?php require BASE_PATH . '/app/views/layaouts/footer.php'; ?>
