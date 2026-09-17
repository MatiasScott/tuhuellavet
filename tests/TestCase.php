<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use PDO;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase
    extends PHPUnitTestCase
{
    protected PDO $db;


    protected function setUp(): void
    {
        parent::setUp();

        $this->db =
            Database::connection();
    }


    protected function db(): PDO
    {
        return $this->db;
    }


    protected function tableExists(
        string $table
    ): bool {
        $stmt =
            $this->db->prepare(
                '
                SELECT COUNT(*)

                FROM information_schema.TABLES

                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table
                  AND TABLE_TYPE = "BASE TABLE"
                '
            );

        $stmt->execute([
            'table' => $table,
        ]);

        return
            (int) $stmt->fetchColumn()
            > 0;
    }


    protected function columnExists(
        string $table,
        string $column
    ): bool {
        $stmt =
            $this->db->prepare(
                '
                SELECT COUNT(*)

                FROM information_schema.COLUMNS

                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table
                  AND COLUMN_NAME = :column
                '
            );

        $stmt->execute([
            'table' => $table,
            'column' => $column,
        ]);

        return
            (int) $stmt->fetchColumn()
            > 0;
    }


    protected function foreignKeyExists(
        string $table,
        string $column
    ): bool {
        $stmt =
            $this->db->prepare(
                '
                SELECT COUNT(*)

                FROM information_schema.KEY_COLUMN_USAGE

                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table
                  AND COLUMN_NAME = :column
                  AND REFERENCED_TABLE_NAME IS NOT NULL
                '
            );

        $stmt->execute([
            'table' => $table,
            'column' => $column,
        ]);

        return
            (int) $stmt->fetchColumn()
            > 0;
    }


    protected function indexExists(
        string $table,
        string $index
    ): bool {
        $stmt =
            $this->db->prepare(
                '
                SELECT COUNT(*)

                FROM information_schema.STATISTICS

                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table
                  AND INDEX_NAME = :index
                '
            );

        $stmt->execute([
            'table' => $table,
            'index' => $index,
        ]);

        return
            (int) $stmt->fetchColumn()
            > 0;
    }


    protected function countRows(
        string $table
    ): int {
        /*
         * Solamente permitimos identificadores SQL
         * simples para evitar inyección accidental.
         */
        if (
            !preg_match(
                '/^[A-Za-z0-9_]+$/',
                $table
            )
        ) {
            throw new \InvalidArgumentException(
                'Nombre de tabla inválido.'
            );
        }

        return (int)
            $this->db
                ->query(
                    "SELECT COUNT(*) FROM `{$table}`"
                )
                ->fetchColumn();
    }


    protected function beginTestTransaction(): void
    {
        if (
            !$this->db
                ->inTransaction()
        ) {
            $this->db
                ->beginTransaction();
        }
    }


    protected function rollbackTestTransaction(): void
    {
        if (
            $this->db
                ->inTransaction()
        ) {
            $this->db
                ->rollBack();
        }
    }


    protected function tearDown(): void
    {
        /*
         * Protección adicional.
         *
         * Si un futuro test abre una transacción y
         * olvida cerrarla, hacemos rollback.
         */
        $this->rollbackTestTransaction();

        parent::tearDown();
    }
}