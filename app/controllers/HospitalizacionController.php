<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\HospitalizacionService;
use Throwable;

final class HospitalizacionController extends Controller
{
    private HospitalizacionService $service;

    public function __construct()
    {
        $this->service = new HospitalizacionService();
    }

    public function index(Request $request, Response $response): never
    {
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);
        $data = $this->service->data($empresaId);

        $response->view('hospitalizaciones/index', [
            'animales' => $data['animales'],
            'consultas' => $data['consultas'],
            'tamanos' => $data['tamanos'],
            'hospitalizaciones' => $data['hospitalizaciones'],
            'fluidoterapia' => $data['fluidoterapia'],
            'csrfToken' => Csrf::token((int) config('auth.csrf_token_ttl', 3600)),
            'success' => flash_get('success'),
            'error' => flash_get('error'),
        ]);
    }

    public function createTamano(Request $request, Response $response): never
    {
        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/hospitalizaciones');
        }

        try {
            $this->service->createTamano($request->all());
            flash_set('success', 'Tamano de animal registrado.');
        } catch (Throwable $e) {
            flash_set('error', $e->getMessage());
        }

        $response->redirect('/hospitalizaciones');
    }

    public function createHospitalizacion(Request $request, Response $response): never
    {
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);
        $user = Session::get((string) config('auth.session_key'));
        if (!is_array($user)) {
            $response->redirect('/login');
        }

        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/hospitalizaciones');
        }

        try {
            $this->service->createHospitalizacion($empresaId, (int) $user['id'], $request->all());
            flash_set('success', 'Hospitalizacion registrada.');
        } catch (Throwable $e) {
            flash_set('error', $e->getMessage());
        }

        $response->redirect('/hospitalizaciones');
    }

    public function updateEstado(Request $request, Response $response): never
    {
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);

        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/hospitalizaciones');
        }

        try {
            $this->service->updateEstado($empresaId, $request->all());
            flash_set('success', 'Estado de hospitalizacion actualizado.');
        } catch (Throwable $e) {
            flash_set('error', $e->getMessage());
        }

        $response->redirect('/hospitalizaciones');
    }

    public function createFluidoterapia(Request $request, Response $response): never
    {
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);

        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/hospitalizaciones');
        }

        try {
            $this->service->createFluidoterapia($empresaId, $request->all());
            flash_set('success', 'Registro de fluidoterapia guardado.');
        } catch (Throwable $e) {
            flash_set('error', $e->getMessage());
        }

        $response->redirect('/hospitalizaciones');
    }
}
