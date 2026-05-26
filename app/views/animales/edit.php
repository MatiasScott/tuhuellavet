<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar paciente</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars(asset('css/global.css')); ?>" rel="stylesheet">
</head>
<body>
<?php
$rowSafe = isset($row) && is_array($row) ? $row : [];
$catalogosSafe = isset($catalogos) && is_array($catalogos) ? $catalogos : [];
$propietariosSafe = isset($catalogosSafe['propietarios']) && is_array($catalogosSafe['propietarios']) ? $catalogosSafe['propietarios'] : [];
$especiesSafe = isset($catalogosSafe['especies']) && is_array($catalogosSafe['especies']) ? $catalogosSafe['especies'] : [];
$razasSafe = isset($catalogosSafe['razas']) && is_array($catalogosSafe['razas']) ? $catalogosSafe['razas'] : [];
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$errorSafe = isset($error) ? (string) $error : '';
?>
<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 m-0">Editar paciente #<?php echo (int) ($rowSafe['id'] ?? 0); ?></h1>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/animales')); ?>">Volver</a>
    </div>

    <?php if ($errorSafe !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($errorSafe); ?></div><?php endif; ?>

    <section class="card tvg-card p-3">
        <form method="post" action="<?php echo htmlspecialchars(url('/animales/actualizar')); ?>" enctype="multipart/form-data" class="row g-2">
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
            <input type="hidden" name="id" value="<?php echo (int) ($rowSafe['id'] ?? 0); ?>">
            <div class="col-md-2"><input class="form-control" name="codigo" value="<?php echo htmlspecialchars((string) ($rowSafe['codigo'] ?? '')); ?>"></div>
            <div class="col-md-3"><input class="form-control" name="nombre" value="<?php echo htmlspecialchars((string) ($rowSafe['nombre'] ?? '')); ?>" required></div>
            <div class="col-md-3">
                <select class="form-select" name="propietario_id">
                    <option value="">Propietario...</option>
                    <?php foreach ($propietariosSafe as $p): ?>
                        <?php $pid = (int) ($p['id'] ?? 0); ?>
                        <option value="<?php echo $pid; ?>" <?php echo ((int) ($rowSafe['propietario_id'] ?? 0) === $pid) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($p['nombre'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="especie_id" required>
                    <option value="">Especie...</option>
                    <?php foreach ($especiesSafe as $e): ?>
                        <?php $eid = (int) ($e['id'] ?? 0); ?>
                        <option value="<?php echo $eid; ?>" <?php echo ((int) ($rowSafe['especie_id'] ?? 0) === $eid) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($e['nombre'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="raza_id">
                    <option value="">Raza...</option>
                    <?php foreach ($razasSafe as $r): ?>
                        <?php $rid = (int) ($r['id'] ?? 0); ?>
                        <option value="<?php echo $rid; ?>" <?php echo ((int) ($rowSafe['raza_id'] ?? 0) === $rid) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($r['nombre'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="sexo">
                    <option value="">Sexo...</option>
                    <option value="macho" <?php echo ((string) ($rowSafe['sexo'] ?? '') === 'macho') ? 'selected' : ''; ?>>Macho</option>
                    <option value="hembra" <?php echo ((string) ($rowSafe['sexo'] ?? '') === 'hembra') ? 'selected' : ''; ?>>Hembra</option>
                </select>
            </div>
            <div class="col-md-2"><input class="form-control" type="date" name="fecha_nacimiento" value="<?php echo htmlspecialchars((string) ($rowSafe['fecha_nacimiento'] ?? '')); ?>"></div>
            <div class="col-md-2"><input class="form-control" type="number" step="0.01" name="peso_actual" value="<?php echo htmlspecialchars((string) ($rowSafe['peso_actual'] ?? '')); ?>"></div>
            <div class="col-md-2"><input class="form-control" name="color" value="<?php echo htmlspecialchars((string) ($rowSafe['color'] ?? '')); ?>"></div>
            <div class="col-md-2"><input class="form-control" name="microchip" value="<?php echo htmlspecialchars((string) ($rowSafe['microchip'] ?? '')); ?>"></div>
            <div class="col-md-2"><input class="form-control" type="file" name="foto" accept=".jpg,.jpeg,.png,.webp"></div>
            <div class="col-md-6"><input class="form-control" name="observaciones" value="<?php echo htmlspecialchars((string) ($rowSafe['observaciones'] ?? '')); ?>"></div>
            <div class="col-md-2"><button class="btn btn-brand w-100" type="submit">Actualizar</button></div>
            <div class="col-12"><small class="text-muted">Foto actual: <?php echo htmlspecialchars((string) ($rowSafe['foto'] ?? '')); ?></small></div>
        </form>
    </section>
</main>
</body>
</html>
