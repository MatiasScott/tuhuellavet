<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

class BillingService
{
    /**
     * Crea y finaliza una venta.
     *
     * REGLAS:
     * - Los precios se obtienen desde la base de datos.
     * - Los impuestos se obtienen desde la base de datos.
     * - Los totales siempre se recalculan en backend.
     * - El navegador únicamente puede enviar:
     *      producto/servicio
     *      cantidad
     *      descuento
     * - Cada detalle conserva un snapshot tributario.
     */
    public function createSale(
        array $data,
        int $env,
        int $by
    ): int {
        return Database::transaction(
            function (PDO $db) use ($data, $env, $by): int {

                /*
                |--------------------------------------------------------------------------
                | Entorno
                |--------------------------------------------------------------------------
                */

                $environment = $this->environment(
                    $db,
                    $env
                );

                /*
                |--------------------------------------------------------------------------
                | Detalles recibidos
                |--------------------------------------------------------------------------
                */

                $lines = $data['items'] ?? [];

                if (
                    !is_array($lines)
                    || $lines === []
                ) {
                    throw new RuntimeException(
                        'Agrega al menos un producto o servicio.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Propietario / paciente / datos fiscales
                |--------------------------------------------------------------------------
                */

                $ownerEnvironmentId =
                    !empty($data['propietario_entorno_id'])
                    ? (int) $data['propietario_entorno_id']
                    : null;

                $animalId =
                    !empty($data['animal_id'])
                    ? (int) $data['animal_id']
                    : null;

                $fiscalDataId =
                    !empty($data['datos_fiscales_id'])
                    ? (int) $data['datos_fiscales_id']
                    : null;

                /*
                |--------------------------------------------------------------------------
                | Validaciones relacionales
                |--------------------------------------------------------------------------
                */

                if ($ownerEnvironmentId !== null) {
                    $this->validateOwner(
                        $db,
                        $ownerEnvironmentId,
                        $env
                    );
                }

                if ($animalId !== null) {
                    $this->validatePatient(
                        $db,
                        $animalId,
                        $env,
                        $ownerEnvironmentId
                    );
                }

                if ($fiscalDataId !== null) {
                    if ($ownerEnvironmentId === null) {
                        throw new RuntimeException(
                            'No puedes seleccionar datos fiscales sin seleccionar un propietario.'
                        );
                    }

                    $this->validateFiscalData(
                        $db,
                        $fiscalDataId,
                        $ownerEnvironmentId
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Preparar detalles
                |--------------------------------------------------------------------------
                */

                $preparedLines = [];

                $subtotal = 0.00;
                $discount = 0.00;
                $tax = 0.00;
                $total = 0.00;

                foreach ($lines as $line) {

                    if (!is_array($line)) {
                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Cantidad
                    |--------------------------------------------------------------------------
                    */

                    $qty = round(
                        (float) ($line['cantidad'] ?? 0),
                        4
                    );

                    if ($qty <= 0) {
                        continue;
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Tipo de ítem
                    |--------------------------------------------------------------------------
                    */

                    $serviceId =
                        !empty($line['servicio_id'])
                        ? (int) $line['servicio_id']
                        : null;

                    $productId =
                        !empty($line['producto_id'])
                        ? (int) $line['producto_id']
                        : null;

                    /*
                     * XOR:
                     *
                     * Debe existir exactamente uno:
                     * - producto
                     * - servicio
                     */

                    if (
                        ($serviceId === null)
                        ===
                        ($productId === null)
                    ) {
                        throw new RuntimeException(
                            'Cada detalle debe corresponder a un producto o a un servicio, no a ambos.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Obtener configuración real
                    |--------------------------------------------------------------------------
                    */

                    if ($productId !== null) {
                        $item = $this->product(
                            $db,
                            $productId
                        );
                    } else {
                        $item = $this->service(
                            $db,
                            (int) $serviceId
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Precio
                    |--------------------------------------------------------------------------
                    */

                    $price = round(
                        (float) $item['precio'],
                        4
                    );

                    if ($price < 0) {
                        throw new RuntimeException(
                            'El precio configurado no puede ser negativo.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Subtotal bruto
                    |--------------------------------------------------------------------------
                    */

                    $lineSubtotal = round(
                        $qty * $price,
                        2
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Descuento
                    |--------------------------------------------------------------------------
                    |
                    | Por ahora el descuento enviado por el formulario
                    | representa un VALOR MONETARIO por línea.
                    |
                    */

                    $lineDiscount = round(
                        max(
                            0,
                            (float) (
                                $line['descuento']
                                ?? 0
                            )
                        ),
                        2
                    );

                    if ($lineDiscount > $lineSubtotal) {
                        throw new RuntimeException(
                            'El descuento no puede superar el subtotal del detalle.'
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Importe comercial después del descuento
                    |--------------------------------------------------------------------------
                    */

                    $taxableAmount = round(
                        $lineSubtotal - $lineDiscount,
                        2
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Impuesto
                    |--------------------------------------------------------------------------
                    */

                    $taxRate = round(
                        (float) $item['impuesto_porcentaje'],
                        4
                    );

                    if ($taxRate < 0) {
                        throw new RuntimeException(
                            'La tarifa de impuesto configurada no es válida.'
                        );
                    }

                    $priceIncludesTax =
                        (int) $item['precio_incluye_impuesto'] === 1;

                    /*
                    |--------------------------------------------------------------------------
                    | Precio CON impuesto incluido
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $taxRate > 0
                        && $priceIncludesTax
                    ) {
                        /*
                         * Ejemplo:
                         *
                         * Precio final = 18.50
                         * IVA = 15%
                         *
                         * Base =
                         * 18.50 / 1.15
                         */

                        $baseAmount = round(
                            $taxableAmount
                                /
                                (
                                    1
                                    +
                                    ($taxRate / 100)
                                ),
                            2
                        );

                        $lineTax = round(
                            $taxableAmount
                                - $baseAmount,
                            2
                        );

                        /*
                         * El impuesto ya estaba incluido.
                         */
                        $lineTotal =
                            $taxableAmount;
                    } else {

                        /*
                        |--------------------------------------------------------------------------
                        | Precio SIN impuesto incluido
                        |--------------------------------------------------------------------------
                        |
                        | Esto incluye también una tarifa explícita de 0%.
                        |
                        */

                        $baseAmount =
                            $taxableAmount;

                        $lineTax = round(
                            $baseAmount
                                * ($taxRate / 100),
                            2
                        );

                        $lineTotal = round(
                            $baseAmount
                                + $lineTax,
                            2
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Snapshot del detalle
                    |--------------------------------------------------------------------------
                    */

                    $preparedLines[] = [
                        'servicio_id'
                        => $serviceId,

                        'producto_id'
                        => $productId,

                        'impuesto_tarifa_id'
                        => (int) $item['impuesto_tarifa_id'],

                        'impuesto_codigo'
                        => $item['impuesto_codigo'],

                        'impuesto_nombre'
                        => $item['impuesto_nombre'],

                        'impuesto_porcentaje'
                        => $taxRate,

                        'descripcion'
                        => $item['nombre'],

                        'cantidad'
                        => $qty,

                        'precio_unitario'
                        => $price,

                        'precio_incluye_impuesto'
                        => $priceIncludesTax ? 1 : 0,

                        'subtotal'
                        => $lineSubtotal,

                        'base_imponible'
                        => $baseAmount,

                        'descuento'
                        => $lineDiscount,

                        'impuesto'
                        => $lineTax,

                        'total'
                        => $lineTotal,
                    ];

                    /*
                    |--------------------------------------------------------------------------
                    | Acumuladores
                    |--------------------------------------------------------------------------
                    */

                    $subtotal += $lineSubtotal;
                    $discount += $lineDiscount;
                    $tax += $lineTax;
                    $total += $lineTotal;
                }

                if ($preparedLines === []) {
                    throw new RuntimeException(
                        'Agrega al menos un detalle válido.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Totales definitivos
                |--------------------------------------------------------------------------
                */

                $subtotal = round(
                    $subtotal,
                    2
                );

                $discount = round(
                    $discount,
                    2
                );

                $tax = round(
                    $tax,
                    2
                );

                $total = round(
                    $total,
                    2
                );

                if ($total < 0) {
                    throw new RuntimeException(
                        'El total de la venta no puede ser negativo.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Número interno de venta
                |--------------------------------------------------------------------------
                */

                $number = $this->nextSaleNumber(
                    $db,
                    $env
                );

                /*
                |--------------------------------------------------------------------------
                | Venta simulada / productiva
                |--------------------------------------------------------------------------
                */

                $simulated =
                    empty($environment['es_productivo'])
                    ? 1
                    : 0;

                /*
                |--------------------------------------------------------------------------
                | Crear venta
                |--------------------------------------------------------------------------
                */

                $stmt = $db->prepare(
                    '
                    INSERT INTO ventas (
                        numero,
                        entorno_id,
                        propietario_entorno_id,
                        animal_id,
                        datos_fiscales_id,
                        fecha,
                        subtotal,
                        descuento,
                        impuestos,
                        total,
                        observaciones,
                        estado,
                        finalizada_at,
                        es_simulada,
                        creado_por
                    )
                    VALUES (
                        :numero,
                        :entorno,
                        :propietario,
                        :animal,
                        :datos_fiscales,
                        NOW(),
                        :subtotal,
                        :descuento,
                        :impuestos,
                        :total,
                        :observaciones,
                        "FINALIZADA",
                        NOW(),
                        :simulada,
                        :usuario
                    )
                    '
                );

                $stmt->execute([
                    'numero'
                    => $number,

                    'entorno'
                    => $env,

                    'propietario'
                    => $ownerEnvironmentId,

                    'animal'
                    => $animalId,

                    'datos_fiscales'
                    => $fiscalDataId,

                    'subtotal'
                    => $subtotal,

                    'descuento'
                    => $discount,

                    'impuestos'
                    => $tax,

                    'total'
                    => $total,

                    'observaciones'
                    => $this->nullableString(
                        $data['observaciones']
                            ?? null
                    ),

                    'simulada'
                    => $simulated,

                    'usuario'
                    => $by,
                ]);

                $saleId =
                    (int) $db->lastInsertId();

                /*
                |--------------------------------------------------------------------------
                | Insertar detalles
                |--------------------------------------------------------------------------
                */

                $detailStmt = $db->prepare(
                    '
                    INSERT INTO venta_detalles (
                        venta_id,
                        servicio_id,
                        producto_id,

                        impuesto_tarifa_id,
                        impuesto_codigo,
                        impuesto_nombre,
                        impuesto_porcentaje,

                        descripcion,
                        cantidad,
                        precio_unitario,
                        precio_incluye_impuesto,

                        subtotal,
                        base_imponible,
                        descuento,
                        impuesto,
                        total
                    )
                    VALUES (
                        :venta,
                        :servicio,
                        :producto,

                        :impuesto_tarifa,
                        :impuesto_codigo,
                        :impuesto_nombre,
                        :impuesto_porcentaje,

                        :descripcion,
                        :cantidad,
                        :precio,
                        :precio_incluye_impuesto,

                        :subtotal,
                        :base_imponible,
                        :descuento,
                        :impuesto,
                        :total
                    )
                    '
                );

                foreach ($preparedLines as $line) {

                    $detailStmt->execute([
                        'venta'
                        => $saleId,

                        'servicio'
                        => $line['servicio_id'],

                        'producto'
                        => $line['producto_id'],

                        'impuesto_tarifa'
                        => $line['impuesto_tarifa_id'],

                        'impuesto_codigo'
                        => $line['impuesto_codigo'],

                        'impuesto_nombre'
                        => $line['impuesto_nombre'],

                        'impuesto_porcentaje'
                        => $line['impuesto_porcentaje'],

                        'descripcion'
                        => $line['descripcion'],

                        'cantidad'
                        => $line['cantidad'],

                        'precio'
                        => $line['precio_unitario'],

                        'precio_incluye_impuesto'
                        => $line['precio_incluye_impuesto'],

                        'subtotal'
                        => $line['subtotal'],

                        'base_imponible'
                        => $line['base_imponible'],

                        'descuento'
                        => $line['descuento'],

                        'impuesto'
                        => $line['impuesto'],

                        'total'
                        => $line['total'],
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Salida automática de inventario
                |--------------------------------------------------------------------------
                |
                | Cada producto vendido genera movimientos SALIDA.
                | Para productos con lote se usa FEFO y se excluyen lotes vencidos.
                | Todo ocurre dentro de la misma transacción de la venta.
                |
                */

                foreach ($preparedLines as $line) {
                    if ($line['producto_id'] === null) {
                        continue;
                    }

                    $this->consumeProductStock(
                        $db,
                        (int) $line['producto_id'],
                        (float) $line['cantidad'],
                        $env,
                        $saleId,
                        $by
                    );
                }


                /*
                |--------------------------------------------------------------------------
                | Auditoría
                |--------------------------------------------------------------------------
                */

                (new AuditService())->log(
                    $by,
                    $env,
                    'VENTAS',
                    'CREAR',
                    'ventas',
                    $saleId,
                    null,
                    [
                        'numero'
                        => $number,

                        'estado'
                        => 'FINALIZADA',

                        'items'
                        => count($preparedLines),

                        'subtotal'
                        => $subtotal,

                        'descuento'
                        => $discount,

                        'impuestos'
                        => $tax,

                        'total'
                        => $total,

                        'es_simulada'
                        => $simulated,
                    ]
                );

                return $saleId;
            }
        );
    }

    /*
|--------------------------------------------------------------------------
| Pagos / Cobros
|--------------------------------------------------------------------------
*/

    public function registerPayment(
        int $saleId,
        array $data,
        int $env,
        int $by
    ): int {
        return Database::transaction(
            function (PDO $db) use (
                $saleId,
                $data,
                $env,
                $by
            ): int {

                /*
            |--------------------------------------------------------------------------
            | Bloquear venta
            |--------------------------------------------------------------------------
            */

                $stmt = $db->prepare(
                    '
                SELECT
                    id,
                    numero,
                    total,
                    estado
                FROM ventas
                WHERE id = :venta
                  AND entorno_id = :entorno
                LIMIT 1
                FOR UPDATE
                '
                );

                $stmt->execute([
                    'venta' => $saleId,
                    'entorno' => $env,
                ]);

                $sale = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$sale) {
                    throw new RuntimeException(
                        'Venta no encontrada.'
                    );
                }

                if ($sale['estado'] !== 'FINALIZADA') {
                    throw new RuntimeException(
                        'Solo se pueden registrar pagos en ventas finalizadas.'
                    );
                }


                /*
            |--------------------------------------------------------------------------
            | Monto
            |--------------------------------------------------------------------------
            */

                $amount = round(
                    (float) ($data['monto'] ?? 0),
                    2
                );

                if ($amount <= 0) {
                    throw new RuntimeException(
                        'El monto del pago debe ser mayor a cero.'
                    );
                }


                /*
            |--------------------------------------------------------------------------
            | Método de pago
            |--------------------------------------------------------------------------
            */

                $paymentMethodId =
                    (int) ($data['metodo_pago_id'] ?? 0);

                if ($paymentMethodId <= 0) {
                    throw new RuntimeException(
                        'Selecciona un método de pago.'
                    );
                }

                $stmt = $db->prepare(
                    '
                SELECT
                    id,
                    codigo,
                    nombre
                FROM metodos_pago
                WHERE id = :id
                  AND activo = 1
                LIMIT 1
                '
                );

                $stmt->execute([
                    'id' => $paymentMethodId,
                ]);

                $method = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$method) {
                    throw new RuntimeException(
                        'El método de pago seleccionado no está disponible.'
                    );
                }


                /*
            |--------------------------------------------------------------------------
            | Total ya pagado
            |--------------------------------------------------------------------------
            */

                $stmt = $db->prepare(
                    '
                SELECT
                    COALESCE(SUM(monto), 0)
                FROM pagos
                WHERE venta_id = :venta
                  AND estado = "REGISTRADO"
                  AND anulado_at IS NULL
                '
                );

                $stmt->execute([
                    'venta' => $saleId,
                ]);

                $paid = round(
                    (float) $stmt->fetchColumn(),
                    2
                );

                $saleTotal = round(
                    (float) $sale['total'],
                    2
                );

                $balance = round(
                    max(0, $saleTotal - $paid),
                    2
                );


                /*
            |--------------------------------------------------------------------------
            | Venta ya pagada
            |--------------------------------------------------------------------------
            */

                if ($balance <= 0.004) {
                    throw new RuntimeException(
                        'La venta ya se encuentra pagada.'
                    );
                }


                /*
            |--------------------------------------------------------------------------
            | Evitar sobrepago
            |--------------------------------------------------------------------------
            */

                if ($amount > $balance + 0.004) {
                    throw new RuntimeException(
                        'El pago supera el saldo pendiente. '
                            . 'Saldo disponible: $'
                            . number_format($balance, 2, '.', ',')
                            . '.'
                    );
                }


                /*
            |--------------------------------------------------------------------------
            | Referencia
            |--------------------------------------------------------------------------
            */

                $reference = trim(
                    (string) ($data['referencia'] ?? '')
                );

                $reference =
                    $reference !== ''
                    ? mb_substr($reference, 0, 150)
                    : null;


                /*
            |--------------------------------------------------------------------------
            | Registrar pago
            |--------------------------------------------------------------------------
            */

                $stmt = $db->prepare(
                    '
                INSERT INTO pagos (
                    venta_id,
                    metodo_pago_id,
                    monto,
                    fecha_pago,
                    referencia,
                    estado,
                    registrado_por
                )
                VALUES (
                    :venta,
                    :metodo,
                    :monto,
                    NOW(),
                    :referencia,
                    "REGISTRADO",
                    :usuario
                )
                '
                );

                $stmt->execute([
                    'venta' => $saleId,
                    'metodo' => $paymentMethodId,
                    'monto' => $amount,
                    'referencia' => $reference,
                    'usuario' => $by,
                ]);

                $paymentId =
                    (int) $db->lastInsertId();


                /*
            |--------------------------------------------------------------------------
            | Auditoría
            |--------------------------------------------------------------------------
            */

                (new AuditService())->log(
                    $by,
                    $env,
                    'VENTAS',
                    'REGISTRAR_PAGO',
                    'pagos',
                    $paymentId,
                    null,
                    [
                        'venta_id' => $saleId,
                        'venta_numero' => $sale['numero'],
                        'metodo_pago_id' => $paymentMethodId,
                        'metodo' => $method['codigo'],
                        'monto' => $amount,
                        'referencia' => $reference,
                        'saldo_anterior' => $balance,
                        'saldo_nuevo' => round(
                            max(0, $balance - $amount),
                            2
                        ),
                    ]
                );

                return $paymentId;
            }
        );
    }

    public function cancelPayment(
        int $saleId,
        int $paymentId,
        string $reason,
        int $env,
        int $by
    ): void {
        Database::transaction(
            function (PDO $db) use (
                $saleId,
                $paymentId,
                $reason,
                $env,
                $by
            ): void {

                $reason = trim($reason);

                if ($reason === '') {
                    throw new RuntimeException(
                        'Debes indicar el motivo de anulación del pago.'
                    );
                }

                if (mb_strlen($reason) > 255) {
                    throw new RuntimeException(
                        'El motivo de anulación es demasiado largo.'
                    );
                }


                /*
            |--------------------------------------------------------------------------
            | Bloquear venta
            |--------------------------------------------------------------------------
            */

                $stmt = $db->prepare(
                    '
                SELECT
                    id,
                    numero,
                    estado
                FROM ventas
                WHERE id = :venta
                  AND entorno_id = :entorno
                LIMIT 1
                FOR UPDATE
                '
                );

                $stmt->execute([
                    'venta' => $saleId,
                    'entorno' => $env,
                ]);

                $sale = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$sale) {
                    throw new RuntimeException(
                        'Venta no encontrada.'
                    );
                }

                if ($sale['estado'] === 'ANULADA') {
                    throw new RuntimeException(
                        'No se pueden modificar pagos de una venta anulada.'
                    );
                }


                /*
            |--------------------------------------------------------------------------
            | Bloquear pago
            |--------------------------------------------------------------------------
            */

                $stmt = $db->prepare(
                    '
                SELECT
                    pg.*,
                    mp.codigo AS metodo_codigo,
                    mp.nombre AS metodo_nombre

                FROM pagos pg

                INNER JOIN metodos_pago mp
                    ON mp.id = pg.metodo_pago_id

                WHERE pg.id = :pago
                  AND pg.venta_id = :venta

                LIMIT 1
                FOR UPDATE
                '
                );

                $stmt->execute([
                    'pago' => $paymentId,
                    'venta' => $saleId,
                ]);

                $payment =
                    $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$payment) {
                    throw new RuntimeException(
                        'Pago no encontrado.'
                    );
                }

                if (
                    $payment['estado'] !== 'REGISTRADO'
                    || $payment['anulado_at'] !== null
                ) {
                    throw new RuntimeException(
                        'Este pago ya fue anulado.'
                    );
                }


                /*
            |--------------------------------------------------------------------------
            | Anulación lógica
            |--------------------------------------------------------------------------
            */

                $stmt = $db->prepare(
                    '
                UPDATE pagos
                SET
                    estado = "ANULADO",
                    anulado_at = NOW(),
                    anulado_por = :usuario,
                    motivo_anulacion = :motivo
                WHERE id = :id
                  AND venta_id = :venta
                '
                );

                $stmt->execute([
                    'usuario' => $by,
                    'motivo' => $reason,
                    'id' => $paymentId,
                    'venta' => $saleId,
                ]);


                /*
            |--------------------------------------------------------------------------
            | Auditoría
            |--------------------------------------------------------------------------
            */

                (new AuditService())->log(
                    $by,
                    $env,
                    'VENTAS',
                    'ANULAR_PAGO',
                    'pagos',
                    $paymentId,
                    [
                        'estado' => $payment['estado'],
                        'monto' => $payment['monto'],
                        'metodo' => $payment['metodo_codigo'],
                        'referencia' => $payment['referencia'],
                    ],
                    [
                        'estado' => 'ANULADO',
                        'monto' => $payment['monto'],
                        'motivo_anulacion' => $reason,
                    ]
                );
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Facturación fiscal
    |--------------------------------------------------------------------------
    */

    public function queueInvoice(
        int $sale,
        int $env,
        int $by
    ): int {
        return Database::transaction(
            function (PDO $db) use (
                $sale,
                $env,
                $by
            ): int {

                /*
                |--------------------------------------------------------------------------
                | Obtener venta
                |--------------------------------------------------------------------------
                */

                $stmt = $db->prepare(
                    '
                    SELECT
                        v.*,
                        e.permite_facturacion_real

                    FROM ventas v

                    INNER JOIN entornos e
                        ON e.id = v.entorno_id

                    WHERE v.id = :venta
                      AND v.entorno_id = :entorno

                    LIMIT 1
                    '
                );

                $stmt->execute([
                    'venta'
                    => $sale,

                    'entorno'
                    => $env,
                ]);

                $saleRow =
                    $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$saleRow) {
                    throw new RuntimeException(
                        'Venta no encontrada.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Estado
                |--------------------------------------------------------------------------
                */

                if (
                    $saleRow['estado']
                    !== 'FINALIZADA'
                ) {
                    throw new RuntimeException(
                        'Solo se puede facturar una venta finalizada.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Entorno productivo
                |--------------------------------------------------------------------------
                */

                if (
                    empty($saleRow['permite_facturacion_real'])
                    ||
                    !empty($saleRow['es_simulada'])
                ) {
                    throw new RuntimeException(
                        'Este entorno no permite facturación real.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Idempotencia
                |--------------------------------------------------------------------------
                |
                | No permitimos crear una segunda factura
                | para la misma venta.
                |
                */

                $existing = $db->prepare(
                    '
                    SELECT id

                    FROM documentos_fiscales

                    WHERE venta_id = :venta
                      AND tipo_documento = "FACTURA"

                    LIMIT 1
                    '
                );

                $existing->execute([
                    'venta'
                    => $sale,
                ]);

                if ($existing->fetchColumn()) {
                    throw new RuntimeException(
                        'Esta venta ya tiene una factura fiscal asociada.'
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Documento fiscal
                |--------------------------------------------------------------------------
                */

                $stmt = $db->prepare(
                    '
                    INSERT INTO documentos_fiscales (
                        venta_id,
                        tipo_documento,
                        estado
                    )
                    VALUES (
                        :venta,
                        "FACTURA",
                        "PENDIENTE"
                    )
                    '
                );

                $stmt->execute([
                    'venta'
                    => $sale,
                ]);

                $documentId =
                    (int) $db->lastInsertId();

                /*
                |--------------------------------------------------------------------------
                | Cola Contífico
                |--------------------------------------------------------------------------
                */

                $stmt = $db->prepare(
                    '
                    INSERT INTO contifico_documentos (
                        documento_fiscal_id,
                        estado,
                        request_payload
                    )
                    VALUES (
                        :documento,
                        "PENDIENTE",
                        :payload
                    )
                    '
                );

                $stmt->execute([
                    'documento'
                    => $documentId,

                    'payload'
                    => json_encode(
                        [
                            'venta_id'
                            => $sale,

                            'documento_fiscal_id'
                            => $documentId,
                        ],
                        JSON_UNESCAPED_UNICODE
                            |
                            JSON_UNESCAPED_SLASHES
                    ),
                ]);

                /*
                |--------------------------------------------------------------------------
                | Auditoría
                |--------------------------------------------------------------------------
                */

                (new AuditService())->log(
                    $by,
                    $env,
                    'FACTURACION',
                    'FACTURAR',
                    'documentos_fiscales',
                    $documentId,
                    null,
                    [
                        'venta_id'
                        => $sale,

                        'estado'
                        => 'PENDIENTE',
                    ]
                );

                return $documentId;
            }
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Entorno
    |--------------------------------------------------------------------------
    */

    private function environment(
        PDO $db,
        int $env
    ): array {
        $stmt = $db->prepare(
            '
            SELECT
                id,
                es_productivo,
                permite_facturacion_real

            FROM entornos

            WHERE id = :id

            LIMIT 1
            '
        );

        $stmt->execute([
            'id'
            => $env,
        ]);

        $row =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new RuntimeException(
                'Entorno no encontrado.'
            );
        }

        return $row;
    }


    /*
    |--------------------------------------------------------------------------
    | Propietario
    |--------------------------------------------------------------------------
    */

    private function validateOwner(
        PDO $db,
        int $ownerEnvironmentId,
        int $env
    ): void {
        $stmt = $db->prepare(
            '
            SELECT id

            FROM propietarios_entornos

            WHERE id = :id
              AND entorno_id = :entorno

            LIMIT 1
            '
        );

        $stmt->execute([
            'id'
            => $ownerEnvironmentId,

            'entorno'
            => $env,
        ]);

        if (!$stmt->fetchColumn()) {
            throw new RuntimeException(
                'El propietario no pertenece al entorno activo.'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Paciente
    |--------------------------------------------------------------------------
    */

    private function validatePatient(
        PDO $db,
        int $animalId,
        int $env,
        ?int $ownerEnvironmentId
    ): void {
        $stmt = $db->prepare(
            '
            SELECT
                a.id,
                a.propietario_entorno_id

            FROM animales a

            WHERE a.id = :id
              AND a.entorno_id = :entorno

            LIMIT 1
            '
        );

        $stmt->execute([
            'id'
            => $animalId,

            'entorno'
            => $env,
        ]);

        $patient =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$patient) {
            throw new RuntimeException(
                'El paciente no pertenece al entorno activo.'
            );
        }

        if (
            $ownerEnvironmentId !== null
            &&
            (int) $patient['propietario_entorno_id'] !== $ownerEnvironmentId
        ) {
            throw new RuntimeException(
                'El paciente seleccionado no pertenece al propietario indicado.'
            );
        }
    }

    public function cancelSale(
        int $saleId,
        string $reason,
        int $environmentId,
        int $cancelledBy
    ): void {
        $db = Database::connection();

        $reason = trim($reason);

        if ($saleId <= 0) {
            throw new RuntimeException('La venta indicada no es válida.');
        }

        if ($environmentId <= 0) {
            throw new RuntimeException('No existe un entorno activo válido.');
        }

        if ($cancelledBy <= 0) {
            throw new RuntimeException('No se pudo identificar al usuario.');
        }

        if ($reason === '') {
            throw new RuntimeException(
                'Debes indicar el motivo de anulación de la venta.'
            );
        }

        if (mb_strlen($reason) > 255) {
            throw new RuntimeException(
                'El motivo de anulación no puede superar los 255 caracteres.'
            );
        }

        $db->beginTransaction();

        try {

            /*
        |--------------------------------------------------------------------------
        | 1. Bloquear y validar venta
        |--------------------------------------------------------------------------
        */

            $stmt = $db->prepare(
                'SELECT
                id,
                numero,
                entorno_id,
                total,
                estado,
                anulada_at,
                anulada_por,
                motivo_anulacion
             FROM ventas
             WHERE id = :id
               AND entorno_id = :entorno
             LIMIT 1
             FOR UPDATE'
            );

            $stmt->execute([
                'id' => $saleId,
                'entorno' => $environmentId,
            ]);

            $sale = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$sale) {
                throw new RuntimeException(
                    'La venta no existe o no pertenece al entorno activo.'
                );
            }

            if (
                $sale['estado'] === 'ANULADA'
                || !empty($sale['anulada_at'])
            ) {
                throw new RuntimeException(
                    'La venta ya se encuentra anulada.'
                );
            }

            if ($sale['estado'] !== 'FINALIZADA') {
                throw new RuntimeException(
                    'Solo se pueden anular ventas finalizadas.'
                );
            }


            /*
        |--------------------------------------------------------------------------
        | 2. No permitir anular si existen pagos vigentes
        |--------------------------------------------------------------------------
        */

            $stmt = $db->prepare(
                'SELECT
                COALESCE(SUM(monto), 0) AS total_pagado,
                COUNT(*) AS cantidad
             FROM pagos
             WHERE venta_id = :venta_id
               AND estado = "REGISTRADO"
               AND anulado_at IS NULL'
            );

            $stmt->execute([
                'venta_id' => $saleId,
            ]);

            $paymentInfo = $stmt->fetch(PDO::FETCH_ASSOC);

            $validPayments = (int) ($paymentInfo['cantidad'] ?? 0);
            $paidAmount = (float) ($paymentInfo['total_pagado'] ?? 0);

            if ($validPayments > 0) {
                throw new RuntimeException(
                    'La venta tiene pagos registrados por $'
                        . number_format($paidAmount, 2)
                        . '. Anule primero los pagos antes de anular la venta.'
                );
            }


            /*
        |--------------------------------------------------------------------------
        | 3. No permitir anulación simple si existe documento fiscal
        |--------------------------------------------------------------------------
        */

            $stmt = $db->prepare(
                'SELECT id, estado
             FROM documentos_fiscales
             WHERE venta_id = :venta_id
             LIMIT 1
             FOR UPDATE'
            );

            $stmt->execute([
                'venta_id' => $saleId,
            ]);

            $fiscalDocument = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($fiscalDocument) {
                throw new RuntimeException(
                    'La venta ya posee un documento fiscal asociado. '
                        . 'Debe utilizarse el proceso de anulación fiscal.'
                );
            }


            /*
        |--------------------------------------------------------------------------
        | 4. Obtener IDs de tipos de movimiento
        |--------------------------------------------------------------------------
        */

            $stmt = $db->prepare(
                'SELECT id, codigo
             FROM tipos_movimiento_inventario
             WHERE codigo IN ("SALIDA", "DEVOLUCION_VENTA")'
            );

            $stmt->execute();

            $movementTypes = [];

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $movementTypes[$row['codigo']] = (int) $row['id'];
            }

            if (empty($movementTypes['SALIDA'])) {
                throw new RuntimeException(
                    'No se encontró el tipo de movimiento SALIDA.'
                );
            }

            if (empty($movementTypes['DEVOLUCION_VENTA'])) {
                throw new RuntimeException(
                    'No se encontró el tipo de movimiento DEVOLUCION_VENTA.'
                );
            }

            $exitTypeId = $movementTypes['SALIDA'];
            $returnTypeId = $movementTypes['DEVOLUCION_VENTA'];


            /*
        |--------------------------------------------------------------------------
        | 5. Obtener las salidas originales de la venta
        |--------------------------------------------------------------------------
        |
        | No recalculamos FEFO.
        | Revertimos exactamente inventario/producto/lote de la salida original.
        |
        */

            $stmt = $db->prepare(
                'SELECT
                id,
                inventario_id,
                producto_id,
                lote_id,
                cantidad,
                costo_unitario
             FROM movimientos_inventario
             WHERE tipo_movimiento_id = :tipo_salida
               AND referencia_tipo = "VENTA"
               AND referencia_id = :venta_id
             ORDER BY id
             FOR UPDATE'
            );

            $stmt->execute([
                'tipo_salida' => $exitTypeId,
                'venta_id' => $saleId,
            ]);

            $saleExits = $stmt->fetchAll(PDO::FETCH_ASSOC);


            /*
        |--------------------------------------------------------------------------
        | 6. Crear devolución por cada salida original
        |--------------------------------------------------------------------------
        */

            $findExistingReturn = $db->prepare(
                'SELECT id
             FROM movimientos_inventario
             WHERE tipo_movimiento_id = :tipo_devolucion
               AND referencia_tipo = "ANULACION_VENTA"
               AND referencia_id = :movimiento_original
             LIMIT 1
             FOR UPDATE'
            );

            $insertReturn = $db->prepare(
                'INSERT INTO movimientos_inventario (
                inventario_id,
                producto_id,
                lote_id,
                tipo_movimiento_id,
                cantidad,
                costo_unitario,
                realizado_por,
                fecha_movimiento,
                referencia_tipo,
                referencia_id,
                observaciones,
                created_at
             ) VALUES (
                :inventario_id,
                :producto_id,
                :lote_id,
                :tipo_movimiento_id,
                :cantidad,
                :costo_unitario,
                :realizado_por,
                NOW(),
                "ANULACION_VENTA",
                :referencia_id,
                :observaciones,
                NOW()
             )'
            );

            $returnedMovements = [];

            foreach ($saleExits as $exit) {

                $originalMovementId = (int) $exit['id'];

                /*
             * Protección adicional contra una devolución duplicada.
             */
                $findExistingReturn->execute([
                    'tipo_devolucion' => $returnTypeId,
                    'movimiento_original' => $originalMovementId,
                ]);

                if ($findExistingReturn->fetch(PDO::FETCH_ASSOC)) {
                    throw new RuntimeException(
                        'Ya existe una devolución asociada al movimiento '
                            . $originalMovementId
                            . '. La anulación fue detenida para evitar duplicar stock.'
                    );
                }

                $quantity = (float) $exit['cantidad'];

                if ($quantity <= 0) {
                    throw new RuntimeException(
                        'El movimiento de inventario '
                            . $originalMovementId
                            . ' posee una cantidad inválida.'
                    );
                }

                $insertReturn->execute([
                    'inventario_id' => (int) $exit['inventario_id'],
                    'producto_id' => (int) $exit['producto_id'],
                    'lote_id' => $exit['lote_id'] !== null
                        ? (int) $exit['lote_id']
                        : null,
                    'tipo_movimiento_id' => $returnTypeId,
                    'cantidad' => $quantity,
                    'costo_unitario' => $exit['costo_unitario'],
                    'realizado_por' => $cancelledBy,
                    'referencia_id' => $originalMovementId,
                    'observaciones' =>
                    'Devolución automática por anulación de venta '
                        . ($sale['numero'] ?: ('#' . $saleId)),
                ]);

                $returnedMovements[] = [
                    'movimiento_salida_id' => $originalMovementId,
                    'movimiento_devolucion_id' => (int) $db->lastInsertId(),
                    'inventario_id' => (int) $exit['inventario_id'],
                    'producto_id' => (int) $exit['producto_id'],
                    'lote_id' => $exit['lote_id'] !== null
                        ? (int) $exit['lote_id']
                        : null,
                    'cantidad' => $quantity,
                ];
            }


            /*
        |--------------------------------------------------------------------------
        | 7. Marcar venta como ANULADA
        |--------------------------------------------------------------------------
        */

            $stmt = $db->prepare(
                'UPDATE ventas
             SET
                estado = "ANULADA",
                anulada_at = NOW(),
                anulada_por = :anulada_por,
                motivo_anulacion = :motivo
             WHERE id = :id
               AND entorno_id = :entorno'
            );

            $stmt->execute([
                'anulada_por' => $cancelledBy,
                'motivo' => $reason,
                'id' => $saleId,
                'entorno' => $environmentId,
            ]);


            /*
        |--------------------------------------------------------------------------
        | 8. Auditoría
        |--------------------------------------------------------------------------
        */

            (new AuditService())->log(
                $cancelledBy,
                $environmentId,
                'VENTAS',
                'ANULAR',
                'ventas',
                $saleId,
                [
                    'estado' => $sale['estado'],
                    'anulada_at' => $sale['anulada_at'],
                    'anulada_por' => $sale['anulada_por'],
                    'motivo_anulacion' => $sale['motivo_anulacion'],
                ],
                [
                    'estado' => 'ANULADA',
                    'motivo_anulacion' => $reason,
                    'inventario_revertido' => $returnedMovements,
                ]
            );


            /*
        |--------------------------------------------------------------------------
        | 9. Confirmar todo junto
        |--------------------------------------------------------------------------
        */

            $db->commit();
        } catch (Throwable $e) {

            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $e;
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Datos fiscales
    |--------------------------------------------------------------------------
    */

    private function validateFiscalData(
        PDO $db,
        int $fiscalDataId,
        int $ownerEnvironmentId
    ): void {
        $stmt = $db->prepare(
            '
        SELECT pdf.id

        FROM propietarios_datos_fiscales pdf

        INNER JOIN propietarios_entornos pe
            ON pe.propietario_id = pdf.propietario_id

        WHERE pdf.id = :id
          AND pe.id = :propietario_entorno
          AND pdf.activo = 1

        LIMIT 1
        '
        );

        $stmt->execute([
            'id' => $fiscalDataId,
            'propietario_entorno' => $ownerEnvironmentId,
        ]);

        if (!$stmt->fetchColumn()) {
            throw new RuntimeException(
                'Los datos fiscales seleccionados no corresponden al propietario indicado.'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Producto
    |--------------------------------------------------------------------------
    */

    private function product(
        PDO $db,
        int $id
    ): array {
        $stmt = $db->prepare(
            '
            SELECT
                p.id,
                p.nombre,
                p.precio_venta AS precio,
                p.precio_incluye_impuesto,
                p.impuesto_tarifa_id,
                p.controla_lote,

                i.codigo AS impuesto_codigo,

                CONCAT(
                    i.nombre,
                    " - ",
                    it.nombre
                ) AS impuesto_nombre,

                it.porcentaje
                    AS impuesto_porcentaje

            FROM productos p

            LEFT JOIN impuesto_tarifas it
                ON it.id = p.impuesto_tarifa_id
               AND it.activo = 1
               AND it.fecha_desde <= CURDATE()
               AND (
                    it.fecha_hasta IS NULL
                    OR it.fecha_hasta >= CURDATE()
               )

            LEFT JOIN impuestos i
                ON i.id = it.impuesto_id
               AND i.activo = 1

            WHERE p.id = :id
              AND p.activo = 1
              AND p.deleted_at IS NULL

            LIMIT 1
            '
        );

        $stmt->execute([
            'id'
            => $id,
        ]);

        $row =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new RuntimeException(
                'Producto no disponible.'
            );
        }

        if ($row['precio'] === null) {
            throw new RuntimeException(
                'El producto "'
                    . $row['nombre']
                    . '" no tiene precio de venta configurado.'
            );
        }

        /*
         * NULL NO significa 0%.
         *
         * Para 0% debe existir una tarifa
         * explícitamente configurada.
         */

        if (
            $row['impuesto_tarifa_id']
            === null
        ) {
            throw new RuntimeException(
                'El producto "'
                    . $row['nombre']
                    . '" no tiene un impuesto configurado.'
            );
        }

        if (
            $row['impuesto_porcentaje']
            === null
            ||
            $row['impuesto_codigo']
            === null
        ) {
            throw new RuntimeException(
                'La configuración tributaria del producto "'
                    . $row['nombre']
                    . '" no es válida o no está vigente.'
            );
        }

        return $row;
    }


    /*
    |--------------------------------------------------------------------------
    | Servicio
    |--------------------------------------------------------------------------
    */

    private function service(
        PDO $db,
        int $id
    ): array {
        $stmt = $db->prepare(
            '
            SELECT
                s.id,
                s.nombre,
                s.precio_base AS precio,
                s.precio_incluye_impuesto,
                s.impuesto_tarifa_id,

                i.codigo AS impuesto_codigo,

                CONCAT(
                    i.nombre,
                    " - ",
                    it.nombre
                ) AS impuesto_nombre,

                it.porcentaje
                    AS impuesto_porcentaje

            FROM servicios s

            LEFT JOIN impuesto_tarifas it
                ON it.id = s.impuesto_tarifa_id
               AND it.activo = 1
               AND it.fecha_desde <= CURDATE()
               AND (
                    it.fecha_hasta IS NULL
                    OR it.fecha_hasta >= CURDATE()
               )

            LEFT JOIN impuestos i
                ON i.id = it.impuesto_id
               AND i.activo = 1

            WHERE s.id = :id
              AND s.activo = 1

            LIMIT 1
            '
        );

        $stmt->execute([
            'id'
            => $id,
        ]);

        $row =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new RuntimeException(
                'Servicio no disponible.'
            );
        }

        if ($row['precio'] === null) {
            throw new RuntimeException(
                'El servicio "'
                    . $row['nombre']
                    . '" no tiene precio configurado.'
            );
        }

        /*
         * NULL = configuración pendiente.
         *
         * Para tarifa 0% debe existir una tarifa
         * explícita de 0%.
         */

        if (
            $row['impuesto_tarifa_id']
            === null
        ) {
            throw new RuntimeException(
                'El servicio "'
                    . $row['nombre']
                    . '" no tiene un impuesto configurado.'
            );
        }

        if (
            $row['impuesto_porcentaje']
            === null
            ||
            $row['impuesto_codigo']
            === null
        ) {
            throw new RuntimeException(
                'La configuración tributaria del servicio "'
                    . $row['nombre']
                    . '" no es válida o no está vigente.'
            );
        }

        return $row;
    }


    /*
    |--------------------------------------------------------------------------
    | Inventario de venta / FEFO
    |--------------------------------------------------------------------------
    */

    private function consumeProductStock(
        PDO $db,
        int $productId,
        float $quantity,
        int $env,
        int $saleId,
        int $by
    ): void {
        $quantity = round($quantity, 4);

        if ($quantity <= 0) {
            throw new RuntimeException(
                'La cantidad a descontar del inventario no es válida.'
            );
        }

        $product = $this->inventoryProduct(
            $db,
            $productId
        );

        if ((int) $product['controla_lote'] === 1) {
            $this->consumeProductStockFefo(
                $db,
                $product,
                $quantity,
                $env,
                $saleId,
                $by
            );

            return;
        }

        $this->consumeProductStockWithoutLot(
            $db,
            $product,
            $quantity,
            $env,
            $saleId,
            $by
        );
    }


    private function inventoryProduct(
        PDO $db,
        int $productId
    ): array {
        $stmt = $db->prepare(
            '
            SELECT
                id,
                nombre,
                controla_lote

            FROM productos

            WHERE id = :id
              AND activo = 1
              AND deleted_at IS NULL

            LIMIT 1
            '
        );

        $stmt->execute([
            'id' => $productId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new RuntimeException(
                'Producto no disponible para movimiento de inventario.'
            );
        }

        return $row;
    }


    private function saleMovementTypeId(
        PDO $db
    ): int {
        $stmt = $db->prepare(
            '
            SELECT id

            FROM tipos_movimiento_inventario

            WHERE codigo = "SALIDA"
              AND factor = -1

            LIMIT 1
            '
        );

        $stmt->execute();

        $id = (int) $stmt->fetchColumn();

        if ($id <= 0) {
            throw new RuntimeException(
                'No existe el tipo de movimiento SALIDA en inventario.'
            );
        }

        return $id;
    }


    private function consumeProductStockFefo(
        PDO $db,
        array $product,
        float $quantity,
        int $env,
        int $saleId,
        int $by
    ): void {
        /*
         * Bloqueamos las filas inventario_producto del producto.
         * Esto serializa ventas concurrentes del mismo producto
         * dentro de los inventarios activos del entorno.
         */
        $lock = $db->prepare(
            '
            SELECT
                ip.inventario_id

            FROM inventario_productos ip

            INNER JOIN inventarios i
                ON i.id = ip.inventario_id

            WHERE i.entorno_id = :entorno
              AND i.activo = 1
              AND ip.producto_id = :producto
              AND ip.activo = 1

            ORDER BY ip.inventario_id

            FOR UPDATE
            '
        );

        $lock->execute([
            'entorno' => $env,
            'producto' => (int) $product['id'],
        ]);

        $inventoryIds = $lock->fetchAll(
            PDO::FETCH_COLUMN
        );

        if ($inventoryIds === []) {
            throw new RuntimeException(
                'El producto "'
                    . $product['nombre']
                    . '" no está habilitado en ningún inventario activo del entorno.'
            );
        }

        /*
         * Saldo por inventario + lote.
         *
         * FEFO:
         * 1. vencimiento más próximo;
         * 2. inventario menor;
         * 3. lote menor.
         *
         * Los lotes vencidos no son vendibles.
         * Los lotes sin fecha se dejan al final.
         */
        $stmt = $db->prepare(
            '
            SELECT
                mi.inventario_id,
                mi.lote_id,
                lp.numero_lote,
                lp.fecha_vencimiento,

                ROUND(
                    SUM(
                        tm.factor
                        * mi.cantidad
                    ),
                    4
                ) AS stock

            FROM movimientos_inventario mi

            INNER JOIN tipos_movimiento_inventario tm
                ON tm.id = mi.tipo_movimiento_id

            INNER JOIN inventarios i
                ON i.id = mi.inventario_id

            INNER JOIN inventario_productos ip
                ON ip.inventario_id = mi.inventario_id
               AND ip.producto_id = mi.producto_id
               AND ip.activo = 1

            INNER JOIN lotes_producto lp
                ON lp.id = mi.lote_id
               AND lp.producto_id = mi.producto_id

            WHERE i.entorno_id = :entorno
              AND i.activo = 1
              AND mi.producto_id = :producto
              AND mi.lote_id IS NOT NULL
              AND (
                    lp.fecha_vencimiento IS NULL
                    OR lp.fecha_vencimiento >= CURDATE()
              )

            GROUP BY
                mi.inventario_id,
                mi.lote_id,
                lp.numero_lote,
                lp.fecha_vencimiento

            HAVING stock > 0

            ORDER BY
                CASE
                    WHEN lp.fecha_vencimiento IS NULL
                    THEN 1
                    ELSE 0
                END,
                lp.fecha_vencimiento ASC,
                mi.inventario_id ASC,
                mi.lote_id ASC
            '
        );

        $stmt->execute([
            'entorno' => $env,
            'producto' => (int) $product['id'],
        ]);

        $sources = $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

        $available = 0.0;

        foreach ($sources as $source) {
            $available += (float) $source['stock'];
        }

        $available = round($available, 4);

        if ($available + 0.00001 < $quantity) {
            throw new RuntimeException(
                'Stock insuficiente para "'
                    . $product['nombre']
                    . '". Disponible en lotes vigentes: '
                    . number_format($available, 4, '.', '')
                    . '. Solicitado: '
                    . number_format($quantity, 4, '.', '')
                    . '.'
            );
        }

        $movementTypeId =
            $this->saleMovementTypeId($db);

        $remaining = $quantity;

        foreach ($sources as $source) {
            if ($remaining <= 0.00001) {
                break;
            }

            $sourceStock =
                round((float) $source['stock'], 4);

            if ($sourceStock <= 0) {
                continue;
            }

            $take = round(
                min($remaining, $sourceStock),
                4
            );

            if ($take <= 0) {
                continue;
            }

            $this->insertSaleStockMovement(
                $db,
                (int) $source['inventario_id'],
                (int) $product['id'],
                (int) $source['lote_id'],
                $movementTypeId,
                $take,
                $saleId,
                $by,
                'Salida automática por venta · FEFO · lote '
                    . $source['numero_lote']
            );

            $remaining = round(
                $remaining - $take,
                4
            );
        }

        if ($remaining > 0.00001) {
            throw new RuntimeException(
                'No fue posible completar la asignación FEFO del producto "'
                    . $product['nombre']
                    . '".'
            );
        }
    }


    private function consumeProductStockWithoutLot(
        PDO $db,
        array $product,
        float $quantity,
        int $env,
        int $saleId,
        int $by
    ): void {
        /*
         * Igual que en FEFO, bloqueamos la relación
         * inventario_producto para evitar dos ventas simultáneas
         * consumiendo el mismo saldo.
         */
        $lock = $db->prepare(
            '
            SELECT
                ip.inventario_id

            FROM inventario_productos ip

            INNER JOIN inventarios i
                ON i.id = ip.inventario_id

            WHERE i.entorno_id = :entorno
              AND i.activo = 1
              AND ip.producto_id = :producto
              AND ip.activo = 1

            ORDER BY ip.inventario_id

            FOR UPDATE
            '
        );

        $lock->execute([
            'entorno' => $env,
            'producto' => (int) $product['id'],
        ]);

        $inventoryIds = $lock->fetchAll(
            PDO::FETCH_COLUMN
        );

        if ($inventoryIds === []) {
            throw new RuntimeException(
                'El producto "'
                    . $product['nombre']
                    . '" no está habilitado en ningún inventario activo del entorno.'
            );
        }

        /*
         * Para productos que NO controlan lote se calcula
         * el saldo por inventario usando todos sus movimientos.
         */
        $stmt = $db->prepare(
            '
            SELECT
                mi.inventario_id,

                ROUND(
                    SUM(
                        tm.factor
                        * mi.cantidad
                    ),
                    4
                ) AS stock

            FROM movimientos_inventario mi

            INNER JOIN tipos_movimiento_inventario tm
                ON tm.id = mi.tipo_movimiento_id

            INNER JOIN inventarios i
                ON i.id = mi.inventario_id

            INNER JOIN inventario_productos ip
                ON ip.inventario_id = mi.inventario_id
               AND ip.producto_id = mi.producto_id
               AND ip.activo = 1

            WHERE i.entorno_id = :entorno
              AND i.activo = 1
              AND mi.producto_id = :producto

            GROUP BY
                mi.inventario_id

            HAVING stock > 0

            ORDER BY
                mi.inventario_id ASC
            '
        );

        $stmt->execute([
            'entorno' => $env,
            'producto' => (int) $product['id'],
        ]);

        $sources = $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

        $available = 0.0;

        foreach ($sources as $source) {
            $available += (float) $source['stock'];
        }

        $available = round($available, 4);

        if ($available + 0.00001 < $quantity) {
            throw new RuntimeException(
                'Stock insuficiente para "'
                    . $product['nombre']
                    . '". Disponible: '
                    . number_format($available, 4, '.', '')
                    . '. Solicitado: '
                    . number_format($quantity, 4, '.', '')
                    . '.'
            );
        }

        $movementTypeId =
            $this->saleMovementTypeId($db);

        $remaining = $quantity;

        foreach ($sources as $source) {
            if ($remaining <= 0.00001) {
                break;
            }

            $sourceStock =
                round((float) $source['stock'], 4);

            if ($sourceStock <= 0) {
                continue;
            }

            $take = round(
                min($remaining, $sourceStock),
                4
            );

            if ($take <= 0) {
                continue;
            }

            $this->insertSaleStockMovement(
                $db,
                (int) $source['inventario_id'],
                (int) $product['id'],
                null,
                $movementTypeId,
                $take,
                $saleId,
                $by,
                'Salida automática por venta'
            );

            $remaining = round(
                $remaining - $take,
                4
            );
        }

        if ($remaining > 0.00001) {
            throw new RuntimeException(
                'No fue posible completar la salida de inventario del producto "'
                    . $product['nombre']
                    . '".'
            );
        }
    }


    private function insertSaleStockMovement(
        PDO $db,
        int $inventoryId,
        int $productId,
        ?int $lotId,
        int $movementTypeId,
        float $quantity,
        int $saleId,
        int $by,
        string $notes
    ): void {
        $stmt = $db->prepare(
            '
            INSERT INTO movimientos_inventario (
                inventario_id,
                producto_id,
                lote_id,
                tipo_movimiento_id,
                cantidad,
                costo_unitario,
                realizado_por,
                fecha_movimiento,
                referencia_tipo,
                referencia_id,
                observaciones
            )
            VALUES (
                :inventario,
                :producto,
                :lote,
                :tipo,
                :cantidad,
                NULL,
                :usuario,
                NOW(),
                "VENTA",
                :venta,
                :observaciones
            )
            '
        );

        $stmt->execute([
            'inventario' => $inventoryId,
            'producto' => $productId,
            'lote' => $lotId,
            'tipo' => $movementTypeId,
            'cantidad' => $quantity,
            'usuario' => $by,
            'venta' => $saleId,
            'observaciones' => $notes,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | Secuencia de ventas
    |--------------------------------------------------------------------------
    */

    private function nextSaleNumber(
        PDO $db,
        int $env
    ): string {
        /*
         * Garantiza que exista la secuencia.
         */

        $stmt = $db->prepare(
            '
            INSERT INTO venta_secuencias (
                entorno_id,
                ultimo_numero
            )
            VALUES (
                :entorno,
                0
            )
            ON DUPLICATE KEY UPDATE
                entorno_id = entorno_id
            '
        );

        $stmt->execute([
            'entorno'
            => $env,
        ]);

        /*
         * Bloqueamos la fila durante
         * la transacción.
         */

        $stmt = $db->prepare(
            '
            SELECT ultimo_numero

            FROM venta_secuencias

            WHERE entorno_id = :entorno

            FOR UPDATE
            '
        );

        $stmt->execute([
            'entorno'
            => $env,
        ]);

        $current =
            (int) $stmt->fetchColumn();

        $next =
            $current + 1;

        /*
         * Actualizar secuencia.
         */

        $stmt = $db->prepare(
            '
            UPDATE venta_secuencias

            SET ultimo_numero = :numero

            WHERE entorno_id = :entorno
            '
        );

        $stmt->execute([
            'numero'
            => $next,

            'entorno'
            => $env,
        ]);

        return 'V-'
            . str_pad(
                (string) $next,
                8,
                '0',
                STR_PAD_LEFT
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Nullable string
    |--------------------------------------------------------------------------
    */

    private function nullableString(
        mixed $value
    ): ?string {
        $value = trim(
            (string) $value
        );

        return $value !== ''

            ? $value
            : null;
    }
}
