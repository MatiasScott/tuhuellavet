<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Database;
use PDO;
use RuntimeException;

class TestDatabase
{
    public static function connection(): PDO
    {
        return Database::connection();
    }


    public static function databaseName(): string
    {
        return (string)
            self::connection()
                ->query(
                    'SELECT DATABASE()'
                )
                ->fetchColumn();
    }


    public static function assertSafeEnvironment(): void
    {
        $environment =
            strtolower(
                (string) (
                    $_ENV['APP_ENV']
                    ?? getenv('APP_ENV')
                    ?: 'development'
                )
            );

        if (
            in_array(
                $environment,
                [
                    'production',
                    'prod',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'Las pruebas automáticas están bloqueadas en producción.'
            );
        }

        if (
            self::databaseName()
            === ''
        ) {
            throw new RuntimeException(
                'No existe una base de datos seleccionada.'
            );
        }
    }
}