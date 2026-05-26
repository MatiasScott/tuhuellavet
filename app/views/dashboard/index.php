<?php
ob_start();
?>
<?php

$pageTitle = 'Dashboard';
?>
<?php
$userSafe = isset($user) && is_array($user) ? $user : [];
$empresaIdSafe = isset($empresaId) ? (int) $empresaId : 0;
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$rolNombreSafe = isset($rolNombre) ? (string) $rolNombre : 'Sin rol';
$empresaTipoSafe = isset($empresaTipo) ? (string) $empresaTipo : 'global';
$widgetsSafe = isset($widgets) && is_array($widgets) ? $widgets : [];
$metricsSafe = isset($metrics) && is_array($metrics) ? $metrics : [];
$pageStyles = [asset('css/dashboard.css')];

$trendPoints = [
    (int) ($metricsSafe['consultas_hoy'] ?? 0),
    (int) ($metricsSafe['hospitalizaciones_activas'] ?? 0),
    (int) ($metricsSafe['vacunas_proximas_7_dias'] ?? 0),
    (int) ($metricsSafe['examenes_mes'] ?? 0),
    (int) ($metricsSafe['cirugias_mes'] ?? 0),
    (int) ($metricsSafe['eventos_timeline_7_dias'] ?? 0),
];
$trendSerialized = implode(',', $trendPoints);
?>

