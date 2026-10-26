<?php

namespace App\Services;

use App\Models\User;
use App\Models\Environment;
use App\Core\Session;

class AuthService
{
    public function attempt(string $email, string $password): bool
    {
        $users = new User();
        $user = $users->findByEmail($email);

        if (!$user || empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        $this->establishSession($user);
        $users->touchLastLogin((int)$user['id']);
        (new AuditService())->log((int)$user['id'], null, 'AUTH', 'LOGIN_LOCAL');
        return true;
    }

    public function establishSession(array $user): void
    {
        Session::regenerate();
        $permissionService = new PermissionService();
        $isSuperAdmin = $permissionService->hasGlobalSuperAdmin((int)$user['id']);

        Session::put('auth_user', [
            'id' => (int)$user['id'],
            'name' => trim($user['nombres'] . ' ' . $user['apellidos']),
            'email' => $user['email'],
            'photo' => $user['foto_path'],
            'requires_password_change' => (bool)$user['requiere_cambio_password'],
            'is_super_admin' => $isSuperAdmin,
            'roles' => [],
            'active_role' => null,
            'permissions' => [],
        ]);

        Session::forget('active_environment_id');
        Session::forget('active_environment');
    }

    public function selectEnvironment(int $environmentId): bool
    {
        $user = auth_user();
        if (!$user) return false;

        $envModel = new Environment();
        $environment = !empty($user['is_super_admin'])
            ? $this->findAnyEnvironment($environmentId)
            : $envModel->findForUser((int)$user['id'], $environmentId);

        if (!$environment) return false;

        $permissions = new PermissionService();
        $user['roles'] = !empty($user['is_super_admin'])
            ? ['SUPER_ADMINISTRADOR']
            : $permissions->rolesForEnvironment((int)$user['id'], $environmentId);

        // Al cambiar de entorno, invalidamos el perfil anterior.
        $user['active_role'] = null;
        $user['permissions'] = [];

        // Si existe un único rol, lo seleccionamos automáticamente.
        if (count($user['roles']) === 1) {
            $roleCode = $user['roles'][0];

            $user['active_role'] = $roleCode;

            $user['permissions'] = $permissions->permissionsForRole(
                (int)$user['id'],
                $environmentId,
                $roleCode
            );
        }

        Session::put('auth_user', $user);
        Session::put('active_environment_id', $environmentId);
        Session::put('active_environment', $environment);

        (new AuditService())->log((int)$user['id'], $environmentId, 'AUTH', 'CAMBIAR_ENTORNO');
        return true;
    }

    /**
     * Activa un rol asignado al usuario
     * dentro del entorno seleccionado.
     */
    public function selectRole(string $roleCode): bool
    {
        $user = auth_user();
        $environmentId = active_environment_id();

        if (!$user || !$environmentId) {
            return false;
        }

        $roleCode = trim($roleCode);

        $permissions = new PermissionService();

        // Volvemos a consultar los roles autorizados en la BD.
        $availableRoles = !empty($user['is_super_admin'])
            ? ['SUPER_ADMINISTRADOR']
            : $permissions->rolesForEnvironment(
                (int)$user['id'],
                (int)$environmentId
            );

        if (!in_array($roleCode, $availableRoles, true)) {
            return false;
        }

        $user['roles'] = $availableRoles;
        $user['active_role'] = $roleCode;
        $user['permissions'] = $permissions->permissionsForRole(
            (int)$user['id'],
            (int)$environmentId,
            $roleCode
        );

        Session::put('auth_user', $user);

        (new AuditService())->log(
            (int)$user['id'],
            (int)$environmentId,
            'AUTH',
            'CAMBIAR_ROL'
        );

        return true;
    }

    private function findAnyEnvironment(int $environmentId): ?array
    {
        foreach ((new Environment())->allActive() as $environment) {
            if ((int)$environment['id'] === $environmentId) return $environment;
        }
        return null;
    }

    public function logout(): void
    {
        if (auth_id()) (new AuditService())->log(auth_id(), active_environment_id(), 'AUTH', 'LOGOUT');
        Session::flush();
    }
}
