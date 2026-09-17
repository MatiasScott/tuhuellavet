<?php

declare(strict_types=1);

namespace Tests\Integration;

use Tests\TestCase;

class ServiceIntegrityTest extends TestCase
{
    public function testServicesDirectoryExists(): void
    {
        $this->assertDirectoryExists(
            BASE_PATH . '/app/Services'
        );
    }

    public function testEveryServiceFileCanBeAutoloaded(): void
    {
        $files = glob(
            BASE_PATH . '/app/Services/*.php'
        ) ?: [];

        $this->assertNotEmpty(
            $files,
            'No existen servicios.'
        );

        foreach ($files as $file) {
            $class = 'App\\Services\\'
                . pathinfo(
                    $file,
                    PATHINFO_FILENAME
                );

            $this->assertTrue(
                class_exists($class),
                "No se puede cargar {$class}."
            );
        }
    }

    public function testCriticalServicesExist(): void
    {
        $services = [
            \App\Services\AuthService::class,
            \App\Services\PermissionService::class,
            \App\Services\AuditService::class,
            \App\Services\PatientService::class,
            \App\Services\OwnerService::class,
            \App\Services\ConsultationService::class,
            \App\Services\TreatmentService::class,
            \App\Services\PreventiveCareService::class,
            \App\Services\HospitalizationService::class,
            \App\Services\LaboratoryService::class,
            \App\Services\SurgeryService::class,
            \App\Services\InventoryService::class,
            \App\Services\FormulaService::class,
            \App\Services\FormulaAdminService::class,
            \App\Services\BillingService::class,
            \App\Services\NotificationService::class,
            \App\Services\ReminderService::class,
            \App\Services\ContificoService::class,
        ];

        foreach ($services as $service) {
            $this->assertTrue(
                class_exists($service),
                "Falta servicio crítico {$service}."
            );
        }
    }
}