<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AdminBusinessService;
use Throwable;

final class AdminBusinessController extends Controller
{
    private AdminBusinessService $service;

    public function __construct()
    {
        $this->service = new AdminBusinessService();
    }

    public function empresasIndex(Request $request, Response $response): never
    {
        $data = $this->service->empresasData();

        $response->view('empresas/index', [
            'empresas' => $data['empresas'],
            'hasColorColumns' => $data['hasColorColumns'],
            'csrfToken' => Csrf::token((int) config('auth.csrf_token_ttl', 3600)),
            'success' => flash_get('success'),
            'error' => flash_get('error'),
        ]);
    }

    public function createEmpresa(Request $request, Response $response): never
    {
        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/empresas');
        }

        try {
            $this->service->createEmpresa($request->all(), $request->file('logo'));
            flash_set('success', 'Empresa creada correctamente.');
        } catch (Throwable $exception) {
            flash_set('error', $exception->getMessage());
        }

        $response->redirect('/empresas');
    }

    public function updateEmpresa(Request $request, Response $response): never
    {
        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/empresas');
        }

        try {
            $this->service->updateEmpresa((int) $request->input('id', 0), $request->all(), $request->file('logo'));
            flash_set('success', 'Empresa actualizada correctamente.');
        } catch (Throwable $exception) {
            flash_set('error', $exception->getMessage());
        }

        $response->redirect('/empresas');
    }

    public function medicamentosIndex(Request $request, Response $response): never
    {
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);
        $data = $this->service->medicamentosData($empresaId);

        $response->view('medicamentos/index', [
            'medicamentos' => $data['medicamentos'],
            'csrfToken' => Csrf::token((int) config('auth.csrf_token_ttl', 3600)),
            'success' => flash_get('success'),
            'error' => flash_get('error'),
        ]);
    }

    public function createMedicamento(Request $request, Response $response): never
    {
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);
        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/medicamentos');
        }

        try {
            $this->service->createMedicamento($empresaId, $request->all(), $request->file('foto'));
            flash_set('success', 'Medicamento creado correctamente.');
        } catch (Throwable $exception) {
            flash_set('error', $exception->getMessage());
        }

        $response->redirect('/medicamentos');
    }

    public function updateMedicamento(Request $request, Response $response): never
    {
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);
        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/medicamentos');
        }

        try {
            $this->service->updateMedicamento((int) $request->input('id', 0), $empresaId, $request->all(), $request->file('foto'));
            flash_set('success', 'Medicamento actualizado correctamente.');
        } catch (Throwable $exception) {
            flash_set('error', $exception->getMessage());
        }

        $response->redirect('/medicamentos');
    }

    public function laboratoriosIndex(Request $request, Response $response): never
    {
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);
        $data = $this->service->laboratoriosData($empresaId);

        $response->view('laboratorios/index', [
            'laboratorios' => $data['laboratorios'],
            'csrfToken' => Csrf::token((int) config('auth.csrf_token_ttl', 3600)),
            'success' => flash_get('success'),
            'error' => flash_get('error'),
        ]);
    }

    public function renameLaboratorio(Request $request, Response $response): never
    {
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);
        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/laboratorios');
        }

        try {
            $rows = $this->service->renameLaboratorio($empresaId, (string) $request->input('old_name', ''), (string) $request->input('new_name', ''));
            flash_set('success', 'Laboratorio estandarizado. Registros afectados: ' . $rows);
        } catch (Throwable $exception) {
            flash_set('error', $exception->getMessage());
        }

        $response->redirect('/laboratorios');
    }

    public function tiposExamenIndex(Request $request, Response $response): never
    {
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);
        $data = $this->service->tiposExamenData($empresaId);

        $response->view('tipos_examen/index', [
            'tipos' => $data['tipos'],
            'csrfToken' => Csrf::token((int) config('auth.csrf_token_ttl', 3600)),
            'success' => flash_get('success'),
            'error' => flash_get('error'),
        ]);
    }

    public function renameTipoExamen(Request $request, Response $response): never
    {
        $empresaId = (int) Session::get((string) config('auth.company_session_key'), 0);
        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/tipos-examen');
        }

        try {
            $rows = $this->service->renameTipoExamen($empresaId, (string) $request->input('old_name', ''), (string) $request->input('new_name', ''));
            flash_set('success', 'Tipo de examen estandarizado. Registros afectados: ' . $rows);
        } catch (Throwable $exception) {
            flash_set('error', $exception->getMessage());
        }

        $response->redirect('/tipos-examen');
    }
}
