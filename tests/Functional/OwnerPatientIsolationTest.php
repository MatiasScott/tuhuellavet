<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Models\Patient;
use App\Services\OwnerService;
use App\Services\PatientService;
use Tests\TestCase;

class OwnerPatientIsolationTest extends TestCase
{
    private int $vetEnvironment;
    private int $farmEnvironment;
    private int $superAdminId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->beginTestTransaction();

        $this->vetEnvironment =
            $this->environment(
                'VETERINARIA'
            );

        $this->farmEnvironment =
            $this->environment(
                'HACIENDA'
            );

        $this->superAdminId =
            $this->superAdmin();
    }

    protected function tearDown(): void
    {
        $this->rollbackTestTransaction();

        parent::tearDown();
    }

    private function environment(
        string $code
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
            LIMIT 1
            '
        );

        $stmt->execute([
            'codigo' => $code,
        ]);

        $id = $stmt->fetchColumn();

        $this->assertNotFalse($id);

        return (int) $id;
    }

    private function superAdmin(): int
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

    private function createPatient(
        int $environmentId
    ): int {
        $owner = (new OwnerService())->create(
            [
                'nombres'
                    => 'QA Isolation',

                'apellidos'
                    => 'Owner',

                'email'
                    => 'qa.iso.'
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
            'propietario'
                => (int) $owner['id'],

            'entorno'
                => $environmentId,
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
                    => 'Paciente Isolation QA',
            ],
            [],
            $environmentId,
            $this->superAdminId
        );
    }

    public function testPatientCannotBeFoundFromOtherEnvironment(): void
    {
        $patientId =
            $this->createPatient(
                $this->vetEnvironment
            );

        $patient =
            (new Patient())->find(
                $patientId,
                $this->farmEnvironment
            );

        $this->assertNull(
            $patient
        );
    }

    public function testWeightCannotBeAddedFromOtherEnvironment(): void
    {
        $patientId =
            $this->createPatient(
                $this->vetEnvironment
            );

        $this->expectException(
            \RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Paciente no encontrado.'
        );

        (new PatientService())->addWeight(
            $patientId,
            20,
            $this->farmEnvironment,
            $this->superAdminId
        );
    }

    public function testPatientCannotBeUpdatedFromOtherEnvironment(): void
    {
        $patientId =
            $this->createPatient(
                $this->vetEnvironment
            );

        $this->expectException(
            \RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Paciente no encontrado.'
        );

        (new PatientService())->update(
            $patientId,
            [
                'nombre'
                    => 'Ataque cross environment',
            ],
            $this->farmEnvironment,
            $this->superAdminId
        );
    }

    public function testDeletingFromOtherEnvironmentDoesNotDeletePatient(): void
    {
        $patientId =
            $this->createPatient(
                $this->vetEnvironment
            );

        (new PatientService())->delete(
            $patientId,
            $this->farmEnvironment,
            $this->superAdminId
        );

        $stmt = $this->db()->prepare(
            '
            SELECT
                activo,
                deleted_at
            FROM animales
            WHERE id = :id
            '
        );

        $stmt->execute([
            'id' => $patientId,
        ]);

        $row = $stmt->fetch();

        $this->assertSame(
            1,
            (int) $row['activo']
        );

        $this->assertNull(
            $row['deleted_at']
        );
    }
}