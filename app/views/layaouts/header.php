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
<script>window.TVG_BASE_URL = <?php echo json_encode(url('/')); ?>;</script>
<?php
$sessionUser = \App\Core\Session::get((string) config('auth.session_key'));
$companySessionKey = (string) config('auth.company_session_key');
$currentCompanyId = (int) \App\Core\Session::get($companySessionKey, 0);
$headerCompanies = [];
$headerCsrf = \App\Core\Csrf::token((int) config('auth.csrf_token_ttl', 3600));

if (is_array($sessionUser) && (int) ($sessionUser['id'] ?? 0) > 0) {
    $headerCompanies = (new \App\Services\AuthService())->userCompanies((int) $sessionUser['id']);
}

$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/dashboard');
$requestPath = (string) (parse_url($requestUri, PHP_URL_PATH) ?? '/dashboard');
$requestQuery = (string) (parse_url($requestUri, PHP_URL_QUERY) ?? '');
$basePath = base_url_path();

if ($basePath !== '' && str_starts_with($requestPath, $basePath . '/')) {
    $requestPath = substr($requestPath, strlen($basePath));
}

if ($basePath !== '' && $requestPath === $basePath) {
    $requestPath = '/';
}

if ($requestPath === '' || $requestPath[0] !== '/') {
    $requestPath = '/dashboard';
}

$redirectToSafe = $requestPath . ($requestQuery !== '' ? ('?' . $requestQuery) : '');
?>
<header class="tvg-header py-3 mb-4">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h5 m-0 tvg-brand-title">Tu Huella Vet</h1>
            <small class="tvg-brand-subtitle">Clinica y gestion integral</small>
        </div>
        <?php if (is_array($sessionUser)): ?>
            <div class="d-flex align-items-center gap-2">
                <?php if ($headerCompanies !== []): ?>
                    <form action="<?php echo htmlspecialchars(url('/empresa/cambiar')); ?>" method="post" class="m-0 d-flex align-items-center gap-2">
                        <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($headerCsrf); ?>">
                        <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($redirectToSafe); ?>">
                        <select class="form-select form-select-sm tvg-company-switch" name="empresa_id" onchange="this.form.submit()">
                            <?php foreach ($headerCompanies as $company): ?>
                                <?php $companyId = (int) ($company['id'] ?? 0); ?>
                                <option value="<?php echo $companyId; ?>" <?php echo $companyId === $currentCompanyId ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars((string) ($company['nombre'] ?? 'Empresa')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                <?php endif; ?>

                <form action="<?php echo htmlspecialchars(url('/logout')); ?>" method="post" class="m-0">
                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($headerCsrf); ?>">
                    <button class="btn btn-sm btn-outline-light" type="submit"><i class="bi bi-box-arrow-right me-1"></i>Cerrar sesion</button>
                </form>
            </div>
        <?php else: ?>
            <a class="btn btn-sm btn-outline-light" href="<?php echo htmlspecialchars(url('/dashboard')); ?>"><i class="bi bi-speedometer2 me-1"></i>Dashboard</a>
        <?php endif; ?>
    </div>
</header>
<div class="container-fluid">
    <div class="row g-3">
