<?php

namespace App\Models;

use App\Core\Model;

class Billing extends Model
{
    public function sales(int $e): array
    {
        $sql = '
            SELECT
                v.*,
                a.nombre AS paciente,
                p.nombres AS propietario_nombres,
                p.apellidos AS propietario_apellidos,
                COALESCE(pg.pagado, 0) AS pagado,
                GREATEST(v.total - COALESCE(pg.pagado, 0), 0) AS saldo,
                CASE
                    WHEN v.estado = "ANULADA" THEN "ANULADA"
                    WHEN COALESCE(pg.pagado, 0) <= 0 THEN "SIN_PAGO"
                    WHEN COALESCE(pg.pagado, 0) + 0.005 >= v.total THEN "PAGADA"
                    ELSE "PARCIAL"
                END AS estado_pago,
                df.id AS documento_fiscal_id,
                df.estado AS estado_fiscal,
                cd.estado AS contifico_estado
            FROM ventas v
            LEFT JOIN animales a
                ON a.id = v.animal_id
            LEFT JOIN propietarios_entornos pe
                ON pe.id = v.propietario_entorno_id
            LEFT JOIN propietarios p
                ON p.id = pe.propietario_id
            LEFT JOIN (
                SELECT
                    venta_id,
                    SUM(monto) AS pagado
                FROM pagos
                WHERE estado = "REGISTRADO"
                  AND anulado_at IS NULL
                GROUP BY venta_id
            ) pg
                ON pg.venta_id = v.id
            LEFT JOIN documentos_fiscales df
                ON df.id = (
                    SELECT df2.id
                    FROM documentos_fiscales df2
                    WHERE df2.venta_id = v.id
                      AND df2.tipo_documento = "FACTURA"
                    ORDER BY df2.id DESC
                    LIMIT 1
                )
            LEFT JOIN contifico_documentos cd
                ON cd.documento_fiscal_id = df.id
            WHERE v.entorno_id = :e
            ORDER BY v.fecha DESC, v.id DESC
            LIMIT 250
        ';

        $s = $this->db->prepare($sql);
        $s->execute(['e' => $e]);
        return $s->fetchAll();
    }

