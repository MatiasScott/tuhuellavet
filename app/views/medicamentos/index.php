<?php
ob_start();
?>
<?php
$pageTitle = 'Medicamentos';
$medicamentosSafe = isset($medicamentos) && is_array($medicamentos) ? $medicamentos : [];
$csrfTokenSafe = isset($csrfToken) ? (string) $csrfToken : '';
$successSafe = isset($success) ? (string) $success : '';
$errorSafe = isset($error) ? (string) $error : '';
?>

<main class="container py-4">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-3">
        <h1 class="h4 m-0">Medicamentos</h1>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/dashboard')); ?>">Volver</a>
    </div>

    <?php if ($successSafe !== ''): ?><div class="alert alert-success"><?php echo htmlspecialchars($successSafe); ?></div><?php endif; ?>
    <?php if ($errorSafe !== ''): ?><div class="alert alert-danger"><?php echo htmlspecialchars($errorSafe); ?></div><?php endif; ?>

    <section class="card tvg-card p-3 mb-4">
        <h2 class="h6">Crear medicamento</h2>
        <form method="post" action="<?php echo htmlspecialchars(url('/medicamentos/crear')); ?>" enctype="multipart/form-data" class="row g-2">
            <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
            <div class="col-md-4"><input class="form-control" name="nombre" placeholder="Nombre" required></div>
            <div class="col-md-2"><input class="form-control" name="concentracion" placeholder="Concentracion"></div>
            <div class="col-md-2"><input class="form-control" name="unidad" placeholder="Unidad"></div>
            <div class="col-md-2"><input class="form-control" type="number" step="0.01" name="stock_actual" placeholder="Stock"></div>
            <div class="col-md-2"><input class="form-control" type="file" name="foto" accept=".jpg,.jpeg,.png,.webp"></div>
            <div class="col-md-10"><input class="form-control" name="descripcion" placeholder="Descripcion"></div>
            <div class="col-md-2 d-flex align-items-center"><label><input type="checkbox" name="estado" value="1" checked> Activo</label></div>
            <div class="col-md-2"><button class="btn btn-brand w-100" type="submit">Crear</button></div>
        </form>
    </section>

    <section class="card tvg-card p-3">
        <h2 class="h6 mb-3">Medicamentos registrados</h2>
        <div class="table-responsive">
            <table class="table tvg-table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Foto</th>
                        <th>Nombre</th>
                        <th>Concentracion</th>
                        <th>Unidad</th>
                        <th>Stock</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($medicamentosSafe as $m): ?>
                    <?php $foto = trim((string) ($m['foto'] ?? '')); ?>
                    <?php $fotoUrl = $foto !== '' ? url('/imagen/ver?path=' . rawurlencode($foto)) : ''; ?>
                    <tr>
                        <td><?php echo (int) ($m['id'] ?? 0); ?></td>
                        <td>
                            <?php if ($fotoUrl !== ''): ?>
                                <img src="<?php echo htmlspecialchars($fotoUrl); ?>" alt="Foto medicamento" style="width:40px;height:40px;object-fit:cover;border-radius:8px;">
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars((string) ($m['nombre'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($m['concentracion'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($m['unidad'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars((string) ($m['stock_actual'] ?? '0')); ?></td>
                        <td><span class="badge tvg-badge <?php echo ((int) ($m['estado'] ?? 0) === 1) ? 'tvg-badge-success' : 'tvg-badge-muted'; ?>"><?php echo ((int) ($m['estado'] ?? 0) === 1) ? 'Activo' : 'Inactivo'; ?></span></td>
                        <td class="text-end">
                            <details>
                                <summary class="btn btn-sm btn-outline-primary">Editar</summary>
                                <form method="post" action="<?php echo htmlspecialchars(url('/medicamentos/actualizar')); ?>" enctype="multipart/form-data" class="row g-2 mt-2 text-start" style="min-width:420px;">
                                    <input type="hidden" name="_csrf_token" value="<?php echo htmlspecialchars($csrfTokenSafe); ?>">
                                    <input type="hidden" name="id" value="<?php echo (int) ($m['id'] ?? 0); ?>">
                                    <div class="col-md-6"><input class="form-control form-control-sm" name="nombre" value="<?php echo htmlspecialchars((string) ($m['nombre'] ?? '')); ?>" required></div>
                                    <div class="col-md-3"><input class="form-control form-control-sm" name="concentracion" value="<?php echo htmlspecialchars((string) ($m['concentracion'] ?? '')); ?>"></div>
                                    <div class="col-md-3"><input class="form-control form-control-sm" name="unidad" value="<?php echo htmlspecialchars((string) ($m['unidad'] ?? '')); ?>"></div>
                                    <div class="col-md-4"><input class="form-control form-control-sm" type="number" step="0.01" name="stock_actual" value="<?php echo htmlspecialchars((string) ($m['stock_actual'] ?? '0')); ?>"></div>
                                    <div class="col-md-4"><input class="form-control form-control-sm" type="file" name="foto" accept=".jpg,.jpeg,.png,.webp"></div>
                                    <div class="col-md-4 d-flex align-items-center"><label><input type="checkbox" name="estado" value="1" <?php echo ((int) ($m['estado'] ?? 0) === 1) ? 'checked' : ''; ?>> Activo</label></div>
                                    <div class="col-md-12"><input class="form-control form-control-sm" name="descripcion" value="<?php echo htmlspecialchars((string) ($m['descripcion'] ?? '')); ?>"></div>
                                    <div class="col-md-12"><button class="btn btn-sm btn-outline-primary" type="submit">Guardar cambios</button></div>
                                </form>
                            </details>
                        </td>
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
