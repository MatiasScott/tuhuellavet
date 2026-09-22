<?php

namespace App\Core;

class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;

        session_name($_ENV['SESSION_NAME'] ?? 'vet_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => (($_ENV['APP_ENV'] ?? 'production') === 'production'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }
    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }
    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $key, mixed $value): void
    {
        $_SESSION['_flash'][$key] = $value;
    }
    public static function pullFlash(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        return $_SESSION['_csrf'];
    }

    public static function validateCsrf(?string $token): bool
    {
        $stored = $_SESSION['_csrf'] ?? '';
        return is_string($token) && $stored !== '' && hash_equals($stored, $token);
    }

    public static function flush(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }
}
