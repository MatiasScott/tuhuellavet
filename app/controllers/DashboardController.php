<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class DashboardController extends Controller
{
    public function index(Request $request, Response $response): never
    {
        $response->view('dashboard/index', [
            'user' => Session::get((string) config('auth.session_key')),
            'empresaId' => Session::get((string) config('auth.company_session_key')),
            'csrfToken' => Csrf::token((int) config('auth.csrf_token_ttl', 3600)),
        ]);
    }
}
