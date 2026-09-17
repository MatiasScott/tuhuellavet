<?php

declare(strict_types=1);

namespace Tests\Database;

use Tests\TestCase;

class ForeignKeyIntegrityTest extends TestCase
{
    /**
     * Debemos tener relaciones FK en el modelo.
     */
    public function testDatabaseContainsForeignKeys(): void
    {
        $stmt = $this->db()->query(
            '
            SELECT COUNT(*)
            FROM information_schema.REFERENTIAL_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = DATABASE()
            '
        );

        $this->assertGreaterThan(
            0,
            (int) $stmt->fetchColumn(),
            'La base no contiene FOREIGN KEY.'
        );
    }


    /**
     * Todas las FK deben apuntar a tablas existentes.
     */
    public function testAllForeignKeysReferenceExistingTables(): void
    {
        $rows = $this->db()->query(
            '
            SELECT
                k.TABLE_NAME,
                k.COLUMN_NAME,
                k.CONSTRAINT_NAME,
                k.REFERENCED_TABLE_NAME,
                k.REFERENCED_COLUMN_NAME

            FROM information_schema.KEY_COLUMN_USAGE k

            LEFT JOIN information_schema.TABLES t
                ON t.TABLE_SCHEMA = k.REFERENCED_TABLE_SCHEMA
               AND t.TABLE_NAME = k.REFERENCED_TABLE_NAME

            WHERE k.TABLE_SCHEMA = DATABASE()
              AND k.REFERENCED_TABLE_NAME IS NOT NULL
              AND t.TABLE_NAME IS NULL
            '
        )->fetchAll();

        $this->assertCount(
            0,
            $rows,
            'Existen FOREIGN KEY apuntando a tablas inexistentes.'
        );
    }


    /**
     * La columna referenciada también debe existir.
     */
    public function testAllForeignKeysReferenceExistingColumns(): void
    {
        $rows = $this->db()->query(
            '
            SELECT
                k.TABLE_NAME,
                k.COLUMN_NAME,
                k.CONSTRAINT_NAME,
                k.REFERENCED_TABLE_NAME,
                k.REFERENCED_COLUMN_NAME

            FROM information_schema.KEY_COLUMN_USAGE k

            LEFT JOIN information_schema.COLUMNS c
                ON c.TABLE_SCHEMA = k.REFERENCED_TABLE_SCHEMA
               AND c.TABLE_NAME = k.REFERENCED_TABLE_NAME
               AND c.COLUMN_NAME = k.REFERENCED_COLUMN_NAME

            WHERE k.TABLE_SCHEMA = DATABASE()
              AND k.REFERENCED_TABLE_NAME IS NOT NULL
              AND c.COLUMN_NAME IS NULL
            '
        )->fetchAll();

        $this->assertCount(
            0,
            $rows,
            'Existen FOREIGN KEY apuntando a columnas inexistentes.'
        );
    }


    /**
     * Las columnas de una FK y su columna destino
     * deben ser compatibles.
     *
     * Comparamos tipo, tamaño y UNSIGNED.
     */
    public function testForeignKeyColumnTypesAreCompatible(): void
    {
        $rows = $this->db()->query(
            '
            SELECT
                k.TABLE_NAME AS tabla_hija,
                k.COLUMN_NAME AS columna_hija,

                child.COLUMN_TYPE AS tipo_hijo,

                k.REFERENCED_TABLE_NAME AS tabla_padre,
                k.REFERENCED_COLUMN_NAME AS columna_padre,

                parent.COLUMN_TYPE AS tipo_padre

            FROM information_schema.KEY_COLUMN_USAGE k

            INNER JOIN information_schema.COLUMNS child
                ON child.TABLE_SCHEMA = k.TABLE_SCHEMA
               AND child.TABLE_NAME = k.TABLE_NAME
               AND child.COLUMN_NAME = k.COLUMN_NAME

            INNER JOIN information_schema.COLUMNS parent
                ON parent.TABLE_SCHEMA = k.REFERENCED_TABLE_SCHEMA
               AND parent.TABLE_NAME = k.REFERENCED_TABLE_NAME
               AND parent.COLUMN_NAME = k.REFERENCED_COLUMN_NAME

            WHERE k.TABLE_SCHEMA = DATABASE()
              AND k.REFERENCED_TABLE_NAME IS NOT NULL

              AND LOWER(child.COLUMN_TYPE)
                  <> LOWER(parent.COLUMN_TYPE)
            '
        )->fetchAll();

        $message = '';

        foreach ($rows as $row) {
            $message .= sprintf(
                "%s.%s (%s) -> %s.%s (%s)\n",
                $row['tabla_hija'],
                $row['columna_hija'],
                $row['tipo_hijo'],
                $row['tabla_padre'],
                $row['columna_padre'],
                $row['tipo_padre']
            );
        }

        $this->assertCount(
            0,
            $rows,
            "Hay tipos incompatibles entre FK:\n{$message}"
        );
    }


