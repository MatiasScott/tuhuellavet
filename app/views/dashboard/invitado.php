<?php
ob_start();
?>
<?php

$pageTitle = 'Acceso de consulta';
?>
<?php
$userSafe = isset($user) && is_array($user) ? $user : [];
$rolNombreSafe = isset($rolNombre) ? (string) $rolNombre : 'Invitado';
$pageStyles = [asset('css/dashboard.css')];
?>

<main class="container py-4">
    <section class="card tvg-card p-4">
        <h1 class="h4 mb-2">Acceso de consulta</h1>
        <p class="text-muted mb-3"><?php echo htmlspecialchars((string) ($userSafe['nombre'] ?? 'Usuario')); ?>, tu acceso esta limitado a informacion basica del sistema.</p>
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <div class="tvg-kpi-card">
                    <p class="tvg-kpi-label">Rol</p>
                    <p class="tvg-kpi-value"><?php echo htmlspecialchars($rolNombreSafe); ?></p>
                </div>
            </div>
            <div class="col-12 col-md-8">
                <div class="tvg-kpi-card h-100">
                    <p class="tvg-kpi-label">Navegacion</p>
                    <p class="mb-0">Solo puedes revisar tu perfil y cambiar tu contrasena. Si necesitas acceso completo, contacta al administrador del sistema.</p>
                </div>
            </div>
        </div>
    </section>
</main>

<?php
$pageContent = ob_get_clean();
require BASE_PATH . "/app/views/layaouts/app.php";
