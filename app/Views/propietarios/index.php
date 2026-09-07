<div class="page-heading">
    <div><span class="eyebrow">Clientes</span><h1>Propietarios</h1><p>Propietarios de <?= e(active_environment()['nombre'] ?? 'este entorno') ?>.</p></div>
    <?php if (can('propietarios.crear')): ?><button class="btn btn-primary">＋ Nuevo propietario</button><?php endif; ?>
</div>
<section class="card">
    <form class="toolbar" method="GET" action="<?= url('/propietarios') ?>">
        <div class="search-box">🔎 <input type="search" name="q" value="<?= e($search) ?>" placeholder="Buscar por nombre, email o identificación"></div>
        <button class="btn btn-secondary">Buscar</button>
    </form>
    <?php if (empty($owners)): ?>
        <div class="empty-state"><div class="empty-icon">👥</div><h2>No encontramos propietarios</h2><p>Registra el primer propietario de este entorno.</p></div>
    <?php else: ?>
        <div class="table-wrap"><table class="modern-table">
            <thead><tr><th>Propietario</th><th>Contacto</th><th>Identificación</th><th>Animales</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($owners as $owner): ?>
                <tr>
                    <td><div class="entity-cell"><div class="avatar"><?= e(strtoupper(substr($owner['nombres'] ?? 'P',0,1))) ?></div><div><strong><?= e(trim(($owner['nombres'] ?? '').' '.($owner['apellidos'] ?? ''))) ?></strong><small><?= e($owner['email'] ?? '') ?></small></div></div></td>
                    <td><?= e($owner['celular'] ?: ($owner['telefono'] ?: '—')) ?></td>
                    <td><?= e($owner['identificacion'] ?: '—') ?></td>
                    <td><span class="status-badge status-info"><?= (int)$owner['animales_count'] ?></span></td>
                    <td class="table-actions"><?php if (can('propietarios.ver')): ?><button class="btn btn-small btn-secondary">Ver</button><?php endif; ?> <?php if (can('propietarios.editar')): ?><button class="btn btn-small btn-ghost">Editar</button><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>
