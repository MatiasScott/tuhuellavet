<?php

declare(strict_types=1);

use App\Services\ContificoService;

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('STORAGE_PATH', BASE_PATH . '/storage');
define('PUBLIC_PATH', BASE_PATH . '/public');

require BASE_PATH . '/vendor/autoload.php';

if (file_exists(BASE_PATH . '/.env')) {
    Dotenv\Dotenv::createImmutable(BASE_PATH)->safeLoad();
}

if (PHP_SAPI !== 'cli') {
    exit("Este script solo puede ejecutarse desde CLI.\n");
}

$fiscalDataId = isset($argv[1])
    ? (int) $argv[1]
    : 0;

if ($fiscalDataId <= 0) {
    exit("Uso:\n"
        . "php scripts/test_contifico_ensure_person.php DATOS_FISCALES_ID\n");
}

try {
    $service = new ContificoService();

    if (!$service->isConfigured()) {
        throw new RuntimeException(
            'Contífico no está configurado o está deshabilitado.'
        );
    }

    echo "=== SINCRONIZACIÓN PERSONA CONTÍFICO ===\n";
    echo "Datos fiscales ID: {$fiscalDataId}\n\n";

    echo "ADVERTENCIA: esta prueba puede modificar o crear ";
    echo "una persona real en Contífico.\n\n";

    $result = $service->ensurePerson(
        $fiscalDataId
    );

    echo json_encode(
        $result,
        JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_PRETTY_PRINT
    ) . PHP_EOL;
} catch (Throwable $e) {
    fwrite(
        STDERR,
        "ERROR: "
            . $e->getMessage()
            . PHP_EOL
    );

    exit(1);
}
