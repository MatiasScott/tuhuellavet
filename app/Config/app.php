<?php
return [
    'name' => $_ENV['APP_NAME'] ?? 'Vet Academic ERP',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOL),
    'url' => rtrim($_ENV['APP_URL'] ?? '', '/'),
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'America/Guayaquil',
];
