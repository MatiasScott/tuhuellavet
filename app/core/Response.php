<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public function redirect(string $path): never
    {
        header('Location: ' . $this->resolveRedirectPath($path));
        exit;
    }

    public function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public function view(string $view, array $data = []): never
    {
        extract($data, EXTR_SKIP);
        require BASE_PATH . '/app/views/' . $view . '.php';
        exit;
    }

    private function resolveRedirectPath(string $path): string
    {
        if (preg_match('/^https?:\/\//i', $path) === 1) {
            return $path;
        }

        if (function_exists('url')) {
            return \url($path);
        }

        return $path;
    }
}
