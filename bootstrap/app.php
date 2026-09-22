<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use App\Core\App;

define('BASE_PATH', dirname(__DIR__));
define('APP_PATH', BASE_PATH . '/app');
define('PUBLIC_PATH', BASE_PATH . '/public');
$isTesting = ($_ENV['APP_ENV'] ?? getenv('APP_ENV')) === 'testing';

define(
    'STORAGE_PATH',
    $isTesting
        ? BASE_PATH . '/storage/qa'
        : BASE_PATH . '/storage'
);
require BASE_PATH . '/vendor/autoload.php';
if (file_exists(BASE_PATH . '/.env')) Dotenv::createImmutable(BASE_PATH)->safeLoad();
$config = require APP_PATH . '/Config/app.php';
date_default_timezone_set($config['timezone']);
ini_set('display_errors', $config['debug'] ? '1' : '0');
error_reporting(E_ALL);
return new App($config);
