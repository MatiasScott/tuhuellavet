<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];
    private array $middlewares = [];

    public function get(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->addRoute('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->addRoute('POST', $path, $handler, $middlewares);
    }

    private function addRoute(string $method, string $path, callable|array $handler, array $middlewares): void
    {
        $this->routes[$method][rtrim($path, '/') ?: '/'] = $handler;
        $this->middlewares[$method][rtrim($path, '/') ?: '/'] = $middlewares;
    }

    public function dispatch(Request $request): void
    {
        $method = $request->method();
        $path = rtrim($request->uri(), '/') ?: '/';

        $handler = $this->routes[$method][$path] ?? null;

        if ($handler === null) {
            http_response_code(404);
            echo 'Ruta no encontrada';
            return;
        }

        foreach ($this->middlewares[$method][$path] ?? [] as $middlewareClass) {
            $middleware = new $middlewareClass();

            if ($middleware instanceof MiddlewareInterface) {
                $middleware->handle($request);
            }
        }

        if (is_callable($handler)) {
            $handler($request, new Response());
            return;
        }

        [$controllerClass, $methodName] = $handler;
        $controller = new $controllerClass();
        $controller->{$methodName}($request, new Response());
    }
}
