<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\TestCase;

class ApplicationWiringTest extends TestCase
{
    public function testCoreApplicationClassesExist(): void
    {
        $classes = [
            \App\Core\App::class,
            \App\Core\Router::class,
            \App\Core\Request::class,
            \App\Core\Controller::class,
            \App\Core\Model::class,
            \App\Core\Database::class,
            \App\Core\Session::class,
            \App\Core\View::class,
        ];

        foreach ($classes as $class) {
            $this->assertTrue(
                class_exists($class),
                "No existe {$class}."
            );
        }
    }

    public function testRegisteredMiddlewareClassesExist(): void
    {
        $classes = [
            \App\Middlewares\AuthMiddleware::class,
            \App\Middlewares\GuestMiddleware::class,
            \App\Middlewares\EnvironmentMiddleware::class,
            \App\Middlewares\PermissionMiddleware::class,
            \App\Middlewares\RoleMiddleware::class,
        ];

        foreach ($classes as $class) {
            $this->assertTrue(
                class_exists($class),
                "Middleware inexistente {$class}."
            );
        }
    }

    public function testRouteFilesAreRegisteredByApplication(): void
    {
        $appFile = BASE_PATH . '/app/Core/App.php';

        $this->assertFileExists(
            $appFile,
            'No existe app/Core/App.php.'
        );

        $content = file_get_contents($appFile);

        $this->assertNotFalse(
            $content,
            'No se pudo leer app/Core/App.php.'
        );

        $routes = [
            'web.php',
            'auth.php',
            'admin.php',
            'cliente.php',
            'academico.php',
        ];

        foreach ($routes as $route) {
            $this->assertStringContainsString(
                "'{$route}'",
                $content,
                "{$route} no está registrado en App::run()."
            );

            $this->assertFileExists(
                BASE_PATH . '/app/Routes/' . $route,
                "La ruta registrada {$route} no existe físicamente."
            );
        }

        $this->assertStringContainsString(
            "APP_PATH.'/Routes/'",
            str_replace(' ', '', $content),
            'App.php no parece resolver los archivos desde app/Routes.'
        );

        $this->assertMatchesRegularExpression(
            '/require\s+\$p\s*;/',
            $content,
            'App.php registra los nombres de rutas pero no parece cargarlos mediante require.'
        );
    }

    public function testDatabaseConnectionUsesPdo(): void
    {
        $this->assertInstanceOf(
            \PDO::class,
            $this->db()
        );
    }

    public function testCriticalTablesRequiredByApplicationExist(): void
    {
        $tables = [
            'usuarios',
            'roles',
            'permisos',
            'rol_permisos',
            'usuarios_entornos_roles',
            'empresas',
            'entornos',
            'propietarios',
            'propietarios_entornos',
            'animales',
            'animales_pesos',
            'especies',
            'razas',
            'eventos_clinicos',
            'archivos',
            'animal_archivos',
            'notificaciones',
            'ventas',
        ];

        $stmt = $this->db()->prepare(
            '
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :tabla
            '
        );

        foreach ($tables as $table) {
            $stmt->execute([
                'tabla' => $table,
            ]);

            $this->assertSame(
                1,
                (int) $stmt->fetchColumn(),
                "Falta tabla requerida: {$table}"
            );
        }
    }

    public function testApplicationHasNoDuplicatePermissionCodes(): void
    {
        $rows = $this->db()
            ->query(
                '
                SELECT codigo, COUNT(*) cantidad
                FROM permisos
                GROUP BY codigo
                HAVING COUNT(*) > 1
                '
            )
            ->fetchAll();

        $this->assertSame(
            [],
            $rows,
            'Existen permisos duplicados.'
        );
    }
}
