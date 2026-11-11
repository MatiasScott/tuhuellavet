<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
if (in_array('--qa', $argv, true)) {
    $_ENV['APP_ENV'] = 'testing';
    $_ENV['DB_DATABASE'] = 'tuhuellavet_qa';
}
require dirname(__DIR__) . '/bootstrap/app.php';

try {
    (new App\Services\HaciendaMigration())->run();
    echo "Migracion Hacienda Agusbella aplicada.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Migracion incompleta: " . $e->getMessage() . "\n");
    exit(1);
}
