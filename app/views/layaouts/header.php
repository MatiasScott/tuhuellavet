<?php

declare(strict_types=1);

$pageTitleSafe = isset($pageTitle) && is_string($pageTitle) && $pageTitle !== '' ? $pageTitle : 'Tu Huella Vet';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitleSafe); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars(asset('css/global.css')); ?>" rel="stylesheet">
</head>
<body>
<header class="tvg-header py-3 mb-4">
    <div class="container d-flex justify-content-between align-items-center">
        <h1 class="h5 m-0">Tu Huella Vet</h1>
        <?php if (isset($csrfTokenSafe) && is_string($csrfTokenSafe) && $csrfTokenSafe !== ''): ?>
            <form action="<?php echo htmlspecialchars(url('/logout')); ?>" method="post" class="m-0">
                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
                <button class="btn btn-sm btn-outline-light" type="submit">Cerrar sesion</button>
            </form>
        <?php else: ?>
            <a class="btn btn-sm btn-outline-light" href="<?php echo htmlspecialchars(url('/dashboard')); ?>">Dashboard</a>
        <?php endif; ?>
    </div>
</header>
<div class="container-fluid">
    <div class="row g-3">
