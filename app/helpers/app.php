<?php

declare(strict_types=1);

if (!function_exists('app_set')) {
    function app_set(string $key, mixed $value): void
    {
        $GLOBALS['_app_container'][$key] = $value;
    }
}

if (!function_exists('app')) {
    function app(string $key, mixed $default = null): mixed
    {
        return $GLOBALS['_app_container'][$key] ?? $default;
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = app('config', []);

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

if (!function_exists('base_url_path')) {
    function base_url_path(): string
    {
        $appUrl = (string) (config('app.url', '') ?? '');

        if ($appUrl !== '') {
            $path = (string) parse_url($appUrl, PHP_URL_PATH);
            $path = '/' . trim($path, '/');

            return $path === '/' ? '' : $path;
        }

        $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $path = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

        return ($path === '' || $path === '.') ? '' : $path;
    }
}

if (!function_exists('url')) {
    function url(string $path = '/'): string
    {
        if (preg_match('/^https?:\/\//i', $path) === 1) {
            return $path;
        }

        $basePath = base_url_path();
        $normalized = '/' . ltrim($path, '/');

        if ($normalized === '/') {
            return ($basePath !== '' ? $basePath : '') . '/';
        }

        return ($basePath !== '' ? $basePath : '') . $normalized;
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        return url('/assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('flash_set')) {
    function flash_set(string $key, string $message): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION['_flash'][$key] = $message;
    }
}

if (!function_exists('flash_get')) {
    function flash_get(string $key): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }

        $message = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);

        return is_string($message) ? $message : null;
    }
}

if (!function_exists('auth_user')) {
    function auth_user(): ?array
    {
        $user = \App\Core\Session::get((string) config('auth.session_key'));
        return is_array($user) ? $user : null;
    }
}

if (!function_exists('auth_role')) {
    function auth_role(): string
    {
        $user = auth_user();
        return is_array($user) ? (string) ($user['rol_codigo'] ?? 'invitado') : 'invitado';
    }
}

if (!function_exists('auth_can')) {
    function auth_can(string $permission): bool
    {
        return (new \App\Services\AccessControlService())->can($permission);
    }
}
