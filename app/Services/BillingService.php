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
    public function assignFiscalData(int $saleId, int $fiscalId, int $env, int $by, bool $consumerFinal = false): void
    {
        if (min($saleId, $env, $by) <= 0 || (!$consumerFinal && $fiscalId <= 0) || ($consumerFinal && $fiscalId !== 0)) {
            throw new RuntimeException('Datos de asignación inválidos.');
        }
        Database::transaction(function (PDO $db) use ($saleId, $fiscalId, $env, $by, $consumerFinal): void {
            $q = $db->prepare('SELECT v.* FROM ventas v JOIN entornos e ON e.id=v.entorno_id WHERE v.id=? AND v.entorno_id=? AND e.activo=1 AND e.deleted_at IS NULL AND e.es_productivo=1 AND e.permite_facturacion_real=1 FOR UPDATE');
            $q->execute([$saleId, $env]);
            $sale = $q->fetch(PDO::FETCH_ASSOC);
            if (!$sale || $sale['estado'] !== 'FINALIZADA' || (int)$sale['es_simulada'] !== 0) {
                throw new RuntimeException('Venta no disponible para asignación fiscal.');
            }
            $q = $db->prepare('SELECT id FROM documentos_fiscales WHERE venta_id=? FOR UPDATE');
            $q->execute([$saleId]);
            if ($q->fetchColumn() !== false) {
                throw new RuntimeException('La venta ya tiene un documento fiscal.');
            }
            if ($consumerFinal) {
                $before = ['datos_fiscales_id' => $sale['datos_fiscales_id'], 'consumidor_final' => (int)$sale['consumidor_final']];
                $q = $db->prepare('UPDATE ventas SET datos_fiscales_id=NULL, consumidor_final=1 WHERE id=? AND entorno_id=?');
                $q->execute([$saleId, $env]);
                (new AuditService())->log($by, $env, 'facturacion', 'ASIGNAR_DATOS_FISCALES', 'ventas', $saleId, $before, ['datos_fiscales_id' => null, 'consumidor_final' => 1]);
                return;
            }
            $q = $db->prepare("SELECT pe.id FROM propietarios_datos_fiscales f JOIN propietarios p ON p.id=f.propietario_id JOIN propietarios_entornos pe ON pe.propietario_id=p.id JOIN tipos_identificacion t ON t.id=f.tipo_identificacion_id WHERE f.id=? AND pe.entorno_id=? AND f.activo=1 AND p.activo=1 AND p.deleted_at IS NULL AND pe.activo=1 AND t.codigo IN ('CEDULA','RUC','PASAPORTE') FOR UPDATE");
            $q->execute([$fiscalId, $env]);
            $owners = $q->fetchAll(PDO::FETCH_COLUMN);
            if (count($owners) !== 1) {
                throw new RuntimeException('Datos fiscales no disponibles en este entorno.');
            }
            $owner = (int)$owners[0];
            if ($sale['animal_id'] !== null) {
                $this->validatePatient($db, (int)$sale['animal_id'], $env, $owner);
            }
            $before = ['propietario_entorno_id' => $sale['propietario_entorno_id'], 'datos_fiscales_id' => $sale['datos_fiscales_id'], 'consumidor_final' => (int)$sale['consumidor_final']];
            $after = ['propietario_entorno_id' => $owner, 'datos_fiscales_id' => $fiscalId, 'consumidor_final' => 0];
            if ((int)$before['propietario_entorno_id'] === $owner && (int)$before['datos_fiscales_id'] === $fiscalId && !$before['consumidor_final']) return;
            $q = $db->prepare('UPDATE ventas SET propietario_entorno_id=?, datos_fiscales_id=?, consumidor_final=0 WHERE id=? AND entorno_id=?');
            $q->execute([$owner, $fiscalId, $saleId, $env]);
            (new AuditService())->log($by, $env, 'facturacion', 'ASIGNAR_DATOS_FISCALES', 'ventas', $saleId, $before, $after);
        });
    }

    public static function parseConsumerFinal(mixed $value): bool
    {
        if (!in_array($value, ['0', '1', 0, 1], true)) {
            throw new RuntimeException('Modalidad fiscal inválida.');
        }
        return (string)$value === '1';
    }

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

                $consumerFinal = self::parseConsumerFinal($data['consumidor_final'] ?? '0');
                if ($consumerFinal && (empty($environment['es_productivo']) || empty($environment['permite_facturacion_real']))) {
                    throw new RuntimeException('Consumidor final no disponible en este entorno.');
                }
                if ($consumerFinal && $fiscalDataId !== null) {
                    throw new RuntimeException('Selecciona una sola modalidad fiscal.');
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

                $clinicalBilling = new ClinicalBillingService();
                $usedClinicalMovements = [];

                $subtotal = 0.00;
                $discount = 0.00;
                $tax = 0.00;
                $total = 0.00;

                foreach ($lines as $line) {

                    if (!is_array($line)) {
                        continue;
                    }

                    $clinicalMovement = null;
                    $clinicalMovementId = null;

                    if (
                        isset($line['consumo_clinico_id'])
                        && $line['consumo_clinico_id'] !== ''
                    ) {
                        $clinicalMovementId = filter_var(
                            $line['consumo_clinico_id'],
                            FILTER_VALIDATE_INT,
                            ['options' => ['min_range' => 1]]
                        );

                        if ($clinicalMovementId === false) {
                            throw new RuntimeException(
                                'Consumo clínico no válido.'
                            );
                        }

                        if (isset($usedClinicalMovements[$clinicalMovementId])) {
                            throw new RuntimeException(
                                'No puedes facturar dos veces el mismo consumo clínico.'
                            );
                        }

                        if (
                            !empty($line['servicio_id'])
                            || !empty($line['producto_id'])
                            || !empty($line['inventario_id'])
                        ) {
                            throw new RuntimeException(
                                'El consumo clínico no puede combinarse con otro origen.'
                            );
                        }

                        $clinicalMovement = $clinicalBilling->lockAvailable(
                            $db,
                            $clinicalMovementId,
                            $env,
                            $animalId,
                            $ownerEnvironmentId
                        );

                        $usedClinicalMovements[$clinicalMovementId] = true;

                        $line['producto_id'] = (int) $clinicalMovement['producto_id'];
                        $line['cantidad'] = $clinicalMovement['cantidad'];
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

                    $inventoryId = null;

                    if ($clinicalMovement !== null) {
                        $inventoryId = (int) $clinicalMovement['inventario_id'];

                        $item = $this->product(
                            $db,
                            (int) $clinicalMovement['producto_id']
                        );
                    } elseif ($productId !== null) {
                        $rawInventory = $line['inventario_id'] ?? null;
                        if ($rawInventory === null || $rawInventory === '') {
                            // Compatibilidad con llamadas existentes: solo si hay un único origen habilitado.
                            $origins = $db->prepare('SELECT ip.inventario_id FROM inventario_productos ip JOIN inventarios i ON i.id=ip.inventario_id WHERE ip.producto_id=? AND ip.activo=1 AND i.activo=1 AND i.entorno_id=? ORDER BY ip.inventario_id FOR UPDATE');
                            $origins->execute([$productId, $env]);
                            $ids = $origins->fetchAll(PDO::FETCH_COLUMN);
                            if (count($ids) !== 1) {
                                throw new RuntimeException('Selecciona el inventario de salida del producto.');
                            }
                            $inventoryId = (int) $ids[0];
                        } else {
                            $inventoryId = filter_var($rawInventory, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                            if ($inventoryId === false) {
                                throw new RuntimeException('Inventario de salida no válido.');
                            }
                        }
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
                        'inventario_id' => $inventoryId,

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

                        'consumo_clinico_id'
                        => $clinicalMovementId,
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
                        consumidor_final,
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
                        :consumidor_final,
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
                    'consumidor_final' => (int)$consumerFinal,

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

                    $detailId = (int) $db->lastInsertId();

                    if ($line['consumo_clinico_id'] !== null) {
                        $clinicalBilling->link(
                            $db,
                            $detailId,
                            (int) $line['consumo_clinico_id'],
                            (float) $line['cantidad'],
                            $by
                        );
                    }
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
                    if (
                        $line['producto_id'] === null
                        || $line['consumo_clinico_id'] !== null
                    ) {
                        continue;
                    }

                    $this->consumeProductStock(
                        $db,
                        (int) $line['producto_id'],
                        (float) $line['cantidad'],
                        $env,
                        $saleId,
                        $by,
                        $line['inventario_id']
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
                        'consumidor_final' => (int)$consumerFinal,
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

    public static function canRetryInvoice(array $document): bool
    {
        if (
            strtoupper(trim((string) ($document['tipo_documento'] ?? ''))) !== 'FACTURA'
            || strtoupper(trim((string) ($document['estado'] ?? ''))) !== 'ERROR'
            || strtoupper(trim((string) ($document['iconta_estado'] ?? ''))) !== 'ERROR'
            || strtoupper(trim((string) ($document['venta_estado'] ?? ''))) !== 'FINALIZADA'
            || (int) ($document['permite_facturacion_real'] ?? 0) !== 1
            || (int) ($document['iconta_intentos'] ?? -1) !== 0
            || trim((string) ($document['iconta_id_documento'] ?? '')) !== ''
            || !empty($document['iconta_enviado_at'])
            || (int) ($document['iconta_documento_id'] ?? 0) <= 0
        ) {
            return false;
        }

        return true;
    }

    /**
     * Reintenta una factura existente que falló antes
     * de iniciar su generación remota en iConta.
     *
     * Nunca crea otro documento fiscal.
     */
    public function retryInvoice(
        int $fiscalDocumentId,
        int $env,
        int $by
    ): void {
        if ($fiscalDocumentId <= 0 || $env <= 0 || $by <= 0) {
            throw new RuntimeException(
                'Los datos del reintento fiscal no son válidos.'
            );
        }

        $db = Database::connection();

        $stmt = $db->prepare(
            '
        SELECT
            df.id AS documento_fiscal_id,
            df.tipo_documento,
            df.estado,
            v.id AS venta_id,
            v.estado AS venta_estado,
            v.entorno_id,
            e.permite_facturacion_real,
            idoc.id AS iconta_documento_id,
            idoc.estado AS iconta_estado,
            idoc.iconta_id_documento,
            idoc.intentos AS iconta_intentos,
            idoc.ultimo_error AS iconta_ultimo_error,
            idoc.enviado_at AS iconta_enviado_at

        FROM documentos_fiscales df

        INNER JOIN ventas v
            ON v.id = df.venta_id

        INNER JOIN entornos e
            ON e.id = v.entorno_id

        INNER JOIN iconta_documentos idoc
            ON idoc.documento_fiscal_id = df.id

        WHERE df.id = :documento
          AND v.entorno_id = :entorno

        LIMIT 1
        '
        );

        $stmt->execute([
            'documento' => $fiscalDocumentId,
            'entorno' => $env,
        ]);

        $document = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$document) {
            throw new RuntimeException(
                'No se encontró el documento fiscal en el entorno activo.'
            );
        }

        if (
            strtoupper(trim((string) $document['tipo_documento']))
            !== 'FACTURA'
        ) {
            throw new RuntimeException(
                'El documento no corresponde a una factura.'
            );
        }

        if (
            strtoupper(trim((string) $document['venta_estado']))
            !== 'FINALIZADA'
        ) {
            throw new RuntimeException(
                'La venta debe estar finalizada para reintentar la factura.'
            );
        }

        if ((int) $document['permite_facturacion_real'] !== 1) {
            throw new RuntimeException(
                'El entorno no permite facturación real.'
            );
        }

        if (
            strtoupper(trim((string) $document['estado']))
            !== 'ERROR'
            ||
            strtoupper(trim((string) $document['iconta_estado']))
            !== 'ERROR'
        ) {
            throw new RuntimeException(
                'Solo pueden reintentarse facturas en estado ERROR.'
            );
        }

        if (
            trim((string) ($document['iconta_id_documento'] ?? ''))
            !== ''
            ||
            !empty($document['iconta_enviado_at'])
        ) {
            throw new RuntimeException(
                'La factura presenta evidencia de envío previo a iConta. '
                    . 'Debe verificarse antes de reintentar.'
            );
        }

        if ((int) $document['iconta_intentos'] !== 0) {
            throw new RuntimeException(
                'La factura registra intentos de generación previos. '
                    . 'Se requiere conciliación con iConta antes de reenviarla.'
            );
        }

        /*
     * La seguridad depende de no haber iniciado la generación,
     * no del texto del error del intento anterior.
     */
        if (!self::canRetryInvoice($document)) {
            throw new RuntimeException(
                'El documento no cumple las condiciones para reintentar el envío. '
                    . 'Se requiere revisión del documento.'
            );
        }

        /*
     * El servicio existente gestiona el bloqueo de ejecución,
     * sincroniza la persona y procesa la misma factura local.
     */
        (new IContaService())->processInvoice(
            (int) $document['iconta_documento_id']
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
                    FOR UPDATE
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
| Datos fiscales
|--------------------------------------------------------------------------
*/

                if (!empty($saleRow['consumidor_final'])) {
                    throw new RuntimeException('Emisión de consumidor final pendiente de habilitación fiscal.');
                }
                $fiscalDataId = (int) (
                    $saleRow['datos_fiscales_id']
                    ?? 0
                );

                if ($fiscalDataId <= 0) {
                    throw new RuntimeException(
                        'La venta debe tener datos fiscales para poder emitir la factura.'
                    );
                }

                /*
|--------------------------------------------------------------------------
| Pago total
|--------------------------------------------------------------------------
|
| Una venta únicamente puede facturarse cuando todos sus
| pagos registrados cubren completamente el total.
|
| Los pagos anulados no forman parte del valor pagado.
|
*/

                $paymentStmt = $db->prepare(
                    '
    SELECT
        COALESCE(SUM(pg.monto), 0)

    FROM pagos pg

    WHERE pg.venta_id = :venta
      AND pg.estado = "REGISTRADO"
      AND pg.anulado_at IS NULL
    '
                );

                $paymentStmt->execute([
                    'venta' => $sale,
                ]);

                $paidAmount = round(
                    (float) $paymentStmt->fetchColumn(),
                    2
                );

                $saleTotal = round(
                    (float) (
                        $saleRow['total']
                        ?? 0
                    ),
                    2
                );

                $balance = round(
                    $saleTotal - $paidAmount,
                    2
                );

                if ($balance > 0.004) {
                    throw new RuntimeException(
                        'La venta debe estar pagada en su totalidad antes de emitir la factura. '
                            . 'Saldo pendiente: $'
                            . number_format(
                                $balance,
                                2,
                                '.',
                                ''
                            )
                            . '.'
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
                    FOR UPDATE
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
| Métodos de pago compatibles con iConta
|--------------------------------------------------------------------------
|
| Todos los pagos vigentes de la venta deben tener configurado un código
| de forma de pago para iConta antes de crear el documento fiscal.
|
*/

                $paymentMethodStmt = $db->prepare(
                    '
    SELECT
        pg.id,
        mp.nombre,
        mp.codigo,
        mp.iconta_codigo

    FROM pagos pg

    INNER JOIN metodos_pago mp
        ON mp.id = pg.metodo_pago_id

    WHERE pg.venta_id = :venta
      AND pg.estado = "REGISTRADO"
      AND pg.anulado_at IS NULL

    ORDER BY pg.id ASC
    '
                );

                $paymentMethodStmt->execute([
                    'venta' => $sale,
                ]);

                $validPayments = $paymentMethodStmt->fetchAll(
                    \PDO::FETCH_ASSOC
                );

                foreach ($validPayments as $payment) {
                    $iContaPaymentCode = trim(
                        (string) (
                            $payment['iconta_codigo']
                            ?? ''
                        )
                    );

                    if ($iContaPaymentCode === '') {
                        $paymentMethodName = trim(
                            (string) (
                                $payment['nombre']
                                ?? $payment['codigo']
                                ?? 'desconocido'
                            )
                        );

                        throw new RuntimeException(
                            'El método de pago "'
                                . $paymentMethodName
                                . '" no tiene configurado su código de forma de pago en iConta.'
                        );
                    }
                }

                $iConta = new IContaService();
                $iConta->validateInvoiceCustomer($db, $fiscalDataId);

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

                $iConta->validateInvoicePayload($db, $sale, $documentId);

                /*
                |--------------------------------------------------------------------------
                | Cola iConta
                |--------------------------------------------------------------------------
                */

                $stmt = $db->prepare(
                    '
                    INSERT INTO iconta_documentos (
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
                    'documento' => $documentId,
                    'payload' => json_encode(
                        [
                            'venta_id' => $sale,
                            'documento_fiscal_id' => $documentId,
                        ],
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
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
                'SELECT id, codigo, factor
             FROM tipos_movimiento_inventario
             WHERE codigo IN ("SALIDA", "DEVOLUCION_VENTA")'
            );

            $stmt->execute();

            $movementTypes = [];

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $movementTypes[$row['codigo']] = $row;
            }

            if (empty($movementTypes['SALIDA'])) {
                throw new RuntimeException(
                    'No se encontró el tipo de movimiento SALIDA.'
                );
            }

            if ((int) $movementTypes['SALIDA']['factor'] !== -1) {
                throw new RuntimeException(
                    'El tipo de movimiento SALIDA debe tener factor -1.'
                );
            }

            $exitTypeId = (int) $movementTypes['SALIDA']['id'];


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

            if ($saleExits && empty($movementTypes['DEVOLUCION_VENTA'])) {
                throw new RuntimeException(
                    'No se encontró el tipo de movimiento DEVOLUCION_VENTA. '
                        . 'Un administrador debe ejecutar la migración de devolución de ventas.'
                );
            }

            if ($saleExits && (int) $movementTypes['DEVOLUCION_VENTA']['factor'] !== 1) {
                throw new RuntimeException(
                    'El tipo de movimiento DEVOLUCION_VENTA debe tener factor 1.'
                );
            }

            $returnTypeId = $saleExits
                ? (int) $movementTypes['DEVOLUCION_VENTA']['id']
                : null;

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

            (new ClinicalBillingService())->releaseSale(
                $db,
                $saleId,
                $environmentId,
                $cancelledBy,
                $reason
            );


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
        int $by,
        int $inventoryId
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
                $by,
                $inventoryId
            );

            return;
        }

        $this->consumeProductStockWithoutLot(
            $db,
            $product,
            $quantity,
            $env,
            $saleId,
            $by,
            $inventoryId
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
        int $by,
        int $inventoryId
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
              AND i.id = :inventario
              AND i.activo = 1
              AND ip.producto_id = :producto
              AND ip.activo = 1

            ORDER BY ip.inventario_id

            FOR UPDATE
            '
        );

        $lock->execute([
            'entorno' => $env,
            'inventario' => $inventoryId,
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
              AND i.id = :inventario
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
            'inventario' => $inventoryId,
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
        int $by,
        int $inventoryId
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
              AND i.id = :inventario
              AND i.activo = 1
              AND ip.producto_id = :producto
              AND ip.activo = 1

            ORDER BY ip.inventario_id

            FOR UPDATE
            '
        );

        $lock->execute([
            'entorno' => $env,
            'inventario' => $inventoryId,
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
              AND i.id = :inventario
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
            'inventario' => $inventoryId,
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
