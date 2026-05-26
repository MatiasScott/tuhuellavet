<?php

declare(strict_types=1);

$pageTitleSafe = isset($pageTitle) && is_string($pageTitle) && $pageTitle !== '' ? $pageTitle : 'Tu Huella Vet';
$authBodyClassSafe = isset($authBodyClass) && is_string($authBodyClass) ? trim($authBodyClass) : 'auth-page';
$extraCssSafe = isset($authExtraCss) && is_array($authExtraCss) ? $authExtraCss : [];
$authContentSafe = isset($authContent) && is_string($authContent) ? $authContent : '';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitleSafe); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars(asset('css/global.css')); ?>" rel="stylesheet">
    <?php foreach ($extraCssSafe as $cssPath): ?>
        <?php if (is_string($cssPath) && $cssPath !== ''): ?>
            <link href="<?php echo htmlspecialchars($cssPath); ?>" rel="stylesheet">
        <?php endif; ?>
    <?php endforeach; ?>
</head>
<body class="<?php echo htmlspecialchars($authBodyClassSafe); ?>">
<?php echo $authContentSafe; ?>
</body>
</html>