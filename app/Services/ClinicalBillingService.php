<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

class ClinicalBillingService
{
    private function sourceSql(): string
    {
        return "
            FROM movimientos_inventario mi

            INNER JOIN inventarios i
                ON i.id = mi.inventario_id

            INNER JOIN tipos_movimiento_inventario tm
                ON tm.id = mi.tipo_movimiento_id

            INNER JOIN productos p
                ON p.id = mi.producto_id

            LEFT JOIN vacunaciones v
                ON mi.referencia_tipo = 'VACUNACION'
                AND v.id = mi.referencia_id

            LEFT JOIN desparasitaciones d
                ON mi.referencia_tipo = 'DESPARASITACION'
                AND d.id = mi.referencia_id

            LEFT JOIN medicamento_aplicaciones ma
                ON mi.referencia_tipo = 'MEDICAMENTO_APLICACION'
                AND ma.id = mi.referencia_id

            LEFT JOIN tratamiento_medicamentos tmed
                ON tmed.id = ma.tratamiento_medicamento_id

            LEFT JOIN tratamientos t
                ON t.id = tmed.tratamiento_id

            INNER JOIN eventos_clinicos ec
                ON ec.id = CASE
                    WHEN mi.referencia_tipo = 'VACUNACION'
                        THEN v.evento_clinico_id
                    WHEN mi.referencia_tipo = 'DESPARASITACION'
                        THEN d.evento_clinico_id
                    WHEN mi.referencia_tipo = 'MEDICAMENTO_APLICACION'
                        THEN t.evento_clinico_id
                    WHEN mi.referencia_tipo IN (
                        'CONSULTA_INSUMO',
                        'CIRUGIA_INSUMO',
                        'HOSPITALIZACION_INSUMO'
                    )
                        THEN mi.referencia_id
                    ELSE NULL
                END

            INNER JOIN animales a
                ON a.id = ec.animal_id

            WHERE tm.codigo = 'CONSUMO_CLINICO'
              AND tm.factor = -1
              AND i.entorno_id = ?
              AND a.entorno_id = i.entorno_id
              AND a.deleted_at IS NULL
              AND ec.anulado_at IS NULL
              AND p.activo = 1
              AND p.deleted_at IS NULL
              AND (
                  p.entorno_id IS NULL
                  OR p.entorno_id = i.entorno_id
              )
              AND (
                  mi.referencia_tipo <> 'MEDICAMENTO_APLICACION'
                  OR ma.anulado_at IS NULL
              )
        ";
    }

    public function pending(int $environmentId): array
    {
        $db = Database::connection();

        $sql = "
            SELECT
                mi.id,
                mi.producto_id,
                mi.inventario_id,
                mi.cantidad,
                mi.fecha_movimiento,
                mi.referencia_tipo,
                mi.referencia_id,
                a.id AS animal_id,
                a.propietario_entorno_id,
                a.nombre AS paciente,
                p.codigo,
                p.nombre,
                i.nombre AS inventario
        " . $this->sourceSql() . "

            AND NOT EXISTS (
                SELECT 1
                FROM venta_detalle_consumos_clinicos vc
                WHERE vc.movimiento_activo_id = mi.id
            )

            AND NOT EXISTS (
                SELECT 1
                FROM movimientos_inventario rev
                WHERE rev.referencia_tipo = 'REVERSO_INVENTARIO'
                  AND rev.referencia_id = mi.id
            )

            ORDER BY mi.fecha_movimiento DESC, mi.id DESC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([$environmentId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function lockAvailable(
        PDO $db,
        int $movementId,
        int $environmentId,
        ?int $animalId,
        ?int $ownerEnvironmentId
    ): array {
        if (
            $movementId <= 0
            || $animalId === null
            || $ownerEnvironmentId === null
        ) {
            throw new RuntimeException(
                'Selecciona el propietario y paciente del consumo clínico.'
            );
        }

        $stmt = $db->prepare("
            SELECT
                mi.id,
                mi.producto_id,
                mi.inventario_id,
                mi.cantidad,
                a.id AS animal_id,
                a.propietario_entorno_id
        " . $this->sourceSql() . "
            AND mi.id = ?
            AND a.id = ?
            AND a.propietario_entorno_id = ?
            FOR UPDATE
        ");

        $stmt->execute([
            $environmentId,
            $movementId,
            $animalId,
            $ownerEnvironmentId,
        ]);

        $movement = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$movement) {
            throw new RuntimeException(
                'Consumo clínico inexistente, anulado o fuera del paciente seleccionado.'
            );
        }

        $stmt = $db->prepare("
            SELECT id
            FROM movimientos_inventario
            WHERE referencia_tipo = 'REVERSO_INVENTARIO'
              AND referencia_id = ?
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([$movementId]);

        if ($stmt->fetchColumn()) {
            throw new RuntimeException(
                'El consumo clínico ya fue revertido.'
            );
        }

        self::assertNotBilled($db, $movementId);

        return $movement;
    }

    public static function assertNotBilled(
        PDO $db,
        int $movementId
    ): void {
        $stmt = $db->prepare("
            SELECT id
            FROM venta_detalle_consumos_clinicos
            WHERE movimiento_activo_id = ?
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([$movementId]);

        if ($stmt->fetchColumn()) {
            throw new RuntimeException(
                'El consumo clínico tiene una venta vigente. '
                    . 'Primero debes resolver la anulación de la venta.'
            );
        }
    }

    public function link(
        PDO $db,
        int $detailId,
        int $movementId,
        float $quantity,
        int $userId
    ): void {
        $stmt = $db->prepare("
            INSERT INTO venta_detalle_consumos_clinicos (
                venta_detalle_id,
                movimiento_id,
                cantidad,
                vinculado_por
            )
            VALUES (?, ?, ?, ?)
        ");

        $stmt->execute([
            $detailId,
            $movementId,
            $quantity,
            $userId,
        ]);
    }

    public function releaseSale(
        PDO $db,
        int $saleId,
        int $environmentId,
        int $userId,
        string $reason
    ): void {
        $stmt = $db->prepare("
            UPDATE venta_detalle_consumos_clinicos vc

            INNER JOIN venta_detalles vd
                ON vd.id = vc.venta_detalle_id

            INNER JOIN ventas v
                ON v.id = vd.venta_id

            SET
                vc.liberado_at = NOW(),
                vc.liberado_por = ?,
                vc.motivo_liberacion = ?

            WHERE v.id = ?
              AND v.entorno_id = ?
              AND vc.liberado_at IS NULL
        ");

        $stmt->execute([
            $userId,
            $reason,
            $saleId,
            $environmentId,
        ]);
    }
}
