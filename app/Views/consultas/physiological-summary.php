<?php foreach (['temperatura_c' => 'Temperatura pulmonar / cloacal (C)'] + \App\Services\PhysiologicalExam::FIELDS as $field => $label): ?>
    <?php if (isset($examValues[$field]) && $examValues[$field] !== ''): ?>
        <p><strong><?= e($label) ?>:</strong> <?= e($examValues[$field]) ?></p>
    <?php endif; ?>
<?php endforeach; ?>
