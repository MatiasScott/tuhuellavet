<?php

use App\Core\Session;

$roleLabels = [
    'SUPER_ADMINISTRADOR' => 'Superadministrador',
    'ADMINISTRADOR' => 'Administrador',
    'CLIENTE' => 'Cliente',
    'DOCENTE' => 'Docente',
    'ESTUDIANTE' => 'Estudiante',
    'INVITADO' => 'Invitado',
];

$roleDescriptions = [
    'SUPER_ADMINISTRADOR' => 'Administración general del sistema.',
    'ADMINISTRADOR' => 'Gestión administrativa y operativa.',
    'CLIENTE' => 'Consulta y gestión de tus mascotas.',
    'DOCENTE' => 'Acceso a las funciones docentes.',
    'ESTUDIANTE' => 'Acceso a las funciones estudiantiles.',
    'INVITADO' => 'Acceso de consulta.',
];

$roleIcons = [
    'SUPER_ADMINISTRADOR' => 'bi-shield-lock',
    'ADMINISTRADOR' => 'bi-person-gear',
    'CLIENTE' => 'bi-heart-pulse',
    'DOCENTE' => 'bi-mortarboard',
    'ESTUDIANTE' => 'bi-book',
    'INVITADO' => 'bi-eye',
];

$error = Session::pullFlash('error');
?>

<style>
    .role-selector {
        width: 100%;
        max-width: 780px;
        margin: 35px auto;
        padding: 20px;
    }

    .role-selector-header {
        text-align: center;
        margin-bottom: 30px;
    }

    .role-selector-header h2 {
        font-weight: 700;
        margin-bottom: 10px;
    }

    .role-selector-header p {
        color: #6c757d;
    }

    .role-selector-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 18px;
    }

    .role-card {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
        width: 100%;
        padding: 30px 20px;
        border: 2px solid #e5e7eb;
        border-radius: 16px;
        background: #fff;
        cursor: pointer;
        transition: all .2s ease;
        color: #263238;
        text-align: center;
        font: inherit;
    }

    .role-card:hover,
    .role-card:focus-visible {
        border-color: #008b9e;
        background: #f0fafb;
        transform: translateY(-3px);
        box-shadow: 0 8px 24px rgba(0, 139, 158, .12);
    }

    .role-card i {
        font-size: 42px;
        color: #008b9e;
    }

    .role-card strong {
        font-size: 18px;
    }

    .role-card small {
        color: #6c757d;
        line-height: 1.5;
    }

    .role-card-active {
        border-color: #309e8f;
    }

    .role-active-label {
        font-size: 12px;
        color: #309e8f;
        font-weight: 600;
    }
</style>

<div class="role-selector">

    <div class="role-selector-header">
        <h2>Selecciona tu perfil</h2>

        <p>
            Elige el perfil con el que deseas trabajar en
            <strong>
                <?= e($environment['nombre'] ?? 'Axxis') ?>
            </strong>
        </p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($roles)): ?>

        <div class="alert alert-warning">
            No tienes perfiles disponibles en este entorno.
        </div>

    <?php else: ?>

        <div class="role-selector-grid">

            <?php foreach ($roles as $role): ?>

                <?php
                $label = $roleLabels[$role] ?? ucwords(
                    strtolower(str_replace('_', ' ', $role))
                );

                $description = $roleDescriptions[$role]
                    ?? 'Acceder con este perfil.';

                $icon = $roleIcons[$role] ?? 'bi-person-circle';

                $isActive = $activeRole === $role;
                ?>

                <form
                    method="POST"
                    action="<?= e(url('/seleccionar-rol')) ?>">
                    <?= csrf_field() ?>

                    <input
                        type="hidden"
                        name="role"
                        value="<?= e($role) ?>">

                    <button
                        type="submit"
                        class="role-card <?= $isActive ? 'role-card-active' : '' ?>">
                        <i
                            class="bi <?= e($icon) ?>"
                            aria-hidden="true"></i>

                        <strong><?= e($label) ?></strong>

                        <small><?= e($description) ?></small>

                        <?php if ($isActive): ?>
                            <span class="role-active-label">
                                Perfil actual
                            </span>
                        <?php endif; ?>
                    </button>
                </form>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>