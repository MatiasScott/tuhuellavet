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

    public function updateCatalogo(Request $request, Response $response): never
    {
        $user = Session::get((string) config('auth.session_key'));
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);
        if (!is_array($user)) {
            $response->redirect('/login');
        }

        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/diagnosticos');
        }

        try {
            $this->service->updateCatalogo((int) $request->input('id', 0), (int) $user['id'], $empresaId, $request->all(), $this->auditMeta($request));
            flash_set('success', 'Diagnostico actualizado.');
        } catch (Throwable $e) {
            flash_set('error', $e->getMessage());
        }

        $response->redirect('/diagnosticos');
    }

    public function deleteCatalogo(Request $request, Response $response): never
    {
        $user = Session::get((string) config('auth.session_key'));
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);
        if (!is_array($user)) {
            $response->redirect('/login');
        }

        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/diagnosticos');
        }

        try {
            $this->service->deleteCatalogo((int) $request->input('id', 0), (int) $user['id'], $empresaId, $this->auditMeta($request));
            flash_set('success', 'Diagnostico eliminado.');
        } catch (Throwable $e) {
            flash_set('error', $e->getMessage());
        }

        $response->redirect('/diagnosticos');
    }

    private function auditMeta(Request $request): array
    {
        return [
            'ip' => $request->server('REMOTE_ADDR'),
            'user_agent' => (string) $request->server('HTTP_USER_AGENT', ''),
        ];
    }
}
