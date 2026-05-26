<?php
ob_start();
?>
<?php

$pageTitle = 'Nueva formula';
?>
<?php
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$errorSafe = isset($error) ? (string) $error : '';
?>

<main class="container py-4">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 m-0">Nueva formula medica</h1>
            <p class="text-muted m-0">Define expresiones seguras para dosificacion, fluidoterapia o calculos clinicos.</p>
        </div>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/formulas')); ?>">Ver listado</a>
    </div>

    <?php if ($errorSafe !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($errorSafe); ?></div><?php endif; ?>

    <section class="card tvg-card tvg-surface-strong p-4">
        <form method="post" action="<?php echo htmlspecialchars(url('/formulas')); ?>" class="row g-3" id="formulaEditorForm">
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">

            <div class="col-12">
                <h2 class="h6 m-0">Informacion general</h2>
            </div>
            <div class="col-md-5">
                <label class="form-label">Nombre</label>
                <input class="form-control" name="nombre" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">Categoria</label>
                <input class="form-control" name="categoria" placeholder="dosis, fluidos, analgesia..." value="general">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <label class="form-check-label"><input class="form-check-input me-1" type="checkbox" name="estado" value="1" checked>Activa</label>
            </div>
            <div class="col-md-12">
                <label class="form-label">Descripcion</label>
                <textarea class="form-control" rows="2" name="descripcion"></textarea>
            </div>

            <div class="col-12 mt-2">
                <h2 class="h6 m-0">Expresion matematica</h2>
            </div>
            <div class="col-12">
                <label class="form-label">Expresion</label>
                <input class="form-control" name="expresion_formula" id="expresion_formula" placeholder="(peso * dosis) / concentracion" required>
                <small class="text-muted">Operadores permitidos: +, -, *, /, ^ y parentesis.</small>
            </div>

            <div class="col-12">
                <button class="btn btn-outline-primary" type="button" id="detectarVariablesBtn" data-detect-url="<?php echo htmlspecialchars(url('/formulas/variables/detectar')); ?>">Detectar variables</button>
            </div>
            <div class="col-12">
                <div id="variablesDetectadas" class="row g-2"></div>
            </div>

            <div class="col-12 d-flex justify-content-end gap-2 mt-3">
                <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/formulas')); ?>">Cancelar</a>
                <button class="btn btn-brand" type="submit">Guardar formula</button>
            </div>
        </form>
    </section>
</main>

<script src="<?php echo htmlspecialchars(asset('js/formulas.js')); ?>"></script>

<?php
$pageContent = ob_get_clean();
require BASE_PATH . '/app/views/layaouts/app.php';
