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

$limit = isset($argv[1]) ? (int) $argv[1] : 20;
$result = (new ContificoService())->processPending($limit);

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
