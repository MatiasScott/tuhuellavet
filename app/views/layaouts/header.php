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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars(asset('css/global.css')); ?>" rel="stylesheet">
    <?php if (isset($pageStyles) && is_array($pageStyles)): ?>
        <?php foreach ($pageStyles as $style): ?>
            <?php if (is_string($style) && $style !== ''): ?>
                <link href="<?php echo htmlspecialchars($style); ?>" rel="stylesheet">
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
<header class="tvg-header py-3 mb-4">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h5 m-0 tvg-brand-title">Tu Huella Vet</h1>
            <small class="tvg-brand-subtitle">Clinica y gestion integral</small>
        </div>
        <?php if (isset($csrfTokenSafe) && is_string($csrfTokenSafe) && $csrfTokenSafe !== ''): ?>
            <form action="<?php echo htmlspecialchars(url('/logout')); ?>" method="post" class="m-0">
                <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
                <button class="btn btn-sm btn-outline-light" type="submit"><i class="bi bi-box-arrow-right me-1"></i>Cerrar sesion</button>
            </form>
        <?php else: ?>
            <a class="btn btn-sm btn-outline-light" href="<?php echo htmlspecialchars(url('/dashboard')); ?>"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
        <?php endif; ?>
    </div>
</header>
<div class="container-fluid">
    <div class="row g-3">
