<?php

namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use PDO;
use RuntimeException;

class InventoryAlertService
{
    private const EXPIRATION_DAYS = 5;

    public function generate(): array
    {
        $db = Database::connection();

        $environments = $db->query(
            '
            SELECT DISTINCT
                e.id,
                e.nombre

            FROM entornos e

            INNER JOIN inventarios i
                ON i.entorno_id = e.id
               AND i.activo = 1

            WHERE e.activo = 1

            ORDER BY e.id
            '
        )->fetchAll();

        $generated = 0;
        $skipped = 0;

        foreach ($environments as $environment) {
            $environmentId =
                (int)$environment['id'];

            $result = $this->generateForEnvironment(
                $db,
                $environmentId,
                (string)$environment['nombre']
            );

            $generated += $result['generated'];
            $skipped += $result['skipped'];
        }

        return [
            'generated' => $generated,
            'skipped' => $skipped,
        ];
    }

    public function generateForEnvironment(
        PDO $db,
        int $environmentId,
        string $environmentName
    ): array {
        $lowStock = $this->lowStock(
            $db,
            $environmentId
        );

        $expiringLots = $this->expiringLots(
            $db,
            $environmentId
        );

        $expiredLots = $this->expiredLots(
            $db,
            $environmentId
        );

        /*
         * No generamos correo vacío.
         */
        if (
            !$lowStock
            && !$expiringLots
            && !$expiredLots
        ) {
            return [
                'generated' => 0,
                'skipped' => 1,
            ];
        }

        $recipients = $this->administrators(
            $db,
            $environmentId
        );

        if (!$recipients) {
            return [
                'generated' => 0,
                'skipped' => 1,
            ];
        }

        $typeId = $this->lookupId(
            $db,
            'tipos_notificacion',
            'INVENTARIO'
        );

        $emailChannelId = $this->lookupId(
            $db,
            'canales_notificacion',
            'EMAIL'
        );

        $subject =
            'Alerta de inventario - '
            . $environmentName;

        $message = $this->buildMessage(
            $environmentName,
            $lowStock,
            $expiringLots,
            $expiredLots
        );

        /*
         * Referencia diaria.
         *
         * Ejemplo:
         * INVENTARIO_DIARIO
         * referencia_id = 20260924
         *
         * El entorno y destinatario también
         * forman parte de la comprobación.
         */
        $referenceId =
            (int)date('Ymd');

        $generated = 0;

        foreach ($recipients as $recipient) {
            if (
                empty($recipient['email'])
                || !filter_var(
                    $recipient['email'],
                    FILTER_VALIDATE_EMAIL
                )
            ) {
                continue;
            }

            if (
                $this->alreadyGeneratedToday(
                    $db,
                    $environmentId,
                    (int)$recipient['id'],
                    $typeId,
                    $emailChannelId,
                    $referenceId
                )
            ) {
                continue;
            }

            $stmt = $db->prepare(
                '
                INSERT INTO notificaciones
                (
                    entorno_id,
                    tipo_notificacion_id,
                    canal_id,
                    destinatario_usuario_id,
                    destinatario_propietario_id,
                    asunto,
                    mensaje,
                    fecha_programada,
                    estado,
                    referencia_tipo,
                    referencia_id,
                    intentos
                )
                VALUES
                (
                    :entorno,
                    :tipo,
                    :canal,
                    :usuario,
                    NULL,
                    :asunto,
                    :mensaje,
                    NOW(),
                    "PENDIENTE",
                    "INVENTARIO_DIARIO",
                    :referencia,
                    0
                )
                '
            );

            $stmt->execute([
                'entorno' => $environmentId,
                'tipo' => $typeId,
                'canal' => $emailChannelId,
                'usuario' => (int)$recipient['id'],
                'asunto' => $subject,
                'mensaje' => $message,
                'referencia' => $referenceId,
            ]);

            $generated++;
        }

        return [
            'generated' => $generated,
            'skipped' => $generated === 0 ? 1 : 0,
        ];
    }

