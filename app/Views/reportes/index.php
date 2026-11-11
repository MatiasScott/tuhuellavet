<div class="page-heading">
    <div><span class="eyebrow">Analítica</span>
        <h1>Reportes</h1>
        <p>Indicadores operativos y exportaciones.</p>
    </div>
    <div class="inline-actions">
        <a class="btn btn-secondary" href="<?= url('/reportes/pacientes.csv') ?>">CSV</a>
        <a class="btn btn-secondary" href="<?= url('/reportes/pacientes.xlsx') ?>">XLSX</a>
        <a class="btn btn-primary" href="<?= url('/reportes/pacientes.pdf') ?>">PDF</a>
    </div>
</div>
<div class="metric-grid">
    <?php foreach ($stats as $k => $v): ?>
        <div class="metric-card">
            <span><?= e($k) ?></span>
            <strong><?= e($v) ?></strong>
        </div>
    <?php endforeach; ?>
</div>
<section class="card">
    <?php if (\App\Services\EnvironmentModules::isFarm(active_environment())): ?>
        <h2>Reportes de Hacienda</h2>
        <?php foreach (['produccion' => 'Producción por animal o lote', 'alimentacion' => 'Alimentación y trazabilidad de insumos'] as $module => $label): ?>
            <?php if (can($module . '.ver')): ?><p><a class="btn btn-secondary" href="<?= url('/hacienda/' . $module) ?>"><?= e($label) ?></a></p><?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
    <div class="card-header">
        <div>
            <h2>Exportaciones</h2>
            <p>El proyecto incluye Dompdf y PhpSpreadsheet para ampliar PDF/XLSX en producción.</p>
        </div>
    </div>
    <p class="text-muted">Se dejó una exportación CSV funcional como base y la capa de reportes separada para PDF/XLSX.</p>
</section>