<section class="card" id="insumos">

    <h2>Insumos clínicos</h2>

    <?php
    $supplies = (new \App\Models\Inventory())->clinicalSupplies(
        active_environment_id(),
        $supplyType,
        $supplyEventId
    );

    $canManageSupplies = can(
        $supplyPermission ?? ($supplyRoute . '.editar')
    );
    ?>

    <?php if (($supplyCanCreate ?? true) && $canManageSupplies): ?>

        <form
            method="POST"
            action="<?= url('/' . $supplyRoute . '/' . $supplyEventId . '/insumos') ?>">
            <?= csrf_field() ?>

            <?php
            $clinicalKind = 'supply';
            $clinicalPreferred = $supplyPreferred ?? 'Veterinaria';

            require APP_PATH . '/Views/inventario/clinical_consumption.php';
            ?>

            <button class="btn btn-primary" type="submit">
                Registrar consumo
            </button>

        </form>

    <?php endif; ?>

    <h3>Insumos registrados</h3>

    <?php if (empty($supplies)): ?>

        <p class="text-muted">
            No se han registrado consumos de insumos.
        </p>

    <?php else: ?>

        <?php foreach ($supplies as $supply): ?>

            <?php
            $quantity = rtrim(
                rtrim(
                    number_format(
                        (float) $supply['cantidad'],
                        4,
                        '.',
                        ''
                    ),
                    '0'
                ),
                '.'
            );
            ?>

            <div class="soft-panel">

                <div class="form-grid">

                    <div class="field-full">
                        <strong>
                            <?= e($supply['producto']) ?>
                        </strong>
                    </div>

                    <div>
                        <small class="text-muted">
                            Inventario de salida
                        </small>
                        <div>
                            <?= e($supply['inventario']) ?>
                        </div>
                    </div>

                    <div>
                        <small class="text-muted">
                            Cantidad consumida
                        </small>
                        <div>
                            <?= e($quantity) ?>
                        </div>
                    </div>

                    <div>
                        <small class="text-muted">Lote</small>
                        <div>
                            <?= e($supply['numero_lote'] ?? 'Sin lote') ?>
                        </div>
                    </div>

                    <div>
                        <small class="text-muted">Estado</small>
                        <div>
                            <strong>
                                <?= $supply['revertido']
                                    ? 'Revertido'
                                    : 'Registrado' ?>
                            </strong>
                        </div>
                    </div>

                </div>

                <?php if (!$supply['revertido'] && $canManageSupplies): ?>

                    <form
                        method="POST"
                        action="<?= url('/' . $supplyRoute . '/' . $supplyEventId . '/insumos') ?>">
                        <?= csrf_field() ?>

                        <input
                            type="hidden"
                            name="revertir_id"
                            value="<?= (int) $supply['id'] ?>">

                        <div class="form-grid">

                            <label class="field-full">
                                <span>Motivo de reversión *</span>

                                <input
                                    type="text"
                                    name="motivo"
                                    placeholder="Indique el motivo de la reversión"
                                    required
                                    maxlength="500">
                            </label>

                        </div>

                        <button
                            class="btn btn-secondary"
                            type="submit">
                            Revertir consumo
                        </button>

                    </form>

                <?php endif; ?>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>

</section>