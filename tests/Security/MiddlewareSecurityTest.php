<?php

declare(strict_types=1);

namespace Tests\Security;

use App\Middlewares\AuthMiddleware;
use App\Middlewares\EnvironmentMiddleware;
use App\Middlewares\GuestMiddleware;
use App\Middlewares\PermissionMiddleware;
use App\Middlewares\RoleMiddleware;
use Tests\TestCase;

class MiddlewareSecurityTest extends TestCase
{
    public function testAuthenticationMiddlewareExists(): void
    {
        $this->assertTrue(
            class_exists(AuthMiddleware::class),
            'No existe AuthMiddleware.'
        );
    }


    public function testGuestMiddlewareExists(): void
    {
        $this->assertTrue(
            class_exists(GuestMiddleware::class),
            'No existe GuestMiddleware.'
        );
    }


    public function testEnvironmentMiddlewareExists(): void
    {
        $this->assertTrue(
            class_exists(EnvironmentMiddleware::class),
            'No existe EnvironmentMiddleware.'
        );
    }


    public function testPermissionMiddlewareExists(): void
    {
        $this->assertTrue(
            class_exists(PermissionMiddleware::class),
            'No existe PermissionMiddleware.'
        );
    }


    public function testRoleMiddlewareExists(): void
    {
        $this->assertTrue(
            class_exists(RoleMiddleware::class),
            'No existe RoleMiddleware.'
        );
    }


    public function testSecurityMiddlewareFilesAreNotEmpty(): void
    {
        $files = [
            BASE_PATH . '/app/Middlewares/AuthMiddleware.php',
            BASE_PATH . '/app/Middlewares/GuestMiddleware.php',
            BASE_PATH . '/app/Middlewares/EnvironmentMiddleware.php',
            BASE_PATH . '/app/Middlewares/PermissionMiddleware.php',
            BASE_PATH . '/app/Middlewares/RoleMiddleware.php',
        ];

        foreach ($files as $file) {

            $this->assertFileExists(
                $file
            );

            $this->assertGreaterThan(
                20,
                filesize($file),
                "Middleware vacío o incompleto: {$file}"
            );
        }
    }


    public function testAuthenticationHelpersExist(): void
    {
        $required = [
            'auth_user',
            'auth_id',
            'active_environment_id',
        ];

        foreach ($required as $function) {
            $this->assertTrue(
                function_exists($function),
                "No existe el helper {$function}()."
            );
        }
    }


    public function testPermissionHelpersExist(): void
    {
        $this->assertTrue(
            function_exists('can'),
            'No existe el helper can().'
        );
    }
}