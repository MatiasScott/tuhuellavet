<?php
ob_start();
?>
<?php
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$messageSafe = isset($message) ? (string) $message : '';
?>
<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-6 col-lg-5">
            <section class="card tvg-card p-4">
                <h1 class="h5 mb-3">Recuperacion de contrasena</h1>
                <?php if ($messageSafe !== ''): ?>
                    <div class="alert alert-info"><?php echo htmlspecialchars($messageSafe); ?></div>
                    <?php \App\Core\Session::remove('flash_message'); ?>
                <?php endif; ?>
                <form method="post" action="<?php echo htmlspecialchars(url('/password/forgot')); ?>">
                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
                    <div class="mb-3">
                        <label class="form-label" for="email">Correo</label>
                        <input class="form-control" type="email" id="email" name="email" required>
                    </div>
                    <button class="btn btn-brand w-100" type="submit">Generar enlace</button>
                </form>
            </section>
        </div>
    </div>
</main>
<?php
$authContent = ob_get_clean();
$pageTitle = 'Recuperar contrasena';
$authBodyClass = 'auth-page';
$authExtraCss = [asset('css/login.css')];
require BASE_PATH . '/app/views/layaouts/auth.php';
?>
