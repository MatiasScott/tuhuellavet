<?php
ob_start();
?>
<?php

$pageTitle = 'Editar paciente';
?>
<?php
$rowSafe = isset($row) && is_array($row) ? $row : [];
$catalogosSafe = isset($catalogos) && is_array($catalogos) ? $catalogos : [];
$propietariosSafe = isset($catalogosSafe['propietarios']) && is_array($catalogosSafe['propietarios']) ? $catalogosSafe['propietarios'] : [];
$especiesSafe = isset($catalogosSafe['especies']) && is_array($catalogosSafe['especies']) ? $catalogosSafe['especies'] : [];
$razasSafe = isset($catalogosSafe['razas']) && is_array($catalogosSafe['razas']) ? $catalogosSafe['razas'] : [];
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$errorSafe = isset($error) ? (string) $error : '';
$fotoActualSafe = trim((string) ($rowSafe['foto'] ?? ''));
$fotoActualUrl = $fotoActualSafe !== '' ? url('/imagen/ver?path=' . rawurlencode($fotoActualSafe)) : '';
$convierteAWebp = (bool) config('files.convert_to_webp', false);
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
            <div class="col-12">
                <?php if ($fotoActualUrl !== ''): ?>
                    <div class="d-flex align-items-center gap-3">
                        <img src="<?php echo htmlspecialchars($fotoActualUrl); ?>" alt="Foto actual del paciente" style="width:72px;height:72px;object-fit:cover;border-radius:12px;border:1px solid #d5e0e7;">
                        <div>
                            <small class="text-muted d-block">Foto actual cargada correctamente.</small>
                            <?php if ($convierteAWebp): ?>
                                <small class="text-muted d-block">El sistema convierte imagenes PNG/JPG a WEBP para optimizar almacenamiento.</small>
                            <?php else: ?>
                                <small class="text-muted d-block">El sistema conserva el formato original de la imagen al actualizar.</small>
                            <?php endif; ?>
                            <small class="text-muted d-block">Archivo: <?php echo htmlspecialchars($fotoActualSafe); ?></small>
                        </div>
                    </div>
                <?php else: ?>
                    <small class="text-muted">Este paciente no tiene foto registrada.</small>
                <?php endif; ?>
            </div>
        </form>
    </section>
</main>


<?php
$pageContent = ob_get_clean();
require BASE_PATH . "/app/views/layaouts/app.php";
?>
