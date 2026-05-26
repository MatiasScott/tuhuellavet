<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use Throwable;

final class AuditService
{
    private static ?array $tableColumns = null;

    public function log(array $entry): void
    {
        $pdo = Database::connection((array) config('database'));
        $columns = $this->auditoriaColumns($pdo);
        if ($columns === []) {
            return;
        }

        $payload = [
            'usuario_id' => $entry['usuario_id'] ?? null,
            'empresa_id' => $entry['empresa_id'] ?? null,
            'ip' => $entry['ip'] ?? null,
            'user_agent' => $entry['user_agent'] ?? null,
            'modulo' => $entry['modulo'] ?? 'sistema',
            'accion' => $entry['accion'] ?? 'evento',
            'tabla_afectada' => $entry['tabla_afectada'] ?? null,
            'registro_id' => $entry['registro_id'] ?? null,
            'datos_anteriores' => $this->toJson($entry['datos_anteriores'] ?? null),
            'datos_nuevos' => $this->toJson($entry['datos_nuevos'] ?? null),
            'detalle' => $entry['detalle'] ?? null,
            'fecha_evento' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $insertCols = [];
        $insertPlaceholders = [];
        $insertValues = [];

        foreach ($payload as $column => $value) {
            if (!in_array($column, $columns, true)) {
                continue;
            }

            $insertCols[] = $column;
            $insertPlaceholders[] = ':' . $column;
            $insertValues[':' . $column] = $value;
        }

        if ($insertCols === []) {
            return;
        }

        $sql = sprintf(
            'INSERT INTO auditoria (%s) VALUES (%s)',
            implode(', ', $insertCols),
            implode(', ', $insertPlaceholders)
        );

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($insertValues);
        } catch (Throwable) {
            // No bloquear flujo principal por fallas de auditoria.
        }
    }

    private function auditoriaColumns(PDO $pdo): array
    {
        if (is_array(self::$tableColumns)) {
            return self::$tableColumns;
        }

        try {
            $stmt = $pdo->query('SHOW COLUMNS FROM auditoria');
            $rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
            self::$tableColumns = array_values(array_filter(array_map(static fn (array $row): string => (string) ($row['Field'] ?? ''), $rows)));
        } catch (Throwable) {
            self::$tableColumns = [];
        }

        return self::$tableColumns;
    }

    private function toJson(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
