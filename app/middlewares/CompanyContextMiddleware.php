<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class CompanyContextMiddleware implements MiddlewareInterface
{
    public function handle(Request $request): void
    {
        $path = rtrim($request->uri(), '/') ?: '/';

        if ($path === '/empresa/seleccionar') {
            return;
        }

        if (Session::get((string) config('auth.company_session_key')) === null) {
            (new Response())->redirect('/empresa/seleccionar');
        }
    }
}
