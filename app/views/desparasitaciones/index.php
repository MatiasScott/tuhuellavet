<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Desparasitaciones</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars(asset('css/global.css')); ?>" rel="stylesheet">
</head>
<body>
<?php
$animalesSafe = isset($animales) && is_array($animales) ? $animales : [];
$rowsSafe = isset($rows) && is_array($rows) ? $rows : [];
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$successSafe = isset($success) ? (string) $success : '';
$errorSafe = isset($error) ? (string) $error : '';
?>
<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 m-0">Desparasitaciones</h1>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/dashboard')); ?>">Volver</a>
    </div>

    <?php if ($successSafe !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($successSafe); ?></div><?php endif; ?>
    <?php if ($errorSafe !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($errorSafe); ?></div><?php endif; ?>

    <section class="card tvg-card p-3 mb-4">
        <h2 class="h6">Registrar desparasitacion</h2>
        <form method="post" action="<?php echo htmlspecialchars(url('/desparasitaciones')); ?>" class="row g-2">
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
            <div class="col-md-4">
                <select class="form-select" name="animal_id" required>
                    <option value="">Paciente...</option>
                    <?php foreach ($animalesSafe as $a): ?>
                        <option value="<?php echo (int) ($a['id'] ?? 0); ?>"><?php echo htmlspecialchars((string) ($a['nombre'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4"><input class="form-control" name="farmaco" placeholder="Farmaco" required></div>
            <div class="col-md-2"><input class="form-control" name="dosis" placeholder="Dosis"></div>
            <div class="col-md-2"><input class="form-control" type="number" step="0.01" name="peso_actual" placeholder="Peso actual"></div>
            <div class="col-md-3"><input class="form-control" type="date" name="fecha"></div>
            <div class="col-md-3"><input class="form-control" type="date" name="proxima_fecha"></div>
            <div class="col-md-4"><input class="form-control" name="observacion" placeholder="Observacion"></div>
            <div class="col-md-2"><button class="btn btn-brand w-100" type="submit">Guardar</button></div>
        </form>
    </section>

    <section class="card tvg-card p-3">
        <h2 class="h6">Historial</h2>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>ID</th><th>Paciente</th><th>Farmaco</th><th>Dosis</th><th>Fecha</th><th>Proxima</th><th>Peso</th><th>Usuario</th></tr></thead>
                <tbody>
                <?php foreach ($rowsSafe as $r): ?>
                    <tr>
                        <td><?php echo (int) ($r['id'] ?? 0); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['animal'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['farmaco'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['dosis'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['fecha'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['proxima_fecha'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['peso_actual'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['usuario'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
</body>
</html>
