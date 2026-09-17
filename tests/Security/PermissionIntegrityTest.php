<?php

declare(strict_types=1);

namespace Tests\Security;

use Tests\TestCase;

class PermissionIntegrityTest extends TestCase
{
    public function testPermissionsReferenceValidModulesAndActions(): void
    {
        $rows = $this->db()->query(
            '
            SELECT
                p.id,
                p.codigo,
                p.modulo_id,
                p.accion_id

            FROM permisos p

            LEFT JOIN modulos m
                ON m.id = p.modulo_id

            LEFT JOIN acciones_permiso a
                ON a.id = p.accion_id

            WHERE m.id IS NULL
               OR a.id IS NULL
            '
        )->fetchAll();

        $this->assertCount(
            0,
            $rows,
            'Existen permisos con módulo o acción inexistente.'
        );
    }


    public function testPermissionCodesAreUnique(): void
    {
        $rows = $this->db()->query(
            '
            SELECT
                codigo,
                COUNT(*) AS cantidad

            FROM permisos

            GROUP BY codigo

            HAVING COUNT(*) > 1
            '
        )->fetchAll();

        $this->assertCount(
            0,
            $rows,
            'Existen códigos de permisos duplicados.'
        );
    }


    public function testPermissionCodesAreNotEmpty(): void
    {
        $rows = $this->db()->query(
            '
            SELECT id
            FROM permisos

            WHERE codigo IS NULL
               OR TRIM(codigo) = ""
            '
        )->fetchAll();

        $this->assertCount(
            0,
            $rows,
            'Existen permisos sin código.'
        );
    }


    public function testRolePermissionAssignmentsAreValid(): void
    {
        $rows = $this->db()->query(
            '
            SELECT
                rp.rol_id,
                rp.permiso_id

            FROM rol_permisos rp

            LEFT JOIN roles r
                ON r.id = rp.rol_id

            LEFT JOIN permisos p
                ON p.id = rp.permiso_id

            WHERE r.id IS NULL
               OR p.id IS NULL
            '
        )->fetchAll();

        $this->assertCount(
            0,
            $rows,
            'Existen asignaciones rol/permisos inválidas.'
        );
    }


    public function testRolePermissionAssignmentsAreNotDuplicated(): void
    {
        $rows = $this->db()->query(
            '
            SELECT
                rol_id,
                permiso_id,
                COUNT(*) AS cantidad

            FROM rol_permisos

            GROUP BY
                rol_id,
                permiso_id

            HAVING COUNT(*) > 1
            '
        )->fetchAll();

        $this->assertCount(
            0,
            $rows,
            'Existen permisos duplicados dentro de un rol.'
        );
    }


    public function testActivePermissionsUseActiveModules(): void
    {
        /*
         * Ejecutamos esta comprobación solamente si modulos
         * posee columna activo.
         */

        if (!$this->columnExists('modulos', 'activo')) {
            $this->assertTrue(true);
            return;
        }

        $rows = $this->db()->query(
            '
            SELECT
                p.id,
                p.codigo

            FROM permisos p

            INNER JOIN modulos m
                ON m.id = p.modulo_id

            WHERE p.activo = 1
              AND m.activo = 0
            '
        )->fetchAll();

        $this->assertCount(
            0,
            $rows,
            'Existen permisos activos asociados a módulos inactivos.'
        );
    }


    public function testActivePermissionsUseActiveActions(): void
    {
        if (!$this->columnExists('acciones_permiso', 'activo')) {
            $this->assertTrue(true);
            return;
        }

        $rows = $this->db()->query(
            '
            SELECT
                p.id,
                p.codigo

            FROM permisos p

            INNER JOIN acciones_permiso a
                ON a.id = p.accion_id

            WHERE p.activo = 1
              AND a.activo = 0
            '
        )->fetchAll();

        $this->assertCount(
            0,
            $rows,
            'Existen permisos activos asociados a acciones inactivas.'
        );
    }


