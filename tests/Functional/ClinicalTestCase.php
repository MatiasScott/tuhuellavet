<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Services\OwnerService;
use App\Services\PatientService;
use Tests\TestCase;

abstract class ClinicalTestCase extends TestCase
{
    protected int $vetEnvironment;
    protected int $farmEnvironment;
    protected int $superAdminId;
    protected int $dogSpeciesId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->beginTestTransaction();

        $this->vetEnvironment =
            $this->findEnvironment('VETERINARIA');

        $this->farmEnvironment =
            $this->findEnvironment('HACIENDA');

        $this->superAdminId =
            $this->findSuperAdmin();

        $this->dogSpeciesId =
            $this->catalogId(
                'especies',
                'codigo',
                'PERRO'
            );
    }

    protected function tearDown(): void
    {
        $this->rollbackTestTransaction();

        parent::tearDown();
    }

    protected function findEnvironment(string $type): int
    {
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
            "No existe un entorno activo {$type}."
        );

        return (int) $id;
    }

    protected function findSuperAdmin(): int
    {
        $stmt = $this->db()->query(
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
        );

        $id = $stmt->fetchColumn();

        $this->assertNotFalse(
            $id,
            'No existe un Super Administrador activo.'
        );

        return (int) $id;
    }

    protected function catalogId(
        string $table,
        string $field,
        string $value
    ): int {
        $allowed = [
            'especies',
            'tipos_tratamiento',
            'tipos_diagnostico',
            'tipos_evento_clinico',
            'unidades_medida',
            'vias_administracion',
            'frecuencias_administracion',
            'unidades_tiempo',
        ];

        $this->assertContains(
            $table,
            $allowed
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

        $this->assertNotFalse(
            $id,
            "No existe {$table}.{$field}={$value}"
        );

        return (int) $id;
    }

    protected function createPatient(
        ?int $environmentId = null,
        ?float $weight = null
    ): int {
        $environmentId ??=
            $this->vetEnvironment;

        $owner = (new OwnerService())->create(
            [
                'nombres' => 'QA Clinical',
                'apellidos' => 'Owner',
                'email' => 'qa.clinical.'
                    . uniqid('', true)
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

        $ownerEnvironmentId =
            (int) $stmt->fetchColumn();

        $data = [
            'propietario_entorno_id'
                => $ownerEnvironmentId,

            'especie_id'
                => $this->dogSpeciesId,

            'nombre'
                => 'Paciente QA ' . uniqid(),
        ];

        if ($weight !== null) {
            $data['peso_kg'] = $weight;
        }

        return (new PatientService())->create(
            $data,
            [],
            $environmentId,
            $this->superAdminId
        );
    }

    protected function createDrug(): int
    {
        $stmt = $this->db()->prepare(
            '
            INSERT INTO farmacos
            (
                nombre,
                descripcion,
                activo
            )
            VALUES
            (
                :nombre,
                :descripcion,
                1
            )
            '
        );

        $stmt->execute([
            'nombre' => 'QA Fármaco ' . uniqid(),
            'descripcion' => 'Fixture temporal PHPUnit',
        ]);

        return (int) $this->db()->lastInsertId();
    }

    protected function scalar(
        string $sql,
        array $params = []
    ): mixed {
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchColumn();
    }
}