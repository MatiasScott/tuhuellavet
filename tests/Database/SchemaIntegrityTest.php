<?php

declare(strict_types=1);

namespace Tests\Database;

use Tests\TestCase;

class SchemaIntegrityTest extends TestCase
{
    /**
     * La base debe contener tablas.
     */
    public function testDatabaseContainsTables(): void
    {
        $stmt = $this->db()->query(
            '
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_TYPE = "BASE TABLE"
            '
        );

        $total = (int) $stmt->fetchColumn();

        $this->assertGreaterThan(
            0,
            $total,
            'La base de datos no contiene tablas.'
        );
    }


    /**
     * Todas las tablas deben utilizar InnoDB.
     *
     * Esto es especialmente importante porque nuestro
     * modelo depende ampliamente de FOREIGN KEY.
     */
    public function testAllTablesUseInnoDB(): void
    {
        $rows = $this->db()->query(
            '
            SELECT
                TABLE_NAME,
                ENGINE
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_TYPE = "BASE TABLE"
              AND (
                    ENGINE IS NULL
                    OR UPPER(ENGINE) <> "INNODB"
                  )
            ORDER BY TABLE_NAME
            '
        )->fetchAll();

        $message = '';

        foreach ($rows as $row) {
            $message .= sprintf(
                "%s usa %s\n",
                $row['TABLE_NAME'],
                $row['ENGINE'] ?? 'NULL'
            );
        }

        $this->assertCount(
            0,
            $rows,
            "Hay tablas que no utilizan InnoDB:\n{$message}"
        );
    }


    /**
     * Toda tabla física debe tener PRIMARY KEY.
     */
    public function testAllTablesHavePrimaryKey(): void
    {
        $rows = $this->db()->query(
            '
            SELECT
                t.TABLE_NAME
            FROM information_schema.TABLES t

            LEFT JOIN information_schema.TABLE_CONSTRAINTS tc
                ON tc.CONSTRAINT_SCHEMA = t.TABLE_SCHEMA
               AND tc.TABLE_NAME = t.TABLE_NAME
               AND tc.CONSTRAINT_TYPE = "PRIMARY KEY"

            WHERE t.TABLE_SCHEMA = DATABASE()
              AND t.TABLE_TYPE = "BASE TABLE"
              AND tc.CONSTRAINT_NAME IS NULL

            ORDER BY t.TABLE_NAME
            '
        )->fetchAll();

        $tables = array_column(
            $rows,
            'TABLE_NAME'
        );

        $this->assertCount(
            0,
            $rows,
            'Tablas sin PRIMARY KEY: '
            . implode(', ', $tables)
        );
    }


    /**
     * No debe haber tablas vacías estructuralmente.
     */
    public function testAllTablesHaveColumns(): void
    {
        $rows = $this->db()->query(
            '
            SELECT
                t.TABLE_NAME
            FROM information_schema.TABLES t

            LEFT JOIN information_schema.COLUMNS c
                ON c.TABLE_SCHEMA = t.TABLE_SCHEMA
               AND c.TABLE_NAME = t.TABLE_NAME

            WHERE t.TABLE_SCHEMA = DATABASE()
              AND t.TABLE_TYPE = "BASE TABLE"

            GROUP BY t.TABLE_NAME

            HAVING COUNT(c.COLUMN_NAME) = 0
            '
        )->fetchAll();

        $this->assertCount(
            0,
            $rows,
            'Existen tablas sin columnas.'
        );
    }


    /**
     * Tablas fundamentales del sistema.
     */
    public function testCoreTablesExist(): void
    {
        $tables = [
            'empresas',
            'tipos_entorno',
            'entornos',

            'usuarios',
            'roles',
            'permisos',
            'usuarios_roles_globales',
            'usuarios_entornos_roles',

            'propietarios',
            'animales',
            'animales_pesos',

            'categorias_animales',
            'especies',
            'razas',
            'sexos_animales',

            'eventos_clinicos',
            'consultas_externas',

            'vacunaciones',
            'desparasitaciones',

            'tratamientos',

            'hospitalizaciones',

            'formulas',
            'formula_versiones',
            'formula_ejecuciones',

            'auditoria',
            'notificaciones',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(
                $this->tableExists($table),
                "Falta la tabla crítica: {$table}"
            );
        }
    }


