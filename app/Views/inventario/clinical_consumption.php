<?php
// Shared optional physical consumption. Clinical dose and stock quantity are independent.
$clinicalInventory = new \App\Models\Inventory();
$clinicalEnv = active_environment_id();
$clinicalProducts = array_column($clinicalInventory->products(), null, 'id');
$clinicalLots = array_column($clinicalInventory->lots($clinicalEnv), null, 'id');
$clinicalInventories = array_column($clinicalInventory->inventories($clinicalEnv), null, 'id');
$clinicalPreferred = $clinicalPreferred ?? 'Veterinaria';

$clinicalDrugPresentations = [];

if ($clinicalKind === 'deworming') {
    $clinicalDrugPresentations = array_column(
        (new \App\Models\Catalog())->drugPresentations(),
        null,
        'id'
    );
}
?>
<fieldset
    class="field-full soft-panel"
    data-clinical-stock
    data-clinical-kind="<?= e($clinicalKind) ?>">
    <legend>Consumo de inventario</legend>
    <label><span>Descontar insumo del inventario</span><input type="checkbox" data-stock-enable></label>
    <div class="form-grid" data-stock-fields hidden>
        <label class="field-full"><span>Inventario de salida *</span>
            <select name="inventario_consumo[inventario_id]" data-stock-origin disabled required>
                <option value="">Seleccionar inventario</option>
                <?php foreach ($clinicalInventories as $ci): ?>
                    <option value="<?= (int)$ci['id'] ?>" <?= $ci['nombre'] === 'Inventario Tu Huella Vet - ' . $clinicalPreferred ? 'selected' : '' ?>><?= e($ci['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="field-full"><span>Producto físico *</span>
            <select name="inventario_consumo[producto_id]" data-stock-product disabled required>
                <option value="">Seleccionar producto</option>
                <?php foreach ($clinicalProducts as $cp): ?>

                    <?php
                    if (
                        $clinicalKind !== 'supply'
                        && empty($cp[$clinicalKind === 'vaccine'
                            ? 'vacuna_id'
                            : 'farmaco_presentacion_id'])
                    ) {
                        continue;
                    }

                    $presentationId =
                        (int)($cp['farmaco_presentacion_id'] ?? 0);

                    $drugId = (int)(
                        $clinicalDrugPresentations[$presentationId]['farmaco_id']
                        ?? 0
                    );
                    ?>

                    <option
                        value="<?= (int)$cp['id'] ?>"
                        data-lot="<?= (int)$cp['controla_lote'] ?>"
                        data-vaccine="<?= (int)($cp['vacuna_id'] ?? 0) ?>"
                        data-drug="<?= $drugId ?>">

                        <?= e(
                            $cp['codigo']
                                . ' — '
                                . $cp['nombre']
                                . ' ('
                                . $cp['unidad']
                                . ')'
                        ) ?>

                    </option>

                <?php endforeach; ?>
            </select>
        </label>
        <label><span>Cantidad física consumida *</span><input type="number" min="0.0001" step="0.0001" name="inventario_consumo[cantidad_consumida]" disabled required></label>
        <label><span>Lote</span><select name="inventario_consumo[lote_id]" data-stock-lot disabled>
                <option value="">Seleccionar lote</option>
            </select></label>
        <p class="field-full text-muted" data-stock-status></p>
    </div>
    <script type="application/json" data-stock-balances>
        <?= json_encode($clinicalInventory->movementBalances($clinicalEnv), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>
    </script>
    <script type="application/json" data-stock-lots>
        <?= json_encode($clinicalLots, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) ?>
    </script>
</fieldset>
<script src="<?= url('assets/js/views/clinical-stock.js') ?>" defer></script>