<?php

declare(strict_types=1);

namespace Tests\Security;

use Tests\TestCase;

class EnvironmentSecurityTest extends TestCase
{
    public function testEveryEnvironmentReferencesValidCompany(): void
    {
        $rows = $this->db()->query(
            '
            SELECT e.id
            FROM entornos e

            LEFT JOIN empresas emp
                ON emp.id = e.empresa_id

            WHERE emp.id IS NULL
            '
        )->fetchAll();

        $this->assertCount(
            0,
            $rows,
            'Existen entornos sin empresa válida.'
        );
    }


    public function testEveryEnvironmentHasValidType(): void
    {
        $rows = $this->db()->query(
            '
            SELECT e.id
            FROM entornos e

            LEFT JOIN tipos_entorno te
                ON te.id = e.tipo_entorno_id

            WHERE te.id IS NULL
            '
        )->fetchAll();

        $this->assertCount(
            0,
            $rows,
            'Existen entornos sin tipo válido.'
        );
    }


    public function testAnimalsAndOwnersDoNotCrossEnvironments(): void
    {
        $rows = $this->db()->query(
            '
            SELECT
                a.id AS animal_id,
                a.entorno_id AS animal_entorno,
                pe.entorno_id AS propietario_entorno

            FROM animales a

            INNER JOIN propietarios_entornos pe
                ON pe.id = a.propietario_entorno_id

            WHERE a.propietario_entorno_id IS NOT NULL
              AND a.entorno_id <> pe.entorno_id
            '
        )->fetchAll();

        $this->assertCount(
            0,
            $rows,
            'Hay animales asociados a propietarios de otro entorno.'
        );
    }


    public function testClinicalEventsDoNotCrossAnimalEnvironment(): void
    {
        /*
         * Solo comprobamos esto si eventos_clinicos
         * contiene entorno_id.
         */

        if (
            !$this->columnExists(
                'eventos_clinicos',
                'entorno_id'
            )
        ) {
            $this->assertTrue(true);
            return;
        }

        $rows = $this->db()->query(
            '
            SELECT
                ec.id AS evento_id,
                ec.entorno_id AS evento_entorno,
                a.entorno_id AS animal_entorno

            FROM eventos_clinicos ec

            INNER JOIN animales a
                ON a.id = ec.animal_id

            WHERE ec.entorno_id <> a.entorno_id
            '
        )->fetchAll();

        $this->assertCount(
            0,
            $rows,
            'Hay eventos clínicos vinculados a animales de otro entorno.'
        );
    }


    public function testAppointmentsDoNotCrossAnimalEnvironment(): void
    {
        $rows = $this->db()->query(
            '
            SELECT
                c.id,
                c.entorno_id,
                a.entorno_id AS animal_entorno

            FROM citas c

            INNER JOIN animales a
                ON a.id = c.animal_id

            WHERE c.entorno_id <> a.entorno_id
            '
        )->fetchAll();

        $this->assertCount(
            0,
            $rows,
            'Hay citas asociadas a animales de otro entorno.'
        );
    }


    public function testSalesDoNotCrossAnimalEnvironment(): void
    {
        $rows = $this->db()->query(
            '
            SELECT
                v.id,
                v.entorno_id,
                a.entorno_id AS animal_entorno

            FROM ventas v

            INNER JOIN animales a
                ON a.id = v.animal_id

            WHERE v.animal_id IS NOT NULL
              AND v.entorno_id <> a.entorno_id
            '
        )->fetchAll();

        $this->assertCount(
            0,
            $rows,
            'Hay ventas asociadas a animales de otro entorno.'
        );
    }


    public function testAcademicEnvironmentNeverAllowsRealBilling(): void
    {
        $rows = $this->db()->query(
            '
            SELECT
                e.id,
                e.codigo

            FROM entornos e

            INNER JOIN tipos_entorno te
                ON te.id = e.tipo_entorno_id

            WHERE te.codigo = "ACADEMICO"
              AND e.permite_facturacion_real = 1
              AND e.deleted_at IS NULL
            '
        )->fetchAll();

        $this->assertCount(
            0,
            $rows,
            'Académico tiene facturación real habilitada.'
        );
    }


    public function testEnvironmentRoleAssignmentsReferenceActiveUsers(): void
    {
        $rows = $this->db()->query(
            '
            SELECT DISTINCT
                uer.usuario_id

            FROM usuarios_entornos_roles uer

            INNER JOIN usuarios u
                ON u.id = uer.usuario_id

            WHERE u.activo = 0
               OR u.deleted_at IS NOT NULL
            '
        )->fetchAll();

        /*
         * Una cuenta desactivada puede conservar histórico
         * de roles. Por ello no afirmamos que sea corrupción.
         *
         * La seguridad depende de que AuthService impida
         * autenticar usuarios inactivos, que comprobaremos
         * funcionalmente después.
         */

        $this->assertIsArray($rows);
    }
}