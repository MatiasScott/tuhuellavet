<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($_ENV['APP_NAME'] ?? 'Vet Academic ERP') ?></title>
    <link rel="stylesheet" href="<?= asset('css/global.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/components.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/layouts.css') ?>">
</head>

<body class="auth-body"><?= $content ?>
    <script src="<?= asset('js/global.js') ?>"></script>
</body>

</html>