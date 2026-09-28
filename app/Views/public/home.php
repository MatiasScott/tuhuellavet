<?php

declare(strict_types=1);

$pageTitle = 'Axxis | Gestión veterinaria y animal';
$pageDescription =
    'Axxis es la plataforma de gestión utilizada por '
    . 'Tu Huella Vet y Hacienda Agusbella.';
?>

<section class="hero">
    <div class="public-container hero-grid">

        <div class="hero-content">

            <span class="eyebrow">
                Tu Huella Vet · Hacienda Agusbella
            </span>

            <h1>
                Gestión veterinaria y animal
                <span>desde una sola plataforma.</span>
            </h1>

            <p class="hero-description">
                Axxis centraliza la gestión clínica veterinaria,
                el seguimiento de animales, inventario,
                servicios y procesos administrativos en un
                entorno seguro y organizado.
            </p>

            <div class="hero-actions">
                <a
                    href="<?= url('/login') ?>"
                    class="button button-primary">
                    Iniciar sesión
                </a>

                <a
                    href="#plataforma"
                    class="button button-secondary">
                    Conocer Axxis
                </a>
            </div>

            <p class="access-note">
                El acceso está disponible únicamente para
                usuarios autorizados.
            </p>

        </div>

        <div class="hero-panel">

            <div class="hero-panel-header">
                <span class="status-dot"></span>
                Plataforma Axxis
            </div>

            <div class="hero-stat">
                <span>Clínica</span>
                <strong>Tu Huella Vet</strong>
            </div>

            <div class="hero-stat">
                <span>Gestión animal</span>
                <strong>Hacienda Agusbella</strong>
            </div>

            <div class="hero-stat">
                <span>Formación</span>
                <strong>Entorno académico</strong>
            </div>

        </div>

    </div>
</section>


<section class="features" id="plataforma">
    <div class="public-container">

        <div class="section-heading">
            <span class="eyebrow">La plataforma</span>

            <h2>
                Una solución para diferentes áreas de gestión
            </h2>

            <p>
                Cada entorno mantiene sus procesos y datos
                organizados de acuerdo con las necesidades
                operativas de la organización.
            </p>
        </div>

        <div class="feature-grid">

            <article class="feature-card">
                <div class="feature-icon">01</div>

                <h3>Clínica veterinaria</h3>

                <p>
                    Gestión de propietarios, pacientes,
                    consultas, vacunación, desparasitación,
                    hospitalización y otros procedimientos
                    clínicos.
                </p>
            </article>

            <article class="feature-card">
                <div class="feature-icon">02</div>

                <h3>Hacienda</h3>

                <p>
                    Registro y seguimiento de animales dentro
                    de un entorno independiente para la
                    operación de Hacienda Agusbella.
                </p>
            </article>

            <article class="feature-card">
                <div class="feature-icon">03</div>

                <h3>Gestión administrativa</h3>

                <p>
                    Herramientas para inventario, citas,
                    servicios, ventas, reportes y procesos
                    administrativos relacionados.
                </p>
            </article>

            <article class="feature-card">
                <div class="feature-icon">04</div>

                <h3>Entorno académico</h3>

                <p>
                    Espacio independiente orientado a docentes
                    y estudiantes para actividades académicas
                    y prácticas.
                </p>
            </article>

        </div>

    </div>
</section>


<section class="about-section">
    <div class="public-container about-grid">

        <div>
            <span class="eyebrow">Sobre Axxis</span>

            <h2>
                Información organizada, acceso controlado
            </h2>
        </div>

        <div class="about-copy">
            <p>
                Axxis es una plataforma privada de gestión
                utilizada por Tu Huella Vet y Hacienda
                Agusbella para apoyar sus procesos clínicos,
                operativos y administrativos.
            </p>

            <p>
                El acceso a las funcionalidades internas
                requiere una cuenta previamente autorizada.
                Los permisos disponibles dependen del rol y
                del entorno asignado a cada usuario.
            </p>
        </div>

    </div>
</section>


<section class="google-section">
    <div class="public-container google-card">

        <div>
            <span class="eyebrow">Acceso seguro</span>

            <h2>Inicio de sesión con Google</h2>

            <p>
                Axxis permite utilizar una cuenta de Google
                como mecanismo de autenticación para usuarios
                previamente autorizados en la plataforma.
            </p>

            <p>
                La información básica proporcionada por Google
                se utiliza para identificar al usuario y
                gestionar su acceso a Axxis.
            </p>
        </div>

        <div>
            <a
                href="<?= url('/login') ?>"
                class="button button-primary">
                Ir al inicio de sesión
            </a>
        </div>

    </div>
</section>