    public function documents(int $e): array
    {
        $s = $this->db->prepare('
            SELECT
                df.*,
                v.numero AS venta_numero,
                v.total,
                cd.contifico_id,
                cd.estado AS contifico_estado
            FROM documentos_fiscales df
            INNER JOIN ventas v
                ON v.id = df.venta_id
            LEFT JOIN contifico_documentos cd
                ON cd.documento_fiscal_id = df.id
            WHERE v.entorno_id = :e
            ORDER BY df.id DESC
        ');
        $s->execute(['e' => $e]);
        return $s->fetchAll();
    }

    public function fiscalData(int $e): array
    {
        $s = $this->db->prepare('
            SELECT
                pdf.id,
                pe.id AS propietario_entorno_id,
                pdf.propietario_id,
                pdf.identificacion,
                pdf.razon_social,
                pdf.direccion,
                pdf.email,
                pdf.telefono,
                pdf.es_principal
            FROM propietarios_datos_fiscales pdf
            INNER JOIN propietarios_entornos pe
                ON pe.propietario_id = pdf.propietario_id
               AND pe.entorno_id = :e
            WHERE pdf.activo = 1
            ORDER BY
                pe.id,
                pdf.es_principal DESC,
                pdf.razon_social,
                pdf.id
        ');
        $s->execute(['e' => $e]);
        return $s->fetchAll();
    }

    public function saleServices(): array
    {
        $sql = '
            SELECT
                s.id,
                s.codigo,
                s.nombre,
                s.descripcion,
                s.precio_base AS precio,
                s.precio_incluye_impuesto,
                s.impuesto_tarifa_id,
                it.codigo AS impuesto_tarifa_codigo,
                it.nombre AS impuesto_tarifa_nombre,
                it.porcentaje AS impuesto_porcentaje,
                i.codigo AS impuesto_codigo,
                i.nombre AS impuesto_nombre
            FROM servicios s
            LEFT JOIN impuesto_tarifas it
                ON it.id = s.impuesto_tarifa_id
               AND it.activo = 1
               AND it.fecha_desde <= CURDATE()
               AND (it.fecha_hasta IS NULL OR it.fecha_hasta >= CURDATE())
            LEFT JOIN impuestos i
                ON i.id = it.impuesto_id
               AND i.activo = 1
            WHERE s.activo = 1
            ORDER BY s.nombre
        ';

        return $this->db->query($sql)->fetchAll();
    }

    public function saleProducts(int $e): array
    {
        $sql = '
            SELECT
                p.id,
                p.codigo,
                p.nombre,
                p.descripcion,
                p.controla_lote,
                p.controla_vencimiento,
                p.precio_venta AS precio,
                p.precio_incluye_impuesto,
                p.impuesto_tarifa_id,
                um.simbolo AS unidad,
                it.codigo AS impuesto_tarifa_codigo,
                it.nombre AS impuesto_tarifa_nombre,
                it.porcentaje AS impuesto_porcentaje,
                tx.codigo AS impuesto_codigo,
                tx.nombre AS impuesto_nombre,
                COALESCE(st.stock, 0) AS stock
            FROM productos p
            INNER JOIN unidades_medida um
                ON um.id = p.unidad_base_id
            LEFT JOIN impuesto_tarifas it
                ON it.id = p.impuesto_tarifa_id
               AND it.activo = 1
               AND it.fecha_desde <= CURDATE()
               AND (it.fecha_hasta IS NULL OR it.fecha_hasta >= CURDATE())
            LEFT JOIN impuestos tx
                ON tx.id = it.impuesto_id
               AND tx.activo = 1
            INNER JOIN (
                SELECT DISTINCT ip.producto_id
                FROM inventario_productos ip
                INNER JOIN inventarios i
                    ON i.id = ip.inventario_id
                WHERE i.entorno_id = :e_enabled
                  AND i.activo = 1
                  AND ip.activo = 1
            ) enabled
                ON enabled.producto_id = p.id
            LEFT JOIN (
                SELECT
                    mi.producto_id,
                    ROUND(SUM(
                        CASE
                            WHEN p2.controla_lote = 1
                                 AND (lp.id IS NULL OR (lp.fecha_vencimiento IS NOT NULL AND lp.fecha_vencimiento < CURDATE()))
                                THEN 0
                            ELSE tm.factor * mi.cantidad
                        END
                    ), 4) AS stock
                FROM movimientos_inventario mi
                INNER JOIN tipos_movimiento_inventario tm
                    ON tm.id = mi.tipo_movimiento_id
                INNER JOIN inventarios i2
                    ON i2.id = mi.inventario_id
                   AND i2.entorno_id = :e_stock
                   AND i2.activo = 1
                INNER JOIN inventario_productos ip2
                    ON ip2.inventario_id = mi.inventario_id
                   AND ip2.producto_id = mi.producto_id
                   AND ip2.activo = 1
                INNER JOIN productos p2
                    ON p2.id = mi.producto_id
                LEFT JOIN lotes_producto lp
                    ON lp.id = mi.lote_id
                   AND lp.producto_id = mi.producto_id
                GROUP BY mi.producto_id
            ) st
                ON st.producto_id = p.id
            WHERE p.activo = 1
              AND p.deleted_at IS NULL
            ORDER BY p.nombre
        ';

        $s = $this->db->prepare($sql);
        $s->execute([
            'e_enabled' => $e,
            'e_stock' => $e,
        ]);
        return $s->fetchAll();
    }

    public function paymentMethods(): array
    {
        $stmt = $this->db->query(
            '
        SELECT
            id,
            codigo,
            nombre
        FROM metodos_pago
        WHERE activo = 1
        ORDER BY id
        '
        );

        return $stmt->fetchAll();
    }


    public function paymentsByEnvironment(int $environmentId): array
    {
        $stmt = $this->db->prepare(
            '
        SELECT
            pg.id,
            pg.venta_id,
            pg.metodo_pago_id,
            pg.monto,
            pg.fecha_pago,
            pg.referencia,
            pg.estado,
            pg.anulado_at,
            pg.anulado_por,
            pg.motivo_anulacion,
            pg.registrado_por,
            pg.created_at,

            mp.codigo AS metodo_codigo,
            mp.nombre AS metodo_nombre,

            v.numero AS venta_numero,

            CONCAT(
                COALESCE(u.nombres, ""),
                " ",
                COALESCE(u.apellidos, "")
            ) AS registrado_por_nombre

        FROM pagos pg

        INNER JOIN ventas v
            ON v.id = pg.venta_id

        INNER JOIN metodos_pago mp
            ON mp.id = pg.metodo_pago_id

        LEFT JOIN usuarios u
            ON u.id = pg.registrado_por

        WHERE v.entorno_id = :entorno

        ORDER BY
            pg.fecha_pago DESC,
            pg.id DESC
        '
        );

        $stmt->execute([
            'entorno' => $environmentId,
        ]);

        return $stmt->fetchAll();
    }

    
}
