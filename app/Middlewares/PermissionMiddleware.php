<?php

namespace App\Middlewares;

use App\Core\Request;

class PermissionMiddleware
{
    public function __construct(private ?string $permission) {}
    public function handle(Request $request): void
    {
        if (!$this->permission || !can($this->permission)) {
            http_response_code(403);
            echo 'Acceso denegado.';
            exit;
        }
    }
}
