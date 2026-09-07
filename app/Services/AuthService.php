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

        if (!$user || empty($user['password_hash']) || !password_verify($password,$user['password_hash'])) {
            return false;
        }

        $this->establishSession($user);
        $users->touchLastLogin((int)$user['id']);
        (new AuditService())->log((int)$user['id'],null,'AUTH','LOGIN_LOCAL');
        return true;
    }

    public function establishSession(array $user): void
    {
        Session::regenerate();
        $permissionService = new PermissionService();
        $isSuperAdmin = $permissionService->hasGlobalSuperAdmin((int)$user['id']);

        Session::put('auth_user',[
            'id'=>(int)$user['id'],
            'name'=>trim($user['nombres'].' '.$user['apellidos']),
            'email'=>$user['email'],
            'photo'=>$user['foto_path'],
            'requires_password_change'=>(bool)$user['requiere_cambio_password'],
            'is_super_admin'=>$isSuperAdmin,
            'roles'=>[],
            'permissions'=>[],
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
            : $envModel->findForUser((int)$user['id'],$environmentId);

        if (!$environment) return false;

        $permissions = new PermissionService();
        $user['roles'] = !empty($user['is_super_admin'])
            ? ['SUPER_ADMINISTRADOR']
            : $permissions->rolesForEnvironment((int)$user['id'],$environmentId);

        $user['permissions'] = $permissions->permissionsForEnvironment((int)$user['id'],$environmentId);

        Session::put('auth_user',$user);
        Session::put('active_environment_id',$environmentId);
        Session::put('active_environment',$environment);

        (new AuditService())->log((int)$user['id'],$environmentId,'AUTH','CAMBIAR_ENTORNO');
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
        if (auth_id()) (new AuditService())->log(auth_id(),active_environment_id(),'AUTH','LOGOUT');
        Session::flush();
    }
}
