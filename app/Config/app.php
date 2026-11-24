<?php

$environment = strtolower(
    trim((string) ($_ENV['APP_ENV'] ?? getenv('APP_ENV') ?: 'production'))
);

$debug = filter_var(
    $_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: false,
    FILTER_VALIDATE_BOOL
);

return [
    'name' => $_ENV['APP_NAME'] ?? 'Vet Academic ERP',
    'env' => $environment,
    'debug' => $environment !== 'production' && $debug,
    'url' => rtrim($_ENV['APP_URL'] ?? '', '/'),
    'timezone' => $_ENV['APP_TIMEZONE'] ?? 'America/Guayaquil',
];