    /**
     * Buscamos registros huérfanos automáticamente.
     *
     * La consulta se construye desde information_schema,
     * por lo que no necesitamos escribir una prueba
     * manual para cada FK.
     */
    public function testNoForeignKeyContainsOrphanRecords(): void
    {
        $foreignKeys = $this->db()->query(
            '
            SELECT
                TABLE_NAME,
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME

            FROM information_schema.KEY_COLUMN_USAGE

            WHERE TABLE_SCHEMA = DATABASE()
              AND REFERENCED_TABLE_NAME IS NOT NULL

            ORDER BY TABLE_NAME, COLUMN_NAME
            '
        )->fetchAll();

        $orphans = [];

        foreach ($foreignKeys as $fk) {
            $childTable =
                $fk['TABLE_NAME'];

            $childColumn =
                $fk['COLUMN_NAME'];

            $parentTable =
                $fk['REFERENCED_TABLE_NAME'];

            $parentColumn =
                $fk['REFERENCED_COLUMN_NAME'];

            /*
             * Los nombres provienen exclusivamente de
             * information_schema, no de entrada del usuario.
             */
            $sql = "
                SELECT COUNT(*)

                FROM `{$childTable}` child

                LEFT JOIN `{$parentTable}` parent
                    ON parent.`{$parentColumn}`
                     = child.`{$childColumn}`

                WHERE child.`{$childColumn}` IS NOT NULL
                  AND parent.`{$parentColumn}` IS NULL
            ";

            $count = (int)
            $this->db()
                ->query($sql)
                ->fetchColumn();

            if ($count > 0) {
                $orphans[] = sprintf(
                    '%s.%s -> %s.%s : %d huérfanos',
                    $childTable,
                    $childColumn,
                    $parentTable,
                    $parentColumn,
                    $count
                );
            }
        }

        $this->assertCount(
            0,
            $orphans,
            "Se encontraron registros huérfanos:\n"
                . implode("\n", $orphans)
        );
    }


