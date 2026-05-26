<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\DiagnosticoService;
use Throwable;

final class DiagnosticoController extends Controller
{
    private DiagnosticoService $service;

    public function __construct()
    {
        $this->service = new DiagnosticoService();
    }

    public function index(Request $request, Response $response): never
    {
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);
        $data = $this->service->data($empresaId);

        $response->view('diagnosticos/index', [
            'catalogo' => $data['catalogo'],
            'consultas' => $data['consultas'],
            'asignaciones' => $data['asignaciones'],
            'csrfToken' => Csrf::token((int) config('auth.csrf_token_ttl', 3600)),
            'success' => flash_get('success'),
            'error' => flash_get('error'),
        ]);
    }

    public function createCatalogo(Request $request, Response $response): never
    {
        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/diagnosticos');
        }

        try {
            $this->service->createCatalogo($request->all());
            flash_set('success', 'Diagnostico agregado al catalogo.');
        } catch (Throwable $e) {
            flash_set('error', $e->getMessage());
        }

        $response->redirect('/diagnosticos');
    }

    public function asignar(Request $request, Response $response): never
    {
        $user = Session::get((string) config('auth.session_key'));
        if (!is_array($user)) {
            $response->redirect('/login');
        }

        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/diagnosticos');
        }

        try {
            $this->service->asignar((int) $user['id'], $request->all());
            flash_set('success', 'Diagnostico aplicado a la consulta.');
        } catch (Throwable $e) {
            flash_set('error', $e->getMessage());
        }

        $response->redirect('/diagnosticos');
    }
}
