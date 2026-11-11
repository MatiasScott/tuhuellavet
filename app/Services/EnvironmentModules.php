<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;

class EnvironmentModules
{
    public static function isFarm(?array $environment): bool
    {
        return ($environment['codigo'] ?? '') === 'HACIENDA_AGUSBELLA';
    }

    public static function allows(string $permission, ?array $environment): bool
    {
        $module = explode('.', strtolower($permission), 2)[0];
        if (self::isFarm($environment)) {
            return !in_array($module, ['peluqueria', 'formulas', 'ventas', 'facturacion'], true);
        }
        return !in_array($module, ['lotes_animales', 'produccion', 'genealogia', 'alimentacion'], true);
    }

    public static function assertFarm(int $environmentId): void
    {
        if (!self::isFarm(self::find($environmentId))) {
            throw new RuntimeException('Esta operacion solo esta disponible en Hacienda Agusbella.');
        }
    }

    public static function find(int $environmentId): ?array
    {
        $stmt = Database::connection()->prepare('SELECT id, codigo FROM entornos WHERE id = ? AND activo = 1 AND deleted_at IS NULL');
        $stmt->execute([$environmentId]);
        return $stmt->fetch() ?: null;
    }
}
