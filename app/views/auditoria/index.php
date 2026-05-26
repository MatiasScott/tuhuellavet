<?php
ob_start();
?>
<?php

$pageTitle = 'Auditoria del sistema';
?>
<?php
$rowsSafe = isset($rows) && is_array($rows) ? $rows : [];
$usuariosSafe = isset($usuarios) && is_array($usuarios) ? $usuarios : [];
$empresasSafe = isset($empresas) && is_array($empresas) ? $empresas : [];
$modulosSafe = isset($modulos) && is_array($modulos) ? $modulos : [];
$accionesSafe = isset($acciones) && is_array($acciones) ? $acciones : [];
$filtersSafe = isset($filters) && is_array($filters) ? $filters : [];

$accionBadgeClass = static function (string $accion): string {
    $value = strtolower($accion);
    if (in_array($value, ['crear', 'create', 'login', 'activar'], true)) {
        return 'tvg-badge-success';
    }
    if (in_array($value, ['editar', 'update', 'cambiar'], true)) {
        return 'tvg-badge-muted';
    }
    if (in_array($value, ['eliminar', 'delete', 'logout', 'desactivar'], true)) {
        return 'bg-danger text-white';
    }

    return 'tvg-badge-muted';
};
?>

<main class="container py-4">
    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 m-0">Auditoria del sistema</h1>
            <p class="text-muted m-0">Trazabilidad completa de eventos por modulo, usuario, empresa y accion.</p>
        </div>
        <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/dashboard')); ?>">Volver</a>
    </div>

    <section class="card tvg-card tvg-surface-strong p-3 mb-3">
        <form method="get" action="<?php echo htmlspecialchars(url('/auditoria')); ?>" class="row g-2">
            <div class="col-md-3"><input class="form-control" name="q" placeholder="Buscar..." value="<?php echo htmlspecialchars((string) ($filtersSafe['q'] ?? '')); ?>"></div>
            <div class="col-md-2">
                <select class="form-select" name="usuario_id">
                    <option value="0">Usuario</option>
                    <?php foreach ($usuariosSafe as $usuario): ?>
                        <option value="<?php echo (int) ($usuario['id'] ?? 0); ?>" <?php echo ((int) ($filtersSafe['usuario_id'] ?? 0) === (int) ($usuario['id'] ?? 0)) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($usuario['nombre'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="empresa_id">
                    <option value="0">Empresa</option>
                    <?php foreach ($empresasSafe as $empresa): ?>
                        <option value="<?php echo (int) ($empresa['id'] ?? 0); ?>" <?php echo ((int) ($filtersSafe['empresa_id'] ?? 0) === (int) ($empresa['id'] ?? 0)) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string) ($empresa['nombre'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="modulo">
                    <option value="">Modulo</option>
                    <?php foreach ($modulosSafe as $mod): ?>
                        <?php $value = (string) ($mod['modulo'] ?? ''); ?>
                        <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ((string) ($filtersSafe['modulo'] ?? '') === $value) ? 'selected' : ''; ?>><?php echo htmlspecialchars($value); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select" name="accion">
                    <option value="">Accion</option>
                    <?php foreach ($accionesSafe as $ac): ?>
                        <?php $value = (string) ($ac['accion'] ?? ''); ?>
                        <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ((string) ($filtersSafe['accion'] ?? '') === $value) ? 'selected' : ''; ?>><?php echo htmlspecialchars($value); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2"><input class="form-control" type="date" name="fecha_desde" value="<?php echo htmlspecialchars((string) ($filtersSafe['fecha_desde'] ?? '')); ?>"></div>
            <div class="col-md-2"><input class="form-control" type="date" name="fecha_hasta" value="<?php echo htmlspecialchars((string) ($filtersSafe['fecha_hasta'] ?? '')); ?>"></div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-brand w-100" type="submit">Filtrar</button>
                <a class="btn btn-outline-secondary" href="<?php echo htmlspecialchars(url('/auditoria')); ?>">Limpiar</a>
            </div>
        </form>
    </section>

    <section class="card tvg-card p-3">
        <div class="table-responsive">
            <table class="table tvg-table table-hover align-middle mb-0">
                <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Accion</th>
                    <th>Modulo</th>
                    <th>Usuario</th>
                    <th>Empresa</th>
                    <th>Registro</th>
                    <th>Detalle</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($rowsSafe as $row): ?>
                    <?php
                    $accion = (string) ($row['accion'] ?? 'evento');
                    $before = $row['datos_anteriores_decoded'] ?? null;
                    $after = $row['datos_nuevos_decoded'] ?? null;
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars((string) ($row['fecha_evento'] ?? '')); ?></td>
                        <td><span class="badge tvg-badge <?php echo $accionBadgeClass($accion); ?>"><?php echo htmlspecialchars($accion); ?></span></td>
                        <td><?php echo htmlspecialchars((string) ($row['modulo'] ?? 'sistema')); ?></td>
                        <td><?php echo htmlspecialchars(trim((string) ($row['usuario_nombre'] ?? '')) !== '' ? (string) ($row['usuario_nombre'] ?? '') : ('ID ' . (int) ($row['usuario_id'] ?? 0))); ?></td>
                        <td><?php echo htmlspecialchars((string) ($row['empresa_nombre'] ?? '')); ?></td>
                        <td>
                            <small class="text-muted"><?php echo htmlspecialchars((string) ($row['tabla_afectada'] ?? '')); ?></small>
                            <div>#<?php echo (int) ($row['registro_id'] ?? 0); ?></div>
                        </td>
                        <td>
                            <details>
                                <summary>Ver cambios</summary>
                                <div class="mt-2">
                                    <strong>Antes:</strong>
                                    <pre class="small mb-2"><?php echo htmlspecialchars((string) json_encode($before, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></pre>
                                    <strong>Despues:</strong>
                                    <pre class="small mb-0"><?php echo htmlspecialchars((string) json_encode($after, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></pre>
                                </div>
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
require BASE_PATH . '/app/views/layaouts/app.php';
