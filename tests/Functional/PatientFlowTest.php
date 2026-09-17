<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Services\OwnerService;
use App\Services\PatientService;
use Tests\TestCase;

class PatientFlowTest extends TestCase
{
    private int $environmentId;
    private int $otherEnvironmentId;
    private int $superAdminId;
    private int $dogSpeciesId;
    private int $dogBreedId;
    private int $maleSexId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->beginTestTransaction();

        $this->environmentId =
            $this->findEnvironment(
                'VETERINARIA'
            );

        $this->otherEnvironmentId =
            $this->findEnvironment(
                'HACIENDA'
            );

        $this->superAdminId =
            $this->findSuperAdmin();

        $this->dogSpeciesId =
            $this->findCatalogId(
                'especies',
                'codigo',
                'PERRO'
            );

        $this->dogBreedId =
            $this->findBreed(
                $this->dogSpeciesId,
                'Mestizo'
            );

        $this->maleSexId =
            $this->findCatalogId(
                'sexos_animales',
                'codigo',
                'MACHO'
            );
    }

    protected function tearDown(): void
    {
        $this->rollbackTestTransaction();

        parent::tearDown();
    }

    private function findEnvironment(
        string $type
    ): int {
        $stmt = $this->db()->prepare(
            '
            SELECT e.id
            FROM entornos e
            INNER JOIN tipos_entorno te
                ON te.id = e.tipo_entorno_id
            WHERE te.codigo = :codigo
              AND e.activo = 1
              AND e.deleted_at IS NULL
            ORDER BY e.id
            LIMIT 1
            '
        );

        $stmt->execute([
            'codigo' => $type,
        ]);

        $id = $stmt->fetchColumn();

        $this->assertNotFalse(
            $id,
            "No existe entorno {$type}."
        );

        return (int) $id;
    }

    private function findSuperAdmin(): int
    {
        $id = $this->db()
            ->query(
                '
                SELECT u.id
                FROM usuarios u
                INNER JOIN usuarios_roles_globales urg
                    ON urg.usuario_id = u.id
                INNER JOIN roles r
                    ON r.id = urg.rol_id
                WHERE r.codigo = "SUPER_ADMINISTRADOR"
                  AND u.activo = 1
                  AND u.deleted_at IS NULL
                LIMIT 1
                '
            )
            ->fetchColumn();

        $this->assertNotFalse($id);

        return (int) $id;
    }

    private function findCatalogId(
        string $table,
        string $field,
        string $value
    ): int {
        $allowedTables = [
            'especies',
            'sexos_animales',
        ];

        $allowedFields = [
            'codigo',
        ];

        $this->assertContains(
            $table,
            $allowedTables
        );

        $this->assertContains(
            $field,
            $allowedFields
        );

        $stmt = $this->db()->prepare(
            "SELECT id
             FROM `{$table}`
             WHERE `{$field}` = :value
             LIMIT 1"
        );

        $stmt->execute([
            'value' => $value,
        ]);

        $id = $stmt->fetchColumn();

        $this->assertNotFalse($id);

        return (int) $id;
    }

    private function findBreed(
        int $speciesId,
        string $name
    ): int {
        $stmt = $this->db()->prepare(
            '
            SELECT id
            FROM razas
            WHERE especie_id = :especie
              AND nombre = :nombre
            LIMIT 1
            '
        );

        $stmt->execute([
            'especie' => $speciesId,
            'nombre' => $name,
        ]);

        $id = $stmt->fetchColumn();

        $this->assertNotFalse($id);

        return (int) $id;
    }

    private function createOwnerInEnvironment(
        int $environmentId
    ): int {
        $owner = (new OwnerService())->create(
            [
                'nombres' => 'QA',
                'apellidos' => 'Propietario',
                'email' => 'qa.owner.'
                    . uniqid()
                    . '@example.test',
            ],
            $environmentId,
            $this->superAdminId
        );

        $stmt = $this->db()->prepare(
            '
            SELECT id
            FROM propietarios_entornos
            WHERE propietario_id = :propietario
              AND entorno_id = :entorno
            LIMIT 1
            '
        );

        $stmt->execute([
            'propietario' => (int) $owner['id'],
            'entorno' => $environmentId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function testPatientCanBeCreated(): void
    {
        $ownerEnvironmentId =
            $this->createOwnerInEnvironment(
                $this->environmentId
            );

        $patientId =
            (new PatientService())->create(
                [
                    'propietario_entorno_id'
                        => $ownerEnvironmentId,

                    'especie_id'
                        => $this->dogSpeciesId,

                    'raza_id'
                        => $this->dogBreedId,

                    'sexo_id'
                        => $this->maleSexId,

                    'codigo'
                        => 'QA-' . uniqid(),

                    'nombre'
                        => 'Firulais QA',

                    'color'
                        => 'Café',

                    'peso_kg'
                        => '12.500',
                ],
                [],
                $this->environmentId,
                $this->superAdminId
            );

        $this->assertGreaterThan(
            0,
            $patientId
        );

        $stmt = $this->db()->prepare(
            '
            SELECT *
            FROM animales
            WHERE id = :id
            '
        );

        $stmt->execute([
            'id' => $patientId,
        ]);

        $patient = $stmt->fetch();

        $this->assertNotFalse(
            $patient
        );

        $this->assertSame(
            $this->environmentId,
            (int) $patient['entorno_id']
        );

        $this->assertSame(
            $ownerEnvironmentId,
            (int) $patient[
                'propietario_entorno_id'
            ]
        );

        $this->assertSame(
            $this->dogSpeciesId,
            (int) $patient['especie_id']
        );
    }

    public function testPatientCannotUseOwnerFromAnotherEnvironment(): void
    {
        $foreignOwner =
            $this->createOwnerInEnvironment(
                $this->otherEnvironmentId
            );

        $service =
            new PatientService();

        $this->expectException(
            \RuntimeException::class
        );

        $this->expectExceptionMessage(
            'El propietario no pertenece al entorno.'
        );

        $service->create(
            [
                'propietario_entorno_id'
                    => $foreignOwner,

                'especie_id'
                    => $this->dogSpeciesId,

                'raza_id'
                    => $this->dogBreedId,

                'sexo_id'
                    => $this->maleSexId,

                'nombre'
                    => 'Paciente ilegal',
            ],
            [],
            $this->environmentId,
            $this->superAdminId
        );
    }

    public function testPatientRequiresSpecies(): void
    {
        $service =
            new PatientService();

        $this->expectException(
            \RuntimeException::class
        );

        $this->expectExceptionMessage(
            'La especie es obligatoria.'
        );

        $service->create(
            [
                'nombre'
                    => 'QA sin especie',
            ],
            [],
            $this->environmentId,
            $this->superAdminId
        );
    }
}