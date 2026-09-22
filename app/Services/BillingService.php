<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

class BillingService
{
    public function createSale(array $d, int $env, int $by): int
    {
        return Database::transaction(function (PDO $db) use ($d, $env, $by) {
            $envRow = $db->prepare('SELECT es_productivo,permite_facturacion_real FROM entornos WHERE id=:e');
            $envRow->execute(['e' => $env]);
            $er = $envRow->fetch();
            $sim = empty($er['es_productivo']) ? 1 : 0;
            $lines = $d['items'] ?? [];
            if (!is_array($lines) || !$lines) throw new RuntimeException('Agrega al menos un detalle.');
            $subtotal = 0;
            $disc = 0;
            $tax = 0;
            foreach ($lines as $x) {
                $qty = (float)($x['cantidad'] ?? 0);
                $price = (float)($x['precio_unitario'] ?? 0);
                if ($qty <= 0) continue;
                $subtotal += $qty * $price;
                $disc += (float)($x['descuento'] ?? 0);
                $tax += (float)($x['impuesto'] ?? 0);
            }
            $total = $subtotal - $disc + $tax;
            $s = $db->prepare('INSERT INTO ventas(entorno_id,propietario_entorno_id,animal_id,datos_fiscales_id,fecha,subtotal,descuento,impuestos,total,estado,es_simulada,creado_por) VALUES(:e,:p,:a,:df,NOW(),:s,:d,:i,:t,"EMITIDA",:sim,:u)');
            $s->execute(['e' => $env, 'p' => !empty($d['propietario_entorno_id']) ? (int)$d['propietario_entorno_id'] : null, 'a' => !empty($d['animal_id']) ? (int)$d['animal_id'] : null, 'df' => !empty($d['datos_fiscales_id']) ? (int)$d['datos_fiscales_id'] : null, 's' => $subtotal, 'd' => $disc, 'i' => $tax, 't' => $total, 'sim' => $sim, 'u' => $by]);
            $sale = (int)$db->lastInsertId();
            foreach ($lines as $x) {
                $qty = (float)($x['cantidad'] ?? 0);
                if ($qty <= 0) continue;
                $service = !empty($x['servicio_id']) ? (int)$x['servicio_id'] : null;
                $product = !empty($x['producto_id']) ? (int)$x['producto_id'] : null;
                if (($service === null) == ($product === null)) throw new RuntimeException('Cada detalle debe ser servicio o producto, no ambos.');
                $price = (float)($x['precio_unitario'] ?? 0);
                $de = (float)($x['descuento'] ?? 0);
                $im = (float)($x['impuesto'] ?? 0);
                $db->prepare('INSERT INTO venta_detalles(venta_id,servicio_id,producto_id,descripcion,cantidad,precio_unitario,descuento,impuesto,total) VALUES(:v,:s,:p,:d,:c,:pu,:de,:i,:t)')->execute(['v' => $sale, 's' => $service, 'p' => $product, 'd' => trim((string)($x['descripcion'] ?? '')) ?: 'Detalle', 'c' => $qty, 'pu' => $price, 'de' => $de, 'i' => $im, 't' => $qty * $price - $de + $im]);
            }
            (new AuditService())->log($by, $env, 'VENTAS', 'CREAR', 'ventas', $sale);
            return $sale;
        });
    }
    public function queueInvoice(int $sale, int $env, int $by): int
    {
        return Database::transaction(function (PDO $db) use ($sale, $env, $by) {
            $s = $db->prepare('SELECT v.*,e.permite_facturacion_real FROM ventas v JOIN entornos e ON e.id=v.entorno_id WHERE v.id=:v AND v.entorno_id=:e');
            $s->execute(['v' => $sale, 'e' => $env]);
            $v = $s->fetch();
            if (!$v) throw new RuntimeException('Venta no encontrada.');
            if (empty($v['permite_facturacion_real']) || !empty($v['es_simulada'])) throw new RuntimeException('Este entorno no permite facturación real.');
            $db->prepare('INSERT INTO documentos_fiscales(venta_id,tipo_documento,estado) VALUES(:v,"FACTURA","PENDIENTE")')->execute(['v' => $sale]);
            $doc = (int)$db->lastInsertId();
            $db->prepare('INSERT INTO contifico_documentos(documento_fiscal_id,estado,request_payload) VALUES(:d,"PENDIENTE",:p)')->execute(['d' => $doc, 'p' => json_encode(['venta_id' => $sale], JSON_UNESCAPED_UNICODE)]);
            (new AuditService())->log($by, $env, 'FACTURACION', 'FACTURAR', 'documentos_fiscales', $doc);
            return $doc;
        });
    }
}
