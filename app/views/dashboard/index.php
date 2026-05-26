<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo htmlspecialchars(asset('css/global.css')); ?>" rel="stylesheet">
    <link href="<?php echo htmlspecialchars(asset('css/dashboard.css')); ?>" rel="stylesheet">
</head>
<body>
<?php
$userSafe = isset($user) && is_array($user) ? $user : [];
$empresaIdSafe = isset($empresaId) ? (int) $empresaId : 0;
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
?>
<header class="tvg-header py-3 mb-4">
    <div class="container d-flex justify-content-between align-items-center">
        <h1 class="h5 m-0">Tu Huella Vet</h1>
        <form action="<?php echo htmlspecialchars(url('/logout')); ?>" method="post">
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
            <button class="btn btn-sm btn-outline-light" type="submit">Cerrar sesion</button>
        </form>
    </div>
</header>
<main class="container">
    <section class="row g-3">
        <div class="col-12 col-lg-8">
            <article class="card tvg-card p-4">
                <h2 class="h5">Bienvenido</h2>
                <p class="mb-1">Usuario: <?php echo htmlspecialchars((string) ($userSafe['nombre'] ?? '')); ?></p>
                <p class="mb-0">Empresa activa ID: <?php echo $empresaIdSafe; ?></p>
            </article>
        </div>
        <div class="col-12 col-lg-4">
            <article class="card tvg-card p-4">
                <h3 class="h6">Accesos rapidos</h3>
                <a class="btn btn-brand w-100 mt-2" href="<?php echo htmlspecialchars(url('/propietarios')); ?>">Propietarios / Clientes</a>
                <a class="btn btn-brand w-100 mt-2" href="<?php echo htmlspecialchars(url('/animales')); ?>">Pacientes / Animales</a>
                <a class="btn btn-brand w-100 mt-2" href="<?php echo htmlspecialchars(url('/consultas')); ?>">Consulta externa</a>
                <a class="btn btn-brand w-100 mt-2" href="<?php echo htmlspecialchars(url('/diagnosticos')); ?>">Diagnosticos</a>
                <a class="btn btn-brand w-100 mt-2" href="<?php echo htmlspecialchars(url('/vacunas')); ?>">Vacunas</a>
                <a class="btn btn-brand w-100 mt-2" href="<?php echo htmlspecialchars(url('/desparasitaciones')); ?>">Desparasitaciones</a>
            </article>
        </div>
    </section>
</main>
<script src="<?php echo htmlspecialchars(asset('js/dashboard.js')); ?>"></script>
</body>
</html>
