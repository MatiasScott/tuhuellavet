<?php

declare(strict_types=1);

namespace Tests\Database;

use Tests\TestCase;

class CatalogIntegrityTest extends TestCase
{
    /*
    |--------------------------------------------------------------------------
    | Helper
    |--------------------------------------------------------------------------
    */

    private function assertCatalogHasRows(
        string $table
    ): void {
        $this->assertTrue(
            $this->tableExists($table),
            "No existe el catálogo {$table}."
        );

        $this->assertGreaterThan(
            0,
            $this->countRows($table),
            "El catálogo {$table} está vacío."
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Categorías de animales
    |--------------------------------------------------------------------------
    */

    public function testAnimalCategoriesExist(): void
    {
        $this->assertCatalogHasRows(
            'categorias_animales'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Especies
    |--------------------------------------------------------------------------
    */

    public function testSpeciesCatalogIsPopulated(): void
    {
        $this->assertCatalogHasRows(
            'especies'
        );
    }


    public function testEverySpeciesHasCategory(): void
    {
        $rows = $this->db()->query(
            '
            SELECT
                e.id,
                e.codigo,
                e.nombre_comun

            FROM especies e

            LEFT JOIN categorias_animales ca
                ON ca.id = e.categoria_id

            WHERE ca.id IS NULL
            '
        )->fetchAll();

        $this->assertCount(
            0,
            $rows,
            'Existen especies sin categoría válida.'
        );
    }


    public function testSpeciesCodesAreUnique(): void
    {
        $rows = $this->db()->query(
            '
            SELECT
                codigo,
                COUNT(*) AS cantidad

            FROM especies

            GROUP BY codigo

            HAVING COUNT(*) > 1
            '
        )->fetchAll();

        $this->assertCount(
            0,
            $rows,
            'Existen códigos de especies duplicados.'
        );
    }


    public function testSpeciesHaveRequiredValues(): void
    {
        $rows = $this->db()->query(
            '
            SELECT id
            FROM especies

            WHERE codigo IS NULL
               OR TRIM(codigo) = ""
               OR nombre_comun IS NULL
               OR TRIM(nombre_comun) = ""
            '
        )->fetchAll();

        $this->assertCount(
            0,
            $rows,
            'Existen especies sin código o nombre común.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Razas
    |--------------------------------------------------------------------------
    */

    public function testBreedsCatalogIsPopulated(): void
    {
        $this->assertCatalogHasRows(
            'razas'
        );
    }


    public function testEveryBreedBelongsToSpecies(): void
    {
        $rows = $this->db()->query(
            '
            SELECT
                r.id

            FROM razas r

            LEFT JOIN especies e
                ON e.id = r.especie_id

            WHERE e.id IS NULL
            '
        )->fetchAll();

        $this->assertCount(
            0,
            $rows,
            'Existen razas sin especie válida.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Sexos
    |--------------------------------------------------------------------------
    |
    | IMPORTANTE:
    |
    | No utilizamos WHERE activo = 1 porque ya comprobamos
    | que sexos_animales no necesariamente posee esa columna.
    |--------------------------------------------------------------------------
    */

    public function testAnimalSexCatalogIsPopulated(): void
    {
        $this->assertCatalogHasRows(
            'sexos_animales'
        );
    }


    public function testAnimalSexCodesAreUnique(): void
    {
        $rows = $this->db()->query(
            '
            SELECT
                codigo,
                COUNT(*) AS cantidad

            FROM sexos_animales

            GROUP BY codigo

            HAVING COUNT(*) > 1
            '
        )->fetchAll();

        $this->assertCount(
            0,
            $rows,
            'Existen códigos duplicados en sexos_animales.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Eventos clínicos
    |--------------------------------------------------------------------------
    */

    public function testClinicalEventTypesArePopulated(): void
    {
        $this->assertCatalogHasRows(
            'tipos_evento_clinico'
        );
    }


    public function testClinicalEventTypeCodesAreUnique(): void
    {
        $rows = $this->db()->query(
            '
            SELECT
                codigo,
                COUNT(*) AS cantidad

            FROM tipos_evento_clinico

            GROUP BY codigo

            HAVING COUNT(*) > 1
            '
        )->fetchAll();

        $this->assertCount(
            0,
            $rows,
            'Existen tipos de evento clínico duplicados.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Hospitalización
    |--------------------------------------------------------------------------
    */

    public function testHospitalizationStatesArePopulated(): void
    {
        $this->assertCatalogHasRows(
            'estados_hospitalizacion'
        );
    }


    public function testHospitalizationStateCodesAreUnique(): void
    {
        $rows = $this->db()->query(
            '
            SELECT
                codigo,
                COUNT(*) AS cantidad

            FROM estados_hospitalizacion

            GROUP BY codigo

            HAVING COUNT(*) > 1
            '
        )->fetchAll();

        $this->assertCount(
            0,
            $rows,
            'Existen estados de hospitalización duplicados.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Fluidoterapia
    |--------------------------------------------------------------------------
    */

    public function testFluidMaintenanceCategoriesArePopulated(): void
    {
        $this->assertCatalogHasRows(
            'categorias_mantenimiento_fluido'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Entornos
    |--------------------------------------------------------------------------
    */

    public function testEnvironmentTypesArePopulated(): void
    {
        $this->assertCatalogHasRows(
            'tipos_entorno'
        );
    }


    public function testRequiredEnvironmentTypesExist(): void
    {
        $required = [
            'VETERINARIA',
            'HACIENDA',
            'ACADEMICO',
        ];

        foreach ($required as $code) {

            $stmt = $this->db()->prepare(
                '
                SELECT COUNT(*)

                FROM tipos_entorno

                WHERE codigo = :codigo
                '
            );

            $stmt->execute([
                'codigo' => $code,
            ]);

            $this->assertSame(
                1,
                (int) $stmt->fetchColumn(),
                "Debe existir exactamente un tipo de entorno {$code}."
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    */

    public function testRolesCatalogIsPopulated(): void
    {
        $this->assertCatalogHasRows(
            'roles'
        );
    }


    public function testRequiredRolesExist(): void
    {
        $required = [
            'SUPER_ADMINISTRADOR',
            'ADMINISTRADOR',
            'CLIENTE',
            'INVITADO',
            'DOCENTE',
            'ESTUDIANTE',
        ];

        foreach ($required as $code) {

            $stmt = $this->db()->prepare(
                '
                SELECT COUNT(*)

                FROM roles

                WHERE codigo = :codigo
                  AND activo = 1
                '
            );

            $stmt->execute([
                'codigo' => $code,
            ]);

            $this->assertSame(
                1,
                (int) $stmt->fetchColumn(),
                "Debe existir exactamente un rol activo {$code}."
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Permisos
    |--------------------------------------------------------------------------
    */

    public function testPermissionsCatalogIsPopulated(): void
    {
        $this->assertCatalogHasRows(
            'permisos'
        );
    }


    public function testPermissionsHaveRequiredInformation(): void
    {
        $rows = $this->db()->query(
            '
            SELECT id

            FROM permisos

            WHERE codigo IS NULL
               OR TRIM(codigo) = ""
               OR modulo_id IS NULL
               OR accion_id IS NULL
            '
        )->fetchAll();

        $this->assertCount(
            0,
            $rows,
            'Existen permisos incompletos.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Canales de notificación
    |--------------------------------------------------------------------------
    */

    public function testNotificationChannelsArePopulated(): void
    {
        $this->assertCatalogHasRows(
            'canales_notificacion'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Tipos de notificación
    |--------------------------------------------------------------------------
    */

    public function testNotificationTypesArePopulated(): void
    {
        $this->assertCatalogHasRows(
            'tipos_notificacion'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Validación general de códigos
    |--------------------------------------------------------------------------
    |
    | Solamente inspeccionamos catálogos que realmente poseen
    | una columna llamada codigo.
    |--------------------------------------------------------------------------
    */

    public function testCatalogCodesAreNotEmpty(): void
    {
        $tables = [
            'categorias_animales',
            'especies',
            'razas',
            'sexos_animales',
            'tipos_evento_clinico',
            'estados_hospitalizacion',
            'categorias_mantenimiento_fluido',
            'tipos_entorno',
            'roles',
            'permisos',
            'canales_notificacion',
            'tipos_notificacion',
        ];

        $problems = [];

        foreach ($tables as $table) {

            if (
                !$this->tableExists($table)
                || !$this->columnExists(
                    $table,
                    'codigo'
                )
            ) {
                continue;
            }

            $count = (int)
            $this->db()
                ->query(
                    "
                        SELECT COUNT(*)

                        FROM `{$table}`

                        WHERE codigo IS NULL
                           OR TRIM(codigo) = ''
                        "
                )
                ->fetchColumn();

            if ($count > 0) {
                $problems[] =
                    "{$table}: {$count} código(s) vacío(s)";
            }
        }

        $this->assertCount(
            0,
            $problems,
            "Catálogos con códigos vacíos:\n"
                . implode("\n", $problems)
        );
    }

    public function testEverySpeciesHasAtLeastOneBreed(): void
    {
        $rows = $this->db()->query(
            '
        SELECT
            e.id,
            e.codigo,
            e.nombre_comun,
            COUNT(r.id) AS cantidad_razas

        FROM especies e

        LEFT JOIN razas r
            ON r.especie_id = e.id

        GROUP BY
            e.id,
            e.codigo,
            e.nombre_comun

        HAVING COUNT(r.id) = 0

        ORDER BY e.id
        '
        )->fetchAll();

        $message = '';

        foreach ($rows as $row) {
            $message .= sprintf(
                "%s (%s) no tiene razas configuradas.\n",
                $row['nombre_comun'],
                $row['codigo']
            );
        }

        $this->assertCount(
            0,
            $rows,
            "Hay especies sin razas disponibles:\n{$message}"
        );
    }

    public function testBreedNamesAreUniqueWithinSpecies(): void
    {
        $rows = $this->db()->query(
            '
        SELECT
            especie_id,
            nombre,
            COUNT(*) AS cantidad

        FROM razas

        GROUP BY
            especie_id,
            nombre

        HAVING COUNT(*) > 1
        '
        )->fetchAll();

        $message = '';

        foreach ($rows as $row) {
            $message .= sprintf(
                "especie_id=%d / raza=%s / repeticiones=%d\n",
                $row['especie_id'],
                $row['nombre'],
                $row['cantidad']
            );
        }

        $this->assertCount(
            0,
            $rows,
            "Existen razas duplicadas dentro de una especie:\n{$message}"
        );
    }
}
