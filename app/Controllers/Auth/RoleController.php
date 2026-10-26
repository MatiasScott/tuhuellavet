<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Session;
use App\Core\Controller;
use App\Core\Request;
use App\Services\AuthService;

class RoleController extends Controller
{
    /**
     * Muestra los perfiles disponibles.
     */
    public function index(Request $request): void
    {
        $user = auth_user();

        if (!$user) {
            $this->redirect('/login');
            return;
        }

        if (active_environment_id() === null) {
            $this->redirect('/seleccionar-entorno');
            return;
        }

        // Consultar los perfiles vigentes del usuario en el entorno.
        $permissionService = new \App\Services\PermissionService();

        $roles = $permissionService->hasGlobalSuperAdmin((int)$user['id'])
            ? ['SUPER_ADMINISTRADOR']
            : $permissionService->rolesForEnvironment(
                (int)$user['id'],
                (int)active_environment_id()
            );

        if (count($roles) === 1) {
            $role = $roles[0];

            // El único perfil ya fue activado al seleccionar el entorno.
            if (($user['active_role'] ?? null) === $role) {
                $this->redirect('/dashboard');
                return;
            }

            // Recuperar el perfil si todavía no está activo.
            if ((new AuthService())->selectRole($role)) {
                $this->redirect('/dashboard');
                return;
            }
        }

        $this->view(
            'auth/select-role',
            [
                'roles' => $roles,
                'activeRole' => $user['active_role'] ?? null,
                'environment' => active_environment(),
            ],
            'layouts/auth'
        );
    }

    /**
     * Activa el perfil seleccionado.
     */
    public function select(Request $request): void
    {
        // Validación CSRF.
        if (!Session::validateCsrf($request->input('_token'))) {
            http_response_code(419);
            echo 'Sesión expirada. Actualiza la página e inténtalo nuevamente.';
            return;
        }

        // Debe existir un entorno seleccionado.
        if (active_environment_id() === null) {
            $this->redirect('/seleccionar-entorno');
            return;
        }

        $roleCode = trim((string)$request->input('role'));

        if ($roleCode === '') {
            Session::flash('error', 'Debes seleccionar un perfil.');
            $this->redirect('/seleccionar-rol');
            return;
        }

        // AuthService verifica que el rol esté asignado al usuario.
        if (!(new AuthService())->selectRole($roleCode)) {
            Session::flash(
                'error',
                'No tienes autorización para utilizar el perfil seleccionado.'
            );

            $this->redirect('/seleccionar-rol');
            return;
        }

        // Redirigimos al panel correspondiente al rol activo.
        $this->redirect('/dashboard');
    }
}