<main class="container">
    <section class="row g-3 mb-3">
        <div class="col-12 col-lg-8">
            <article class="card tvg-card tvg-hero p-4">
                <h2 class="h4 mb-2">Centro de control clinico</h2>
                <p class="mb-1">Usuario: <?php echo htmlspecialchars((string) ($userSafe['nombre'] ?? '')); ?></p>
                <p class="mb-1">Empresa activa ID: <?php echo $empresaIdSafe; ?> | Rol: <?php echo htmlspecialchars($rolNombreSafe); ?></p>
                <p class="mb-3">Contexto de empresa: <?php echo htmlspecialchars($empresaTipoSafe); ?></p>
                <div class="tvg-chart-wrap">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong>Tendencia operativa</strong>
                        <small class="text-muted">Snapshot de metricas clave</small>
                    </div>
                    <canvas id="dashboardTrendChart" data-points="<?php echo htmlspecialchars($trendSerialized); ?>"></canvas>
                </div>
            </article>
        </div>
        <div class="col-12 col-lg-4">
            <article class="card tvg-card p-4">
                <h3 class="h6 mb-3">Accesos rapidos</h3>
                <div class="tvg-grid-quick">
                    <a class="tvg-quick-link" href="<?php echo htmlspecialchars(url('/propietarios')); ?>"><i class="bi bi-person-vcard"></i>Propietarios</a>
                    <a class="tvg-quick-link" href="<?php echo htmlspecialchars(url('/animales')); ?>"><i class="bi bi-shield-plus"></i>Pacientes</a>
                    <a class="tvg-quick-link" href="<?php echo htmlspecialchars(url('/consultas')); ?>"><i class="bi bi-clipboard2-pulse"></i>Consultas</a>
                    <a class="tvg-quick-link" href="<?php echo htmlspecialchars(url('/diagnosticos')); ?>"><i class="bi bi-activity"></i>Diagnosticos</a>
                    <a class="tvg-quick-link" href="<?php echo htmlspecialchars(url('/vacunas')); ?>"><i class="bi bi-droplet-half"></i>Vacunas</a>
                    <a class="tvg-quick-link" href="<?php echo htmlspecialchars(url('/hospitalizaciones')); ?>"><i class="bi bi-hospital"></i>Hospitalizacion</a>
                </div>
            </article>
        </div>
    </section>

    <section class="row g-3 mb-3">
        <div class="col-12 col-md-6 col-lg-3">
            <article class="tvg-kpi-card">
                <p class="tvg-kpi-label">Pacientes</p>
                <p class="tvg-kpi-value"><?php echo (int) ($metricsSafe['animales'] ?? 0); ?></p>
            </article>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <article class="tvg-kpi-card">
                <p class="tvg-kpi-label">Propietarios</p>
                <p class="tvg-kpi-value"><?php echo (int) ($metricsSafe['propietarios'] ?? 0); ?></p>
            </article>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <article class="tvg-kpi-card">
                <p class="tvg-kpi-label">Consultas hoy</p>
                <p class="tvg-kpi-value"><?php echo (int) ($metricsSafe['consultas_hoy'] ?? 0); ?></p>
            </article>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <article class="tvg-kpi-card">
                <p class="tvg-kpi-label">Hospitalizaciones activas</p>
                <p class="tvg-kpi-value"><?php echo (int) ($metricsSafe['hospitalizaciones_activas'] ?? 0); ?></p>
            </article>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <article class="tvg-kpi-card">
                <p class="tvg-kpi-label">Vacunas proximas (7 dias)</p>
                <p class="tvg-kpi-value"><?php echo (int) ($metricsSafe['vacunas_proximas_7_dias'] ?? 0); ?></p>
            </article>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <article class="tvg-kpi-card">
                <p class="tvg-kpi-label">Examenes del mes</p>
                <p class="tvg-kpi-value"><?php echo (int) ($metricsSafe['examenes_mes'] ?? 0); ?></p>
            </article>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <article class="tvg-kpi-card">
                <p class="tvg-kpi-label">Cirugias del mes</p>
                <p class="tvg-kpi-value"><?php echo (int) ($metricsSafe['cirugias_mes'] ?? 0); ?></p>
            </article>
        </div>
        <div class="col-12 col-md-6 col-lg-3">
            <article class="tvg-kpi-card">
                <p class="tvg-kpi-label">Eventos timeline (7 dias)</p>
                <p class="tvg-kpi-value"><?php echo (int) ($metricsSafe['eventos_timeline_7_dias'] ?? 0); ?></p>
            </article>
        </div>
    </section>

    <section class="row g-3 mb-3">
        <div class="col-12 col-lg-7">
            <article class="card tvg-card p-3">
                <h2 class="h6">Widgets dinamicos por rol</h2>
                <?php if ($widgetsSafe === []): ?>
                    <p class="mb-0 text-muted">No hay widgets configurados para el rol/contexto actual.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table tvg-table table-hover mb-0">
                            <thead>
                            <tr>
                                <th>Orden</th>
                                <th>Codigo</th>
                                <th>Nombre</th>
                                <th>Modulo</th>
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
            </article>
        </div>
        <div class="col-12 col-lg-5">
            <article class="card tvg-card p-3">
                <h2 class="h6 mb-2">Actividad resumida</h2>
                <div class="tvg-activity-item">
                    <p class="tvg-activity-title">Consultas del dia</p>
                    <p class="tvg-activity-meta"><?php echo (int) ($metricsSafe['consultas_hoy'] ?? 0); ?> atenciones registradas.</p>
                </div>
                <div class="tvg-activity-item">
                    <p class="tvg-activity-title">Vacunacion pendiente</p>
                    <p class="tvg-activity-meta"><?php echo (int) ($metricsSafe['vacunas_proximas_7_dias'] ?? 0); ?> pacientes por controlar en 7 dias.</p>
                </div>
                <div class="tvg-activity-item">
                    <p class="tvg-activity-title">Examenes de laboratorio</p>
                    <p class="tvg-activity-meta"><?php echo (int) ($metricsSafe['examenes_mes'] ?? 0); ?> pruebas durante el mes actual.</p>
                </div>
                <div class="tvg-activity-item">
                    <p class="tvg-activity-title">Cirugias del periodo</p>
                    <p class="tvg-activity-meta"><?php echo (int) ($metricsSafe['cirugias_mes'] ?? 0); ?> procedimientos en curso mensual.</p>
                </div>
            </article>
        </div>
    </section>
</main>
<script src="<?php echo htmlspecialchars(asset('js/dashboard.js')); ?>"></script>


<?php
$pageContent = ob_get_clean();
require BASE_PATH . "/app/views/layaouts/app.php";
?>
