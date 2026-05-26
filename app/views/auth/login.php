<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesion - Tu Huella Vet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars(asset('css/global.css')); ?>" rel="stylesheet">
    <link href="<?php echo htmlspecialchars(asset('css/login.css')); ?>" rel="stylesheet">
</head>
<body class="login-page">
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
</body>
</html>
