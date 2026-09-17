<?php

declare(strict_types=1);

namespace Tests\Functional;

use App\Services\OwnerService;
use Tests\TestCase;

class OwnerFlowTest extends TestCase
{
    private int $environmentId;
    private int $superAdminId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->environmentId = $this->findEnvironment('VETERINARIA');
        $this->superAdminId = $this->findSuperAdmin();

        $this->beginTestTransaction();
    }

    protected function tearDown(): void
    {
        $this->rollbackTestTransaction();

        parent::tearDown();
    }

    private function findEnvironment(string $type): int
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

    private function findSuperAdmin(): int
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
            'No existe Super Administrador activo para QA.'
        );

        return (int) $id;
    }

    public function testOwnerCanBeCreatedWithoutLoginAccess(): void
    {
        $service = new OwnerService();

        $result = $service->create(
            [
                'nombres' => 'QA Owner',
                'apellidos' => 'Sin Acceso',
                'email' => 'qa.owner.noaccess@example.test',
                'celular' => '0999999999',
                'crear_acceso' => 0,
            ],
            $this->environmentId,
            $this->superAdminId
        );

        $this->assertGreaterThan(
            0,
            (int) $result['id']
        );

        $stmt = $this->db()->prepare(
            '
            SELECT
                p.id,
                p.usuario_id,
                pe.entorno_id,
                pe.activo
            FROM propietarios p
            INNER JOIN propietarios_entornos pe
                ON pe.propietario_id = p.id
            WHERE p.id = :id
            '
        );

        $stmt->execute([
            'id' => (int) $result['id'],
        ]);

        $row = $stmt->fetch();

        $this->assertNotFalse($row);

        $this->assertNull(
            $row['usuario_id']
        );

        $this->assertSame(
            $this->environmentId,
            (int) $row['entorno_id']
        );

        $this->assertSame(
            1,
            (int) $row['activo']
        );
    }

    public function testOwnerCanCreateClientAccess(): void
    {
        $service = new OwnerService();

        $email = 'qa.client.'
            . uniqid()
            . '@example.test';

        $result = $service->create(
            [
                'nombres' => 'QA',
                'apellidos' => 'Cliente',
                'email' => $email,
                'celular' => '0999999999',
                'crear_acceso' => 1,
                'password_temporal' => 'Qa#12345678',
            ],
            $this->environmentId,
            $this->superAdminId
        );

        $stmt = $this->db()->prepare(
            '
            SELECT
                p.usuario_id,
                u.email,
                u.activo
            FROM propietarios p
            INNER JOIN usuarios u
                ON u.id = p.usuario_id
            WHERE p.id = :id
            '
        );

        $stmt->execute([
            'id' => (int) $result['id'],
        ]);

        $row = $stmt->fetch();

        $this->assertNotFalse($row);

        $userId = (int) $row['usuario_id'];

        $this->assertGreaterThan(
            0,
            $userId
        );

        $this->assertSame(
            $email,
            $row['email']
        );

        $this->assertSame(
            1,
            (int) $row['activo']
        );

        $roleStmt = $this->db()->prepare(
            '
            SELECT COUNT(*)
            FROM usuarios_entornos_roles uer
            INNER JOIN roles r
                ON r.id = uer.rol_id
            WHERE uer.usuario_id = :usuario
              AND uer.entorno_id = :entorno
              AND r.codigo = "CLIENTE"
            '
        );

        $roleStmt->execute([
            'usuario' => $userId,
            'entorno' => $this->environmentId,
        ]);

        $this->assertSame(
            1,
            (int) $roleStmt->fetchColumn()
        );
    }

    public function testOwnerCreationWritesAudit(): void
    {
        $service = new OwnerService();

        $result = $service->create(
            [
                'nombres' => 'QA Auditoria',
                'apellidos' => 'Owner',
                'email' => 'qa.audit.' . uniqid() . '@example.test',
            ],
            $this->environmentId,
            $this->superAdminId
        );

        $stmt = $this->db()->prepare(
            '
            SELECT COUNT(*)
            FROM auditoria
            WHERE usuario_id = :usuario
              AND entorno_id = :entorno
              AND modulo = "PROPIETARIOS"
              AND accion = "CREAR"
              AND tabla_afectada = "propietarios"
              AND registro_id = :registro
            '
        );

        $stmt->execute([
            'usuario' => $this->superAdminId,
            'entorno' => $this->environmentId,
            'registro' => (int) $result['id'],
        ]);

        $this->assertSame(
            1,
            (int) $stmt->fetchColumn()
        );
    }

    public function testOwnerRequiresName(): void
    {
        $service = new OwnerService();

        $this->expectException(
            \RuntimeException::class
        );

        $this->expectExceptionMessage(
            'Los nombres son obligatorios.'
        );

        $service->create(
            [
                'nombres' => '',
            ],
            $this->environmentId,
            $this->superAdminId
        );
    }
}