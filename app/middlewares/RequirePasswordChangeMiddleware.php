<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class RequirePasswordChangeMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): void
    {
        $user = Session::get((string) config('auth.session_key'));

        if (is_array($user) && (int) ($user['require_password_change'] ?? 0) === 1) {
            $path = rtrim($request->uri(), '/') ?: '/';
            if (!in_array($path, ['/password/change', '/logout'], true)) {
                (new Response())->redirect('/password/change');
            }
        }
    }
}
