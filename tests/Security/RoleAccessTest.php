<?php

declare(strict_types=1);

namespace Tests\Security;

use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    private function permissions(string $role): array
    {
        $stmt = $this->db()->prepare(
            '
            SELECT DISTINCT p.codigo
            FROM roles r
            INNER JOIN rol_permisos rp
                ON rp.rol_id = r.id
            INNER JOIN permisos p
                ON p.id = rp.permiso_id
            WHERE r.codigo = :rol
              AND r.activo = 1
              AND p.activo = 1
            ORDER BY p.codigo
            '
        );

        $stmt->execute([
            'rol' => $role,
        ]);

        return array_column(
            $stmt->fetchAll(),
            'codigo'
        );
    }

    public function testAdministratorCannotManageRoles(): void
    {
        $permissions = $this->permissions('ADMINISTRADOR');

        foreach (
            [
                'roles.ver',
                'roles.crear',
                'roles.editar',
                'roles.eliminar',
            ] as $permission
        ) {
            $this->assertNotContains(
                $permission,
                $permissions,
                "ADMINISTRADOR no debe poseer {$permission}."
            );
        }
    }

    public function testAdministratorCannotManagePermissions(): void
    {
        $permissions = $this->permissions('ADMINISTRADOR');

        foreach (
            [
                'permisos.ver',
                'permisos.crear',
                'permisos.editar',
                'permisos.eliminar',
            ] as $permission
        ) {
            $this->assertNotContains(
                $permission,
                $permissions,
                "ADMINISTRADOR no debe poseer {$permission}."
            );
        }
    }

    public function testAdministratorCannotAccessAudit(): void
    {
        $permissions = $this->permissions('ADMINISTRADOR');

        foreach (
            [
                'auditoria.ver',
                'auditoria.exportar',
            ] as $permission
        ) {
            $this->assertNotContains(
                $permission,
                $permissions,
                "ADMINISTRADOR no debe poseer {$permission}."
            );
        }
    }

    public function testGuestIsReadOnly(): void
    {
        $permissions = $this->permissions('INVITADO');

        foreach ($permissions as $permission) {
            $stmt = $this->db()->prepare(
                '
                SELECT a.codigo
                FROM permisos p
                INNER JOIN acciones_permiso a
                    ON a.id = p.accion_id
                WHERE p.codigo = :codigo
                '
            );

            $stmt->execute([
                'codigo' => $permission,
            ]);

            $action = strtoupper(
                (string) $stmt->fetchColumn()
            );

            $this->assertSame(
                'VER',
                $action,
                "INVITADO tiene permiso no-lectura: {$permission}"
            );
        }
    }

    public function testStudentCannotDelete(): void
    {
        $permissions = $this->permissions('ESTUDIANTE');

        foreach ($permissions as $permission) {
            $this->assertFalse(
                str_ends_with($permission, '.eliminar'),
                "ESTUDIANTE puede eliminar mediante {$permission}."
            );
        }
    }

    public function testClientCannotManageSystemAdministration(): void
    {
        $permissions = $this->permissions('CLIENTE');

        $forbiddenPrefixes = [
            'usuarios.',
            'roles.',
            'permisos.',
            'empresas.',
            'auditoria.',
        ];

        foreach ($permissions as $permission) {
            foreach ($forbiddenPrefixes as $prefix) {
                $this->assertFalse(
                    str_starts_with($permission, $prefix),
                    "CLIENTE posee permiso administrativo {$permission}."
                );
            }
        }
    }

    public function testClientCannotModifyClinicalRecords(): void
    {
        $permissions = $this->permissions('CLIENTE');

        $forbidden = [
            'consultas.crear',
            'consultas.editar',
            'consultas.eliminar',

            'vacunas.crear',
            'vacunas.editar',
            'vacunas.eliminar',

            'desparasitacion.crear',
            'desparasitacion.editar',
            'desparasitacion.eliminar',

            'tratamientos.crear',
            'tratamientos.editar',
            'tratamientos.eliminar',

            'hospitalizacion.crear',
            'hospitalizacion.editar',
            'hospitalizacion.eliminar',

            'laboratorio.crear',
            'laboratorio.editar',
            'laboratorio.eliminar',

            'cirugias.crear',
            'cirugias.editar',
            'cirugias.eliminar',
        ];

        foreach ($forbidden as $permission) {
            $this->assertNotContains(
                $permission,
                $permissions,
                "CLIENTE posee permiso clínico indebido {$permission}."
            );
        }
    }

    public function testTeacherCannotDeleteClinicalRecords(): void
    {
        $permissions = $this->permissions('DOCENTE');

        $clinicalModules = [
            'pacientes',
            'consultas',
            'vacunas',
            'vacunacion',
            'desparasitacion',
            'tratamientos',
            'hospitalizacion',
            'laboratorio',
            'cirugias',
        ];

        foreach ($clinicalModules as $module) {
            $permission = $module . '.eliminar';

            $this->assertNotContains(
                $permission,
                $permissions,
                "DOCENTE posee permiso clínico de eliminación {$permission}."
            );
        }
    }

    public function testOnlyTeacherCanModifyAcademicModuleAmongRestrictedRoles(): void
    {
        $teacher = $this->permissions('DOCENTE');

        $this->assertContains(
            'academico.crear',
            $teacher
        );

        $this->assertContains(
            'academico.editar',
            $teacher
        );

        foreach (
            [
                'CLIENTE',
                'INVITADO',
                'ESTUDIANTE',
            ] as $role
        ) {
            $permissions = $this->permissions($role);

            $this->assertNotContains(
                'academico.crear',
                $permissions,
                "{$role} no debe crear contenido académico."
            );

            $this->assertNotContains(
                'academico.editar',
                $permissions,
                "{$role} no debe editar contenido académico."
            );

            $this->assertNotContains(
                'academico.eliminar',
                $permissions,
                "{$role} no debe eliminar contenido académico."
            );
        }
    }
}
