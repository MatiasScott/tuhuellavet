<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($title ?? 'Mi portal') ?></title>
    <link rel="stylesheet" href="<?= asset('css/global.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/components.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/layouts.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/searchable-selects.css') ?>">
    <link
        rel="icon"
        type="image/png"
        href="<?= url('/assets/img/Logo-tuhuella.png') ?>">
    <link
        rel="apple-touch-icon"
        href="<?= url('/assets/img/Logo-tuhuella.png') ?>">
</head>

<body>
    <header class="topbar"><a class="brand" href="<?= url('/cliente') ?>">
            <div class="brand-mark">🐾</div>
            <div>
                <strong>Tu Huella Vet</strong>
                <span>Portal del cliente</span>
            </div>
        </a>
        <div class="topbar-spacer"></div>
        <a class="environment-switcher" href="<?= url('/seleccionar-entorno') ?>">
            <?= e(active_environment()['nombre'] ?? 'Entorno') ?> ↔
        </a>
        <?php if (count(auth_user()['roles'] ?? []) > 1): ?>
            <a
                href="<?= e(url('/seleccionar-rol')) ?>"
                class="btn btn-ghost"
                title="Cambiar perfil de usuario">
                🔄 Cambiar perfil
            </a>
        <?php endif; ?>
        <form action="<?= url('/logout') ?>" method="POST">
            <?= csrf_field() ?>
            <button class="btn btn-ghost">Salir</button>
        </form>
    </header>
    <main class="portal-shell page-content"><?= $content ?></main>
    <script src="<?= asset('js/global.js') ?>"></script>
    <script src="<?= asset('js/helpers.js') ?>"></script>
    <script src="<?= asset('js/searchable-selects.js') ?>" defer></script>
</body>

</html>