    /**
     * Relaciones críticas que no deben desaparecer
     * accidentalmente durante el desarrollo.
     */
    public function testCriticalForeignKeysExist(): void
    {
        $foreignKeys = [

            /*
        |--------------------------------------------------------------------------
        | Propietarios
        |--------------------------------------------------------------------------
        */

            [
                'propietarios_entornos',
                'propietario_id'
            ],

            [
                'propietarios_entornos',
                'entorno_id'
            ],


            /*
        |--------------------------------------------------------------------------
        | Animales
        |--------------------------------------------------------------------------
        */

            [
                'animales',
                'entorno_id'
            ],

            [
                'animales',
                'propietario_entorno_id'
            ],

            [
                'animales',
                'especie_id'
            ],

            [
                'animales',
                'sexo_id'
            ],


            /*
        |--------------------------------------------------------------------------
        | Historial de peso
        |--------------------------------------------------------------------------
        */

            [
                'animales_pesos',
                'animal_id'
            ],


            /*
        |--------------------------------------------------------------------------
        | Historia clínica
        |--------------------------------------------------------------------------
        */

            [
                'eventos_clinicos',
                'animal_id'
            ],

            [
                'consultas_externas',
                'evento_clinico_id'
            ],

            [
                'vacunaciones',
                'evento_clinico_id'
            ],

            [
                'desparasitaciones',
                'evento_clinico_id'
            ],

            [
                'hospitalizaciones',
                'evento_clinico_id'
            ],


            /*
        |--------------------------------------------------------------------------
        | Usuarios y roles globales
        |--------------------------------------------------------------------------
        */

            [
                'usuarios_roles_globales',
                'usuario_id'
            ],

            [
                'usuarios_roles_globales',
                'rol_id'
            ],


            /*
        |--------------------------------------------------------------------------
        | Usuarios, entornos y roles
        |--------------------------------------------------------------------------
        */

            [
                'usuarios_entornos_roles',
                'usuario_id'
            ],

            [
                'usuarios_entornos_roles',
                'entorno_id'
            ],

            [
                'usuarios_entornos_roles',
                'rol_id'
            ],
        ];


        foreach (
            $foreignKeys
            as [$table, $column]
        ) {
            $this->assertTrue(
                $this->foreignKeyExists(
                    $table,
                    $column
                ),
                "Falta FOREIGN KEY en {$table}.{$column}"
            );
        }
    }

    public function testAnimalOwnerRelationshipUsesEnvironmentOwner(): void
    {
        $stmt = $this->db()->prepare(
            '
        SELECT COUNT(*)

        FROM information_schema.KEY_COLUMN_USAGE

        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = "animales"
          AND COLUMN_NAME = "propietario_entorno_id"
          AND REFERENCED_TABLE_NAME = "propietarios_entornos"
          AND REFERENCED_COLUMN_NAME = "id"
        '
        );

        $stmt->execute();

        $this->assertGreaterThan(
            0,
            (int) $stmt->fetchColumn(),
            'animales.propietario_entorno_id debe referenciar propietarios_entornos.id.'
        );
    }

    public function testAnimalsAndOwnersBelongToSameEnvironment(): void
    {
        $rows = $this->db()->query(
            '
        SELECT
            a.id AS animal_id,
            a.entorno_id AS animal_entorno_id,
            pe.id AS propietario_entorno_id,
            pe.entorno_id AS propietario_entorno_id_real

        FROM animales a

        INNER JOIN propietarios_entornos pe
            ON pe.id = a.propietario_entorno_id

        WHERE a.propietario_entorno_id IS NOT NULL

          AND a.entorno_id
              <> pe.entorno_id
        '
        )->fetchAll();

        $message = '';

        foreach ($rows as $row) {
            $message .= sprintf(
                "Animal %d: entorno %d / propietario_entorno %d: entorno %d\n",
                $row['animal_id'],
                $row['animal_entorno_id'],
                $row['propietario_entorno_id'],
                $row['propietario_entorno_id_real']
            );
        }

        $this->assertCount(
            0,
            $rows,
            "Hay animales vinculados a propietarios de otro entorno:\n{$message}"
        );
    }

    public function testEnvironmentSensitiveAnimalRelationsExist(): void
    {
        $relations = [
            'citas',
            'ventas',
        ];

        foreach ($relations as $table) {

            $stmt = $this->db()->prepare(
                '
            SELECT
                CONSTRAINT_NAME,
                COUNT(*) AS columnas

            FROM information_schema.KEY_COLUMN_USAGE

            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :table
              AND REFERENCED_TABLE_NAME = "animales"

            GROUP BY CONSTRAINT_NAME

            HAVING
                SUM(
                    COLUMN_NAME = "animal_id"
                    AND REFERENCED_COLUMN_NAME = "id"
                ) > 0

                AND

                SUM(
                    COLUMN_NAME = "entorno_id"
                    AND REFERENCED_COLUMN_NAME = "entorno_id"
                ) > 0
            '
            );

            $stmt->execute([
                'table' => $table,
            ]);

            $rows = $stmt->fetchAll();

            $this->assertNotEmpty(
                $rows,
                "{$table} debe proteger animal_id + entorno_id mediante una FK compuesta hacia animales."
            );
        }
    }
}
