<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\TimelineService;

final class TimelineController extends Controller
{
    private TimelineService $service;

    public function __construct()
    {
        $this->service = new TimelineService();
    }

    public function index(Request $request, Response $response): never
    {
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);

        $data = $this->service->data($empresaId, [
            'animal_id' => $request->input('animal_id'),
            'propietario_id' => $request->input('propietario_id'),
            'fecha_inicio' => $request->input('fecha_inicio'),
            'fecha_fin' => $request->input('fecha_fin'),
        ]);

        $response->view('timeline/index', [
            'animales' => $data['animales'],
            'propietarios' => $data['propietarios'],
            'rows' => $data['rows'],
            'filters' => $data['filters'],
        ]);
    }
}
