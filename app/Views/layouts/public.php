<?php

declare(strict_types=1);

$pageTitle = $pageTitle ?? 'Axxis | Tu Huella Vet';
$pageDescription = $pageDescription
    ?? 'Axxis es la plataforma de gestión de Tu Huella Vet y Hacienda Agusbella.';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0">

    <meta
        name="description"
        content="<?= htmlspecialchars(
                        $pageDescription,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>">

    <meta name="robots" content="index,follow">

    <title>
        <?= htmlspecialchars(
            $pageTitle,
            ENT_QUOTES,
            'UTF-8'
        ) ?>
    </title>

    <link
        rel="stylesheet"
        href="<?= url('/assets/css/views/public.css') ?>">
</head>

<body>

    <header class="public-header">
        <div class="public-container header-inner">

            <a
                href="<?= url('/') ?>"
                class="brand"
                aria-label="Axxis - Página principal">
                <span class="brand-mark">A</span>

                <span class="brand-copy">
                    <strong>Axxis</strong>
                    <small>Tu Huella Vet · Hacienda Agusbella</small>
                </span>
            </a>

            <nav class="public-nav" aria-label="Navegación principal">
                <a href="<?= url('/') ?>">
                    Inicio
                </a>

                <a href="<?= url('/privacidad') ?>">
                    Privacidad
                </a>

                <a href="<?= url('/terminos') ?>">
                    Términos
                </a>

                <a
                    href="<?= url('/login') ?>"
                    class="nav-login">
                    Iniciar sesión
                </a>
            </nav>

        </div>
    </header>

    <main>
        <?= $content ?? '' ?>
    </main>

    <footer class="public-footer">
        <div class="public-container footer-grid">

            <div>
                <strong class="footer-brand">Axxis</strong>

                <p>
                    Plataforma de gestión de Tu Huella Vet
                    y Hacienda Agusbella.
                </p>
            </div>

            <div class="footer-links">
                <a href="<?= url('/privacidad') ?>">
                    Política de privacidad
                </a>

                <a href="<?= url('/terminos') ?>">
                    Términos y condiciones
                </a>

                <a href="<?= url('/login') ?>">
                    Acceso al sistema
                </a>
            </div>

        </div>

        <div class="public-container footer-bottom">
            <span>
                &copy; <?= date('Y') ?> Tu Huella Vet.
                Todos los derechos reservados.
            </span>
        </div>
    </footer>

</body>

</html>