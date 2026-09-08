<?php
$app=require __DIR__.'/../bootstrap/app.php';
$result=(new App\Services\NotificationService())->processBatch((int)($argv[1]??50));
echo json_encode($result,JSON_UNESCAPED_UNICODE).PHP_EOL;
