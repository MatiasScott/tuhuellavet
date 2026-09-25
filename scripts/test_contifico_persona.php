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

$identification = trim(
    (string) ($argv[1] ?? '')
);

if ($identification === '') {
    exit("Uso:\n"
        . "php scripts/test_contifico_persona.php IDENTIFICACION\n");
}

try {
    $service = new ContificoService();

    echo "=== CONTÍFICO ===\n";
    echo "Identificación: {$identification}\n\n";

    if (!$service->isConfigured()) {
        throw new RuntimeException(
            'Contífico no está configurado o está deshabilitado.'
        );
    }

    echo "Buscando persona...\n\n";

    $person = $service->findPersonByIdentification(
        $identification
    );

    if ($person === null) {
        echo "RESULTADO: NO ENCONTRADA\n";
        echo "No se creó ninguna persona.\n";
        exit(0);
    }

    echo "RESULTADO: ENCONTRADA\n\n";

    echo json_encode(
        $person,
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
