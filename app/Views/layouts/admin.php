<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($title ?? 'Tu Huella Vet') ?></title>
    <link rel="stylesheet" href="<?= asset('css/global.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/components.css') ?>">
    <link rel="stylesheet" href="<?= asset('css/layouts.css') ?>">
</head>

<body>
    <div class="app-shell">
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-mark">🐾</div>
                <div>
                    <strong>Tu Huella Vet</strong>
                    <span>Clínica · Hacienda · Academia</span>
                </div>
            </div>
            <nav class="sidebar-nav">
                <a href="<?= url('/dashboard') ?>" class="nav-item">
                    🏠 <span>Inicio</span>
                </a>
                <div class="nav-label">Clínica</div>
                <?php if (can('pacientes.ver')): ?>
                    <a href="<?= url('/pacientes') ?>" class="nav-item">
                        🐾 <span>Pacientes</span>
                    </a>
                <?php endif; ?>
                <?php if (can('propietarios.ver')): ?>
                    <a href="<?= url('/propietarios') ?>" class="nav-item">
                        👥 <span>Propietarios</span>
                    </a>
                <?php endif; ?>
                <?php if (can('consultas.ver')): ?>
                    <a href="<?= url('/consultas') ?>" class="nav-item">
                        🩺 <span>Consultas</span>
                    </a>
                <?php endif; ?>
                <?php if (can('vacunas.ver')): ?>
                    <a href="<?= url('/vacunas') ?>" class="nav-item">
                        💉 <span>Vacunación</span>
                    </a>
                <?php endif; ?>
                <?php if (can('desparasitacion.ver')): ?>
                    <a href="<?= url('/desparasitaciones') ?>" class="nav-item">
                        💊 <span>Desparasitación</span>
                    </a>
                <?php endif; ?>
                <?php if (can('peluqueria.ver')): ?>
                    <a href="<?= url('/peluquerias') ?>" class="nav-item">
                        🛁 <span>Peluquería</span>
                    </a>
                <?php endif; ?>
                <?php if (can('hospitalizacion.ver')): ?>
                    <a href="<?= url('/hospitalizaciones') ?>" class="nav-item">
                        🏥 <span>Hospitalización</span>
                    </a>
                <?php endif; ?>
                <?php if (can('laboratorio.ver')): ?>
                    <a href="<?= url('/laboratorio') ?>" class="nav-item">
                        🧪 <span>Laboratorio</span>
                    </a>
                <?php endif; ?>
                <?php if (can('cirugias.ver')): ?>
                    <a href="<?= url('/cirugias') ?>" class="nav-item">
                        ✂️ <span>Cirugías</span>
                    </a>
                <?php endif; ?>
                <div class="nav-label">Gestión</div>
                <?php if (can('formulas.ver')): ?>
                    <a href="<?= url('/formulas') ?>" class="nav-item">
                        🧮 <span>Fórmulas</span>
                    </a>
                <?php endif; ?>
                <?php if (can('inventario.ver')): ?>
                    <a href="<?= url('/inventario') ?>" class="nav-item">
                        📦 <span>Inventario</span>
                    </a>
                <?php endif; ?>
                <?php if (can('citas.ver')): ?>
                    <a href="<?= url('/citas') ?>" class="nav-item">
                        📅 <span>Agenda</span>
                    </a>
                <?php endif; ?>
                <?php if (can('notificaciones.ver')): ?>
                    <a href="<?= url('/notificaciones') ?>" class="nav-item">
                        🔔 <span>Notificaciones</span>
                    </a>
                <?php endif; ?>
                <?php if (can('ventas.ver')): ?>
                    <a href="<?= url('/facturacion') ?>" class="nav-item">
                        💵 <span>Ventas</span>
                    </a>
                <?php endif; ?>
                <?php if (can('reportes.ver')): ?>
                    <a href="<?= url('/reportes') ?>" class="nav-item">
                        📈 <span>Reportes</span>
                    </a>
                <?php endif; ?>
                <?php if (can('academico.ver')): ?>
                    <div class="nav-label">Académico</div>
                    <a href="<?= url('/academico') ?>" class="nav-item">
                        🎓 <span>Aula clínica</span>
                    </a>
                <?php endif; ?>
                <?php if (can('usuarios.ver') || can('roles.ver') || can('auditoria.ver')): ?>
                    <div class="nav-label">Administración</div>
                    <?php if (can('usuarios.ver')): ?>
                        <a href="<?= url('/admin/usuarios') ?>" class="nav-item">👤 <span>Usuarios</span></a>
                    <?php endif; ?>
                    <?php if (can('roles.ver')): ?>
                        <a href="<?= url('/admin/roles') ?>" class="nav-item">🛡️ <span>Roles y permisos</span></a>
                    <?php endif; ?>
                    <?php if (can('empresas.ver')): ?>
                        <a href="<?= url('/admin/empresas') ?>" class="nav-item">🏢 <span>Empresas</span></a>
                    <?php endif; ?>
                    <?php if (can('pacientes.editar')): ?>
                        <a href="<?= url('/admin/catalogos') ?>" class="nav-item">🧩 <span>Catálogos</span></a>
                    <?php endif; ?>
                    <?php if (can('auditoria.ver')): ?>
                        <a href="<?= url('/admin/auditoria') ?>" class="nav-item">📊 <span>Auditoría</span></a>
                    <?php endif; ?>
                <?php endif; ?>
            </nav>
        </aside>
        <main class="main-shell">
            <header class="topbar">
                <button class="icon-button" data-sidebar-toggle>☰</button>
                <div class="topbar-spacer"></div>
                <a href="<?= url('/seleccionar-entorno') ?>" class="environment-switcher">
                    <span class="environment-dot"></span>
                    <span><?= e(active_environment()['nombre'] ?? 'Entorno') ?></span>
                    <span>↔</span>
                </a>
                <div class="user-chip">
                    <div class="avatar">👤</div>
                    <div>
                        <strong><?= e(auth_user()['name'] ?? 'Usuario') ?></strong>
                        <span><?= e(implode(', ', auth_user()['roles'] ?? [])) ?></span>
                    </div>
                </div>
                <form action="<?= url('/logout') ?>" method="POST"><?= csrf_field() ?>
                    <button class="btn btn-ghost">Salir</button>
                </form>
            </header>
            <section class="page-content"><?= $content ?></section>
        </main>
    </div>
    <script src="<?= asset('js/global.js') ?>"></script>
    <script src="<?= asset('js/helpers.js') ?>"></script>
</body>

</html>