    private function lowStock(
        PDO $db,
        int $environmentId
    ): array {
        $stmt = $db->prepare(
            '
            SELECT
                i.id AS inventario_id,
                i.nombre AS inventario,

                p.id AS producto_id,
                p.nombre AS producto,

                ub.simbolo AS unidad,

                ip.stock_minimo,

                COALESCE(
                    SUM(
                        mi.cantidad
                        * tmi.factor
                    ),
                    0
                ) AS stock_actual

            FROM inventario_productos ip

            INNER JOIN inventarios i
                ON i.id = ip.inventario_id

            INNER JOIN productos p
                ON p.id = ip.producto_id

            LEFT JOIN unidades_medida ub
                ON ub.id = p.unidad_base_id

            LEFT JOIN movimientos_inventario mi
                ON mi.inventario_id =
                   ip.inventario_id

               AND mi.producto_id =
                   ip.producto_id

            LEFT JOIN tipos_movimiento_inventario tmi
                ON tmi.id =
                   mi.tipo_movimiento_id

            WHERE i.entorno_id = :entorno
              AND i.activo = 1
              AND ip.activo = 1
              AND p.activo = 1
              AND p.deleted_at IS NULL

              AND ip.stock_minimo IS NOT NULL

            GROUP BY
                i.id,
                i.nombre,
                p.id,
                p.nombre,
                ub.simbolo,
                ip.stock_minimo

            HAVING
                stock_actual <= ip.stock_minimo

            ORDER BY
                i.nombre,
                p.nombre
            '
        );

        $stmt->execute([
            'entorno' => $environmentId,
        ]);

        return $stmt->fetchAll();
    }

    private function expiringLots(
        PDO $db,
        int $environmentId
    ): array {
        $stmt = $db->prepare(
            '
            SELECT
                i.id AS inventario_id,
                i.nombre AS inventario,

                p.id AS producto_id,
                p.nombre AS producto,

                ub.simbolo AS unidad,

                lp.id AS lote_id,
                lp.numero_lote,
                lp.fecha_vencimiento,

                SUM(
                    mi.cantidad
                    * tmi.factor
                ) AS existencia

            FROM movimientos_inventario mi

            INNER JOIN tipos_movimiento_inventario tmi
                ON tmi.id = mi.tipo_movimiento_id

            INNER JOIN inventarios i
                ON i.id = mi.inventario_id

            INNER JOIN productos p
                ON p.id = mi.producto_id

            LEFT JOIN unidades_medida ub
                ON ub.id = p.unidad_base_id

            INNER JOIN lotes_producto lp
                ON lp.id = mi.lote_id
               AND lp.producto_id = mi.producto_id

            WHERE i.entorno_id = :entorno
              AND i.activo = 1
              AND p.activo = 1
              AND p.deleted_at IS NULL

              AND lp.fecha_vencimiento
                    BETWEEN CURDATE()
                    AND DATE_ADD(
                        CURDATE(),
                        INTERVAL 5 DAY
                    )

            GROUP BY
                i.id,
                i.nombre,
                p.id,
                p.nombre,
                ub.simbolo,
                lp.id,
                lp.numero_lote,
                lp.fecha_vencimiento

            HAVING existencia > 0

            ORDER BY
                lp.fecha_vencimiento,
                i.nombre,
                p.nombre
            '
        );

        $stmt->execute([
            'entorno' => $environmentId,
        ]);

        return $stmt->fetchAll();
    }

    private function expiredLots(
        PDO $db,
        int $environmentId
    ): array {
        $stmt = $db->prepare(
            '
            SELECT
                i.id AS inventario_id,
                i.nombre AS inventario,

                p.id AS producto_id,
                p.nombre AS producto,

                ub.simbolo AS unidad,

                lp.id AS lote_id,
                lp.numero_lote,
                lp.fecha_vencimiento,

                SUM(
                    mi.cantidad
                    * tmi.factor
                ) AS existencia

            FROM movimientos_inventario mi

            INNER JOIN tipos_movimiento_inventario tmi
                ON tmi.id = mi.tipo_movimiento_id

            INNER JOIN inventarios i
                ON i.id = mi.inventario_id

            INNER JOIN productos p
                ON p.id = mi.producto_id

            LEFT JOIN unidades_medida ub
                ON ub.id = p.unidad_base_id

            INNER JOIN lotes_producto lp
                ON lp.id = mi.lote_id
               AND lp.producto_id = mi.producto_id

            WHERE i.entorno_id = :entorno
              AND i.activo = 1
              AND p.activo = 1
              AND p.deleted_at IS NULL

              AND lp.fecha_vencimiento < CURDATE()

            GROUP BY
                i.id,
                i.nombre,
                p.id,
                p.nombre,
                ub.simbolo,
                lp.id,
                lp.numero_lote,
                lp.fecha_vencimiento

            HAVING existencia > 0

            ORDER BY
                lp.fecha_vencimiento,
                i.nombre,
                p.nombre
            '
        );

        $stmt->execute([
            'entorno' => $environmentId,
        ]);

        return $stmt->fetchAll();
    }

