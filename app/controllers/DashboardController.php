<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\DashboardService;

final class DashboardController extends Controller
{
    private DashboardService $service;

    public function __construct()
    {
        $this->service = new DashboardService();
    }

    public function index(Request $request, Response $response): never
    {
        $user = Session::get((string) config('auth.session_key'));
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);

        $dashboardData = [
            'rolNombre' => 'Sin rol',
            'empresaTipo' => 'global',
            'widgets' => [],
            'metrics' => [
                'animales' => 0,
                'propietarios' => 0,
                'consultas_hoy' => 0,
                'hospitalizaciones_activas' => 0,
                'vacunas_proximas_7_dias' => 0,
                'examenes_mes' => 0,
                'cirugias_mes' => 0,
                'eventos_timeline_7_dias' => 0,
            ],
        ];

        if (is_array($user) && isset($user['id']) && $empresaId > 0) {
            $dashboardData = $this->service->buildData((int) $user['id'], $empresaId);
        }

        $response->view('dashboard/index', [
            'user' => $user,
            'empresaId' => $empresaId,
            'csrfToken' => Csrf::token((int) config('auth.csrf_token_ttl', 3600)),
            'rolNombre' => $dashboardData['rolNombre'],
            'empresaTipo' => $dashboardData['empresaTipo'],
            'widgets' => $dashboardData['widgets'],
            'metrics' => $dashboardData['metrics'],
        ]);
    }
}
