<div class="page-heading">
    <div><span class="eyebrow">Portal del cliente</span>
        <h1>Mis animales</h1>
        <p>Información clínica de tus mascotas y animales.</p>
    </div>
</div>
<div class="cards-grid">
    <?php foreach ($animals as $a): ?>
        <a class="card" href="<?= url('/cliente/pacientes/' . $a['id']) ?>">
            <h2>🐾 <?= e($a['nombre']) ?></h2>
            <p><?= e($a['especie']) ?><?= !empty($a['raza']) ? ' · ' . e($a['raza']) : '' ?></p>
            <strong><?= e($a['peso_actual'] ?? '—') ?><?= ($a['peso_actual'] !== null ? ' kg' : '') ?></strong>
        </a>
    <?php endforeach; ?>
</div>