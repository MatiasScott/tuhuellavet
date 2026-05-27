<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AccessControlService;

final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): void
    {
        if (Session::get((string) config('auth.session_key')) === null) {
            (new Response())->redirect('/login');
        }

        $access = new AccessControlService();
        if ($access->canAccessRoute($request) !== true) {
            flash_set('error', 'No tienes permisos para acceder a ese modulo.');
            (new Response())->redirect('/dashboard');
        }
    }
}
