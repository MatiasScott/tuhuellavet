<?php

namespace App\Middlewares;

use App\Core\Request;

class AuthMiddleware
{
    public function handle(Request $request): void
    {
        if (!is_authenticated()) {
            header('Location: ' . url('/login'));
            exit;
        }
    }
}
