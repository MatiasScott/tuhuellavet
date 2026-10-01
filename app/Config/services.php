<?php
return [
    'google' => [
        'client_id' => $_ENV['GOOGLE_CLIENT_ID'] ?? '',
        'client_secret' => $_ENV['GOOGLE_CLIENT_SECRET'] ?? '',
        'redirect_uri' => $_ENV['GOOGLE_REDIRECT_URI'] ?? ''
    ],
    'contifico' => [
        'api_url' => $_ENV['CONTIFICO_API_URL'] ?? '',
        'api_key' => $_ENV['CONTIFICO_API_KEY'] ?? ''
    ],
    'iconta' => [
        'enabled' => filter_var(
            $_ENV['ICONTA_ENABLED'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        ),

        'environment' => $_ENV['ICONTA_ENVIRONMENT'] ?? 'test',

        'api_url' => rtrim(
            $_ENV['ICONTA_API_URL']
                ?? 'https://test.iconta.ec:15443',
            '/'
        ),

        'api_key' => $_ENV['ICONTA_API_KEY'] ?? '',

        'timeout' => (int) ($_ENV['ICONTA_TIMEOUT'] ?? 30),
    ],
];
