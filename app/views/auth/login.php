<?php
ob_start();
?>
<?php
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$errorSafe = isset($error) ? (string) $error : '';
?>
<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-7 col-lg-5">
            <section class="card tvg-card p-4">
                <h1 class="h4 mb-4">Ingreso al sistema</h1>
                <?php if ($errorSafe !== ''): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($errorSafe); ?></div>
                    <?php \App\Core\Session::remove('flash_error'); ?>
                <?php endif; ?>
                <form action="<?php echo htmlspecialchars(url('/login')); ?>" method="post" id="loginForm">
                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
                    <div class="mb-3">
                        <label class="form-label" for="email">Correo</label>
                        <input class="form-control" type="email" id="email" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password">Contrasena</label>
                        <input class="form-control" type="password" id="password" name="password" required>
                    </div>
                    <button class="btn btn-brand w-100" type="submit">Entrar</button>
                </form>
                <a class="small d-inline-block mt-3" href="<?php echo htmlspecialchars(url('/password/forgot')); ?>">Olvide mi contrasena</a>
            </section>
        </div>
    </div>
</main>
<script src="<?php echo htmlspecialchars(asset('js/auth/login.js')); ?>"></script>
<?php
$authContent = ob_get_clean();
$pageTitle = 'Iniciar sesion - Tu Huella Vet';
$authBodyClass = 'login-page';
$authExtraCss = [asset('css/login.css')];
require BASE_PATH . '/app/views/layaouts/auth.php';
?>
