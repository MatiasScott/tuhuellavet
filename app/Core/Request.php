<?php

namespace App\Core;

class Request
{
    public function __construct(
        private string $method,
        private string $uri,
        private array $query,
        private array $body,
        private array $files,
        private array $server
    ) {}

    public static function capture(): self
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($method === 'POST' && isset($_POST['_method'])) {
            $override = strtoupper((string)$_POST['_method']);
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }

        $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $appPath = parse_url($_ENV['APP_URL'] ?? '', PHP_URL_PATH) ?: '';
        $appPath = rtrim($appPath, '/');

        if ($appPath !== '' && str_starts_with($requestPath, $appPath)) {
            $requestPath = substr($requestPath, strlen($appPath)) ?: '/';
        }

        $requestPath = '/' . trim($requestPath, '/');

        return new self($method, $requestPath, $_GET, $_POST, $_FILES, $_SERVER);
    }

    public function method(): string
    {
        return $this->method;
    }
    public function uri(): string
    {
        return $this->uri === '//' ? '/' : $this->uri;
    }
    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }
    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }
    public function files(): array
    {
        return $this->files;
    }
    public function server(string $key, mixed $default = null): mixed
    {
        return $this->server[$key] ?? $default;
    }
}
