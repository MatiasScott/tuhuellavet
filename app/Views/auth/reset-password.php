<div class="auth-card">
    <div class="auth-logo">🔐</div>
    <h1>Nueva contraseña</h1>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="POST" action="<?= url('/restablecer-password') ?>" class="form-stack">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <label>
            <span>Contraseña</span>
            <input type="password" name="password" minlength="8" required>
        </label>
        <label>
            <span>Confirmar</span>
            <input type="password" name="password_confirmation" minlength="8" required>
        </label>
        <button class="btn btn-primary btn-block">Actualizar contraseña</button>
    </form>
</div>