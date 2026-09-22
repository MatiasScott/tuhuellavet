<?php

namespace App\Middlewares;

use App\Core\Request;

class GuestMiddleware
{
    public function handle(Request $request): void
    {
        if (is_authenticated()) {
            header('Location: ' . url('/dashboard'));
            exit;
        }
    }
}
