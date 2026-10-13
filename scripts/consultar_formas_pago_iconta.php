<?php

use App\Services\IContaService;

if (PHP_SAPI !== 'cli') {
    exit("Solo disponible desde CLI.\n");
}

require dirname(__DIR__) . '/bootstrap/app.php';

try {
    $service = new IContaService();

    if (!$service->isEnabled()) {
        throw new RuntimeException(
            'La integración iConta está deshabilitada.'
        );
    }

    $methods = $service->searchSalePaymentMethods();

    echo "FORMAS DE PAGO DE VENTAS EN ICONTA\n";
    echo "=================================\n\n";

    foreach ($methods as $method) {
        echo json_encode(
            $method,
            JSON_PRETTY_PRINT
                | JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
        );

        echo "\n-----------------------------\n";
    }

} catch (Throwable $e) {
    fwrite(
        STDERR,
        'ERROR: ' . $e->getMessage() . PHP_EOL
    );

    exit(1);
}