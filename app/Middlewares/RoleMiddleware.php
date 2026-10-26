<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\Request;

class RoleMiddleware
{
    public function __construct(
        private ?string $role
    ) {}

    public function handle(Request $request): void
    {
        $user = auth_user();

        if (!$user) {
            http_response_code(403);
            echo 'Usuario no autenticado.';
            exit;
        }

        $activeRole = $user['active_role'] ?? null;
        $availableRoles = $user['roles'] ?? [];

        // Si todavía no seleccionó un perfil,
        // enviarlo al selector.
        if ($activeRole === null) {
            header('Location: ' . url('/seleccionar-rol'));
            exit;
        }

        // Verificar que el perfil activo siga estando
        // entre los roles autorizados de la sesión.
        if (!in_array($activeRole, $availableRoles, true)) {
            http_response_code(403);
            echo 'Perfil activo no autorizado.';
            exit;
        }

        // La ruta debe coincidir con el perfil activo.
        if (!$this->role || $activeRole !== $this->role) {
            http_response_code(403);
            echo 'El perfil seleccionado no tiene acceso a esta sección.';
            exit;
        }
    }
}