<?php

declare(strict_types=1);

use App\Core\Application;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';
require BASE_PATH . '/app/helpers/app.php';

$app = new Application(BASE_PATH);
$app->bootstrap();
$app->run();
