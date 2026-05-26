<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function view(string $view, array $data = []): never
    {
        (new Response())->view($view, $data);
    }

    protected function redirect(string $path): never
    {
        (new Response())->redirect($path);
    }

    protected function json(array $payload, int $status = 200): never
    {
        (new Response())->json($payload, $status);
    }
}