    private function administrators(
        PDO $db,
        int $environmentId
    ): array {
        /*
         * Administradores del entorno.
         */
        $stmt = $db->prepare(
            '
            SELECT DISTINCT
                u.id,
                u.nombres,
                u.apellidos,
                u.email

            FROM usuarios u

            INNER JOIN usuarios_entornos ue
                ON ue.usuario_id = u.id
               AND ue.entorno_id = :entorno
               AND ue.activo = 1

            INNER JOIN usuarios_entornos_roles uer
                ON uer.usuario_id = u.id
               AND uer.entorno_id = ue.entorno_id

            INNER JOIN roles r
                ON r.id = uer.rol_id

            WHERE u.activo = 1
              AND r.activo = 1
              AND r.codigo = "ADMINISTRADOR"
            '
        );

        $stmt->execute([
            'entorno' => $environmentId,
        ]);

        $users = $stmt->fetchAll();

        /*
         * Superadministradores globales.
         */
        $superAdmins = $db->query(
            '
            SELECT DISTINCT
                u.id,
                u.nombres,
                u.apellidos,
                u.email

            FROM usuarios u

            INNER JOIN usuarios_entornos_roles uer
                ON uer.usuario_id = u.id

            INNER JOIN roles r
                ON r.id = uer.rol_id

            WHERE u.activo = 1
              AND r.activo = 1
              AND r.codigo =
                  "SUPER_ADMINISTRADOR"
              AND r.es_global = 1
            '
        )->fetchAll();

        /*
         * Unificamos por usuario_id.
         */
        $result = [];

        foreach (
            array_merge(
                $users,
                $superAdmins
            )
            as $user
        ) {
            $result[(int)$user['id']] =
                $user;
        }

        return array_values($result);
    }

    private function alreadyGeneratedToday(
        PDO $db,
        int $environmentId,
        int $userId,
        int $typeId,
        int $channelId,
        int $referenceId
    ): bool {
        $stmt = $db->prepare(
            '
            SELECT id
            FROM notificaciones

            WHERE entorno_id = :entorno
              AND destinatario_usuario_id = :usuario
              AND tipo_notificacion_id = :tipo
              AND canal_id = :canal
              AND referencia_tipo =
                  "INVENTARIO_DIARIO"
              AND referencia_id = :referencia

            LIMIT 1
            '
        );

        $stmt->execute([
            'entorno' => $environmentId,
            'usuario' => $userId,
            'tipo' => $typeId,
            'canal' => $channelId,
            'referencia' => $referenceId,
        ]);

        return (bool)$stmt->fetchColumn();
    }

