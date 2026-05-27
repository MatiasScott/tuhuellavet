<?php
$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
$isActive = static function (string $path) use ($requestUri): string {
    return strpos($requestUri, $path) !== false ? 'is-active' : '';
};
?>

<aside class="col-12 col-lg-3 col-xl-2">
    <nav class="card tvg-card p-3 tvg-sidebar" id="tvg-sidebar">
        <button class="tvg-sidebar-toggle" type="button" data-sidebar-toggle>
            <i class="bi bi-list"></i>
            <span>Menu</span>
        </button>

        <div class="tvg-sidebar-head mb-2">
            <small class="tvg-sidebar-caption">NAVEGACION</small>
        </div>

        <?php if (auth_can('dashboard.ver')): ?>
            <a class="tvg-nav-link <?php echo $isActive('/dashboard'); ?>" href="<?php echo htmlspecialchars(url('/dashboard')); ?>">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
        <?php endif; ?>

        <?php if (auth_can('consultas.ver') || auth_can('catalogos.ver') || auth_can('hospitalizaciones.ver') || auth_can('cirugias.ver') || auth_can('examenes.ver') || auth_can('formulas.ver') || auth_can('timeline.ver')): ?>
            <details class="tvg-nav-group" open>
                <summary><i class="bi bi-heart-pulse"></i><span>Clinica</span></summary>
                <?php if (auth_can('consultas.ver')): ?><a class="tvg-nav-link <?php echo $isActive('/consultas'); ?>" href="<?php echo htmlspecialchars(url('/consultas')); ?>"><i class="bi bi-clipboard2-pulse"></i><span>Consultas</span></a><?php endif; ?>
                <?php if (auth_can('catalogos.ver')): ?><a class="tvg-nav-link <?php echo $isActive('/diagnosticos'); ?>" href="<?php echo htmlspecialchars(url('/diagnosticos')); ?>"><i class="bi bi-activity"></i><span>Diagnosticos</span></a><?php endif; ?>
                <?php if (auth_can('hospitalizaciones.ver')): ?><a class="tvg-nav-link <?php echo $isActive('/hospitalizaciones'); ?>" href="<?php echo htmlspecialchars(url('/hospitalizaciones')); ?>"><i class="bi bi-hospital"></i><span>Hospitalizacion</span></a><?php endif; ?>
                <?php if (auth_can('cirugias.ver')): ?><a class="tvg-nav-link <?php echo $isActive('/cirugias'); ?>" href="<?php echo htmlspecialchars(url('/cirugias')); ?>"><i class="bi bi-scissors"></i><span>Cirugias</span></a><?php endif; ?>
                <?php if (auth_can('examenes.ver')): ?><a class="tvg-nav-link <?php echo $isActive('/examenes'); ?>" href="<?php echo htmlspecialchars(url('/examenes')); ?>"><i class="bi bi-flask"></i><span>Examenes</span></a><?php endif; ?>
                <?php if (auth_can('formulas.ver')): ?><a class="tvg-nav-link <?php echo $isActive('/formulas'); ?>" href="<?php echo htmlspecialchars(url('/formulas')); ?>"><i class="bi bi-calculator"></i><span>Formulas</span></a><?php endif; ?>
                <?php if (auth_can('timeline.ver')): ?><a class="tvg-nav-link <?php echo $isActive('/timeline'); ?>" href="<?php echo htmlspecialchars(url('/timeline')); ?>"><i class="bi bi-clock-history"></i><span>Timeline</span></a><?php endif; ?>
            </details>
        <?php endif; ?>

        <?php if (auth_can('propietarios.ver') || auth_can('pacientes.ver')): ?>
            <details class="tvg-nav-group" open>
                <summary><i class="bi bi-people"></i><span>Pacientes y clientes</span></summary>
                <?php if (auth_can('propietarios.ver')): ?><a class="tvg-nav-link <?php echo $isActive('/propietarios'); ?>" href="<?php echo htmlspecialchars(url('/propietarios')); ?>"><i class="bi bi-person-vcard"></i><span>Propietarios</span></a><?php endif; ?>
                <?php if (auth_can('pacientes.ver')): ?><a class="tvg-nav-link <?php echo $isActive('/animales'); ?>" href="<?php echo htmlspecialchars(url('/animales')); ?>"><i class="bi bi-shield-plus"></i><span>Pacientes</span></a><?php endif; ?>
            </details>
        <?php endif; ?>

        <?php if (auth_can('vacunas.ver') || auth_can('desparasitaciones.ver')): ?>
            <details class="tvg-nav-group" open>
                <summary><i class="bi bi-shield-check"></i><span>Prevencion</span></summary>
                <?php if (auth_can('vacunas.ver')): ?><a class="tvg-nav-link <?php echo $isActive('/vacunas'); ?>" href="<?php echo htmlspecialchars(url('/vacunas')); ?>"><i class="bi bi-droplet-half"></i><span>Vacunas</span></a><?php endif; ?>
                <?php if (auth_can('desparasitaciones.ver')): ?><a class="tvg-nav-link <?php echo $isActive('/desparasitaciones'); ?>" href="<?php echo htmlspecialchars(url('/desparasitaciones')); ?>"><i class="bi bi-bug"></i><span>Desparasitaciones</span></a><?php endif; ?>
            </details>
        <?php endif; ?>

        <?php if (auth_can('usuarios.ver') || auth_can('roles.ver') || auth_can('permisos.ver') || auth_can('empresas.ver') || auth_can('catalogos.ver') || auth_can('inventario.ver')): ?>
            <details class="tvg-nav-group" open>
                <summary><i class="bi bi-gear"></i><span>Administracion</span></summary>
                <?php if (auth_can('usuarios.ver')): ?><a class="tvg-nav-link <?php echo $isActive('/usuarios'); ?>" href="<?php echo htmlspecialchars(url('/usuarios')); ?>"><i class="bi bi-person-gear"></i><span>Usuarios</span></a><?php endif; ?>
                <?php if (auth_can('roles.ver')): ?><a class="tvg-nav-link <?php echo $isActive('/roles'); ?>" href="<?php echo htmlspecialchars(url('/roles')); ?>"><i class="bi bi-person-badge"></i><span>Roles</span></a><?php endif; ?>
                <?php if (auth_can('permisos.ver')): ?><a class="tvg-nav-link <?php echo $isActive('/permisos'); ?>" href="<?php echo htmlspecialchars(url('/permisos')); ?>"><i class="bi bi-shield-lock"></i><span>Permisos</span></a><?php endif; ?>
                <?php if (auth_can('empresas.ver')): ?><a class="tvg-nav-link <?php echo $isActive('/empresas'); ?>" href="<?php echo htmlspecialchars(url('/empresas')); ?>"><i class="bi bi-buildings"></i><span>Empresas</span></a><?php endif; ?>
                <?php if (auth_can('catalogos.ver')): ?><a class="tvg-nav-link <?php echo $isActive('/especies'); ?>" href="<?php echo htmlspecialchars(url('/especies')); ?>"><i class="bi bi-diagram-3"></i><span>Especies</span></a><?php endif; ?>
                <?php if (auth_can('catalogos.ver')): ?><a class="tvg-nav-link <?php echo $isActive('/razas'); ?>" href="<?php echo htmlspecialchars(url('/razas')); ?>"><i class="bi bi-list-nested"></i><span>Razas</span></a><?php endif; ?>
                <?php if (auth_can('catalogos.ver')): ?><a class="tvg-nav-link <?php echo $isActive('/categorias-animales'); ?>" href="<?php echo htmlspecialchars(url('/categorias-animales')); ?>"><i class="bi bi-tags"></i><span>Categorias animales</span></a><?php endif; ?>
                <?php if (auth_can('inventario.ver')): ?><a class="tvg-nav-link <?php echo $isActive('/medicamentos'); ?>" href="<?php echo htmlspecialchars(url('/medicamentos')); ?>"><i class="bi bi-capsule"></i><span>Medicamentos</span></a><?php endif; ?>
                <?php if (auth_can('catalogos.ver')): ?><a class="tvg-nav-link <?php echo $isActive('/laboratorios'); ?>" href="<?php echo htmlspecialchars(url('/laboratorios')); ?>"><i class="bi bi-bezier2"></i><span>Laboratorios</span></a><?php endif; ?>
                <?php if (auth_can('catalogos.ver')): ?><a class="tvg-nav-link <?php echo $isActive('/tipos-examen'); ?>" href="<?php echo htmlspecialchars(url('/tipos-examen')); ?>"><i class="bi bi-clipboard2-data"></i><span>Tipos examen</span></a><?php endif; ?>
            </details>
        <?php endif; ?>

        <?php if (auth_can('auditoria.ver')): ?>
            <a class="tvg-nav-link <?php echo $isActive('/auditoria'); ?>" href="<?php echo htmlspecialchars(url('/auditoria')); ?>">
                <i class="bi bi-journal-text"></i>
                <span>Auditoria</span>
            </a>
        <?php endif; ?>
    </nav>
</aside>
