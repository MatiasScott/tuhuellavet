<div class="page-heading">
    <div><span class="eyebrow">Clínica</span><h1>Pacientes</h1><p>Pacientes de <?= e(active_environment()['nombre'] ?? 'este entorno') ?>.</p></div>
    <?php if (can('pacientes.crear')): ?><button class="btn btn-primary">＋ Nuevo paciente</button><?php endif; ?>
</div>
<section class="card">
    <form class="toolbar" method="GET" action="<?= url('/pacientes') ?>">
        <div class="search-box">🔎 <input type="search" name="q" value="<?= e($search) ?>" placeholder="Buscar paciente, propietario, especie o raza"></div>
        <button class="btn btn-secondary">Buscar</button>
    </form>
    <?php if (empty($patients)): ?>
        <div class="empty-state"><div class="empty-icon">🐾</div><h2>No encontramos pacientes</h2><p>Registra el primer paciente de este entorno.</p></div>
    <?php else: ?>
        <div class="table-wrap"><table class="modern-table">
            <thead><tr><th>Paciente</th><th>Propietario</th><th>Especie / raza</th><th>Peso</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($patients as $patient): ?>
                <tr>
                    <td><div class="entity-cell"><div class="pet-avatar"><?= ($patient['especie'] ?? '') === 'Gato' ? '🐱' : '🐾' ?></div><div><strong><?= e($patient['nombre'] ?: 'Sin nombre') ?></strong><small><?= e($patient['codigo'] ?? '') ?></small></div></div></td>
                    <td><?= e(trim(($patient['propietario_nombres'] ?? '').' '.($patient['propietario_apellidos'] ?? '')) ?: 'Sin propietario') ?></td>
                    <td><?= e(($patient['especie'] ?? '').($patient['raza'] ? ' · '.$patient['raza'] : '')) ?></td>
                    <td><?= $patient['peso_actual'] !== null ? e($patient['peso_actual']).' kg' : '—' ?></td>
                    <td class="table-actions"><?php if (can('pacientes.ver')): ?><button class="btn btn-small btn-secondary">Ver</button><?php endif; ?> <?php if (can('pacientes.editar')): ?><button class="btn btn-small btn-ghost">Editar</button><?php endif; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>
