<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Procesador automático de notificaciones
|--------------------------------------------------------------------------
|
| Este script está pensado exclusivamente para ejecutarse desde CLI/cron.
| Reutiliza el bootstrap normal de Tu Huella Vet para disponer de:
|
| - Autoload
| - Variables .env
| - Configuración
| - Base de datos
| - Servicios
|
*/

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este proceso solo puede ejecutarse desde CLI.');
}

require dirname(__DIR__) . '/bootstrap/app.php';

use App\Services\InventoryAlertService;
use App\Services\NotificationService;

$startedAt = microtime(true);

try {

    /*
    |--------------------------------------------------------------------------
    | 1. Generar alertas administrativas
    |--------------------------------------------------------------------------
    |
    | Actualmente genera las alertas consolidadas de inventario.
    | El propio InventoryAlertService evita duplicados diarios.
    |
    */

    $inventory = (new InventoryAlertService())
        ->generate();


    /*
    |--------------------------------------------------------------------------
    | 2. Procesar cola de notificaciones
    |--------------------------------------------------------------------------
    |
    | Solamente se procesarán las notificaciones:
    |
    | estado = PENDIENTE
    | fecha_programada <= NOW()
    |
    */

    $notifications = (new NotificationService())
        ->processBatch(200);


    /*
    |--------------------------------------------------------------------------
    | Resultado
    |--------------------------------------------------------------------------
    */

    $elapsed = round(
        microtime(true) - $startedAt,
        3
    );

    echo sprintf(
        "[%s] OK | Inventario: %s | Notificaciones: %s | Tiempo: %ss%s",
        date('Y-m-d H:i:s'),
        json_encode(
            $inventory,
            JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
        ),
        json_encode(
            $notifications,
            JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
        ),
        $elapsed,
        PHP_EOL
    );

    exit(0);
} catch (Throwable $e) {

    fwrite(
        STDERR,
        sprintf(
            "[%s] ERROR | %s | %s:%d%s",
            date('Y-m-d H:i:s'),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            PHP_EOL
        )
    );

    exit(1);
}
