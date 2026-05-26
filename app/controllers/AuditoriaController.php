<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditoriaService;

final class AuditoriaController extends Controller
{
    private AuditoriaService $service;

    public function __construct()
    {
        $this->service = new AuditoriaService();
    }

    public function index(Request $request, Response $response): never
    {
        $filters = [
            'q' => (string) $request->input('q', ''),
            'usuario_id' => (int) $request->input('usuario_id', 0),
            'empresa_id' => (int) $request->input('empresa_id', 0),
            'modulo' => (string) $request->input('modulo', ''),
            'accion' => (string) $request->input('accion', ''),
            'fecha_desde' => (string) $request->input('fecha_desde', ''),
            'fecha_hasta' => (string) $request->input('fecha_hasta', ''),
        ];

        $data = $this->service->data($filters);

        $response->view('auditoria/index', [
            'rows' => $data['rows'],
            'usuarios' => $data['usuarios'],
            'empresas' => $data['empresas'],
            'modulos' => $data['modulos'],
            'acciones' => $data['acciones'],
            'filters' => $filters,
            'csrfToken' => Csrf::token((int) config('auth.csrf_token_ttl', 3600)),
        ]);
    }
}
