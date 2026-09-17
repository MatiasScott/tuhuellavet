<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/bootstrap/app.php';

use App\Services\FileIntegrityService;

try {
    $report = (new FileIntegrityService())->audit();

    echo json_encode(
        $report,
        JSON_PRETTY_PRINT
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
    ) . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        'Error de auditoría: '
            . $exception->getMessage()
            . PHP_EOL
    );

    exit(2);
}
