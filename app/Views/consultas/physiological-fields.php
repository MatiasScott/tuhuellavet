<?php foreach (\App\Services\PhysiologicalExam::FIELDS as $field => $label): ?>
    <label>
        <span><?= e($label) ?></span>
        <input name="<?= e($field) ?>" maxlength="<?= in_array($field, ['sonido', 'ritmo_cardiaco', 'mucosas'], true) ? 100 : 10000 ?>" value="<?= e($examValues[$field] ?? '') ?>">
    </label>
<?php endforeach; ?>
