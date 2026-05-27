<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Request;
use App\Core\Session;

final class AccessControlService
{
    public function canAccessRoute(Request $request): bool
    {
        $user = Session::get((string) config('auth.session_key'));
        if (!is_array($user)) {
            return false;
        }

        $role = (string) ($user['rol_codigo'] ?? 'invitado');
        $path = rtrim($request->uri(), '/') ?: '/';
        $method = strtoupper($request->method());

        if ($role === 'super_administrador') {
            return true;
        }

        $alwaysAllowed = (array) config('permissions.always_allowed_paths', []);
        if (in_array($path, $alwaysAllowed, true)) {
            return true;
        }

        $requiredPermission = (string) (config('permissions.route_map.' . $method . '.' . $path, '') ?? '');

        if ($requiredPermission === '') {
            return $role === 'administrador';
        }

        if ($this->isBlockedByRole($role, $requiredPermission)) {
            return false;
        }

        $permissions = $this->sessionPermissions($user);
        return $this->hasPermission($permissions, $requiredPermission);
    }

    public function can(string $permission): bool
    {
        $user = Session::get((string) config('auth.session_key'));
        if (!is_array($user)) {
            return false;
        }

        $role = (string) ($user['rol_codigo'] ?? 'invitado');
        if ($role === 'super_administrador') {
            return true;
        }

        if ($this->isBlockedByRole($role, $permission)) {
            return false;
        }

        return $this->hasPermission($this->sessionPermissions($user), $permission);
    }

    private function sessionPermissions(array $user): array
    {
        $permissions = $user['permisos'] ?? [];
        if (!is_array($permissions)) {
            $permissions = [];
        }

        return array_values(array_unique(array_map(static fn ($v): string => (string) $v, $permissions)));
    }

    private function hasPermission(array $permissions, string $permission): bool
    {
        if (in_array('*', $permissions, true)) {
            return true;
        }

        return in_array($permission, $permissions, true);
    }

    private function isBlockedByRole(string $role, string $permission): bool
    {
        $blocked = (array) config('permissions.blocked_by_role.' . $role, []);

        foreach ($blocked as $patternRaw) {
            $pattern = (string) $patternRaw;
            if (str_ends_with($pattern, '.*')) {
                $prefix = substr($pattern, 0, -1);
                if (str_starts_with($permission, $prefix)) {
                    return true;
                }
                continue;
            }

            if ($pattern === $permission) {
                return true;
            }
        }

        return false;
    }
}
