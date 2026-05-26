<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recuperar contrasena</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars(asset('css/global.css')); ?>" rel="stylesheet">
</head>
<body>
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
</body>
</html>
