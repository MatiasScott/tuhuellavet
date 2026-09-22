<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($title ?? 'Mi portal') ?></title>
    <link rel="stylesheet" href="<?= asset('css/global.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/components.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/layouts.css') ?>">
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
        <a class="environment-switcher" href="<?= url('/seleccionar-entorno') ?>"><?= e(active_environment()['nombre'] ?? 'Entorno') ?> ↔</a>
        <form action="<?= url('/logout') ?>" method="POST">
            <?= csrf_field() ?>
            <button class="btn btn-ghost">Salir</button>
        </form>
    </header>
    <main class="portal-shell page-content"><?= $content ?></main>
    <script src="<?= asset('js/global.js') ?>"></script>
    <script src="<?= asset('js/helpers.js') ?>"></script>
</body>

</html>