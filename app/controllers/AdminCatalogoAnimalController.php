<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Services\AdminCatalogoAnimalService;
use Throwable;

final class AdminCatalogoAnimalController extends Controller
{
    private AdminCatalogoAnimalService $service;

    public function __construct()
    {
        $this->service = new AdminCatalogoAnimalService();
    }

    public function especiesIndex(Request $request, Response $response): never
    {
        $data = $this->service->especiesData();

        $response->view('especies/index', [
            'especies' => $data['especies'],
            'categorias' => $data['categorias'],
            'canToggle' => $data['canToggle'],
            'csrfToken' => Csrf::token((int) config('auth.csrf_token_ttl', 3600)),
            'success' => flash_get('success'),
            'error' => flash_get('error'),
        ]);
    }

    public function createEspecie(Request $request, Response $response): never
    {
        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/especies');
        }

        try {
            $this->service->createEspecie($request->all());
            flash_set('success', 'Especie creada correctamente.');
        } catch (Throwable $exception) {
            flash_set('error', $exception->getMessage());
        }

        $response->redirect('/especies');
    }

    public function updateEspecie(Request $request, Response $response): never
    {
        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/especies');
        }

        try {
            $this->service->updateEspecie((int) $request->input('id', 0), $request->all());
            flash_set('success', 'Especie actualizada correctamente.');
        } catch (Throwable $exception) {
            flash_set('error', $exception->getMessage());
        }

        $response->redirect('/especies');
    }

    public function toggleEspecie(Request $request, Response $response): never
    {
        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/especies');
        }

        try {
            $this->service->toggleEstado('especies', (int) $request->input('id', 0), (int) $request->input('estado', 0));
            flash_set('success', 'Estado de especie actualizado.');
        } catch (Throwable $exception) {
            flash_set('error', $exception->getMessage());
        }

        $response->redirect('/especies');
    }

    public function razasIndex(Request $request, Response $response): never
    {
        $data = $this->service->razasData();

        $response->view('razas/index', [
            'razas' => $data['razas'],
            'especies' => $data['especies'],
            'canToggle' => $data['canToggle'],
            'csrfToken' => Csrf::token((int) config('auth.csrf_token_ttl', 3600)),
            'success' => flash_get('success'),
            'error' => flash_get('error'),
        ]);
    }

    public function createRaza(Request $request, Response $response): never
    {
        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/razas');
        }

        try {
            $this->service->createRaza($request->all());
            flash_set('success', 'Raza creada correctamente.');
        } catch (Throwable $exception) {
            flash_set('error', $exception->getMessage());
        }

        $response->redirect('/razas');
    }

    public function updateRaza(Request $request, Response $response): never
    {
        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/razas');
        }

        try {
            $this->service->updateRaza((int) $request->input('id', 0), $request->all());
            flash_set('success', 'Raza actualizada correctamente.');
        } catch (Throwable $exception) {
            flash_set('error', $exception->getMessage());
        }

        $response->redirect('/razas');
    }

    public function toggleRaza(Request $request, Response $response): never
    {
        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/razas');
        }

        try {
            $this->service->toggleEstado('razas', (int) $request->input('id', 0), (int) $request->input('estado', 0));
            flash_set('success', 'Estado de raza actualizado.');
        } catch (Throwable $exception) {
            flash_set('error', $exception->getMessage());
        }

        $response->redirect('/razas');
    }

    public function categoriasIndex(Request $request, Response $response): never
    {
        $data = $this->service->categoriasData();

        $response->view('categorias_animales/index', [
            'categorias' => $data['categorias'],
            'canToggle' => $data['canToggle'],
            'csrfToken' => Csrf::token((int) config('auth.csrf_token_ttl', 3600)),
            'success' => flash_get('success'),
            'error' => flash_get('error'),
        ]);
    }

    public function createCategoria(Request $request, Response $response): never
    {
        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/categorias-animales');
        }

        try {
            $this->service->createCategoria($request->all());
            flash_set('success', 'Categoria creada correctamente.');
        } catch (Throwable $exception) {
            flash_set('error', $exception->getMessage());
        }

        $response->redirect('/categorias-animales');
    }

    public function updateCategoria(Request $request, Response $response): never
    {
        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/categorias-animales');
        }

        try {
            $this->service->updateCategoria((int) $request->input('id', 0), $request->all());
            flash_set('success', 'Categoria actualizada correctamente.');
        } catch (Throwable $exception) {
            flash_set('error', $exception->getMessage());
        }

        $response->redirect('/categorias-animales');
    }

    public function toggleCategoria(Request $request, Response $response): never
    {
        if (!Csrf::verify((string) $request->input('_csrf_token'), (int) config('auth.csrf_token_ttl', 3600))) {
            flash_set('error', 'Token CSRF invalido.');
            $response->redirect('/categorias-animales');
        }

        try {
            $this->service->toggleEstado('categorias_animales', (int) $request->input('id', 0), (int) $request->input('estado', 0));
            flash_set('success', 'Estado de categoria actualizado.');
        } catch (Throwable $exception) {
            flash_set('error', $exception->getMessage());
        }

        $response->redirect('/categorias-animales');
    }
}
