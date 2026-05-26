<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Animales</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars(asset('css/global.css')); ?>" rel="stylesheet">
    <link href="<?php echo htmlspecialchars(asset('css/animales.css')); ?>" rel="stylesheet">
</head>
<body>
<?php
$rowsSafe = isset($rows) && is_array($rows) ? $rows : [];
$catalogosSafe = isset($catalogos) && is_array($catalogos) ? $catalogos : [];
$propietariosSafe = isset($catalogosSafe['propietarios']) && is_array($catalogosSafe['propietarios']) ? $catalogosSafe['propietarios'] : [];
$especiesSafe = isset($catalogosSafe['especies']) && is_array($catalogosSafe['especies']) ? $catalogosSafe['especies'] : [];
$razasSafe = isset($catalogosSafe['razas']) && is_array($catalogosSafe['razas']) ? $catalogosSafe['razas'] : [];
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$successSafe = isset($success) ? (string) $success : '';
$errorSafe = isset($error) ? (string) $error : '';
?>
<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 m-0">Pacientes / Animales</h1>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/dashboard')); ?>">Volver</a>
    </div>

    <?php if ($successSafe !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($successSafe); ?></div><?php endif; ?>
    <?php if ($errorSafe !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($errorSafe); ?></div><?php endif; ?>

    <section class="card tvg-card p-3 mb-4">
        <h2 class="h6">Nuevo paciente</h2>
        <form method="post" action="<?php echo htmlspecialchars(url('/animales')); ?>" enctype="multipart/form-data" class="row g-2">
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
            <div class="col-md-2"><input class="form-control" name="codigo" placeholder="Codigo"></div>
            <div class="col-md-3"><input class="form-control" name="nombre" placeholder="Nombre" required></div>
            <div class="col-md-3">
                <select class="form-select" name="propietario_id">
                    <option value="">Propietario...</option>
                    <?php foreach ($propietariosSafe as $p): ?>
                        <option value="<?php echo (int) ($p['id'] ?? 0); ?>"><?php echo htmlspecialchars((string) ($p['nombre'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="especie_id" required>
                    <option value="">Especie...</option>
                    <?php foreach ($especiesSafe as $e): ?>
                        <option value="<?php echo (int) ($e['id'] ?? 0); ?>"><?php echo htmlspecialchars((string) ($e['nombre'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="raza_id">
                    <option value="">Raza...</option>
                    <?php foreach ($razasSafe as $r): ?>
                        <option value="<?php echo (int) ($r['id'] ?? 0); ?>"><?php echo htmlspecialchars((string) ($r['nombre'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="sexo">
                    <option value="">Sexo...</option>
                    <option value="macho">Macho</option>
                    <option value="hembra">Hembra</option>
                </select>
            </div>
            <div class="col-md-2"><input class="form-control" type="date" name="fecha_nacimiento"></div>
            <div class="col-md-2"><input class="form-control" type="number" step="0.01" name="peso_actual" placeholder="Peso actual"></div>
            <div class="col-md-2"><input class="form-control" name="color" placeholder="Color"></div>
            <div class="col-md-2"><input class="form-control" name="microchip" placeholder="Microchip"></div>
            <div class="col-md-2"><input class="form-control" type="file" name="foto" accept=".jpg,.jpeg,.png,.webp"></div>
            <div class="col-md-6"><input class="form-control" name="observaciones" placeholder="Observaciones"></div>
            <div class="col-md-2"><button class="btn btn-brand w-100" type="submit">Guardar</button></div>
        </form>
    </section>

    <section class="card tvg-card p-3">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Propietario</th>
                    <th>Especie/Raza</th>
                    <th>Peso</th>
                    <th>Edad</th>
                    <th>Foto</th>
                    <th></th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rowsSafe as $row): ?>
                    <tr>
                        <td><?php echo (int) ($row['id'] ?? 0); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row['nombre'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row['propietario_nombre'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) (($row['especie'] ?? '') . ' / ' . ($row['raza'] ?? ''))); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row['peso_actual'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row['edad_anios'] ?? '')); ?> anos</td>
                        <td><small class="text-muted"><?php echo htmlspecialchars((string) ($row['foto'] ?? '')); ?></small></td>
                        <td><a class="btn btn-sm btn-outline-primary" href="<?php echo htmlspecialchars(url('/animales/editar?id=' . (int) ($row['id'] ?? 0))); ?>">Editar</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<script src="<?php echo htmlspecialchars(asset('js/animales/index.js')); ?>"></script>
</body>
</html>
