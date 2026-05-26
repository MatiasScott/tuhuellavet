<?php

$pageTitle = 'Dashboard';
require BASE_PATH . '/app/views/layaouts/header.php';
?>
<?php
$userSafe = isset($user) && is_array($user) ? $user : [];
$empresaIdSafe = isset($empresaId) ? (int) $empresaId : 0;
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$rolNombreSafe = isset($rolNombre) ? (string) $rolNombre : 'Sin rol';
$empresaTipoSafe = isset($empresaTipo) ? (string) $empresaTipo : 'global';
$widgetsSafe = isset($widgets) && is_array($widgets) ? $widgets : [];
$metricsSafe = isset($metrics) && is_array($metrics) ? $metrics : [];
?>

<?php require BASE_PATH . '/app/views/layaouts/sidebar.php'; ?>
<section class="col-12 col-lg-9 col-xl-10">
<main class="container">
    <section class="row g-3 mb-3">
        <div class="col-12 col-lg-8">
            <article class="card tvg-card p-4">
                <h2 class="h5">Bienvenido</h2>
                <p class="mb-1">Usuario: <?php echo htmlspecialchars((string) ($userSafe['nombre'] ?? '')); ?></p>
                <p class="mb-1">Empresa activa ID: <?php echo $empresaIdSafe; ?></p>
                <p class="mb-1">Rol activo: <?php echo htmlspecialchars($rolNombreSafe); ?></p>
                <p class="mb-0">Contexto de empresa: <?php echo htmlspecialchars($empresaTipoSafe); ?></p>
            </article>
        </div>
        <div class="col-12 col-lg-4">
            <article class="card tvg-card p-4">
                <h3 class="h6">Accesos rapidos</h3>
                <a class="btn btn-brand w-100 mt-2" href="<?php echo htmlspecialchars(url('/propietarios')); ?>">Propietarios / Clientes</a>
                <a class="btn btn-brand w-100 mt-2" href="<?php echo htmlspecialchars(url('/animales')); ?>">Pacientes / Animales</a>
                <a class="btn btn-brand w-100 mt-2" href="<?php echo htmlspecialchars(url('/consultas')); ?>">Consulta externa</a>
                <a class="btn btn-brand w-100 mt-2" href="<?php echo htmlspecialchars(url('/diagnosticos')); ?>">Diagnosticos</a>
                <a class="btn btn-brand w-100 mt-2" href="<?php echo htmlspecialchars(url('/vacunas')); ?>">Vacunas</a>
                <a class="btn btn-brand w-100 mt-2" href="<?php echo htmlspecialchars(url('/desparasitaciones')); ?>">Desparasitaciones</a>
                <a class="btn btn-brand w-100 mt-2" href="<?php echo htmlspecialchars(url('/hospitalizaciones')); ?>">Hospitalizacion y fluidoterapia</a>
                <a class="btn btn-brand w-100 mt-2" href="<?php echo htmlspecialchars(url('/examenes')); ?>">Examenes de laboratorio</a>
                <a class="btn btn-brand w-100 mt-2" href="<?php echo htmlspecialchars(url('/cirugias')); ?>">Cirugias</a>
                <a class="btn btn-brand w-100 mt-2" href="<?php echo htmlspecialchars(url('/timeline')); ?>">Timeline clinico</a>
            </article>
        </div>
    </section>

    <section class="row g-3 mb-3">
        <div class="col-12 col-md-6 col-lg-3">
            <article class="card tvg-card p-3">
                <h3 class="h6 mb-1">Pacientes</h3>
                <p class="h3 m-0"><?php echo (int) ($metricsSafe['animales'] ?? 0); ?></p>
            </article>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <article class="card tvg-card p-3">
                <h3 class="h6 mb-1">Propietarios</h3>
                <p class="h3 m-0"><?php echo (int) ($metricsSafe['propietarios'] ?? 0); ?></p>
            </article>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <article class="card tvg-card p-3">
                <h3 class="h6 mb-1">Consultas hoy</h3>
                <p class="h3 m-0"><?php echo (int) ($metricsSafe['consultas_hoy'] ?? 0); ?></p>
            </article>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <article class="card tvg-card p-3">
                <h3 class="h6 mb-1">Hospitalizaciones activas</h3>
                <p class="h3 m-0"><?php echo (int) ($metricsSafe['hospitalizaciones_activas'] ?? 0); ?></p>
            </article>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <article class="card tvg-card p-3">
                <h3 class="h6 mb-1">Vacunas proximas (7 dias)</h3>
                <p class="h3 m-0"><?php echo (int) ($metricsSafe['vacunas_proximas_7_dias'] ?? 0); ?></p>
            </article>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <article class="card tvg-card p-3">
                <h3 class="h6 mb-1">Examenes del mes</h3>
                <p class="h3 m-0"><?php echo (int) ($metricsSafe['examenes_mes'] ?? 0); ?></p>
            </article>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <article class="card tvg-card p-3">
                <h3 class="h6 mb-1">Cirugias del mes</h3>
                <p class="h3 m-0"><?php echo (int) ($metricsSafe['cirugias_mes'] ?? 0); ?></p>
            </article>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <article class="card tvg-card p-3">
                <h3 class="h6 mb-1">Eventos timeline (7 dias)</h3>
                <p class="h3 m-0"><?php echo (int) ($metricsSafe['eventos_timeline_7_dias'] ?? 0); ?></p>
            </article>
        </div>
    </section>

    <section class="card tvg-card p-3 mb-3">
        <h2 class="h6">Widgets dinamicos por rol</h2>
        <?php if ($widgetsSafe === []): ?>
            <p class="mb-0 text-muted">No hay widgets configurados para el rol/contexto actual.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                    <tr>
                        <th>Orden</th>
                        <th>Codigo</th>
                        <th>Nombre</th>
                        <th>Modulo origen</th>
                        <th>Contexto</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($widgetsSafe as $widget): ?>
                        <tr>
                            <td><?php echo (int) ($widget['orden'] ?? 0); ?></td>
                            <td><?php echo htmlspecialchars((string) ($widget['codigo'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($widget['nombre'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($widget['modulo_origen'] ?? '')); ?></td>
                            <td><?php echo htmlspecialchars((string) ($widget['contexto'] ?? '')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</main>
<script src="<?php echo htmlspecialchars(asset('js/dashboard.js')); ?>"></script>

</section>
<?php require BASE_PATH . '/app/views/layaouts/footer.php'; ?>