    /**
     * Usuarios debe mantener email único.
     */
    public function testUsersEmailHasUniqueIndex(): void
    {
        $stmt = $this->db()->query(
            '
            SELECT COUNT(*)
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = "usuarios"
              AND COLUMN_NAME = "email"
              AND NON_UNIQUE = 0
            '
        );

        $this->assertGreaterThan(
            0,
            (int) $stmt->fetchColumn(),
            'usuarios.email no posee índice UNIQUE.'
        );
    }


    /**
     * Los permisos deben tener código único.
     */
    public function testPermissionCodeHasUniqueIndex(): void
    {
        $stmt = $this->db()->query(
            '
            SELECT COUNT(*)
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = "permisos"
              AND COLUMN_NAME = "codigo"
              AND NON_UNIQUE = 0
            '
        );

        $this->assertGreaterThan(
            0,
            (int) $stmt->fetchColumn(),
            'permisos.codigo no posee índice UNIQUE.'
        );
    }


    /**
     * No deben existir códigos de permisos duplicados.
     */
    public function testPermissionCodesAreNotDuplicated(): void
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


    /**
     * Nuestro historial de peso debe existir.
     */
    public function testAnimalWeightHistoryStructureExists(): void
    {
        $this->assertTrue(
            $this->tableExists('animales_pesos'),
            'No existe animales_pesos.'
        );

        $this->assertTrue(
            $this->columnExists(
                'animales_pesos',
                'animal_id'
            ),
            'animales_pesos no tiene animal_id.'
        );

        $this->assertTrue(
            $this->columnExists(
                'animales_pesos',
                'peso_kg'
            ),
            'animales_pesos no tiene peso_kg.'
        );
    }


    /**
     * Vacunaciones y desparasitaciones no deben
     * almacenar el peso como estado actual.
     */
    public function testPreventiveCareDoesNotDuplicateWeight(): void
    {
        $this->assertFalse(
            $this->columnExists(
                'vacunaciones',
                'peso_kg'
            ),
            'vacunaciones contiene peso_kg; el peso debe historizarse en animales_pesos.'
        );

        $this->assertFalse(
            $this->columnExists(
                'desparasitaciones',
                'peso_kg'
            ),
            'desparasitaciones contiene peso_kg; el peso debe historizarse en animales_pesos.'
        );
    }


    /**
     * La separación por entorno debe existir en animales.
     */
    public function testAnimalsAreAssociatedWithEnvironment(): void
    {
        $this->assertTrue(
            $this->columnExists(
                'animales',
                'entorno_id'
            ),
            'animales no posee entorno_id.'
        );

        $this->assertTrue(
            $this->foreignKeyExists(
                'animales',
                'entorno_id'
            ),
            'animales.entorno_id no tiene FOREIGN KEY.'
        );
    }


    /**
     * Académico nunca debe permitir facturación real.
     */
    public function testAcademicEnvironmentCannotAllowRealBilling(): void
    {
        $rows = $this->db()->query(
            '
            SELECT
                e.id,
                e.codigo,
                e.nombre,
                e.permite_facturacion_real

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
            'Existe un entorno ACADÉMICO con facturación real habilitada.'
        );
    }


    /**
     * Los tres tipos fundamentales deben existir.
     */
    public function testRequiredEnvironmentTypesExist(): void
    {
        $required = [
            'VETERINARIA',
            'HACIENDA',
            'ACADEMICO',
        ];

        $stmt = $this->db()->prepare(
            '
            SELECT COUNT(*)
            FROM tipos_entorno
            WHERE codigo = :codigo
              AND activo = 1
            '
        );

        foreach ($required as $code) {
            $stmt->execute([
                'codigo' => $code,
            ]);

            $this->assertSame(
                1,
                (int) $stmt->fetchColumn(),
                "No existe el tipo de entorno activo {$code}."
            );
        }
    }
}