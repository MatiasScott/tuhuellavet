<?php

$pageTitle = 'Cirugias';
require BASE_PATH . '/app/views/layaouts/header.php';
?>
<?php
$animalesSafe = isset($animales) && is_array($animales) ? $animales : [];
$consultasSafe = isset($consultas) && is_array($consultas) ? $consultas : [];
$formulasSafe = isset($formulas) && is_array($formulas) ? $formulas : [];
$rowsSafe = isset($rows) && is_array($rows) ? $rows : [];
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$successSafe = isset($success) ? (string) $success : '';
$errorSafe = isset($error) ? (string) $error : '';
?>

<?php require BASE_PATH . '/app/views/layaouts/sidebar.php'; ?>
<section class="col-12 col-lg-9 col-xl-10">
<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 m-0">Cirugias</h1>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/dashboard')); ?>">Volver</a>
    </div>

    <?php if ($successSafe !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($successSafe); ?></div><?php endif; ?>
    <?php if ($errorSafe !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($errorSafe); ?></div><?php endif; ?>

    <section class="card tvg-card p-3 mb-4">
        <h2 class="h6">Nueva cirugia</h2>
        <form method="post" action="<?php echo htmlspecialchars(url('/cirugias')); ?>" enctype="multipart/form-data" class="row g-2">
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
            <div class="col-md-4"><input class="form-control" type="datetime-local" name="fecha"></div>
            <div class="col-md-6"><input class="form-control" name="procedimiento_quirurgico" placeholder="Procedimiento quirurgico" required></div>
            <div class="col-md-3"><input class="form-control" name="medico_responsable" placeholder="Medico responsable"></div>
            <div class="col-md-3"><input class="form-control" name="anestesia" placeholder="Anestesia"></div>
            <div class="col-md-4">
                <select class="form-select" name="formula_id">
                    <option value="">Formula opcional...</option>
                    <?php foreach ($formulasSafe as $f): ?>
                        <option value="<?php echo (int) ($f['id'] ?? 0); ?>"><?php echo htmlspecialchars((string) ($f['nombre'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-8"><input class="form-control" name="formula_medica" placeholder="Formula medica"></div>
            <div class="col-md-8"><textarea class="form-control" name="observaciones" placeholder="Observaciones"></textarea></div>
            <div class="col-md-4"><input class="form-control" type="file" name="archivo_pdf" accept="application/pdf,.pdf"></div>
            <div class="col-md-2"><button class="btn btn-brand w-100" type="submit">Guardar</button></div>
        </form>
    </section>

    <section class="card tvg-card p-3">
        <h2 class="h6">Historial</h2>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>ID</th><th>Paciente</th><th>Procedimiento</th><th>Formula</th><th>Formula medica</th><th>PDF</th><th>Fecha</th><th>Usuario</th></tr></thead>
                <tbody>
                <?php foreach ($rowsSafe as $r): ?>
                    <tr>
                        <td><?php echo (int) ($r['id'] ?? 0); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['animal'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['procedimiento_quirurgico'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['formula_nombre'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['formula_medica'] ?? '')); ?></td>
                        <td><small class="text-muted"><?php echo htmlspecialchars((string) ($r['archivo_pdf'] ?? '')); ?></small></td>
                        <td><?php echo htmlspecialchars((string) ($r['fecha'] ?? '')); ?></td>
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
