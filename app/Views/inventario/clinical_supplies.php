<section class="card" id="insumos">
<h2>Insumos clínicos</h2>
<?php $supplies = (new \App\Models\Inventory())->clinicalSupplies(active_environment_id(), $supplyType, $supplyEventId); ?>
<?php if (($supplyCanCreate ?? true) && can($supplyPermission ?? ($supplyRoute . ".editar"))): ?>
<form method="POST" action="<?= url('/'.$supplyRoute.'/'.$supplyEventId.'/insumos') ?>">
<?= csrf_field() ?>
<?php $clinicalKind = 'supply'; $clinicalPreferred = $supplyPreferred ?? 'Veterinaria'; require APP_PATH . '/Views/inventario/clinical_consumption.php'; ?>
<button class="btn btn-primary" type="submit">Registrar consumo</button>
</form>
<?php endif; ?>
<?php foreach ($supplies as $supply): ?>
<div class="soft-panel">
<strong><?= e($supply['producto']) ?></strong> · <?= e($supply['inventario']) ?> · <?= e($supply['cantidad']) ?> · <?= e($supply['numero_lote'] ?? 'Sin lote') ?>
<?php if ($supply['revertido']): ?> — Revertido
<?php elseif (can($supplyPermission ?? ($supplyRoute . ".editar"))): ?>
<form method="POST" action="<?= url('/'.$supplyRoute.'/'.$supplyEventId.'/insumos') ?>">
<?= csrf_field() ?><input type="hidden" name="revertir_id" value="<?= (int)$supply['id'] ?>">
<label>Motivo <input name="motivo" required maxlength="500"></label><button class="btn btn-secondary" type="submit">Revertir consumo</button>
</form><?php endif; ?>
</div><?php endforeach; ?>
</section>
