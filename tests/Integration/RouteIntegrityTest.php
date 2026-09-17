<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\TestCase;

class RouteIntegrityTest extends TestCase
{
    private array $routeFiles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->routeFiles = glob(
            BASE_PATH . '/app/Routes/*.php'
        ) ?: [];
    }

    public function testRouteFilesExist(): void
    {
        $this->assertNotEmpty(
            $this->routeFiles,
            'No existen archivos de rutas.'
        );
    }

    public function testRouteFilesAreNotEmpty(): void
    {
        foreach ($this->routeFiles as $file) {
            $content = file_get_contents($file);

            $this->assertNotFalse($content);
            $this->assertNotSame(
                '',
                trim($content),
                basename($file) . ' está vacío.'
            );
        }
    }

    public function testRouteControllersExist(): void
    {
        foreach ($this->routeFiles as $file) {
            $content = file_get_contents($file);

            preg_match_all(
                '/use\s+(App\\\\Controllers\\\\[^;]+);/',
                $content,
                $matches
            );

            foreach ($matches[1] ?? [] as $class) {
                $this->assertTrue(
                    class_exists($class),
                    "Controlador inexistente: {$class} "
                    . 'en ' . basename($file)
                );
            }
        }
    }

    public function testRouteControllerMethodsExist(): void
    {
        foreach ($this->routeFiles as $file) {
            $content = file_get_contents($file);

            preg_match_all(
                '/use\s+(App\\\\Controllers\\\\[^;]+);/',
                $content,
                $uses
            );

            $aliases = [];

            foreach ($uses[1] ?? [] as $class) {
                $parts = explode('\\', $class);
                $aliases[end($parts)] = $class;
            }

            preg_match_all(
                '/\[\s*([A-Za-z0-9_]+)::class\s*,\s*[\'"]([A-Za-z0-9_]+)[\'"]\s*\]/',
                $content,
                $routes,
                PREG_SET_ORDER
            );

            foreach ($routes as $route) {
                $alias = $route[1];
                $method = $route[2];

                $this->assertArrayHasKey(
                    $alias,
                    $aliases,
                    "No se pudo resolver {$alias} "
                    . 'en ' . basename($file)
                );

                if (!isset($aliases[$alias])) {
                    continue;
                }

                $class = $aliases[$alias];

                $this->assertTrue(
                    class_exists($class),
                    "No existe {$class}."
                );

                $this->assertTrue(
                    method_exists($class, $method),
                    "La ruta usa {$class}::{$method}(), "
                    . 'pero el método no existe.'
                );

                $reflection = new \ReflectionMethod(
                    $class,
                    $method
                );

                $this->assertTrue(
                    $reflection->isPublic(),
                    "{$class}::{$method}() no es público."
                );
            }
        }
    }

    public function testPermissionMiddlewaresReferenceExistingPermissions(): void
    {
        $permissions = $this->db()
            ->query(
                'SELECT codigo
                 FROM permisos
                 WHERE activo = 1'
            )
            ->fetchAll(\PDO::FETCH_COLUMN);

        $permissions = array_flip($permissions);

        foreach ($this->routeFiles as $file) {
            $content = file_get_contents($file);

            preg_match_all(
                '/permission:([a-zA-Z0-9_.-]+)/',
                $content,
                $matches
            );

            foreach (
                array_unique($matches[1] ?? [])
                as $permission
            ) {
                $this->assertArrayHasKey(
                    $permission,
                    $permissions,
                    "La ruta utiliza el permiso "
                    . "{$permission}, pero no existe "
                    . 'como permiso activo.'
                );
            }
        }
    }

    public function testRouteFilesDoNotContainUnknownHttpMethods(): void
    {
        $allowed = [
            'get',
            'post',
            'put',
            'delete',
        ];

        foreach ($this->routeFiles as $file) {
            $content = file_get_contents($file);

            preg_match_all(
                '/\$router->([a-zA-Z]+)\s*\(/',
                $content,
                $matches
            );

            foreach ($matches[1] ?? [] as $method) {
                $this->assertContains(
                    strtolower($method),
                    $allowed,
                    "Método HTTP no soportado "
                    . "{$method} en "
                    . basename($file)
                );
            }
        }
    }
}