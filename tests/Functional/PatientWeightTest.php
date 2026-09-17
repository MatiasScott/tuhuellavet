<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Models\Patient;
use App\Services\OwnerService;
use App\Services\PatientService;
use Tests\TestCase;

class PatientWeightTest extends TestCase
{
    private int $environmentId;
    private int $superAdminId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->beginTestTransaction();

        $this->environmentId =
            $this->findEnvironment();

        $this->superAdminId =
            $this->findSuperAdmin();
    }

    protected function tearDown(): void
    {
        $this->rollbackTestTransaction();

        parent::tearDown();
    }

    private function findEnvironment(): int
    {
        $stmt = $this->db()->query(
            '
            SELECT e.id
            FROM entornos e
            INNER JOIN tipos_entorno te
                ON te.id = e.tipo_entorno_id
            WHERE te.codigo = "VETERINARIA"
              AND e.activo = 1
              AND e.deleted_at IS NULL
            LIMIT 1
            '
        );

        $id = $stmt->fetchColumn();

        $this->assertNotFalse($id);

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

    private function createPatient(): int
    {
        $owner = (new OwnerService())->create(
            [
                'nombres' => 'QA Peso',
                'apellidos' => 'Owner',
                'email' => 'qa.weight.'
                    . uniqid()
                    . '@example.test',
            ],
            $this->environmentId,
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
            'propietario'
                => (int) $owner['id'],

            'entorno'
                => $this->environmentId,
        ]);

        $ownerEnvironmentId =
            (int) $stmt->fetchColumn();

        $species =
            (int) $this->db()
                ->query(
                    '
                    SELECT id
                    FROM especies
                    WHERE codigo = "PERRO"
                    LIMIT 1
                    '
                )
                ->fetchColumn();

        return (new PatientService())->create(
            [
                'propietario_entorno_id'
                    => $ownerEnvironmentId,

                'especie_id'
                    => $species,

                'nombre'
                    => 'Paciente Peso QA',

                'peso_kg'
                    => 10.250,
            ],
            [],
            $this->environmentId,
            $this->superAdminId
        );
    }

    public function testInitialWeightCreatesHistoricalRecord(): void
    {
        $patientId =
            $this->createPatient();

        $stmt = $this->db()->prepare(
            '
            SELECT
                peso_kg,
                origen
            FROM animales_pesos
            WHERE animal_id = :animal
            ORDER BY id
            '
        );

        $stmt->execute([
            'animal' => $patientId,
        ]);

        $rows = $stmt->fetchAll();

        $this->assertCount(
            1,
            $rows
        );

        $this->assertEquals(
            10.250,
            (float) $rows[0]['peso_kg']
        );

        $this->assertSame(
            'REGISTRO_INICIAL',
            $rows[0]['origen']
        );
    }

    public function testNewWeightDoesNotOverwritePreviousWeight(): void
    {
        $patientId =
            $this->createPatient();

        (new PatientService())->addWeight(
            $patientId,
            11.750,
            $this->environmentId,
            $this->superAdminId,
            'Control QA'
        );

        $stmt = $this->db()->prepare(
            '
            SELECT peso_kg
            FROM animales_pesos
            WHERE animal_id = :animal
            ORDER BY id
            '
        );

        $stmt->execute([
            'animal' => $patientId,
        ]);

        $weights =
            array_map(
                'floatval',
                $stmt->fetchAll(
                    \PDO::FETCH_COLUMN
                )
            );

        $this->assertCount(
            2,
            $weights
        );

        $this->assertSame(
            10.25,
            $weights[0]
        );

        $this->assertSame(
            11.75,
            $weights[1]
        );
    }

    public function testCurrentWeightIsLatestHistoricalWeight(): void
    {
        $patientId =
            $this->createPatient();

        $service =
            new PatientService();

        $service->addWeight(
            $patientId,
            11.250,
            $this->environmentId,
            $this->superAdminId
        );

        $service->addWeight(
            $patientId,
            12.500,
            $this->environmentId,
            $this->superAdminId
        );

        $patient =
            (new Patient())->find(
                $patientId,
                $this->environmentId
            );

        $this->assertNotNull(
            $patient
        );

        $this->assertEquals(
            12.500,
            (float) $patient['peso_actual']
        );
    }

    public function testZeroWeightIsRejected(): void
    {
        $patientId =
            $this->createPatient();

        $this->expectException(
            \RuntimeException::class
        );

        $this->expectExceptionMessage(
            'El peso debe ser mayor a cero.'
        );

        (new PatientService())->addWeight(
            $patientId,
            0,
            $this->environmentId,
            $this->superAdminId
        );
    }
}