    private function buildMessage(
        string $environmentName,
        array $lowStock,
        array $expiringLots,
        array $expiredLots
    ): string {
        $lines = [];

        $lines[] =
            'Reporte automático de inventario';
        $lines[] =
            'Entorno: ' . $environmentName;
        $lines[] =
            'Fecha: ' . date('d/m/Y');
        $lines[] = '';

        $lines[] =
            'RESUMEN';
        $lines[] =
            'Productos con stock bajo: '
            . count($lowStock);
        $lines[] =
            'Lotes próximos a vencer: '
            . count($expiringLots);
        $lines[] =
            'Lotes vencidos con existencia: '
            . count($expiredLots);
        $lines[] = '';

        if ($lowStock) {
            $lines[] =
                'STOCK BAJO';
            $lines[] =
                '--------------------------------';

            foreach ($lowStock as $row) {
                $unit =
                    trim(
                        (string)($row['unidad'] ?? '')
                    );

                $lines[] =
                    $row['producto'];

                $lines[] =
                    'Inventario: '
                    . $row['inventario'];

                $lines[] =
                    'Stock actual: '
                    . $this->number(
                        $row['stock_actual']
                    )
                    . ($unit !== ''
                        ? ' ' . $unit
                        : '');

                $lines[] =
                    'Stock mínimo: '
                    . $this->number(
                        $row['stock_minimo']
                    )
                    . ($unit !== ''
                        ? ' ' . $unit
                        : '');

                $lines[] = '';
            }
        }

        if ($expiringLots) {
            $lines[] =
                'LOTES PRÓXIMOS A VENCER';
            $lines[] =
                '--------------------------------';

            foreach ($expiringLots as $row) {
                $unit =
                    trim(
                        (string)($row['unidad'] ?? '')
                    );

                $expiration =
                    new DateTimeImmutable(
                        $row['fecha_vencimiento']
                    );

                $today =
                    new DateTimeImmutable(
                        date('Y-m-d')
                    );

                $days =
                    (int)$today
                        ->diff($expiration)
                        ->format('%a');

                $lines[] =
                    $row['producto'];

                $lines[] =
                    'Inventario: '
                    . $row['inventario'];

                $lines[] =
                    'Lote: '
                    . $row['numero_lote'];

                $lines[] =
                    'Existencia: '
                    . $this->number(
                        $row['existencia']
                    )
                    . ($unit !== ''
                        ? ' ' . $unit
                        : '');

                $lines[] =
                    'Vencimiento: '
                    . $expiration->format(
                        'd/m/Y'
                    );

                $lines[] =
                    'Faltan: '
                    . $days
                    . (
                        $days === 1
                        ? ' día'
                        : ' días'
                    );

                $lines[] = '';
            }
        }

        if ($expiredLots) {
            $lines[] =
                'LOTES VENCIDOS CON EXISTENCIA';
            $lines[] =
                '--------------------------------';

            foreach ($expiredLots as $row) {
                $unit =
                    trim(
                        (string)($row['unidad'] ?? '')
                    );

                $expiration =
                    new DateTimeImmutable(
                        $row['fecha_vencimiento']
                    );

                $lines[] =
                    $row['producto'];

                $lines[] =
                    'Inventario: '
                    . $row['inventario'];

                $lines[] =
                    'Lote: '
                    . $row['numero_lote'];

                $lines[] =
                    'Existencia: '
                    . $this->number(
                        $row['existencia']
                    )
                    . ($unit !== ''
                        ? ' ' . $unit
                        : '');

                $lines[] =
                    'Venció: '
                    . $expiration->format(
                        'd/m/Y'
                    );

                $lines[] = '';
            }
        }

        $lines[] =
            'Tu Huella Vet';

        return implode(
            PHP_EOL,
            $lines
        );
    }

    private function lookupId(
        PDO $db,
        string $table,
        string $code
    ): int {
        $allowed = [
            'tipos_notificacion',
            'canales_notificacion',
        ];

        if (!in_array($table, $allowed, true)) {
            throw new RuntimeException(
                'Catálogo no permitido.'
            );
        }

        $stmt = $db->prepare(
            "
            SELECT id
            FROM {$table}
            WHERE codigo = :codigo
            LIMIT 1
            "
        );

        $stmt->execute([
            'codigo' => $code,
        ]);

        $id = $stmt->fetchColumn();

        if (!$id) {
            throw new RuntimeException(
                'No existe el catálogo requerido: '
                    . $code
            );
        }

        return (int)$id;
    }

    private function number(
        mixed $value
    ): string {
        $number = (float)$value;

        if (
            abs(
                $number - round($number)
            ) < 0.00001
        ) {
            return (string)(int)round(
                $number
            );
        }

        return rtrim(
            rtrim(
                number_format(
                    $number,
                    4,
                    '.',
                    ''
                ),
                '0'
            ),
            '.'
        );
    }
}
