<label><span>Animal individual</span>
    <select name="paciente_id">
        <option value="">Sin animal individual</option>
        <?php foreach ($patients as $patient): ?>
            <option value="<?= (int) $patient['id'] ?>" <?= (string) ($filters['paciente_id'] ?? '') === (string) $patient['id'] ? 'selected' : '' ?>><?= e($patient['nombre'] ?: $patient['codigo'] ?: ('Animal #' . $patient['id'])) ?></option>
        <?php endforeach; ?>
    </select>
</label>
<label><span>Lote animal</span>
    <select name="lote_id">
        <option value="">Sin lote</option>
        <?php foreach ($animalLots as $lot): ?>
            <option value="<?= (int) $lot['id'] ?>" <?= (string) ($filters['lote_id'] ?? '') === (string) $lot['id'] ? 'selected' : '' ?>><?= e($lot['codigo'] . ' - ' . $lot['nombre']) ?></option>
        <?php endforeach; ?>
    </select>
</label>
