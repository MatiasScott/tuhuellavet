<?php
$clinicalPrefix = $clinicalPrefix ?? 'create';
?>

<label>
    Vacuna asociada

    <select
        name="vacuna_id"
        id="<?= e($clinicalPrefix) ?>-product-vaccine">

        <option value="">Sin asociación</option>

        <?php foreach (($vaccines ?? []) as $vaccine): ?>
            <option value="<?= (int)$vaccine['id'] ?>">
                <?= e($vaccine['nombre']) ?>
            </option>
        <?php endforeach; ?>

    </select>
</label>

<label>
    Presentación farmacológica asociada

    <select
        name="farmaco_presentacion_id"
        id="<?= e($clinicalPrefix) ?>-product-drug">

        <option value="">Sin asociación</option>

        <?php foreach (($drugPresentations ?? []) as $presentation): ?>
            <option value="<?= (int)$presentation['id'] ?>">
                <?= e($presentation['nombre_comercial']) ?>
                <?php if (!empty($presentation['forma'])): ?>
                    · <?= e($presentation['forma']) ?>
                <?php endif; ?>
            </option>
        <?php endforeach; ?>

    </select>
</label>