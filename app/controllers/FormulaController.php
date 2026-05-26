<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\FormulaService;
use Throwable;

final class FormulaController extends Controller
{
    private FormulaService $service;

    public function __construct()
    {
        $this->service = new FormulaService();
    }

    public function index(Request $request, Response $response): never
    {
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);

        $response->view('formulas/index', [
            'rows' => $this->service->list($empresaId),
            'csrfToken' => Csrf::token((int) config('auth.csrf_token_ttl', 3600)),
            'success' => flash_get('success'),
            'error' => flash_get('error'),
        ]);
    }

    public function createForm(Request $request, Response $response): never
    {
        $response->view('formulas/create', [
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
            $response->redirect('/formulas/crear');
        }

        try {
            $this->service->create($empresaId, (int) $user['id'], $request->all(), $this->auditMeta($request));
            flash_set('success', 'Formula creada correctamente.');
            $response->redirect('/formulas');
        } catch (Throwable $e) {
            flash_set('error', $e->getMessage());
            $response->redirect('/formulas/crear');
        }
    }

    public function edit(Request $request, Response $response): never
    {
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);
        $id = (int) $request->input('id', 0);

        $formula = $this->service->find($id, $empresaId);
        if (!is_array($formula)) {
            flash_set('error', 'Formula no encontrada.');
            $response->redirect('/formulas');
        }

        $response->view('formulas/edit', [
            'formula' => $formula,
            'csrfToken' => Csrf::token((int) config('auth.csrf_token_ttl', 3600)),
            'error' => flash_get('error'),
        ]);
    }

    public function update(Request $request, Response $response): never
    {
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);
        $user = Session::get((string) config('auth.session_key'));
        if (!is_array($user)) {
            $response->redirect('/login');
        }

        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/formulas');
        }

        $id = (int) $request->input('id', 0);

        try {
            $this->service->update($id, $empresaId, (int) $user['id'], $request->all(), $this->auditMeta($request));
            flash_set('success', 'Formula actualizada.');
            $response->redirect('/formulas');
        } catch (Throwable $e) {
            flash_set('error', $e->getMessage());
            $response->redirect('/formulas/editar?id=' . $id);
        }
    }

    public function delete(Request $request, Response $response): never
    {
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);
        $user = Session::get((string) config('auth.session_key'));
        if (!is_array($user)) {
            $response->redirect('/login');
        }

        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/formulas');
        }

        $id = (int) $request->input('id', 0);

        try {
            $this->service->delete($id, $empresaId, (int) $user['id'], $this->auditMeta($request));
            flash_set('success', 'Formula eliminada.');
        } catch (Throwable $e) {
            flash_set('error', $e->getMessage());
        }

        $response->redirect('/formulas');
    }

    public function toggle(Request $request, Response $response): never
    {
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);
        $user = Session::get((string) config('auth.session_key'));
        if (!is_array($user)) {
            $response->redirect('/login');
        }

        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/formulas');
        }

        $id = (int) $request->input('id', 0);
        $activo = ((int) $request->input('estado', 0)) === 1;

        try {
            $this->service->setEstado($id, $empresaId, (int) $user['id'], $activo, $this->auditMeta($request));
            flash_set('success', $activo ? 'Formula activada.' : 'Formula desactivada.');
        } catch (Throwable $e) {
            flash_set('error', $e->getMessage());
        }

        $response->redirect('/formulas');
    }

    public function test(Request $request, Response $response): never
    {
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);
        $id = (int) $request->input('id', 0);
        $formula = $this->service->find($id, $empresaId);

        if (!is_array($formula)) {
            $response->json(['ok' => false, 'message' => 'Formula no encontrada.'], 404);
        }

        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            $response->json(['ok' => false, 'message' => 'Token CSRF invalido.'], 422);
        }

        $inputs = [];
        foreach ($request->all() as $key => $value) {
            if (str_starts_with((string) $key, 'var_')) {
                $inputs[substr((string) $key, 4)] = $value;
            }
        }

        if ($inputs === []) {
            $detected = $this->service->detectVariables((string) $formula['expresion_formula']);
            if ($detected === []) {
                try {
                    $result = $this->service->test((string) $formula['expresion_formula'], []);
                    $response->json(['ok' => true, 'data' => $result]);
                } catch (Throwable $e) {
                    $response->json(['ok' => false, 'message' => $e->getMessage()], 422);
                }
            }

            $response->json([
                'ok' => true,
                'data' => [
                    'variables' => $detected,
                ],
            ]);
        }

        try {
            $result = $this->service->test((string) $formula['expresion_formula'], $inputs);
            $response->json(['ok' => true, 'data' => $result]);
        } catch (Throwable $e) {
            $response->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function detectVariables(Request $request, Response $response): never
    {
        $expression = (string) $request->input('expresion_formula', '');

        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            $response->json(['ok' => false, 'message' => 'Token CSRF invalido.'], 422);
        }

        try {
            $variables = $this->service->detectVariables($expression);
            $response->json(['ok' => true, 'variables' => $variables]);
        } catch (Throwable $e) {
            $response->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    private function auditMeta(Request $request): array
    {
        return [
            'ip' => $request->server('REMOTE_ADDR'),
            'user_agent' => (string) $request->server('HTTP_USER_AGENT', ''),
        ];
    }
}
