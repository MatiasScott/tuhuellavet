<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use Throwable;

final class AuditService
{
    public function log(array $entry): void
    {
        $pdo = Database::connection((array) config('database'));

        $sql = 'INSERT INTO auditoria (usuario_id, ip, modulo, accion, fecha_evento, datos_anteriores, datos_nuevos) VALUES (:usuario_id, :ip, :modulo, :accion, NOW(), :datos_anteriores, :datos_nuevos)';

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':usuario_id' => $entry['usuario_id'] ?? null,
                ':ip' => $entry['ip'] ?? null,
                ':modulo' => $entry['modulo'] ?? 'sistema',
                ':accion' => $entry['accion'] ?? 'evento',
                ':datos_anteriores' => $this->toJson($entry['datos_anteriores'] ?? null),
                ':datos_nuevos' => $this->toJson($entry['datos_nuevos'] ?? null),
            ]);
        } catch (Throwable) {
            // No bloquear flujo principal por fallas de auditoria.
        }
    }

    private function toJson(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
