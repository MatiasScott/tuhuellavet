<div class="environment-page">
    <div class="environment-heading">
        <div class="auth-logo">🐾</div>
        <h1>¿Dónde quieres trabajar?</h1>
        <p>Selecciona un entorno. Tus roles y permisos se cargarán automáticamente.</p>
    </div>

    <?php if (!empty($error)): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <div class="environment-grid">
        <?php foreach ($environments as $environment): ?>
            <?php
                $icon = match ($environment['tipo_codigo'] ?? '') {
                    'VETERINARIA' => '🐶',
                    'HACIENDA' => '🐄',
                    'ACADEMICO' => '🎓',
                    default => '🐾',
                };
            ?>
            <form action="<?= url('/seleccionar-entorno') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="entorno_id" value="<?= (int)$environment['id'] ?>">
                <button class="environment-card" type="submit">
                    <span class="environment-card-icon"><?= $icon ?></span>
                    <strong><?= e($environment['nombre']) ?></strong>
                    <small><?= e($environment['tipo_nombre'] ?? '') ?></small>
                    <span class="environment-card-action">Ingresar →</span>
                </button>
            </form>
        <?php endforeach; ?>
    </div>
</div>
