<?php
ob_start();
?>
<?php
$pageTitle = 'Laboratorios';
$laboratoriosSafe = isset($laboratorios) && is_array($laboratorios) ? $laboratorios : [];
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$successSafe = isset($success) ? (string) $success : '';
$errorSafe = isset($error) ? (string) $error : '';
?>

<main class="container py-4">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <h1 class="h4 m-0">Laboratorios</h1>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/dashboard')); ?>">Volver</a>
    </div>

    <?php if ($successSafe !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($successSafe); ?></div><?php endif; ?>
    <?php if ($errorSafe !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($errorSafe); ?></div><?php endif; ?>

    <section class="card tvg-card p-3 mb-4">
        <h2 class="h6">Estandarizar nombre de laboratorio</h2>
        <p class="text-muted small mb-2">Este modulo reutiliza valores existentes de aplicaciones de vacunas. No crea tabla nueva.</p>
        <form method="post" action="<?php echo htmlspecialchars(url('/laboratorios/renombrar')); ?>" class="row g-2">
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
            <div class="col-md-5"><input class="form-control" name="old_name" placeholder="Nombre actual" required></div>
            <div class="col-md-5"><input class="form-control" name="new_name" placeholder="Nombre nuevo" required></div>
            <div class="col-md-2"><button class="btn btn-brand w-100" type="submit">Renombrar</button></div>
        </form>
    </section>

    <section class="card tvg-card p-3">
        <h2 class="h6 mb-3">Valores detectados</h2>
        <div class="table-responsive">
            <table class="table tvg-table table-hover mb-0">
                <thead><tr><th>Laboratorio</th><th>Total usos</th></tr></thead>
                <tbody>
                <?php foreach ($laboratoriosSafe as $l): ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string) ($l['laboratorio'] ?? '')); ?></td>
                        <td><?php echo (int) ($l['total_usos'] ?? 0); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>

<?php
$pageContent = ob_get_clean();
require BASE_PATH . "/app/views/layaouts/app.php";
?>
