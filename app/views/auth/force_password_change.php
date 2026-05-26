<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cambio obligatorio de contrasena</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars(asset('css/global.css')); ?>" rel="stylesheet">
</head>
<body>
<?php
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$errorSafe = isset($error) ? (string) $error : '';
?>
<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-6">
            <section class="card tvg-card p-4">
                <h1 class="h5 mb-3">Debes cambiar tu contrasena</h1>
                <?php if ($errorSafe !== ''): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($errorSafe); ?></div>
                    <?php \App\Core\Session::remove('flash_error'); ?>
                <?php endif; ?>
                <form method="post" action="<?php echo htmlspecialchars(url('/password/change')); ?>">
                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
                    <div class="mb-3">
                        <label class="form-label" for="password">Nueva contrasena</label>
                        <input class="form-control" type="password" id="password" name="password" required minlength="8">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="password_confirm">Confirmar contrasena</label>
                        <input class="form-control" type="password" id="password_confirm" name="password_confirm" required minlength="8">
                    </div>
                    <button class="btn btn-brand" type="submit">Actualizar contrasena</button>
                </form>
            </section>
        </div>
    </div>
</main>
</body>
</html>
