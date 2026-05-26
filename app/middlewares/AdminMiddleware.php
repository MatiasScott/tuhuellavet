<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class AdminMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): void
    {
        $user = Session::get((string) config('auth.session_key'));
        $role = is_array($user) ? ($user['rol_codigo'] ?? null) : null;

        if (!in_array($role, ['super_administrador', 'administrador'], true)) {
            (new Response())->redirect('/dashboard');
        }
    }
}
