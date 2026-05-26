<?php

$pageTitle = 'Timeline clinico';
require BASE_PATH . '/app/views/layaouts/header.php';
?>
<?php
$animalesSafe = isset($animales) && is_array($animales) ? $animales : [];
$propietariosSafe = isset($propietarios) && is_array($propietarios) ? $propietarios : [];
$rowsSafe = isset($rows) && is_array($rows) ? $rows : [];
$filtersSafe = isset($filters) && is_array($filters) ? $filters : [];
$animalIdSafe = (int) ($filtersSafe['animal_id'] ?? 0);
$propietarioIdSafe = (int) ($filtersSafe['propietario_id'] ?? 0);
$fechaInicioSafe = (string) ($filtersSafe['fecha_inicio'] ?? '');
$fechaFinSafe = (string) ($filtersSafe['fecha_fin'] ?? '');
?>

<?php require BASE_PATH . '/app/views/layaouts/sidebar.php'; ?>
<section class="col-12 col-lg-9 col-xl-10">
<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 m-0">Timeline clinico</h1>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/dashboard')); ?>">Volver</a>
    </div>

    <section class="card tvg-card p-3 mb-4">
        <h2 class="h6">Filtros</h2>
        <form method="get" action="<?php echo htmlspecialchars(url('/timeline')); ?>" class="row g-2">
            <div class="col-md-3">
                <select class="form-select" name="animal_id">
                    <option value="">Paciente...</option>
                    <?php foreach ($animalesSafe as $a): ?>
                        <?php $aid = (int) ($a['id'] ?? 0); ?>
                        <option value="<?php echo $aid; ?>" <?php echo $animalIdSafe === $aid ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($a['nombre'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select" name="propietario_id">
                    <option value="">Propietario...</option>
                    <?php foreach ($propietariosSafe as $p): ?>
                        <?php $pid = (int) ($p['id'] ?? 0); ?>
                        <option value="<?php echo $pid; ?>" <?php echo $propietarioIdSafe === $pid ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($p['nombre'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><input class="form-control" type="date" name="fecha_inicio" value="<?php echo htmlspecialchars($fechaInicioSafe); ?>"></div>
            <div class="col-md-2"><input class="form-control" type="date" name="fecha_fin" value="<?php echo htmlspecialchars($fechaFinSafe); ?>"></div>
            <div class="col-md-2"><button class="btn btn-brand w-100" type="submit">Filtrar</button></div>
        </form>
    </section>

    <section class="card tvg-card p-3">
        <h2 class="h6">Eventos</h2>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead><tr><th>Fecha</th><th>Modulo</th><th>Paciente</th><th>Propietario</th><th>Titulo</th><th>Detalle</th></tr></thead>
                <tbody>
                <?php foreach ($rowsSafe as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string) ($r['fecha_evento'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['modulo'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['animal'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['propietario'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['titulo'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($r['detalle'] ?? '')); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

</section>
<?php require BASE_PATH . '/app/views/layaouts/footer.php'; ?>
