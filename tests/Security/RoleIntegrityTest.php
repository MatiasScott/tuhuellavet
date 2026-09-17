<?php

declare(strict_types=1);

namespace Tests\Security;

use Tests\TestCase;

class RoleIntegrityTest extends TestCase
{
    public function testRequiredRolesExistExactlyOnce(): void
    {
        $required = [
            'SUPER_ADMINISTRADOR',
            'ADMINISTRADOR',
            'CLIENTE',
            'INVITADO',
            'DOCENTE',
            'ESTUDIANTE',
        ];

        $stmt = $this->db()->prepare(
            '
            SELECT COUNT(*)
            FROM roles
            WHERE codigo = :codigo
              AND activo = 1
            '
        );

        foreach ($required as $codigo) {
            $stmt->execute([
                'codigo' => $codigo,
            ]);

            $this->assertSame(
                1,
                (int) $stmt->fetchColumn(),
                "Debe existir exactamente un rol activo {$codigo}."
            );
        }
    }


    public function testRoleCodesAreUnique(): void
    {
        $rows = $this->db()->query(
            '
            SELECT
                codigo,
                COUNT(*) AS cantidad
            FROM roles
            GROUP BY codigo
            HAVING COUNT(*) > 1
            '
        )->fetchAll();

        $this->assertCount(
            0,
            $rows,
            'Existen códigos de roles duplicados.'
        );
    }


    public function testGlobalRoleAssignmentsAreValid(): void
    {
        $rows = $this->db()->query(
            '
            SELECT
                urg.usuario_id,
                urg.rol_id
            FROM usuarios_roles_globales urg

            LEFT JOIN usuarios u
                ON u.id = urg.usuario_id

            LEFT JOIN roles r
                ON r.id = urg.rol_id

            WHERE u.id IS NULL
               OR r.id IS NULL
            '
        )->fetchAll();

        $this->assertCount(
            0,
            $rows,
            'Existen roles globales asignados a usuarios o roles inexistentes.'
        );
    }


    public function testEnvironmentRoleAssignmentsAreValid(): void
    {
        $rows = $this->db()->query(
            '
            SELECT
                uer.usuario_id,
                uer.entorno_id,
                uer.rol_id

            FROM usuarios_entornos_roles uer

            LEFT JOIN usuarios u
                ON u.id = uer.usuario_id

            LEFT JOIN entornos e
                ON e.id = uer.entorno_id

            LEFT JOIN roles r
                ON r.id = uer.rol_id

            WHERE u.id IS NULL
               OR e.id IS NULL
               OR r.id IS NULL
            '
        )->fetchAll();

        $this->assertCount(
            0,
            $rows,
            'Existen asignaciones usuario/entorno/rol inválidas.'
        );
    }


    public function testGlobalRoleAssignmentsAreNotDuplicated(): void
    {
        $rows = $this->db()->query(
            '
            SELECT
                usuario_id,
                rol_id,
                COUNT(*) AS cantidad

            FROM usuarios_roles_globales

            GROUP BY
                usuario_id,
                rol_id

            HAVING COUNT(*) > 1
            '
        )->fetchAll();

        $this->assertCount(
            0,
            $rows,
            'Existen roles globales duplicados.'
        );
    }


    public function testEnvironmentRoleAssignmentsAreNotDuplicated(): void
    {
        $rows = $this->db()->query(
            '
            SELECT
                usuario_id,
                entorno_id,
                rol_id,
                COUNT(*) AS cantidad

            FROM usuarios_entornos_roles

            GROUP BY
                usuario_id,
                entorno_id,
                rol_id

            HAVING COUNT(*) > 1
            '
        )->fetchAll();

        $this->assertCount(
            0,
            $rows,
            'Existen asignaciones usuario/entorno/rol duplicadas.'
        );
    }


    public function testGlobalSuperAdministratorRoleExists(): void
    {
        $stmt = $this->db()->query(
            '
            SELECT COUNT(*)
            FROM roles
            WHERE codigo = "SUPER_ADMINISTRADOR"
              AND activo = 1
            '
        );

        $this->assertSame(
            1,
            (int) $stmt->fetchColumn(),
            'No existe el rol global SUPER_ADMINISTRADOR.'
        );
    }


    public function testAtLeastOneActiveSuperAdministratorExists(): void
    {
        $stmt = $this->db()->query(
            '
            SELECT COUNT(DISTINCT u.id)

            FROM usuarios u

            INNER JOIN usuarios_roles_globales urg
                ON urg.usuario_id = u.id

            INNER JOIN roles r
                ON r.id = urg.rol_id

            WHERE r.codigo = "SUPER_ADMINISTRADOR"
              AND r.activo = 1
              AND u.activo = 1
              AND u.deleted_at IS NULL
            '
        );

        $this->assertGreaterThan(
            0,
            (int) $stmt->fetchColumn(),
            'No existe ningún SUPER_ADMINISTRADOR activo.'
        );
    }


    public function testSuperAdministratorIsAssignedGlobally(): void
    {
        $rows = $this->db()->query(
            '
            SELECT
                uer.usuario_id,
                uer.entorno_id

            FROM usuarios_entornos_roles uer

            INNER JOIN roles r
                ON r.id = uer.rol_id

            WHERE r.codigo = "SUPER_ADMINISTRADOR"
            '
        )->fetchAll();

        $this->assertCount(
            0,
            $rows,
            'SUPER_ADMINISTRADOR debe asignarse globalmente, no mediante usuarios_entornos_roles.'
        );
    }
}