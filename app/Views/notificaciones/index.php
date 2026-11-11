<?php $now = new DateTimeImmutable(); ?>
<div class="page-heading">
    <div><span class="eyebrow">Comunicaciones</span>
        <h1>Notificaciones</h1>
        <p>Cola de email, WhatsApp e internas.</p>
    </div>
    <?php if (!$dailyCronEnabled): ?>
        <form method="POST" action="<?= url('/notificaciones/procesar') ?>">
            <?= csrf_field() ?>
            <button class="btn btn-primary">Procesar cola</button>
        </form>
    <?php endif; ?>
</div>
<?php if ($dailyCronEnabled): ?>
    <p class="muted"><small>Procesamiento programado diariamente a las 18:00 (<?= e(date_default_timezone_get()) ?>). El envío depende de cron y del servicio de correo.</small></p>
<?php else: ?>
    <div class="alert alert-danger">El procesamiento automático diario no está confirmado. Configura cron y activa NOTIFICATIONS_DAILY_CRON_ENABLED antes de retirar el procesamiento manual.</div>
<?php endif; ?>
<section class="card">
    <div class="table-wrap">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Canal</th>
                    <th>Asunto</th>
                    <th>Estado</th>
                    <th>Intentos</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($notifications as $n): ?>
                    <tr>
                        <td><?= e($n['created_at']) ?></td>
                        <td><?= e($n['tipo']) ?></td>
                        <td><?= e($n['canal']) ?></td>
                        <td><?= e($n['asunto'] ?? '—') ?></td>
                        <td>
                            <?= e($n['estado']) ?>
                            <?php if ($n['estado'] === 'PENDIENTE' && in_array($n['canal_codigo'], ['EMAIL', 'INTERNA'], true) && $dailyCronEnabled): ?>
                                <?php $next = \App\Services\NotificationSchedule::nextDailyRun($now, $n['fecha_programada']); ?>
                                <br><small data-next-processing="<?= e($next->format(DateTimeInterface::ATOM)) ?>">Próximo procesamiento estimado: <?= e($next->format('d/m/Y H:i')) ?></small>
                            <?php elseif ($n['estado'] === 'PENDIENTE' && $n['canal_codigo'] === 'WHATSAPP'): ?>
                                <br><small>Este canal no se procesa en la cola automática.</small>
                            <?php endif; ?>
                        </td>
                        <td><?= e($n['intentos']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php if ($dailyCronEnabled): ?>
<script>
    document.querySelectorAll('[data-next-processing]').forEach(function (element) {
        const target = new Date(element.dataset.nextProcessing).getTime();
        const update = function () {
            const minutes = Math.ceil((target - Date.now()) / 60000);
            if (minutes <= 0) {
                element.textContent = 'Procesamiento previsto; comprueba el estado del envío.';
                return;
            }
            const hours = Math.floor(minutes / 60);
            const remainingMinutes = minutes % 60;
            element.textContent = 'Próximo procesamiento estimado en ' +
                (hours ? hours + ' h ' : '') + remainingMinutes + ' min.';
        };
        update();
        window.setInterval(update, 60000);
    });
</script>
<?php endif; ?>