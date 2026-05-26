<?php
ob_start();
?>
<?php

$pageTitle = 'Nuevo paciente';
?>
<?php
$catalogosSafe = isset($catalogos) && is_array($catalogos) ? $catalogos : [];
$propietariosSafe = isset($catalogosSafe['propietarios']) && is_array($catalogosSafe['propietarios']) ? $catalogosSafe['propietarios'] : [];
$especiesSafe = isset($catalogosSafe['especies']) && is_array($catalogosSafe['especies']) ? $catalogosSafe['especies'] : [];
$razasSafe = isset($catalogosSafe['razas']) && is_array($catalogosSafe['razas']) ? $catalogosSafe['razas'] : [];
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$errorSafe = isset($error) ? (string) $error : '';
?>

<main class="container py-4">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 m-0">Nuevo paciente</h1>
            <p class="text-muted m-0">Captura datos clinicos base antes de iniciar consultas y tratamientos.</p>
        </div>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/animales')); ?>">Ver listado</a>
    </div>

    <?php if ($errorSafe !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($errorSafe); ?></div><?php endif; ?>

    <section class="card tvg-card tvg-surface-strong p-4">
        <form method="post" action="<?php echo htmlspecialchars(url('/animales')); ?>" enctype="multipart/form-data" class="row g-3">
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">

            <div class="col-12">
                <h2 class="h6 m-0">Identificacion del paciente</h2>
            </div>
            <div class="col-md-3">
                <label class="form-label">Codigo</label>
                <input class="form-control" name="codigo">
            </div>
            <div class="col-md-5">
                <label class="form-label">Nombre</label>
                <input class="form-control" name="nombre" required>
            </div>
            <div class="col-md-4">
                <label class="form-label">Propietario</label>
                <select class="form-select" name="propietario_id">
                    <option value="">Selecciona...</option>
                    <?php foreach ($propietariosSafe as $p): ?>
                        <option value="<?php echo (int) ($p['id'] ?? 0); ?>"><?php echo htmlspecialchars((string) ($p['nombre'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12 mt-2">
                <h2 class="h6 m-0">Datos biologicos</h2>
            </div>
            <div class="col-md-4">
                <label class="form-label">Especie</label>
                <select class="form-select" name="especie_id" required>
                    <option value="">Selecciona...</option>
                    <?php foreach ($especiesSafe as $e): ?>
                        <option value="<?php echo (int) ($e['id'] ?? 0); ?>"><?php echo htmlspecialchars((string) ($e['nombre'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Raza</label>
                <select class="form-select" name="raza_id">
                    <option value="">Selecciona...</option>
                    <?php foreach ($razasSafe as $r): ?>
                        <option value="<?php echo (int) ($r['id'] ?? 0); ?>"><?php echo htmlspecialchars((string) ($r['nombre'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Sexo</label>
                <select class="form-select" name="sexo">
                    <option value="">Selecciona...</option>
                    <option value="macho">Macho</option>
                    <option value="hembra">Hembra</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Fecha nacimiento</label>
                <input class="form-control" type="date" name="fecha_nacimiento">
            </div>
            <div class="col-md-3">
                <label class="form-label">Peso actual (kg)</label>
                <input class="form-control" type="number" step="0.01" name="peso_actual">
            </div>
            <div class="col-md-3">
                <label class="form-label">Color</label>
                <input class="form-control" name="color">
            </div>
            <div class="col-md-3">
                <label class="form-label">Microchip</label>
                <input class="form-control" name="microchip">
            </div>

            <div class="col-12 mt-2">
                <h2 class="h6 m-0">Adjuntos y notas</h2>
            </div>
            <div class="col-md-4">
                <label class="form-label">Foto</label>
                <input class="form-control" type="file" name="foto" accept=".jpg,.jpeg,.png,.webp">
            </div>
            <div class="col-md-8">
                <label class="form-label">Observaciones</label>
                <input class="form-control" name="observaciones">
            </div>

            <div class="col-12 d-flex justify-content-end gap-2 mt-3">
                <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/animales')); ?>">Cancelar</a>
                <button class="btn btn-brand" type="submit">Guardar paciente</button>
            </div>
        </form>
    </section>
</main>
<script src="<?php echo htmlspecialchars(asset('js/animales/index.js')); ?>"></script>

<?php
$pageContent = ob_get_clean();
require BASE_PATH . "/app/views/layaouts/app.php";
