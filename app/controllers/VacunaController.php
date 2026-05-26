<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\VacunaService;
use Throwable;

final class VacunaController extends Controller
{
    private VacunaService $service;

    public function __construct()
    {
        $this->service = new VacunaService();
    }

    public function index(Request $request, Response $response): never
    {
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);
        $data = $this->service->data($empresaId);

        $response->view('vacunas/index', [
            'catalogo' => $data['catalogo'],
            'animales' => $data['animales'],
            'consultas' => $data['consultas'],
            'aplicaciones' => $data['aplicaciones'],
            'csrfToken' => Csrf::token((int) config('auth.csrf_token_ttl', 3600)),
            'success' => flash_get('success'),
            'error' => flash_get('error'),
        ]);
    }

    public function createCatalogo(Request $request, Response $response): never
    {
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);

        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/vacunas');
        }

        try {
            $this->service->createCatalogo($empresaId, $request->all());
            flash_set('success', 'Vacuna agregada al catalogo.');
        } catch (Throwable $e) {
            flash_set('error', $e->getMessage());
        }

        $response->redirect('/vacunas');
    }

    public function aplicar(Request $request, Response $response): never
    {
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);
        $user = Session::get((string) config('auth.session_key'));
        if (!is_array($user)) {
            $response->redirect('/login');
        }

        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/vacunas');
        }

        try {
            $this->service->aplicar((int) $user['id'], $empresaId, $request->all());
            flash_set('success', 'Aplicacion de vacuna registrada.');
        } catch (Throwable $e) {
            flash_set('error', $e->getMessage());
        }

        $response->redirect('/vacunas');
    }
}
