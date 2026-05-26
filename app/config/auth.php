<?php

declare(strict_types=1);

return [
    'session_key' => 'auth_user',
    'company_session_key' => 'active_company_id',
    'session_name' => $_ENV['SESSION_NAME'] ?? 'tvg_session',
    'session_lifetime' => (int) ($_ENV['SESSION_LIFETIME'] ?? 7200),
    'csrf_token_ttl' => (int) ($_ENV['CSRF_TOKEN_TTL'] ?? 3600),
    'roles' => [
        'super_administrador',
        'administrador',
        'cliente',
        'invitado',
    ],
];
