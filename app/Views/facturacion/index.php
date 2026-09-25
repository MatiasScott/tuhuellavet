<?php
$money = static fn($value): string => '$' . number_format((float) $value, 2, '.', ',');
?>
<link rel="stylesheet" href="<?= url('assets/css/views/facturacion.css') ?>">
<div class="facturacion-page">
    <div class="page-heading">
        <div>
            <span class="eyebrow">Finanzas</span>
            <h1>Ventas</h1>
            <p>Registra productos y servicios con precios, impuestos y stock controlados por el sistema.</p>
        </div>
        <?php if (can('ventas.crear')): ?>
            <button class="btn btn-primary" type="button" data-modal-open="sale-create">＋ Nueva venta</button>
        <?php endif; ?>
    </div>

    <?php if ($success): ?><div class="alert alert-success"><?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>

    <section class="card sales-card">
        <div class="card-header sales-card-header">
            <div>
                <h2>Historial de ventas</h2>
                <p class="muted">El estado de cobro se calcula desde los pagos registrados.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table class="modern-table sales-table">
                <thead>
                    <tr>
                        <th>Número</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Paciente</th>
                        <th>Subtotal</th>
                        <th>Desc.</th>
                        <th>Impuestos</th>
                        <th>Total</th>
                        <th>Cobro</th>
                        <th>Fiscal</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$sales): ?>
                        <tr>
                            <td colspan="11" class="empty-cell">Todavía no hay ventas registradas.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($sales as $s): ?>
                        <?php
                        $ownerName = trim(($s['propietario_nombres'] ?? '') . ' ' . ($s['propietario_apellidos'] ?? ''));
                        $paymentState = (string) ($s['estado_pago'] ?? 'SIN_PAGO');
                        $fiscalState = $s['contifico_estado'] ?? $s['estado_fiscal'] ?? 'SIN_DOCUMENTO';
                        ?>
                        <tr>
                            <td><strong><?= e($s['numero'] ?? ('#' . $s['id'])) ?></strong><?= !empty($s['es_simulada']) ? '<span class="sale-mini-tag">SIMULADA</span>' : '' ?></td>
                            <td><?= e($s['fecha']) ?></td>
                            <td><?= e($ownerName !== '' ? $ownerName : '—') ?></td>
                            <td><?= e($s['paciente'] ?? '—') ?></td>
                            <td><?= $money($s['subtotal']) ?></td>
                            <td><?= $money($s['descuento']) ?></td>
                            <td><?= $money($s['impuestos']) ?></td>
                            <td><strong><?= $money($s['total']) ?></strong></td>
                            <td>
                                <span class="status-pill status-<?= e(strtolower($paymentState)) ?>"><?= e(str_replace('_', ' ', $paymentState)) ?></span>
                                <?php if ((float)($s['saldo'] ?? 0) > 0 && $s['estado'] !== 'ANULADA'): ?><small class="sale-balance">Saldo <?= $money($s['saldo']) ?></small><?php endif; ?>
                            </td>
                            <td><span class="status-pill status-neutral"><?= e(str_replace('_', ' ', (string)$fiscalState)) ?></span></td>
                            <td class="sale-actions">

                                <?php if (
                                    can('ventas.editar')
                                    && $s['estado'] === 'FINALIZADA'
                                    && (float) ($s['saldo'] ?? 0) > 0.004
                                ): ?>
                                    <button
                                        type="button"
                                        class="btn btn-primary btn-sm"
                                        data-modal-open="payment-create-<?= (int) $s['id'] ?>">
                                        Cobrar
                                    </button>
                                <?php endif; ?>


                                <button
                                    type="button"
                                    class="btn btn-secondary btn-sm"
                                    data-modal-open="payment-history-<?= (int) $s['id'] ?>">
                                    Pagos
                                </button>

                                <?php if (
                                    can('ventas.eliminar')
                                    && $s['estado'] === 'FINALIZADA'
                                ): ?>

                                    <button
                                        type="button"
                                        class="btn btn-danger btn-sm"
                                        data-modal-open="sale-cancel-<?= (int) $s['id'] ?>">
                                        Anular
                                    </button>

                                <?php endif; ?>

                                <?php if (
                                    can('facturacion.facturar')
                                    && empty($s['es_simulada'])
                                    && $s['estado'] === 'FINALIZADA'
                                    && empty($s['documento_fiscal_id'])
                                ): ?>
                                    <form
                                        method="POST"
                                        action="<?= url('/facturacion/' . $s['id'] . '/emitir') ?>"
                                        class="sale-inline-form">
                                        <?= csrf_field() ?>

                                        <button
                                            class="btn btn-secondary btn-sm"
                                            type="submit">
                                            Emitir
                                        </button>
                                    </form>
                                <?php endif; ?>

                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="card fiscal-card">
        <div class="card-header">
            <div>
                <h2>Documentos fiscales</h2>
                <p class="muted">Vista temporal; después se separará como módulo propio.</p>
            </div>
        </div>
        <div class="table-wrap">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Venta</th>
                        <th>Estado</th>
                        <th>Contífico</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$documents): ?><tr>
                            <td colspan="5" class="empty-cell">No hay documentos fiscales.</td>
                        </tr><?php endif; ?>
                    <?php foreach ($documents as $d): ?>
                        <tr>
                            <td>#<?= (int)$d['id'] ?></td>
                            <td><?= e($d['venta_numero'] ?? ('#' . $d['venta_id'])) ?></td>
                            <td><?= e($d['estado']) ?></td>
                            <td><?= e($d['contifico_estado'] ?? 'PENDIENTE') ?></td>
                            <td><?= $money($d['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="modal" id="sale-create">
    <div class="modal-backdrop" data-modal-close></div>
    <div class="modal-dialog modal-xl sale-dialog">
        <div class="modal-header">
            <div><span class="eyebrow">Nueva operación</span>
                <h2>Nueva venta</h2>
            </div>
            <button class="modal-close" type="button" data-modal-close>×</button>
        </div>
        <form method="POST" action="<?= url('/facturacion') ?>" id="sale-form" autocomplete="off">
            <?= csrf_field() ?>
            <div class="modal-body sale-form-body">
                <section class="sale-section">
                    <div class="sale-section-title">
                        <div>
                            <h3>Cliente</h3>
                            <p>Relaciona la venta con propietario, paciente y datos fiscales.</p>
                        </div>
                    </div>
                    <div class="form-grid sale-client-grid">
                        <label><span>Propietario</span>
                            <select name="propietario_entorno_id" id="sale-owner">
                                <option value="">Consumidor / sin propietario</option>
                                <?php foreach ($owners as $o): ?>
                                    <option value="<?= (int)$o['id'] ?>"><?= e(trim(($o['nombres'] ?? '') . ' ' . ($o['apellidos'] ?? ''))) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label><span>Paciente</span>
                            <select name="animal_id" id="sale-patient">
                                <option value="">Sin paciente</option>
                                <?php foreach ($patients as $p): ?>
                                    <option value="<?= (int)$p['id'] ?>" data-owner-environment-id="<?= (int)($p['propietario_entorno_id'] ?? 0) ?>"><?= e($p['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="sale-field">
                            <span>Datos fiscales</span>

                            <select
                                id="sale-fiscal-data"
                                name="datos_fiscales_id">
                                <option value="">
                                    Sin datos fiscales seleccionados
                                </option>

                                <?php foreach (($fiscalData ?? []) as $fiscal): ?>

                                    <option
                                        value="<?= (int) $fiscal['id'] ?>"
                                        data-owner-environment-id="<?= (int) $fiscal['propietario_entorno_id'] ?>"
                                        data-owner-id="<?= (int) $fiscal['propietario_id'] ?>"
                                        data-principal="<?= (int) $fiscal['es_principal'] ?>">
                                        <?= htmlspecialchars(
                                            (
                                                (int) $fiscal['es_principal'] === 1
                                                ? 'Principal · '
                                                : ''
                                            )
                                                . $fiscal['identificacion']
                                                . ' · '
                                                . $fiscal['razon_social'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </option>

                                <?php endforeach; ?>
                            </select>
                        </label>
                    </div>
                </section>

                <section class="sale-section">
                    <div class="sale-section-title sale-lines-heading">
                        <div>
                            <h3>Detalle</h3>
                            <p>Agrega todos los productos y servicios de la venta.</p>
                        </div>
                        <button class="btn btn-secondary btn-sm" type="button" id="sale-add-line">＋ Agregar línea</button>
                    </div>
                    <div class="sale-lines" id="sale-lines"></div>
                </section>

                <section class="sale-bottom-grid">
                    <label class="sale-notes"><span>Observaciones</span><textarea name="observaciones" rows="4" placeholder="Observaciones internas de la venta"></textarea></label>
                    <div class="sale-summary">
                        <div><span>Subtotal</span><strong id="sale-summary-subtotal">$0.00</strong></div>
                        <div><span>Descuento</span><strong id="sale-summary-discount">$0.00</strong></div>
                        <div><span>Base imponible</span><strong id="sale-summary-base">$0.00</strong></div>
                        <div><span>Impuestos</span><strong id="sale-summary-tax">$0.00</strong></div>
                        <div class="sale-summary-total"><span>Total</span><strong id="sale-summary-total">$0.00</strong></div>
                    </div>
                </section>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" type="button" data-modal-close>Cancelar</button>
                <button class="btn btn-primary" type="submit" id="sale-submit">Registrar venta</button>
            </div>
        </form>
    </div>
</div>

<?php foreach ($sales as $s): ?>

    <?php
    $saleId = (int) $s['id'];

    $salePayments = array_values(
        array_filter(
            $payments ?? [],
            static fn(array $payment): bool =>
            (int) $payment['venta_id'] === $saleId
        )
    );

    $saleTotal = (float) ($s['total'] ?? 0);
    $salePaid = (float) ($s['pagado'] ?? 0);
    $saleBalance = (float) ($s['saldo'] ?? 0);
    $paymentState = (string) ($s['estado_pago'] ?? 'SIN_PAGO');

    $ownerName = trim(
        ($s['propietario_nombres'] ?? '')
            . ' '
            . ($s['propietario_apellidos'] ?? '')
    );
    ?>


    <!-- =========================================================
         MODAL: REGISTRAR PAGO
         ========================================================= -->

    <?php if (
        can('ventas.editar')
        && $s['estado'] === 'FINALIZADA'
        && $saleBalance > 0.004
    ): ?>

        <div
            class="modal"
            id="payment-create-<?= $saleId ?>">

            <div
                class="modal-backdrop"
                data-modal-close>
            </div>

            <div class="modal-dialog payment-dialog">

                <div class="modal-header">

                    <div>
                        <span class="eyebrow">
                            Cobros
                        </span>

                        <h2>
                            Registrar pago
                        </h2>

                        <p class="muted">
                            Venta
                            <strong>
                                <?= e(
                                    $s['numero']
                                        ?? ('#' . $saleId)
                                ) ?>
                            </strong>
                        </p>
                    </div>

                    <button
                        class="modal-close"
                        type="button"
                        data-modal-close>
                        ×
                    </button>

                </div>


                <form
                    method="POST"
                    action="<?= url(
                                '/facturacion/'
                                    . $saleId
                                    . '/pagos'
                            ) ?>"
                    autocomplete="off">

                    <?= csrf_field() ?>


                    <div class="modal-body">

                        <div class="payment-sale-summary">

                            <div>
                                <span>Total venta</span>
                                <strong>
                                    <?= $money($saleTotal) ?>
                                </strong>
                            </div>

                            <div>
                                <span>Pagado</span>
                                <strong>
                                    <?= $money($salePaid) ?>
                                </strong>
                            </div>

                            <div class="payment-balance-box">
                                <span>Saldo pendiente</span>
                                <strong>
                                    <?= $money($saleBalance) ?>
                                </strong>
                            </div>

                        </div>


                        <?php if ($ownerName !== ''): ?>

                            <div class="payment-customer">
                                <span>Cliente</span>

                                <strong>
                                    <?= e($ownerName) ?>
                                </strong>
                            </div>

                        <?php endif; ?>


                        <div class="form-grid payment-form-grid">

                            <label>

                                <span>
                                    Método de pago *
                                </span>

                                <select
                                    name="metodo_pago_id"
                                    required>

                                    <option value="">
                                        Selecciona...
                                    </option>

                                    <?php foreach (
                                        ($paymentMethods ?? [])
                                        as $method
                                    ): ?>

                                        <option
                                            value="<?= (int) $method['id'] ?>">

                                            <?= e($method['nombre']) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </label>


                            <label>

                                <span>
                                    Monto *
                                </span>

                                <input
                                    type="number"
                                    name="monto"
                                    min="0.01"
                                    max="<?= number_format(
                                                $saleBalance,
                                                2,
                                                '.',
                                                ''
                                            ) ?>"
                                    step="0.01"
                                    value="<?= number_format(
                                                $saleBalance,
                                                2,
                                                '.',
                                                ''
                                            ) ?>"
                                    required>

                                <small class="muted">
                                    Máximo:
                                    <?= $money($saleBalance) ?>
                                </small>

                            </label>


                            <label class="payment-reference-field">

                                <span>
                                    Referencia
                                </span>

                                <input
                                    type="text"
                                    name="referencia"
                                    maxlength="150"
                                    placeholder="N.º transferencia, voucher, autorización...">

                                <small class="muted">
                                    Opcional para efectivo.
                                </small>

                            </label>

                        </div>

                    </div>


                    <div class="modal-footer">

                        <button
                            class="btn btn-secondary"
                            type="button"
                            data-modal-close>
                            Cancelar
                        </button>

                        <button
                            class="btn btn-primary"
                            type="submit">
                            Registrar pago
                        </button>

                    </div>

                </form>

            </div>

        </div>

    <?php endif; ?>



    <!-- =========================================================
         MODAL: HISTORIAL DE PAGOS
         ========================================================= -->

    <div
        class="modal"
        id="payment-history-<?= $saleId ?>">

        <div
            class="modal-backdrop"
            data-modal-close>
        </div>

        <div class="modal-dialog modal-lg payment-history-dialog">

            <div class="modal-header">

                <div>

                    <span class="eyebrow">
                        Cobros
                    </span>

                    <h2>
                        Historial de pagos
                    </h2>

                    <p class="muted">

                        Venta

                        <strong>
                            <?= e(
                                $s['numero']
                                    ?? ('#' . $saleId)
                            ) ?>
                        </strong>

                    </p>

                </div>


                <button
                    class="modal-close"
                    type="button"
                    data-modal-close>
                    ×
                </button>

            </div>


            <div class="modal-body">

                <div class="payment-sale-summary">

                    <div>
                        <span>Total</span>

                        <strong>
                            <?= $money($saleTotal) ?>
                        </strong>
                    </div>

                    <div>
                        <span>Pagado</span>

                        <strong>
                            <?= $money($salePaid) ?>
                        </strong>
                    </div>

                    <div>
                        <span>Saldo</span>

                        <strong>
                            <?= $money($saleBalance) ?>
                        </strong>
                    </div>

                    <div>
                        <span>Estado</span>

                        <strong>
                            <?= e(
                                str_replace(
                                    '_',
                                    ' ',
                                    $paymentState
                                )
                            ) ?>
                        </strong>
                    </div>

                </div>


                <?php if (!$salePayments): ?>

                    <div class="payment-empty">

                        <strong>
                            Sin pagos registrados
                        </strong>

                        <p>
                            Esta venta todavía no tiene
                            movimientos de cobro.
                        </p>

                    </div>

                <?php else: ?>

                    <div class="table-wrap">

                        <table class="modern-table payment-table">

                            <thead>

                                <tr>
                                    <th>Fecha</th>
                                    <th>Método</th>
                                    <th>Referencia</th>
                                    <th>Monto</th>
                                    <th>Estado</th>
                                    <th>Registrado por</th>
                                    <th></th>
                                </tr>

                            </thead>


                            <tbody>

                                <?php foreach (
                                    $salePayments
                                    as $payment
                                ): ?>

                                    <?php
                                    $isRegistered =
                                        $payment['estado']
                                        === 'REGISTRADO'
                                        &&
                                        empty($payment['anulado_at']);
                                    ?>

                                    <tr>

                                        <td>
                                            <?= e(
                                                $payment['fecha_pago']
                                            ) ?>
                                        </td>


                                        <td>

                                            <strong>
                                                <?= e(
                                                    $payment['metodo_nombre']
                                                ) ?>
                                            </strong>

                                        </td>


                                        <td>
                                            <?= e(
                                                $payment['referencia']
                                                    ?: '—'
                                            ) ?>
                                        </td>


                                        <td>

                                            <strong>
                                                <?= $money(
                                                    $payment['monto']
                                                ) ?>
                                            </strong>

                                        </td>


                                        <td>

                                            <?php if ($isRegistered): ?>

                                                <span class="status-pill status-pagada">
                                                    REGISTRADO
                                                </span>

                                            <?php else: ?>

                                                <span class="status-pill status-neutral">
                                                    ANULADO
                                                </span>

                                                <?php if (
                                                    !empty($payment['motivo_anulacion'])
                                                ): ?>

                                                    <small class="payment-cancel-reason">

                                                        <?= e(
                                                            $payment['motivo_anulacion']
                                                        ) ?>

                                                    </small>

                                                <?php endif; ?>

                                            <?php endif; ?>

                                        </td>


                                        <td>

                                            <?= e(
                                                trim(
                                                    $payment['registrado_por_nombre']
                                                )
                                                    ?: '—'
                                            ) ?>

                                        </td>


                                        <td class="sale-actions">

                                            <?php if (
                                                $isRegistered
                                                && can('ventas.editar')
                                                && $s['estado'] !== 'ANULADA'
                                            ): ?>

                                                <button
                                                    type="button"
                                                    class="btn btn-danger btn-sm"
                                                    data-modal-open="payment-cancel-<?= (int) $payment['id'] ?>">
                                                    Anular
                                                </button>

                                            <?php endif; ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </div>


            <div class="modal-footer">

                <button
                    class="btn btn-secondary"
                    type="button"
                    data-modal-close>
                    Cerrar
                </button>

            </div>

        </div>

    </div>

    <?php if (
        can('ventas.eliminar')
        && $s['estado'] === 'FINALIZADA'
    ): ?>

        <div
            class="modal"
            id="sale-cancel-<?= (int) $s['id'] ?>">

            <div
                class="modal-backdrop"
                data-modal-close>
            </div>

            <div class="modal-dialog payment-dialog">

                <div class="modal-header">

                    <div>

                        <span class="eyebrow">
                            Ventas
                        </span>

                        <h2>
                            Anular venta
                        </h2>

                        <p class="muted">
                            Venta
                            <strong>
                                <?= e(
                                    $s['numero']
                                        ?? ('#' . (int) $s['id'])
                                ) ?>
                            </strong>
                        </p>

                    </div>

                    <button
                        class="modal-close"
                        type="button"
                        data-modal-close>
                        ×
                    </button>

                </div>


                <form
                    method="POST"
                    action="<?= url(
                                '/facturacion/'
                                    . (int) $s['id']
                                    . '/anular'
                            ) ?>">

                    <?= csrf_field() ?>


                    <div class="modal-body">

                        <div class="sale-cancel-summary">

                            <div>
                                <span>Total de la venta</span>

                                <strong>
                                    <?= $money(
                                        (float) $s['total']
                                    ) ?>
                                </strong>
                            </div>

                            <div>
                                <span>Pagado</span>

                                <strong>
                                    <?= $money(
                                        (float) ($s['pagado'] ?? 0)
                                    ) ?>
                                </strong>
                            </div>

                            <div>
                                <span>Saldo</span>

                                <strong>
                                    <?= $money(
                                        (float) ($s['saldo'] ?? 0)
                                    ) ?>
                                </strong>
                            </div>

                        </div>


                        <?php if (
                            (float) ($s['pagado'] ?? 0) > 0.004
                        ): ?>

                            <div class="alert alert-warning">

                                <strong>
                                    Esta venta tiene pagos registrados.
                                </strong>

                                <br>

                                Para anular la venta primero deberán
                                anularse todos los pagos vigentes.

                            </div>

                        <?php endif; ?>


                        <?php if (
                            !empty($s['documento_fiscal_id'])
                        ): ?>

                            <div class="alert alert-warning">

                                <strong>
                                    Esta venta posee un documento fiscal.
                                </strong>

                                <br>

                                No puede utilizarse la anulación simple.
                                Deberá realizarse el proceso fiscal
                                correspondiente.

                            </div>

                        <?php endif; ?>


                        <div class="alert alert-danger">

                            <strong>
                                Esta acción afecta el inventario.
                            </strong>

                            <br>

                            Los productos asociados a la venta serán
                            devueltos automáticamente a los mismos
                            inventarios y lotes desde los que salieron.

                            La venta y sus movimientos originales
                            permanecerán en el historial.

                        </div>


                        <label>

                            <span>
                                Motivo de anulación *
                            </span>

                            <textarea
                                name="motivo_anulacion"
                                rows="4"
                                maxlength="255"
                                required
                                placeholder="Indica el motivo de anulación de la venta"></textarea>

                        </label>

                    </div>


                    <div class="modal-footer">

                        <button
                            class="btn btn-secondary"
                            type="button"
                            data-modal-close>
                            Cancelar
                        </button>

                        <button
                            class="btn btn-danger"
                            type="submit">
                            Anular venta
                        </button>

                    </div>

                </form>

            </div>

        </div>

    <?php endif; ?>



    <!-- =========================================================
         MODALES: ANULAR PAGO
         ========================================================= -->

    <?php foreach (
        $salePayments
        as $payment
    ): ?>

        <?php
        $isRegistered =
            $payment['estado'] === 'REGISTRADO'
            && empty($payment['anulado_at']);
        ?>

        <?php if (
            $isRegistered
            && can('ventas.editar')
            && $s['estado'] !== 'ANULADA'
        ): ?>

            <div
                class="modal"
                id="payment-cancel-<?= (int) $payment['id'] ?>">

                <div
                    class="modal-backdrop"
                    data-modal-close>
                </div>


                <div class="modal-dialog payment-dialog">

                    <div class="modal-header">

                        <div>

                            <span class="eyebrow">
                                Cobros
                            </span>

                            <h2>
                                Anular pago
                            </h2>

                            <p class="muted">
                                <?= e(
                                    $s['numero']
                                        ?? ('#' . $saleId)
                                ) ?>
                                ·
                                <?= $money(
                                    $payment['monto']
                                ) ?>
                            </p>

                        </div>


                        <button
                            class="modal-close"
                            type="button"
                            data-modal-close>
                            ×
                        </button>

                    </div>


                    <form
                        method="POST"
                        action="<?= url(
                                    '/facturacion/'
                                        . $saleId
                                        . '/pagos/'
                                        . (int) $payment['id']
                                        . '/anular'
                                ) ?>">

                        <?= csrf_field() ?>


                        <div class="modal-body">

                            <div class="alert alert-danger">

                                El pago no será eliminado.
                                Se conservará en el historial
                                financiero como
                                <strong>ANULADO</strong>.

                            </div>


                            <label>

                                <span>
                                    Motivo de anulación *
                                </span>

                                <textarea
                                    name="motivo_anulacion"
                                    rows="4"
                                    maxlength="255"
                                    required
                                    placeholder="Indica por qué se anula este pago"></textarea>

                            </label>

                        </div>


                        <div class="modal-footer">

                            <button
                                class="btn btn-secondary"
                                type="button"
                                data-modal-close>
                                Cancelar
                            </button>

                            <button
                                class="btn btn-danger"
                                type="submit">
                                Anular pago
                            </button>

                        </div>

                    </form>

                </div>

            </div>

        <?php endif; ?>

    <?php endforeach; ?>


<?php endforeach; ?>

<script type="application/json" id="sale-products-data">
    <?= json_encode(array_values($products), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>
</script>
<script type="application/json" id="sale-services-data">
    <?= json_encode(array_values($services), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>
</script>

<script src="<?= url('/assets/js/views/facturacion.js') ?>" defer></script>