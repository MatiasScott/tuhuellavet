<?php
$requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
$isActive = static function (string $path) use ($requestUri): string {
    return strpos($requestUri, $path) !== false ? 'is-active' : '';
};
?>

<aside class="col-12 col-lg-3 col-xl-2">
    <nav class="card tvg-card p-3 tvg-sidebar tvg-sidebar-invitado" id="tvg-sidebar">
        <button class="tvg-sidebar-toggle" type="button" data-sidebar-toggle>
            <i class="bi bi-list"></i>
            <span>Menu</span>
        </button>

        <div class="tvg-sidebar-head mb-2">
            <small class="tvg-sidebar-caption">CONSULTA BASICA</small>
        </div>

        <a class="tvg-nav-link <?php echo $isActive('/dashboard'); ?>" href="<?php echo htmlspecialchars(url('/dashboard')); ?>">
            <i class="bi bi-house"></i>
            <span>Inicio</span>
        </a>
        <a class="tvg-nav-link <?php echo $isActive('/password/change'); ?>" href="<?php echo htmlspecialchars(url('/password/change')); ?>">
            <i class="bi bi-shield-lock"></i>
            <span>Seguridad</span>
        </a>
    </nav>
</aside>
