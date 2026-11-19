<?php

declare(strict_types=1);

use App\Core\Database;
use App\Services\BillingService;
use App\Services\IContaService;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Solo disponible desde CLI.\n");
}

if ($argc < 2 || $argc > 3 || trim($argv[1]) === '') {
    fwrite(STDERR, "Uso: php scripts/diagnose_iconta_invoice.php CODIGO_PRODUCTO [ID_VENTA]\n");
    exit(1);
}

$saleId = isset($argv[2])
    ? filter_var($argv[2], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : null;

if ($saleId === false) {
    fwrite(STDERR, "ID_VENTA debe ser un entero positivo, no el numero V- de pantalla.\n");
    exit(1);
}

require dirname(__DIR__) . '/bootstrap/app.php';

try {
    $db = Database::connection();
    $db->exec('START TRANSACTION READ ONLY');

    try {
        $read = static function (string $sql, array $parameters = []) use ($db): array {
            $statement = $db->prepare($sql);
            $statement->execute($parameters);
            return $statement->fetchAll(PDO::FETCH_ASSOC);
        };
        $validate = static function (callable $validation): string {
            try {
                $validation();
                return 'OK; no confirma aceptacion por iConta/SRI.';
            } catch (RuntimeException $error) {
                if ($error instanceof PDOException) {
                    throw $error;
                }
                return $error->getMessage();
            }
        };

        $products = $read(
            'SELECT p.id, p.entorno_id, p.codigo, p.precio_venta,
                    p.precio_incluye_impuesto, p.impuesto_tarifa_id,
                    it.porcentaje, it.activo AS tarifa_activa,
                    i.activo AS impuesto_activo,
                    (it.fecha_desde <= CURDATE() AND
                        (it.fecha_hasta IS NULL OR it.fecha_hasta >= CURDATE())) AS tarifa_vigente
             FROM productos p
             LEFT JOIN impuesto_tarifas it ON it.id = p.impuesto_tarifa_id
             LEFT JOIN impuestos i ON i.id = it.impuesto_id
             WHERE p.codigo = :codigo
             ORDER BY p.entorno_id, p.id',
            ['codigo' => trim($argv[1])]
        );

        if ($products === []) {
            throw new RuntimeException('No se encontro el producto en la base configurada.');
        }

        $report = [
            'modo' => 'SOLO LECTURA. Sin peticiones a iConta ni cambios de datos.',
            'productos_actuales' => $products,
        ];

        $sales = $read(
            'SELECT v.id, v.entorno_id, v.fecha, v.estado, v.subtotal,
                    v.descuento, v.impuestos, v.total, v.es_simulada
             FROM ventas v
             WHERE EXISTS (
                 SELECT 1 FROM venta_detalles vd
                 INNER JOIN productos p ON p.id = vd.producto_id
                 WHERE vd.venta_id = v.id AND p.codigo = :codigo
             )' . ($saleId !== null ? ' AND v.id = :venta' : '') . '
             ORDER BY v.id DESC LIMIT 20',
            $saleId !== null
                ? ['codigo' => trim($argv[1]), 'venta' => $saleId]
                : ['codigo' => trim($argv[1])]
        );
        $report['ventas'] = $sales;

        if ($saleId !== null) {
            if ($sales === []) {
                throw new RuntimeException('La venta indicada no contiene ese producto.');
            }

            $report['detalles_guardados'] = $read(
                'SELECT vd.id, vd.producto_id, vd.servicio_id,
                        vd.impuesto_tarifa_id, vd.impuesto_codigo, vd.impuesto_porcentaje,
                        vd.cantidad, vd.precio_unitario, vd.precio_incluye_impuesto,
                        vd.subtotal, vd.base_imponible, vd.descuento, vd.impuesto, vd.total
                 FROM venta_detalles vd WHERE vd.venta_id = :venta ORDER BY vd.id',
                ['venta' => $saleId]
            );
            $report['cuadre'] = $read(
                'SELECT
                    (SELECT COALESCE(SUM(vd.total), 0) FROM venta_detalles vd
                        WHERE vd.venta_id = v.id) AS total_detalles,
                    (SELECT COALESCE(SUM(vd.impuesto), 0) FROM venta_detalles vd
                        WHERE vd.venta_id = v.id) AS iva_detalles,
                    v.total AS total_venta, v.impuestos AS iva_venta,
                    (SELECT COALESCE(SUM(pg.monto), 0) FROM pagos pg
                        WHERE pg.venta_id = v.id AND pg.estado = "REGISTRADO"
                        AND pg.anulado_at IS NULL) AS pagos_vigentes
                 FROM ventas v WHERE v.id = :venta',
                ['venta' => $saleId]
            )[0];
            $balance = $report['cuadre'];
            $report['cuadre']['diferencia_detalles_venta'] = round(
                (float) $balance['total_detalles'] - (float) $balance['total_venta'],
                2
            );
            $report['cuadre']['diferencia_iva'] = round(
                (float) $balance['iva_detalles'] - (float) $balance['iva_venta'],
                2
            );
            $report['cuadre']['saldo_por_pagar'] = round(
                (float) $balance['total_venta'] - (float) $balance['pagos_vigentes'],
                2
            );
            $report['metodos_pago'] = $read(
                'SELECT pg.id, pg.monto, mp.codigo, mp.iconta_codigo,
                        (TRIM(COALESCE(pg.referencia, "")) <> "") AS tiene_referencia
                 FROM pagos pg
                 INNER JOIN metodos_pago mp ON mp.id = pg.metodo_pago_id
                 WHERE pg.venta_id = :venta AND pg.estado = "REGISTRADO"
                   AND pg.anulado_at IS NULL ORDER BY pg.id',
                ['venta' => $saleId]
            );
            $documents = $read(
                'SELECT df.id AS documento_fiscal_id, df.tipo_documento, df.estado,
                        v.estado AS venta_estado, e.permite_facturacion_real,
                        idoc.id AS iconta_documento_id, idoc.estado AS iconta_estado,
                        idoc.iconta_id_documento, idoc.intentos AS iconta_intentos,
                        idoc.enviado_at AS iconta_enviado_at
                 FROM documentos_fiscales df
                 INNER JOIN ventas v ON v.id = df.venta_id
                 INNER JOIN entornos e ON e.id = v.entorno_id
                 LEFT JOIN iconta_documentos idoc ON idoc.documento_fiscal_id = df.id
                 WHERE df.venta_id = :venta ORDER BY df.id',
                ['venta' => $saleId]
            );
            $service = new IContaService();
            $report['integracion_habilitada'] = $service->isEnabled();
            $fiscalData = $read(
                'SELECT datos_fiscales_id FROM ventas WHERE id = :venta',
                ['venta' => $saleId]
            )[0];
            $report['validacion_local_cliente'] = $validate(
                static fn () => $service->validateInvoiceCustomer($db, (int) $fiscalData['datos_fiscales_id'])
            );
            $report['validacion_local_detalles'] = $validate(
                static fn () => $service->validateInvoiceSaleDetails($db, $saleId)
            );
            foreach ($documents as &$document) {
                $document['reintento_local_permitido'] = BillingService::canRetryInvoice($document);
                $documentId = (int) $document['documento_fiscal_id'];
                $document['validacion_local_payload'] = $validate(
                    static fn () => $service->validateInvoicePayload($db, $saleId, $documentId)
                );
            }
            unset($document);
            $report['documentos'] = $documents;
            if ($documents === []) {
                $report['validacion_local_payload'] =
                    'NO EJECUTADA: no existe documento fiscal. No se crea uno para diagnosticar.';
            }
        } else {
            $report['siguiente_paso'] =
                'Repetir el comando agregando el id de la venta de esta lista; no emite facturas.';
        }

        echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), PHP_EOL;
    } finally {
        $db->rollBack();
    }
} catch (PDOException $error) {
    fwrite(STDERR, 'ERROR de base de datos. SQLSTATE: ' . $error->getCode() . PHP_EOL);
    exit(1);
} catch (Throwable $error) {
    fwrite(STDERR, 'ERROR: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
