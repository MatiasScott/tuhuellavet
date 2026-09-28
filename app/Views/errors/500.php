<div class="auth-form-card">
    <div class="auth-logo">🐾</div>

    <h2>Ocurrió un problema</h2>

    <p>
        <?= e($message ?? 'Error interno del sistema.') ?>
    </p>

    <?php if (is_authenticated()): ?>

        <a
            class="btn btn-primary"
            href="<?= url('/seleccionar-entorno') ?>">
            Volver a seleccionar entorno
        </a>

    <?php else: ?>

        <a
            class="btn btn-primary"
            href="<?= url('/') ?>">
            Volver al inicio
        </a>

    <?php endif; ?>
</div>