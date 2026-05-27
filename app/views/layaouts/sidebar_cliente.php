<?php
$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
$isActive = static function (string $path) use ($requestUri): string {
    return strpos($requestUri, $path) !== false ? 'is-active' : '';
};
?>

<aside class="col-12 col-lg-3 col-xl-2">
    <nav class="card tvg-card p-3 tvg-sidebar tvg-sidebar-cliente" id="tvg-sidebar">
        <button class="tvg-sidebar-toggle" type="button" data-sidebar-toggle>
            <i class="bi bi-list"></i>
            <span>Menu</span>
        </button>

        <div class="tvg-sidebar-head mb-2">
            <small class="tvg-sidebar-caption">PORTAL CLIENTE</small>
        </div>

        <a class="tvg-nav-link <?php echo $isActive('/portal'); ?>" href="<?php echo htmlspecialchars(url('/portal#inicio')); ?>">
            <i class="bi bi-house-heart"></i>
            <span>Inicio</span>
        </a>
        <a class="tvg-nav-link <?php echo $isActive('/portal'); ?>" href="<?php echo htmlspecialchars(url('/portal#mis-mascotas')); ?>">
            <i class="bi bi-emoji-smile"></i>
            <span>Mis Mascotas</span>
        </a>
        <a class="tvg-nav-link <?php echo $isActive('/portal'); ?>" href="<?php echo htmlspecialchars(url('/portal#consultas')); ?>">
            <i class="bi bi-clipboard2-pulse"></i>
            <span>Consultas</span>
        </a>
        <a class="tvg-nav-link <?php echo $isActive('/portal'); ?>" href="<?php echo htmlspecialchars(url('/portal#vacunas')); ?>">
            <i class="bi bi-shield-check"></i>
            <span>Vacunas</span>
        </a>
        <a class="tvg-nav-link <?php echo $isActive('/portal'); ?>" href="<?php echo htmlspecialchars(url('/portal#desparasitacion')); ?>">
            <i class="bi bi-bug"></i>
            <span>Desparasitacion</span>
        </a>
        <a class="tvg-nav-link <?php echo $isActive('/portal'); ?>" href="<?php echo htmlspecialchars(url('/portal#cirugias')); ?>">
            <i class="bi bi-scissors"></i>
            <span>Cirugias</span>
        </a>
        <a class="tvg-nav-link <?php echo $isActive('/portal'); ?>" href="<?php echo htmlspecialchars(url('/portal#documentos')); ?>">
            <i class="bi bi-folder2-open"></i>
            <span>Documentos</span>
        </a>
        <a class="tvg-nav-link <?php echo $isActive('/portal'); ?>" href="<?php echo htmlspecialchars(url('/portal#perfil')); ?>">
            <i class="bi bi-person-badge"></i>
            <span>Perfil</span>
        </a>
    </nav>
</aside>
