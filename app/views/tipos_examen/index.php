<?php
ob_start();
?>
<?php
$pageTitle = 'Tipos de examen';
$tiposSafe = isset($tipos) && is_array($tipos) ? $tipos : [];
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$successSafe = isset($success) ? (string) $success : '';
$errorSafe = isset($error) ? (string) $error : '';
?>

<main class="container py-4">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <h1 class="h4 m-0">Tipos de examen</h1>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/dashboard')); ?>">Volver</a>
    </div>

    <?php if ($successSafe !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($successSafe); ?></div><?php endif; ?>
    <?php if ($errorSafe !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($errorSafe); ?></div><?php endif; ?>

    <section class="card tvg-card p-3 mb-4">
        <h2 class="h6">Estandarizar tipo de examen</h2>
        <p class="text-muted small mb-2">Este modulo reutiliza valores de la tabla examenes_laboratorio. No crea catalogo paralelo.</p>
        <form method="post" action="<?php echo htmlspecialchars(url('/tipos-examen/renombrar')); ?>" class="row g-2">
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
            <div class="col-md-5"><input class="form-control" name="old_name" placeholder="Tipo actual" required></div>
            <div class="col-md-5"><input class="form-control" name="new_name" placeholder="Tipo nuevo" required></div>
            <div class="col-md-2"><button class="btn btn-brand w-100" type="submit">Renombrar</button></div>
        </form>
    </section>

    <section class="card tvg-card p-3">
        <h2 class="h6 mb-3">Valores detectados</h2>
        <div class="table-responsive">
            <table class="table tvg-table table-hover mb-0">
                <thead><tr><th>Tipo examen</th><th>Total usos</th></tr></thead>
                <tbody>
                <?php foreach ($tiposSafe as $t): ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string) ($t['tipo_examen'] ?? '')); ?></td>
                        <td><?php echo (int) ($t['total_usos'] ?? 0); ?></td>
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
