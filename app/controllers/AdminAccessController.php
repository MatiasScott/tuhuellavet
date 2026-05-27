<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Services\AdminAccessService;
use Throwable;

final class AdminAccessController extends Controller
{
    private AdminAccessService $service;

    public function __construct()
    {
        $this->service = new AdminAccessService();
    }

    public function usuariosIndex(Request $request, Response $response): never
    {
        $data = $this->service->usuariosData();

        $response->view('usuarios/index', [
            'usuarios' => $data['usuarios'],
            'roles' => $data['roles'],
            'empresas' => $data['empresas'],
            'csrfToken' => Csrf::token((int) config('auth.csrf_token_ttl', 3600)),
            'success' => flash_get('success'),
            'error' => flash_get('error'),
        ]);
    }

    public function createUsuario(Request $request, Response $response): never
    {
        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/usuarios');
        }

        try {
            $this->service->createUsuario($request->all());
            flash_set('success', 'Usuario creado correctamente.');
        } catch (Throwable $exception) {
            flash_set('error', $exception->getMessage());
        }

        $response->redirect('/usuarios');
    }

    public function updateUsuario(Request $request, Response $response): never
    {
        $id = (int) $request->input('id', 0);

        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/usuarios');
        }

        try {
            $this->service->updateUsuario($id, $request->all());
            flash_set('success', 'Usuario actualizado correctamente.');
        } catch (Throwable $exception) {
            flash_set('error', $exception->getMessage());
        }

        $response->redirect('/usuarios');
    }

    public function toggleUsuarioEstado(Request $request, Response $response): never
    {
        $id = (int) $request->input('id', 0);

        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/usuarios');
        }

        try {
            $this->service->toggleUsuarioEstado($id, (int) $request->input('estado', 0) === 1);
            flash_set('success', 'Estado de usuario actualizado.');
        } catch (Throwable $exception) {
            flash_set('error', $exception->getMessage());
        }

        $response->redirect('/usuarios');
    }

    public function resetUsuarioPassword(Request $request, Response $response): never
    {
        $id = (int) $request->input('id', 0);

        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/usuarios');
        }

        try {
            $this->service->resetPassword($id, (string) $request->input('new_password', ''));
            flash_set('success', 'Contrasena reseteada correctamente.');
        } catch (Throwable $exception) {
            flash_set('error', $exception->getMessage());
        }

        $response->redirect('/usuarios');
    }

    public function syncUsuarioEmpresasRol(Request $request, Response $response): never
    {
        $id = (int) $request->input('id', 0);

        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/usuarios');
        }

        try {
            $this->service->syncUsuarioEmpresasRol($id, $request->all());
            flash_set('success', 'Asignaciones de empresa y rol actualizadas.');
        } catch (Throwable $exception) {
            flash_set('error', $exception->getMessage());
        }

        $response->redirect('/usuarios');
    }

    public function rolesIndex(Request $request, Response $response): never
    {
        $data = $this->service->rolesData();

        $response->view('roles/index', [
            'roles' => $data['roles'],
            'permisos' => $data['permisos'],
            'modulosMatriz' => $data['modulosMatriz'],
            'accionesMatriz' => $data['accionesMatriz'],
            'csrfToken' => Csrf::token((int) config('auth.csrf_token_ttl', 3600)),
            'success' => flash_get('success'),
            'error' => flash_get('error'),
        ]);
    }

    public function createRol(Request $request, Response $response): never
    {
        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/roles');
        }

        try {
            $this->service->createRol($request->all());
            flash_set('success', 'Rol creado correctamente.');
        } catch (Throwable $exception) {
            flash_set('error', $exception->getMessage());
        }

        $response->redirect('/roles');
    }

    public function updateRol(Request $request, Response $response): never
    {
        $id = (int) $request->input('id', 0);

        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/roles');
        }

        try {
            $this->service->updateRol($id, $request->all());
            flash_set('success', 'Rol actualizado correctamente.');
        } catch (Throwable $exception) {
            flash_set('error', $exception->getMessage());
        }

        $response->redirect('/roles');
    }

    public function duplicateRol(Request $request, Response $response): never
    {
        $id = (int) $request->input('id', 0);

        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/roles');
        }

        try {
            $this->service->duplicateRol($id, (string) $request->input('new_name', ''));
            flash_set('success', 'Rol duplicado correctamente.');
        } catch (Throwable $exception) {
            flash_set('error', $exception->getMessage());
        }

        $response->redirect('/roles');
    }

    public function syncRolPermisos(Request $request, Response $response): never
    {
        $id = (int) $request->input('id', 0);

        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/roles');
        }

        try {
            $this->service->syncRolPermisos($id, $request->all());
            flash_set('success', 'Permisos del rol actualizados.');
        } catch (Throwable $exception) {
            flash_set('error', $exception->getMessage());
        }

        $response->redirect('/roles');
    }

    public function permisosIndex(Request $request, Response $response): never
    {
        $data = $this->service->permisosData();

        $response->view('permisos/index', [
            'permisos' => $data['permisos'],
            'modulos' => $data['modulos'],
            'csrfToken' => Csrf::token((int) config('auth.csrf_token_ttl', 3600)),
            'success' => flash_get('success'),
            'error' => flash_get('error'),
        ]);
    }

    public function createPermiso(Request $request, Response $response): never
    {
        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/permisos');
        }

        try {
            $this->service->createPermiso($request->all());
            flash_set('success', 'Permiso creado correctamente.');
        } catch (Throwable $exception) {
            flash_set('error', $exception->getMessage());
        }

        $response->redirect('/permisos');
    }

    public function updatePermiso(Request $request, Response $response): never
    {
        $id = (int) $request->input('id', 0);

        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/permisos');
        }

        try {
            $this->service->updatePermiso($id, $request->all());
            flash_set('success', 'Permiso actualizado correctamente.');
        } catch (Throwable $exception) {
            flash_set('error', $exception->getMessage());
        }

        $response->redirect('/permisos');
    }

    public function syncPermisos(Request $request, Response $response): never
    {
        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/permisos');
        }

        try {
            $stats = $this->service->sincronizarPermisos();
            flash_set(
                'success',
                'Sincronizacion completada. Modulos creados: ' . (int) ($stats['modulos_creados'] ?? 0)
                . ', permisos creados: ' . (int) ($stats['permisos_creados'] ?? 0)
                . ', permisos actualizados: ' . (int) ($stats['permisos_actualizados'] ?? 0)
                . ', demo permisos eliminados: ' . (int) ($stats['demo_permisos_eliminados'] ?? 0)
                . ', demo modulos eliminados: ' . (int) ($stats['demo_modulos_eliminados'] ?? 0)
            );
        } catch (Throwable $exception) {
            flash_set('error', $exception->getMessage());
        }

        $response->redirect('/permisos');
    }
}
