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

        if (\App\Services\EnvironmentModules::isFarm(
            \App\Services\EnvironmentModules::find((int) $environmentId)
        ) && preg_match('#^/(peluquerias|formulas|admin/formulas|facturacion)(/|$)#', $request->uri())) {
            http_response_code(403);
            echo 'Modulo no disponible en Hacienda Agusbella.';
            exit;
        }

        if (!$userId) {
            http_response_code(403);
            echo 'Usuario no autenticado.';
            exit;
        }

        $permissions = new PermissionService();

        $isSuperAdmin = $permissions->hasGlobalSuperAdmin(
            (int) $userId
        );

        // Los usuarios normales deben tener acceso al entorno.
        // El superadministrador tiene autorización global.
        if (!$isSuperAdmin) {
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

        // Permitir acceder al selector sin un perfil activo.
        // Esto evita redirecciones circulares.
        if ($request->uri() === '/seleccionar-rol') {
            return;
        }

        // Verificar el perfil activo contra los roles actuales de la BD.
        $user = auth_user();

        $activeRole = $user['active_role'] ?? null;

        $availableRoles = $isSuperAdmin
            ? ['SUPER_ADMINISTRADOR']
            : $permissions->rolesForEnvironment(
                (int)$userId,
                (int)$environmentId
            );

        // Sincronizar los permisos vigentes del perfil activo.
        $currentPermissions = $permissions->permissionsForRole(
            (int)$userId,
            (int)$environmentId,
            (string)$activeRole
        );

        if (($user['permissions'] ?? []) !== $currentPermissions) {
            $user['permissions'] = $currentPermissions;

            \App\Core\Session::put('auth_user', $user);
        }

        // Si el rol fue retirado, invalidamos los permisos anteriores.
        if (
            !$activeRole ||
            !in_array($activeRole, $availableRoles, true)
        ) {
            $user['roles'] = $availableRoles;
            $user['active_role'] = null;
            $user['permissions'] = [];

            \App\Core\Session::put('auth_user', $user);

            header('Location: ' . url('/seleccionar-rol'));
            exit;
        }
    }
}
