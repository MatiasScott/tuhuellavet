<?php

declare(strict_types=1);

namespace Tests\Security;

use Tests\TestCase;

class PermissionTest extends TestCase
{
    private function routeFiles(): array
    {
        return [
            BASE_PATH . '/app/Routes/web.php',
            BASE_PATH . '/app/Routes/admin.php',
            BASE_PATH . '/app/Routes/cliente.php',
            BASE_PATH . '/app/Routes/academico.php',
        ];
    }

    private function contents(): string
    {
        $content = '';

        foreach ($this->routeFiles() as $file) {
            $this->assertFileExists($file);
            $content .= file_get_contents($file) . PHP_EOL;
        }

        return $content;
    }

    public function testPrivateRouteFilesUseAuthenticationMiddleware(): void
    {
        foreach ($this->routeFiles() as $file) {
            $content = file_get_contents($file);

            $this->assertStringContainsString(
                "'auth'",
                $content,
                basename($file) . ' no utiliza middleware auth.'
            );
        }
    }

    public function testAdministrativeRoutesRequireAuthentication(): void
    {
        $content = file_get_contents(
            BASE_PATH . '/app/Routes/admin.php'
        );

        preg_match_all(
            '/\$router->(?:get|post|put|delete)\([^;]+;/s',
            $content,
            $matches
        );

        $this->assertNotEmpty($matches[0]);

        foreach ($matches[0] as $route) {
            $this->assertStringContainsString(
                "'auth'",
                $route,
                "Ruta administrativa sin auth:\n{$route}"
            );
        }
    }

    public function testAdministrativeRoutesRequireEnvironment(): void
    {
        $content = file_get_contents(
            BASE_PATH . '/app/Routes/admin.php'
        );

        preg_match_all(
            '/\$router->(?:get|post|put|delete)\([^;]+;/s',
            $content,
            $matches
        );

        foreach ($matches[0] as $route) {
            $this->assertStringContainsString(
                "'environment'",
                $route,
                "Ruta administrativa sin environment:\n{$route}"
            );
        }
    }

    public function testAdministrativeRoutesRequirePermission(): void
    {
        $content = file_get_contents(
            BASE_PATH . '/app/Routes/admin.php'
        );

        preg_match_all(
            '/\$router->(?:get|post|put|delete)\([^;]+;/s',
            $content,
            $matches
        );

        foreach ($matches[0] as $route) {
            $this->assertStringContainsString(
                'permission:',
                $route,
                "Ruta administrativa sin permission:\n{$route}"
            );
        }
    }

    public function testClinicalMutationRoutesUsePermissions(): void
    {
        $content = file_get_contents(
            BASE_PATH . '/app/Routes/web.php'
        );

        preg_match_all(
            '/\$router->(?:post|put|delete)\([^;]+;/s',
            $content,
            $matches
        );

        $this->assertNotEmpty($matches[0]);

        foreach ($matches[0] as $route) {
            $this->assertStringContainsString(
                "'auth'",
                $route,
                "Mutación sin auth:\n{$route}"
            );

            $this->assertStringContainsString(
                "'environment'",
                $route,
                "Mutación sin environment:\n{$route}"
            );

            $this->assertStringContainsString(
                'permission:',
                $route,
                "Mutación sin permission:\n{$route}"
            );
        }
    }

    public function testAcademicRoutesAreProtected(): void
    {
        $content = file_get_contents(
            BASE_PATH . '/app/Routes/academico.php'
        );

        preg_match_all(
            '/\$router->(?:get|post|put|delete)\([^;]+;/s',
            $content,
            $matches
        );

        foreach ($matches[0] as $route) {
            $this->assertStringContainsString(
                "'auth'",
                $route
            );

            $this->assertStringContainsString(
                "'environment'",
                $route
            );

            $this->assertStringContainsString(
                'permission:academico.',
                $route
            );
        }
    }
}