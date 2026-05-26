<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\CirugiaService;
use Throwable;

final class CirugiaController extends Controller
{
    private CirugiaService $service;

    public function __construct()
    {
        $this->service = new CirugiaService();
    }

    public function index(Request $request, Response $response): never
    {
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);
        $data = $this->service->data($empresaId);

        $response->view('cirugias/index', [
            'animales' => $data['animales'],
            'consultas' => $data['consultas'],
            'formulas' => $data['formulas'],
            'rows' => $data['rows'],
            'csrfToken' => Csrf::token((int) config('auth.csrf_token_ttl', 3600)),
            'success' => flash_get('success'),
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
            $response->redirect('/cirugias');
        }

        try {
            $this->service->create($empresaId, (int) $user['id'], $request->all(), $request->file('archivo_pdf'));
            flash_set('success', 'Cirugia registrada.');
        } catch (Throwable $e) {
            flash_set('error', $e->getMessage());
        }

        $response->redirect('/cirugias');
    }
}
