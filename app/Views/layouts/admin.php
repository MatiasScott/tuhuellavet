<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title ?? 'Vet Academic ERP') ?></title>
    <link rel="stylesheet" href="<?= asset('css/global.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/components.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/layouts.css') ?>">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand"><div class="brand-mark">🐾</div><div><strong>Vet Academic</strong><span>Clínica & Docencia</span></div></div>
        <nav class="sidebar-nav">
            <a href="<?= url('/dashboard') ?>" class="nav-item">🏠 <span>Inicio</span></a>

            <div class="nav-label">Clínica</div>
            <?php if (can('pacientes.ver')): ?><a href="<?= url('/pacientes') ?>" class="nav-item">🐾 <span>Pacientes</span></a><?php endif; ?>
            <?php if (can('propietarios.ver')): ?><a href="<?= url('/propietarios') ?>" class="nav-item">👥 <span>Propietarios</span></a><?php endif; ?>
            <?php if (can('consultas.ver')): ?><a href="#" class="nav-item">🩺 <span>Consultas</span></a><?php endif; ?>
            <?php if (can('vacunas.ver')): ?><a href="#" class="nav-item">💉 <span>Vacunación</span></a><?php endif; ?>
            <?php if (can('laboratorio.ver')): ?><a href="#" class="nav-item">🧪 <span>Laboratorio</span></a><?php endif; ?>
            <?php if (can('hospitalizacion.ver')): ?><a href="#" class="nav-item">🏥 <span>Hospitalización</span></a><?php endif; ?>
            <?php if (can('cirugias.ver')): ?><a href="#" class="nav-item">✂️ <span>Cirugías</span></a><?php endif; ?>
            <?php if (can('tratamientos.ver')): ?><a href="#" class="nav-item">💊 <span>Tratamientos</span></a><?php endif; ?>

            <div class="nav-label">Gestión</div>
            <?php if (can('formulas.ver')): ?><a href="#" class="nav-item">🧮 <span>Fórmulas</span></a><?php endif; ?>
            <?php if (can('inventario.ver')): ?><a href="#" class="nav-item">📦 <span>Inventario</span></a><?php endif; ?>
            <?php if (can('citas.ver')): ?><a href="#" class="nav-item">📅 <span>Agenda</span></a><?php endif; ?>

            <?php if (can('academico.ver')): ?><div class="nav-label">Académico</div><a href="#" class="nav-item">🎓 <span>Docencia</span></a><?php endif; ?>

            <?php if (can('usuarios.ver') || can('roles.ver') || can('permisos.ver') || can('auditoria.ver')): ?>
                <div class="nav-label">Administración</div>
                <?php if (can('usuarios.ver')): ?><a href="#" class="nav-item">👤 <span>Usuarios</span></a><?php endif; ?>
                <?php if (can('roles.ver')): ?><a href="#" class="nav-item">🛡️ <span>Roles</span></a><?php endif; ?>
                <?php if (can('permisos.ver')): ?><a href="#" class="nav-item">🔐 <span>Permisos</span></a><?php endif; ?>
                <?php if (can('auditoria.ver')): ?><a href="#" class="nav-item">📊 <span>Auditoría</span></a><?php endif; ?>
            <?php endif; ?>
        </nav>
    </aside>

    <main class="main-shell">
        <header class="topbar">
            <button class="icon-button" data-sidebar-toggle aria-label="Menú">☰</button>
            <div class="topbar-spacer"></div>
            <a href="<?= url('/seleccionar-entorno') ?>" class="environment-switcher"><span class="environment-dot"></span><span><?= e(active_environment()['nombre'] ?? 'Seleccionar entorno') ?></span><span>↔</span></a>
            <div class="user-chip"><div class="avatar">👤</div><div><strong><?= e(auth_user()['name'] ?? 'Usuario') ?></strong><span><?= e(implode(', ',auth_user()['roles'] ?? [])) ?></span></div></div>
            <form action="<?= url('/logout') ?>" method="POST"><?= csrf_field() ?><button class="btn btn-ghost" type="submit">Salir</button></form>
        </header>
        <section class="page-content"><?= $content ?></section>
    </main>
</div>
<script src="<?= asset('js/global.js') ?>"></script>
<script src="<?= asset('js/helpers.js') ?>"></script>
</body>
</html>