    public function testEveryActiveNonSuperAdminRoleHasPermissions(): void
    {
        $rows = $this->db()->query(
            '
            SELECT
                r.id,
                r.codigo

            FROM roles r

            LEFT JOIN rol_permisos rp
                ON rp.rol_id = r.id

            WHERE r.activo = 1
              AND r.codigo <> "SUPER_ADMINISTRADOR"

            GROUP BY
                r.id,
                r.codigo

            HAVING COUNT(rp.permiso_id) = 0
            '
        )->fetchAll();

        $message = '';

        foreach ($rows as $row) {
            $message .= sprintf(
                "%s no tiene permisos asignados.\n",
                $row['codigo']
            );
        }

        $this->assertCount(
            0,
            $rows,
            "Hay roles activos sin permisos:\n{$message}"
        );
    }


    public function testCriticalPermissionModulesExist(): void
    {
        $required = [
            'dashboard',
            'propietarios',
            'pacientes',
            'consultas',
            'vacunas',
            'desparasitacion',
            'tratamientos',
            'hospitalizacion',
            'laboratorio',
            'cirugias',
            'citas',
            'inventario',
            'facturacion',
            'ventas',
            'notificaciones',
            'reportes',
            'auditoria',
            'usuarios',
            'roles',
            'permisos',
            'empresas',
            'academico',
        ];

        $stmt = $this->db()->prepare(
            '
            SELECT COUNT(*)
            FROM permisos
            WHERE codigo LIKE :pattern
            '
        );

        foreach ($required as $module) {
            $stmt->execute([
                'pattern' => $module . '.%',
            ]);

            $this->assertGreaterThan(
                0,
                (int) $stmt->fetchColumn(),
                "No existen permisos para el módulo {$module}."
            );
        }
    }

    public function testSuperAdministratorDoesNotRequireRolePermissions(): void
    {
        $stmt = $this->db()->query(
            '
        SELECT COUNT(*)

        FROM rol_permisos rp

        INNER JOIN roles r
            ON r.id = rp.rol_id

        WHERE r.codigo = "SUPER_ADMINISTRADOR"
        '
        );

        $this->assertSame(
            0,
            (int) $stmt->fetchColumn(),
            'SUPER_ADMINISTRADOR no debe depender de rol_permisos; su acceso es global.'
        );
    }

    public function testAdministratorDoesNotHaveRestrictedSecurityPermissions(): void
    {
        $rows = $this->db()->query(
            '
        SELECT p.codigo

        FROM rol_permisos rp

        INNER JOIN roles r
            ON r.id = rp.rol_id

        INNER JOIN permisos p
            ON p.id = rp.permiso_id

        WHERE r.codigo = "ADMINISTRADOR"

          AND (
                p.codigo LIKE "roles.%"
                OR p.codigo LIKE "permisos.%"
                OR p.codigo LIKE "auditoria.%"
              )
        '
        )->fetchAll();

        $this->assertCount(
            0,
            $rows,
            'ADMINISTRADOR tiene permisos reservados al SUPER_ADMINISTRADOR.'
        );
    }

    public function testGuestRoleHasReadOnlyPermissions(): void
    {
        $rows = $this->db()->query(
            '
        SELECT p.codigo

        FROM rol_permisos rp

        INNER JOIN roles r
            ON r.id = rp.rol_id

        INNER JOIN permisos p
            ON p.id = rp.permiso_id

        INNER JOIN acciones_permiso a
            ON a.id = p.accion_id

        WHERE r.codigo = "INVITADO"
          AND UPPER(a.codigo) <> "VER"
        '
        )->fetchAll();

        $this->assertCount(
            0,
            $rows,
            'INVITADO posee permisos que no son de solo lectura.'
        );
    }

    public function testStudentCannotDeleteRecords(): void
    {
        $rows = $this->db()->query(
            '
        SELECT p.codigo

        FROM rol_permisos rp

        INNER JOIN roles r
            ON r.id = rp.rol_id

        INNER JOIN permisos p
            ON p.id = rp.permiso_id

        INNER JOIN acciones_permiso a
            ON a.id = p.accion_id

        WHERE r.codigo = "ESTUDIANTE"
          AND UPPER(a.codigo) = "ELIMINAR"
        '
        )->fetchAll();

        $this->assertCount(
            0,
            $rows,
            'ESTUDIANTE posee permisos de eliminación.'
        );
    }
}
