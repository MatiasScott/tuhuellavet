<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\PropietarioService;
use Throwable;

final class PropietarioController extends Controller
{
    private PropietarioService $service;

    public function __construct()
    {
        $this->service = new PropietarioService();
    }

    public function index(Request $request, Response $response): never
    {
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);

        $response->view('propietarios/index', [
            'rows' => $this->service->listByEmpresa($empresaId),
            'csrfToken' => Csrf::token((int) config('auth.csrf_token_ttl', 3600)),
            'success' => flash_get('success'),
            'error' => flash_get('error'),
        ]);
    }

    public function create(Request $request, Response $response): never
    {
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);

        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/propietarios');
        }

        try {
            $this->service->create($empresaId, $request->all(), $request->file('foto'));
            flash_set('success', 'Propietario creado correctamente.');
        } catch (Throwable $e) {
            flash_set('error', $e->getMessage());
        }

        $response->redirect('/propietarios');
    }

    public function edit(Request $request, Response $response): never
    {
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);
        $id = (int) $request->input('id', 0);
        $row = $this->service->find($id, $empresaId);

        if (!is_array($row)) {
            flash_set('error', 'Propietario no encontrado.');
            $response->redirect('/propietarios');
        }

        $response->view('propietarios/edit', [
            'row' => $row,
            'csrfToken' => Csrf::token((int) config('auth.csrf_token_ttl', 3600)),
            'error' => flash_get('error'),
        ]);
    }

    public function update(Request $request, Response $response): never
    {
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);
        $id = (int) $request->input('id', 0);

        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/propietarios/editar?id=' . $id);
        }

        try {
            $this->service->update($id, $empresaId, $request->all(), $request->file('foto'));
            flash_set('success', 'Propietario actualizado correctamente.');
            $response->redirect('/propietarios');
        } catch (Throwable $e) {
            flash_set('error', $e->getMessage());
            $response->redirect('/propietarios/editar?id=' . $id);
        }
    }
}
