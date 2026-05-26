<?php
ob_start();
?>
<?php

$pageTitle = 'Editar formula';
?>
<?php
$formulaSafe = isset($formula) && is_array($formula) ? $formula : [];
$variablesSafe = isset($formulaSafe['variables']) && is_array($formulaSafe['variables']) ? $formulaSafe['variables'] : [];
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$errorSafe = isset($error) ? (string) $error : '';
?>

<main class="container py-4">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 m-0">Editar formula</h1>
            <p class="text-muted m-0">Actualiza expresion y confirma variables detectadas.</p>
        </div>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/formulas')); ?>">Ver listado</a>
    </div>

    <?php if ($errorSafe !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($errorSafe); ?></div><?php endif; ?>

    <section class="card tvg-card tvg-surface-strong p-4">
        <form method="post" action="<?php echo htmlspecialchars(url('/formulas/actualizar')); ?>" class="row g-3" id="formulaEditorForm">
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
            <input type="hidden" name="id" value="<?php echo (int) ($formulaSafe['id'] ?? 0); ?>">

            <div class="col-md-5">
                <label class="form-label">Nombre</label>
                <input class="form-control" name="nombre" value="<?php echo htmlspecialchars((string) ($formulaSafe['nombre'] ?? '')); ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Categoria</label>
                <input class="form-control" name="categoria" value="<?php echo htmlspecialchars((string) ($formulaSafe['categoria'] ?? 'general')); ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <label class="form-check-label"><input class="form-check-input me-1" type="checkbox" name="estado" value="1" <?php echo ((int) ($formulaSafe['estado'] ?? 0) === 1) ? 'checked' : ''; ?>>Activa</label>
            </div>
            <div class="col-md-12">
                <label class="form-label">Descripcion</label>
                <textarea class="form-control" rows="2" name="descripcion"><?php echo htmlspecialchars((string) ($formulaSafe['descripcion'] ?? '')); ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label">Expresion</label>
                <input class="form-control" name="expresion_formula" id="expresion_formula" value="<?php echo htmlspecialchars((string) ($formulaSafe['expresion_formula'] ?? '')); ?>" required>
            </div>

            <div class="col-12">
                <button class="btn btn-outline-primary" type="button" id="detectarVariablesBtn" data-detect-url="<?php echo htmlspecialchars(url('/formulas/variables/detectar')); ?>">Redetectar variables</button>
            </div>
            <div class="col-12">
                <div id="variablesDetectadas" class="row g-2">
                    <?php foreach ($variablesSafe as $variable): ?>
                        <div class="col-md-3"><div class="form-control bg-light"><?php echo htmlspecialchars((string) ($variable['variable'] ?? '')); ?></div></div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-12 d-flex justify-content-end gap-2 mt-3">
                <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/formulas')); ?>">Cancelar</a>
                <button class="btn btn-brand" type="submit">Guardar cambios</button>
            </div>
        </form>
    </section>
</main>

<script src="<?php echo htmlspecialchars(asset('js/formulas.js')); ?>"></script>

<?php
$pageContent = ob_get_clean();
require BASE_PATH . '/app/views/layaouts/app.php';
