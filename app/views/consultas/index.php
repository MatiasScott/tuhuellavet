<?php
ob_start();
?>
<?php

$pageTitle = 'Consultas externas';
?>
<?php
$rowsSafe = isset($rows) && is_array($rows) ? $rows : [];
$animalesSafe = isset($animales) && is_array($animales) ? $animales : [];
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$successSafe = isset($success) ? (string) $success : '';
$errorSafe = isset($error) ? (string) $error : '';
?>

<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 m-0">Consulta externa</h1>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/dashboard')); ?>">Volver</a>
    </div>

    <?php if ($successSafe !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($successSafe); ?></div><?php endif; ?>
    <?php if ($errorSafe !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($errorSafe); ?></div><?php endif; ?>

    <section class="card tvg-card p-3 mb-4">
        <h2 class="h6 mb-3">Nueva consulta</h2>
        <form method="post" action="<?php echo htmlspecialchars(url('/consultas')); ?>" class="row g-2">
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
            <div class="col-md-4">
                <select class="form-select" name="animal_id" required>
                    <option value="">Paciente...</option>
                    <?php foreach ($animalesSafe as $a): ?>
                        <option value="<?php echo (int) ($a['id'] ?? 0); ?>"><?php echo htmlspecialchars((string) ($a['nombre'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4"><input class="form-control" type="datetime-local" name="fecha_consulta"></div>
            <div class="col-md-4"><input class="form-control" name="motivo_consulta" placeholder="Motivo"></div>
            <div class="col-md-4"><textarea class="form-control" name="anamnesis" placeholder="Anamnesis"></textarea></div>
            <div class="col-md-4"><textarea class="form-control" name="antecedentes" placeholder="Antecedentes"></textarea></div>
            <div class="col-md-4"><textarea class="form-control" name="diagnostico" placeholder="Diagnostico texto"></textarea></div>
            <div class="col-md-4"><textarea class="form-control" name="tratamiento" placeholder="Tratamiento"></textarea></div>
            <div class="col-md-4"><textarea class="form-control" name="recomendaciones" placeholder="Recomendaciones"></textarea></div>
            <div class="col-md-4"><textarea class="form-control" name="tratamiento_clinico" placeholder="Tratamiento clinico"></textarea></div>
            <div class="col-md-4"><textarea class="form-control" name="tratamiento_casa" placeholder="Tratamiento en casa"></textarea></div>
            <div class="col-md-4"><textarea class="form-control" name="observaciones" placeholder="Observaciones"></textarea></div>
            <div class="col-md-3"><input class="form-control" type="number" step="0.01" name="peso" placeholder="Peso"></div>
            <div class="col-md-3"><input class="form-control" type="number" step="0.01" name="temperatura" placeholder="Temperatura"></div>
            <div class="col-md-3"><input class="form-control" type="number" step="0.01" name="frecuencia_cardiaca" placeholder="F. cardiaca"></div>
            <div class="col-md-3"><input class="form-control" type="number" step="0.01" name="frecuencia_respiratoria" placeholder="F. respiratoria"></div>
            <div class="col-md-3"><input class="form-control" name="alimentacion" placeholder="Alimentacion"></div>
            <div class="col-md-3"><input class="form-control" name="historial_reproductivo" placeholder="Historial reproductivo"></div>
            <div class="col-md-2"><input class="form-control" type="number" step="0.01" name="eg_frecuencia_cardiaca" placeholder="FC examen"></div>
            <div class="col-md-2"><input class="form-control" type="number" step="0.01" name="eg_frecuencia_respiratoria" placeholder="FR examen"></div>
            <div class="col-md-2"><input class="form-control" type="number" step="0.01" name="eg_temperatura" placeholder="T examen"></div>
            <div class="col-md-3"><input class="form-control" name="tiempo_llenado_capilar" placeholder="T. llenado capilar"></div>
            <div class="col-md-3"><input class="form-control" name="ganglios_linfaticos" placeholder="Ganglios"></div>
            <div class="col-md-3"><input class="form-control" name="condicion_corporal" placeholder="Condicion corporal"></div>
            <div class="col-md-3 d-flex align-items-center gap-2">
                <label><input type="checkbox" name="vomitos" value="1"> Vomitos</label>
                <label><input type="checkbox" name="diarrea" value="1"> Diarrea</label>
                <label><input type="checkbox" name="tos" value="1"> Tos</label>
            </div>
            <div class="col-md-3"><button class="btn btn-brand w-100" type="submit">Guardar consulta</button></div>
        </form>
    </section>

    <section class="card tvg-card p-3">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>Fecha</th>
                    <th>Paciente</th>
                    <th>Veterinario</th>
                    <th>Motivo</th>
                    <th>Estado</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rowsSafe as $row): ?>
                    <tr>
                        <td><?php echo (int) ($row['id'] ?? 0); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row['fecha_consulta'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row['animal'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row['veterinario'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row['motivo_consulta'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row['estado'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>


<?php
$pageContent = ob_get_clean();
require BASE_PATH . "/app/views/layaouts/app.php";
?>
