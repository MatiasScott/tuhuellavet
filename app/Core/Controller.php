<?php

namespace App\Core;

abstract class Controller
{
    protected function view(string $view, array $data = [], string $layout = 'layouts/admin'): void
    {
        if ($layout === 'layouts/admin') {
            $user = auth_user();
            $activeRole = $user['active_role'] ?? null;
            $environment = active_environment();

            if ($activeRole === 'CLIENTE') {
                $layout = 'layouts/cliente';
            } elseif (
                ($environment['tipo_codigo'] ?? '') === 'ACADEMICO'
                && in_array($activeRole, ['DOCENTE', 'ESTUDIANTE'], true)
            ) {
                $layout = 'layouts/academico';
            }
        }
        View::render($view, $data, $layout);
    }

    protected function redirect(string $path): never
    {
        header('Location: ' . url($path));
        exit;
    }

    protected function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
