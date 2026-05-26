<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Animales</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars(asset('css/global.css')); ?>" rel="stylesheet">
    <link href="<?php echo htmlspecialchars(asset('css/animales.css')); ?>" rel="stylesheet">
</head>
<body>
<?php $titleSafe = isset($title) ? (string) $title : 'Animales'; ?>
<main class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 m-0"><?php echo htmlspecialchars($titleSafe); ?></h1>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/dashboard')); ?>">Volver</a>
    </div>

    <section class="card tvg-card p-3">
        <p class="mb-0">Modulo inicial preparado para especies dinamicas, historial clinico y filtrado por empresa.</p>
    </section>
</main>
<script src="<?php echo htmlspecialchars(asset('js/animales/index.js')); ?>"></script>
</body>
</html>
