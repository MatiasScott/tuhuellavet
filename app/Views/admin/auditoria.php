<div class="page-heading">
    <div><span class="eyebrow">Seguridad</span>
        <h1>Auditoría</h1>
        <p>Histórico de acciones realizadas en el sistema.</p>
    </div>
</div>
<section class="card">
    <div class="table-wrap">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Usuario</th>
                    <th>Módulo</th>
                    <th>Acción</th>
                    <th>Tabla</th>
                    <th>ID</th>
                </tr>
            </thead>
            <tbody><?php foreach ($rows as $r): ?><tr>
                        <td><?= e($r['created_at']) ?></td>
                        <td><?= e($r['usuario'] ?? 'Sistema') ?></td>
                        <td><?= e($r['modulo']) ?></td>
                        <td><?= e($r['accion']) ?></td>
                        <td><?= e($r['tabla_afectada'] ?? '—') ?></td>
                        <td><?= e($r['registro_id'] ?? '—') ?></td>
                    </tr><?php endforeach; ?></tbody>
        </table>
    </div>
</section>