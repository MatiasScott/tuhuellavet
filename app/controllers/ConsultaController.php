<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AnimalService;
use App\Services\ConsultaService;
use Throwable;

final class ConsultaController extends Controller
{
    private ConsultaService $service;

    public function __construct()
    {
        $this->service = new ConsultaService();
    }

    public function index(Request $request, Response $response): never
    {
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);
        $animalService = new AnimalService();

        $response->view('consultas/index', [
            'rows' => $this->service->listByEmpresa($empresaId),
            'animales' => $animalService->listByEmpresa($empresaId),
            'csrfToken' => Csrf::token((int) config('auth.csrf_token_ttl', 3600)),
            'success' => flash_get('success'),
            'error' => flash_get('error'),
        ]);
    }

    public function createForm(Request $request, Response $response): never
    {
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);
        $animalService = new AnimalService();

        $response->view('consultas/create', [
            'animales' => $animalService->listByEmpresa($empresaId),
            'csrfToken' => Csrf::token((int) config('auth.csrf_token_ttl', 3600)),
            'error' => flash_get('error'),
        ]);
    }

    public function create(Request $request, Response $response): never
    {
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);
        $user = Session::get((string) config('auth.session_key'));

        if (!is_array($user)) {
            $response->redirect('/login');
        }

        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/consultas');
        }

        try {
            $this->service->create($empresaId, (int) $user['id'], $request->all());
            flash_set('success', 'Consulta registrada correctamente.');
        } catch (Throwable $e) {
            flash_set('error', $e->getMessage());
        }

        $response->redirect('/consultas');
    }
}
