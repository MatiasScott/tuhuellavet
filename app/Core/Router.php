<?php

namespace App\Core;

use App\Middlewares\{AuthMiddleware, GuestMiddleware, PermissionMiddleware, RoleMiddleware, EnvironmentMiddleware};

class Router
{
    private array $routes = [];
    public function get(string $p, array|callable $h, array $m = []): void
    {
        $this->add('GET', $p, $h, $m);
    }
    public function post(string $p, array|callable $h, array $m = []): void
    {
        $this->add('POST', $p, $h, $m);
    }
    public function put(string $p, array|callable $h, array $m = []): void
    {
        $this->add('PUT', $p, $h, $m);
    }
    public function delete(string $p, array|callable $h, array $m = []): void
    {
        $this->add('DELETE', $p, $h, $m);
    }
    private function add(string $method, string $path, array|callable $handler, array $middlewares): void
    {
        $this->routes[] = compact('method', 'path', 'handler', 'middlewares');
    }
    public function dispatch(Request $r): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $r->method()) continue;
            $pat = preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $route['path']);
            $pat = '#^' . rtrim($pat, '/') . '/?$#';
            if (!preg_match($pat, $r->uri(), $matches)) continue;
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            foreach ($route['middlewares'] as $mw) $this->runMiddleware($mw, $r);
            $h = $route['handler'];
            if (is_callable($h)) {
                $h($r, ...array_values($params));
                return;
            }
            [$c, $m] = $h;
            (new $c())->$m($r, ...array_values($params));
            return;
        }
        http_response_code(404);
        View::render('errors/404', [], 'layouts/auth');
    }
    private function runMiddleware(string $d, Request $r): void
    {
        [$n, $a] = array_pad(explode(':', $d, 2), 2, null);
        $mw = match ($n) {
            'auth' => new AuthMiddleware(),
            'guest' => new GuestMiddleware(),
            'permission' => new PermissionMiddleware($a),
            'role' => new RoleMiddleware($a),
            'environment' => new EnvironmentMiddleware(),
            default => throw new \RuntimeException('Middleware no registrado: ' . $n)
        };
        $mw->handle($r);
    }
}
