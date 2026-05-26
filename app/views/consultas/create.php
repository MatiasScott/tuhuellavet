<?php
ob_start();
?>
<?php

$pageTitle = 'Nueva consulta';
?>
<?php
$animalesSafe = isset($animales) && is_array($animales) ? $animales : [];
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$errorSafe = isset($error) ? (string) $error : '';
?>

<main class="container py-4">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 m-0">Nueva consulta externa</h1>
            <p class="text-muted m-0">Completa el examen clinico y plan terapeutico de forma estructurada.</p>
        </div>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/consultas')); ?>">Ver historial</a>
    </div>

    <?php if ($errorSafe !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($errorSafe); ?></div><?php endif; ?>

    <section class="card tvg-card tvg-surface-strong p-4">
        <form method="post" action="<?php echo htmlspecialchars(url('/consultas')); ?>" class="row g-3">
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">

            <div class="col-12">
                <h2 class="h6 m-0">Encabezado de atencion</h2>
            </div>
            <div class="col-md-5">
                <label class="form-label">Paciente</label>
                <select class="form-select" name="animal_id" required>
                    <option value="">Selecciona...</option>
                    <?php foreach ($animalesSafe as $a): ?>
                        <option value="<?php echo (int) ($a['id'] ?? 0); ?>"><?php echo htmlspecialchars((string) ($a['nombre'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">Fecha y hora</label>
                <input class="form-control" type="datetime-local" name="fecha_consulta">
            </div>
            <div class="col-md-3">
                <label class="form-label">Motivo</label>
                <input class="form-control" name="motivo_consulta">
            </div>

            <div class="col-12 mt-2">
                <h2 class="h6 m-0">Historia clinica</h2>
            </div>
            <div class="col-md-4">
                <label class="form-label">Anamnesis</label>
                <textarea class="form-control" rows="3" name="anamnesis"></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label">Antecedentes</label>
                <textarea class="form-control" rows="3" name="antecedentes"></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label">Diagnostico</label>
                <textarea class="form-control" rows="3" name="diagnostico"></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label">Tratamiento</label>
                <textarea class="form-control" rows="3" name="tratamiento"></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label">Tratamiento clinico</label>
                <textarea class="form-control" rows="3" name="tratamiento_clinico"></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label">Tratamiento en casa</label>
                <textarea class="form-control" rows="3" name="tratamiento_casa"></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Recomendaciones</label>
                <textarea class="form-control" rows="3" name="recomendaciones"></textarea>
            </div>
            <div class="col-md-6">
                <label class="form-label">Observaciones</label>
                <textarea class="form-control" rows="3" name="observaciones"></textarea>
            </div>

            <div class="col-12 mt-2">
                <h2 class="h6 m-0">Signos vitales y examen general</h2>
            </div>
            <div class="col-md-3">
                <label class="form-label">Peso</label>
                <input class="form-control" type="number" step="0.01" name="peso">
            </div>
            <div class="col-md-3">
                <label class="form-label">Temperatura</label>
                <input class="form-control" type="number" step="0.01" name="temperatura">
            </div>
            <div class="col-md-3">
                <label class="form-label">Frec. cardiaca</label>
                <input class="form-control" type="number" step="0.01" name="frecuencia_cardiaca">
            </div>
            <div class="col-md-3">
                <label class="form-label">Frec. respiratoria</label>
                <input class="form-control" type="number" step="0.01" name="frecuencia_respiratoria">
            </div>
            <div class="col-md-4">
                <label class="form-label">Alimentacion</label>
                <input class="form-control" name="alimentacion">
            </div>
            <div class="col-md-4">
                <label class="form-label">Historial reproductivo</label>
                <input class="form-control" name="historial_reproductivo">
            </div>
            <div class="col-md-4">
                <label class="form-label">Condicion corporal</label>
                <input class="form-control" name="condicion_corporal">
            </div>
            <div class="col-md-4">
                <label class="form-label">FC examen general</label>
                <input class="form-control" type="number" step="0.01" name="eg_frecuencia_cardiaca">
            </div>
            <div class="col-md-4">
                <label class="form-label">FR examen general</label>
                <input class="form-control" type="number" step="0.01" name="eg_frecuencia_respiratoria">
            </div>
            <div class="col-md-4">
                <label class="form-label">Temperatura examen general</label>
                <input class="form-control" type="number" step="0.01" name="eg_temperatura">
            </div>
            <div class="col-md-6">
                <label class="form-label">Tiempo de llenado capilar</label>
                <input class="form-control" name="tiempo_llenado_capilar">
            </div>
            <div class="col-md-6">
                <label class="form-label">Ganglios linfaticos</label>
                <input class="form-control" name="ganglios_linfaticos">
            </div>
            <div class="col-12 d-flex gap-4">
                <label class="form-check-label"><input class="form-check-input me-1" type="checkbox" name="vomitos" value="1">Vomitos</label>
                <label class="form-check-label"><input class="form-check-input me-1" type="checkbox" name="diarrea" value="1">Diarrea</label>
                <label class="form-check-label"><input class="form-check-input me-1" type="checkbox" name="tos" value="1">Tos</label>
            </div>

            <div class="col-12 d-flex justify-content-end gap-2 mt-3">
                <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/consultas')); ?>">Cancelar</a>
                <button class="btn btn-brand" type="submit">Guardar consulta</button>
            </div>
        </form>
    </section>
</main>

<?php
$pageContent = ob_get_clean();
require BASE_PATH . "/app/views/layaouts/app.php";
