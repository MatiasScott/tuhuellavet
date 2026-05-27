<?php

declare(strict_types=1);

namespace App\Core;

final class Application
{
    private Router $router;
    private array $config = [];

    public function __construct(private readonly string $basePath)
    {
        $this->router = new Router();
    }

    public function bootstrap(): void
    {
        $this->loadEnvironment();
        $this->loadConfiguration();
        $this->initializeRuntime();
        $this->loadRoutes();
    }

    public function run(): void
    {
        $request = new Request();
        $this->router->dispatch($request);
    }

    private function loadEnvironment(): void
    {
        if (file_exists($this->basePath . '/.env') && class_exists('Dotenv\\Dotenv')) {
            $dotenvClass = 'Dotenv\\Dotenv';
            $dotenvClass::createImmutable($this->basePath)->safeLoad();
        }
    }

    private function loadConfiguration(): void
    {
        $this->config = [
            'app' => require $this->basePath . '/app/config/app.php',
            'database' => require $this->basePath . '/app/config/database.php',
            'auth' => require $this->basePath . '/app/config/auth.php',
            'files' => require $this->basePath . '/app/config/files.php',
            'permissions' => require $this->basePath . '/app/config/permissions.php',
        ];
    }

    private function initializeRuntime(): void
    {
        date_default_timezone_set((string) $this->config['app']['timezone']);

        if ((bool) $this->config['app']['debug'] === true) {
            ini_set('display_errors', '1');
            error_reporting(E_ALL);
        }

        Session::start($this->config['auth']);
        Database::connection($this->config['database']);

        app_set('config', $this->config);
        app_set('router', $this->router);
    }

    private function loadRoutes(): void
    {
        require $this->basePath . '/routes/web.php';
    }
}
