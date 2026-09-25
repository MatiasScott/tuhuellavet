<?php

namespace App\Models;

use App\Core\Model;

class Inventory extends Model
{
    public function inventories(int $e): array
    {
        $s = $this->db->prepare('SELECT * FROM inventarios WHERE entorno_id=:e AND activo=1 ORDER BY nombre');
        $s->execute(['e' => $e]);
        return $s->fetchAll();
    }

    public function products(): array
    {
        $sql = '
        SELECT
            p.id,
            p.categoria_producto_id,
            p.codigo,
            p.nombre,
            p.descripcion,
            p.unidad_base_id,
            p.controla_lote,
            p.controla_vencimiento,
            p.farmaco_presentacion_id,
            p.vacuna_id,

            p.precio_venta,
            p.precio_incluye_impuesto,
            p.impuesto_tarifa_id,

            p.activo,

            um.nombre AS unidad_nombre,
            um.simbolo AS unidad,

            cp.nombre AS categoria_nombre,

            it.codigo AS impuesto_tarifa_codigo,
            it.nombre AS impuesto_tarifa_nombre,
            it.porcentaje AS impuesto_porcentaje,

            i.codigo AS impuesto_codigo,
            i.nombre AS impuesto_nombre

        FROM productos p

        INNER JOIN unidades_medida um
            ON um.id = p.unidad_base_id

        LEFT JOIN categorias_producto cp
            ON cp.id = p.categoria_producto_id

        LEFT JOIN impuesto_tarifas it
            ON it.id = p.impuesto_tarifa_id

        LEFT JOIN impuestos i
            ON i.id = it.impuesto_id

        WHERE p.activo = 1
          AND p.deleted_at IS NULL

        ORDER BY p.nombre
    ';

        return $this->db
            ->query($sql)
            ->fetchAll();
    }

    public function stock(int $e): array
    {
        $sql = '
        SELECT
            i.id AS inventario_id,
            i.nombre AS inventario,
            p.id AS producto_id,
            p.codigo,
            p.nombre AS producto,
            um.simbolo,
            COALESCE(
                SUM(tm.factor * mi.cantidad),
                0
            ) AS stock,
            ip.stock_minimo,
            ip.stock_maximo
        FROM inventarios i
        INNER JOIN inventario_productos ip
            ON ip.inventario_id = i.id
        INNER JOIN productos p
            ON p.id = ip.producto_id
        INNER JOIN unidades_medida um
            ON um.id = p.unidad_base_id
        LEFT JOIN movimientos_inventario mi
            ON mi.inventario_id = i.id
            AND mi.producto_id = p.id
        LEFT JOIN tipos_movimiento_inventario tm
            ON tm.id = mi.tipo_movimiento_id
        WHERE
            i.entorno_id = :e
            AND i.activo = 1
            AND ip.activo = 1
        GROUP BY
            i.id,
            p.id,
            um.simbolo,
            ip.stock_minimo,
            ip.stock_maximo
        ORDER BY
            p.nombre
    ';

        $s = $this->db->prepare($sql);
        $s->execute(['e' => $e]);

        return $s->fetchAll();
    }
    public function movements(int $e): array
    {
        $s = $this->db->prepare('SELECT mi.*,i.nombre AS inventario,p.nombre AS producto,tm.nombre AS tipo,tm.factor,lp.numero_lote,CONCAT(u.nombres," ",u.apellidos) AS usuario FROM movimientos_inventario mi JOIN inventarios i ON i.id=mi.inventario_id JOIN productos p ON p.id=mi.producto_id JOIN tipos_movimiento_inventario tm ON tm.id=mi.tipo_movimiento_id LEFT JOIN lotes_producto lp ON lp.id=mi.lote_id JOIN usuarios u ON u.id=mi.realizado_por WHERE i.entorno_id=:e ORDER BY mi.fecha_movimiento DESC LIMIT 250');
        $s->execute(['e' => $e]);
        return $s->fetchAll();
    }

    public function lots(int $environmentId): array
    {
        $sql = '
        SELECT
            lp.id,
            lp.producto_id,
            lp.numero_lote,
            lp.fecha_vencimiento
        FROM lotes_producto lp
        INNER JOIN productos p
            ON p.id = lp.producto_id
        WHERE p.activo = 1
          AND p.deleted_at IS NULL
        ORDER BY
            lp.fecha_vencimiento,
            lp.numero_lote
    ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
