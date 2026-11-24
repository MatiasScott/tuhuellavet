<?php

declare(strict_types=1);

use App\Services\FiscalSyncService;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script solo puede ejecutarse desde CLI.\n");
}

require dirname(__DIR__) . '/bootstrap/app.php';

if (!in_array('--dry-run', $argv, true)) {
    fwrite(
        STDERR,
        "Worker en desarrollo. Utilice --dry-run.\n"
    );

    exit(1);
}

try {

    $service = new FiscalSyncService();

    $documents = $service->pendingDocuments(20);

    echo "========================================\n";
    echo "AXXIS - SINCRONIZACION FISCAL\n";
    echo "MODO DIAGNOSTICO - SOLO LECTURA\n";
    echo "========================================\n";

    echo 'Documentos elegibles: '
        . count($documents)
        . PHP_EOL;

    foreach ($documents as $document) {

        echo PHP_EOL;

        echo 'Documento iConta: '
            . $document['id']
            . PHP_EOL;

        echo 'Venta: '
            . $document['venta_numero']
            . PHP_EOL;

        echo 'Estado: '
            . $document['estado']
            . PHP_EOL;

        echo 'ID remoto: '
            . $document['iconta_id_documento']
            . PHP_EOL;

        echo 'Errores consecutivos: '
            . $document['errores_consulta_sri']
            . PHP_EOL;
    }

    echo PHP_EOL;
    echo "Consultas realizadas a iConta: 0\n";
    echo "Registros modificados: 0\n";

    exit(0);

} catch (Throwable $e) {

    fwrite(
        STDERR,
        'ERROR: ' . $e->getMessage() . PHP_EOL
    );

    exit(1);
}