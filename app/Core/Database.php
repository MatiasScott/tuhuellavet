<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use Throwable;

class Database
{
    private static ?PDO $connection = null;

    private static int $transactionLevel = 0;

    /**
     * Acciones que deben ejecutarse si se revierte
     * una transacción o uno de sus SAVEPOINT.
     *
     * @var array<int, array<int, callable>>
     */
    private static array $rollbackCallbacks = [];

    public static function connection(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        $config = require APP_PATH . '/Config/database.php';

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['database'],
            $config['charset']
        );

        self::$connection = new PDO(
            $dsn,
            $config['username'],
            $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        return self::$connection;
    }

    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();

        /*
         * Es posible que una transacción haya sido abierta directamente
         * sobre PDO, por ejemplo desde PHPUnit.
         */
        $ownsTransaction = !$pdo->inTransaction();

        if ($ownsTransaction) {
            // La transacción anterior pudo haberse cerrado
            // directamente mediante PDO, fuera de esta clase.
            self::$rollbackCallbacks = [];
            self::$transactionLevel = 0;

            $pdo->beginTransaction();

            self::$transactionLevel = 1;
        } else {
            self::$transactionLevel++;

            $savepoint = self::savepointName(
                self::$transactionLevel
            );

            $pdo->exec(
                'SAVEPOINT ' . $savepoint
            );
        }

        try {
            $result = $callback($pdo);

            if ($ownsTransaction) {
                $pdo->commit();

                self::$rollbackCallbacks = [];
                self::$transactionLevel = 0;
            } else {
                $savepoint = self::savepointName(
                    self::$transactionLevel
                );

                $pdo->exec(
                    'RELEASE SAVEPOINT ' . $savepoint
                );

                self::promoteRollbackCallbacks(
                    self::$transactionLevel,
                    self::$transactionLevel - 1
                );

                self::$transactionLevel--;
            }

            return $result;
        } catch (Throwable $exception) {
            if ($ownsTransaction) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                self::runRollbackCallbacks(
                    self::$transactionLevel
                );

                self::$rollbackCallbacks = [];
                self::$transactionLevel = 0;
            } else {
                $savepoint = self::savepointName(
                    self::$transactionLevel
                );

                if ($pdo->inTransaction()) {
                    $pdo->exec(
                        'ROLLBACK TO SAVEPOINT ' . $savepoint
                    );

                    $pdo->exec(
                        'RELEASE SAVEPOINT ' . $savepoint
                    );
                }

                self::$transactionLevel--;

                self::runRollbackCallbacks(
                    self::$transactionLevel + 1
                );
            }

            throw $exception;
        }
    }

    private static function savepointName(
        int $level
    ): string {
        return 'app_transaction_' . $level;
    }

    public static function onRollback(callable $callback): void
    {
        if (!self::connection()->inTransaction()) {
            throw new \RuntimeException(
                'No existe una transacción activa para registrar la compensación.'
            );
        }

        self::$rollbackCallbacks[self::$transactionLevel][] = $callback;
    }

    private static function runRollbackCallbacks(int $level): void
    {
        $callbacks = self::$rollbackCallbacks[$level] ?? [];

        unset(self::$rollbackCallbacks[$level]);

        foreach (array_reverse($callbacks) as $callback) {
            try {
                $callback();
            } catch (Throwable $e) {
                error_log(
                    'Error durante compensación de rollback: '
                        . $e->getMessage()
                );
            }
        }
    }

    private static function promoteRollbackCallbacks(
        int $fromLevel,
        int $toLevel
    ): void {
        $callbacks = self::$rollbackCallbacks[$fromLevel] ?? [];

        unset(self::$rollbackCallbacks[$fromLevel]);

        if ($callbacks !== []) {
            self::$rollbackCallbacks[$toLevel] = array_merge(
                self::$rollbackCallbacks[$toLevel] ?? [],
                $callbacks
            );
        }
    }
}
