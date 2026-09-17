<?php

declare(strict_types=1);

namespace Tests\System;

use Tests\TestCase;
use Tests\Support\TestDatabase;

class ArchitectureTest
    extends TestCase
{
    public function testDatabaseConnectionWorks(): void
    {
        TestDatabase::assertSafeEnvironment();

        $this->assertNotEmpty(
            TestDatabase::databaseName()
        );
    }


    public function testProjectDirectoriesExist(): void
    {
        $directories = [
            BASE_PATH . '/app',
            BASE_PATH . '/app/Config',
            BASE_PATH . '/app/Controllers',
            BASE_PATH . '/app/Core',
            BASE_PATH . '/app/Helpers',
            BASE_PATH . '/app/Middlewares',
            BASE_PATH . '/app/Models',
            BASE_PATH . '/app/Routes',
            BASE_PATH . '/app/Services',
            BASE_PATH . '/app/Views',

            BASE_PATH . '/bootstrap',

            BASE_PATH . '/public',
            BASE_PATH . '/public/assets/css',
            BASE_PATH . '/public/assets/js',

            BASE_PATH . '/storage',
        ];

        foreach (
            $directories
            as $directory
        ) {
            $this->assertDirectoryExists(
                $directory,
                "No existe: {$directory}"
            );
        }
    }


    public function testCoreClassesCanBeLoaded(): void
    {
        $classes = [
            \App\Core\App::class,
            \App\Core\Controller::class,
            \App\Core\Database::class,
            \App\Core\Model::class,
            \App\Core\Request::class,
            \App\Core\Router::class,
            \App\Core\Session::class,
            \App\Core\View::class,
        ];

        foreach (
            $classes
            as $class
        ) {
            $this->assertTrue(
                class_exists($class),
                "No se pudo cargar {$class}"
            );
        }
    }


    public function testCriticalServicesCanBeLoaded(): void
    {
        $classes = [
            \App\Services\AuthService::class,
            \App\Services\AuditService::class,
            \App\Services\PermissionService::class,

            \App\Services\OwnerService::class,
            \App\Services\PatientService::class,

            \App\Services\ConsultationService::class,
            \App\Services\PreventiveCareService::class,
            \App\Services\TreatmentService::class,

            \App\Services\FormulaService::class,
            \App\Services\HospitalizationService::class,
            \App\Services\LaboratoryService::class,
            \App\Services\SurgeryService::class,

            \App\Services\InventoryService::class,
            \App\Services\BillingService::class,

            \App\Services\NotificationService::class,
            \App\Services\ReminderService::class,

            \App\Services\GoogleAuthService::class,
            \App\Services\ContificoService::class,
        ];

        foreach (
            $classes
            as $class
        ) {
            $this->assertTrue(
                class_exists($class),
                "No se pudo cargar {$class}"
            );
        }
    }
}