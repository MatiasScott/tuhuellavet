<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AnimalService;
use Throwable;

final class AnimalController extends Controller
{
    private AnimalService $service;

    public function __construct()
    {
        $this->service = new AnimalService();
    }

    public function index(Request $request, Response $response): never
    {
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);

        $response->view('animales/index', [
            'rows' => $this->service->listByEmpresa($empresaId),
            'catalogos' => $this->service->catalogos($empresaId),
            'csrfToken' => Csrf::token((int) config('auth.csrf_token_ttl', 3600)),
            'success' => flash_get('success'),
            'error' => flash_get('error'),
        ]);
    }

    public function createForm(Request $request, Response $response): never
    {
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);

        $response->view('animales/create', [
            'catalogos' => $this->service->catalogos($empresaId),
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
            $response->redirect('/animales');
        }

        try {
            $this->service->create($empresaId, (int) $user['id'], $request->all(), $request->file('foto'));
            flash_set('success', 'Paciente creado correctamente.');
        } catch (Throwable $e) {
            flash_set('error', $e->getMessage());
        }

        $response->redirect('/animales');
    }

    public function edit(Request $request, Response $response): never
    {
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);
        $id = (int) $request->input('id', 0);
        $row = $this->service->find($id, $empresaId);

        if (!is_array($row)) {
            flash_set('error', 'Paciente no encontrado.');
            $response->redirect('/animales');
        }

        $response->view('animales/edit', [
            'row' => $row,
            'catalogos' => $this->service->catalogos($empresaId),
            'csrfToken' => Csrf::token((int) config('auth.csrf_token_ttl', 3600)),
            'error' => flash_get('error'),
        ]);
    }

    public function update(Request $request, Response $response): never
    {
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);
        $user = Session::get((string) config('auth.session_key'));
        $id = (int) $request->input('id', 0);

        if (!is_array($user)) {
            $response->redirect('/login');
        }

        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/animales/editar?id=' . $id);
        }

        try {
            $this->service->update($id, $empresaId, (int) $user['id'], $request->all(), $request->file('foto'));
            flash_set('success', 'Paciente actualizado correctamente.');
            $response->redirect('/animales');
        } catch (Throwable $e) {
            flash_set('error', $e->getMessage());
            $response->redirect('/animales/editar?id=' . $id);
        }
    }
}
