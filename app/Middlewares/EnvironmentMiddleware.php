<?php

namespace App\Middlewares;

use App\Core\Database;
use App\Core\Request;
use App\Services\PermissionService;

class EnvironmentMiddleware
{
    public function handle(Request $request): void
    {
        $environmentId = active_environment_id();
        $userId = auth_id();

        if ($environmentId === null) {
            header(
                'Location: '
                . url('/seleccionar-entorno')
            );
            exit;
        }

        if (!$userId) {
            http_response_code(403);
            echo 'Usuario no autenticado.';
            exit;
        }

        $permissions = new PermissionService();

        if (
            $permissions->hasGlobalSuperAdmin(
                (int) $userId
            )
        ) {
            return;
        }

        $db = Database::connection();

        $stmt = $db->prepare(
            '
            SELECT COUNT(*)

            FROM usuarios_entornos_roles uer

            INNER JOIN usuarios u
                ON u.id = uer.usuario_id

            INNER JOIN entornos e
                ON e.id = uer.entorno_id

            INNER JOIN roles r
                ON r.id = uer.rol_id

            WHERE uer.usuario_id = :usuario
              AND uer.entorno_id = :entorno

              AND u.activo = 1
              AND u.deleted_at IS NULL

              AND e.activo = 1
              AND e.deleted_at IS NULL

              AND r.activo = 1
            '
        );

        $stmt->execute([
            'usuario' => (int) $userId,
            'entorno' => (int) $environmentId,
        ]);

        if ((int) $stmt->fetchColumn() === 0) {
            http_response_code(403);
            echo 'Entorno no autorizado.';
            exit;
        }
    }
}