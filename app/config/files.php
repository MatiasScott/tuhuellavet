<?php

declare(strict_types=1);

return [
    'storage_root' => BASE_PATH . '/storage/uploads',
    'relative_root' => 'storage/uploads',
    'max_size_bytes' => (int) ($_ENV['UPLOAD_MAX_SIZE_BYTES'] ?? 5242880),
    'max_width' => (int) ($_ENV['UPLOAD_MAX_WIDTH'] ?? 5000),
    'max_height' => (int) ($_ENV['UPLOAD_MAX_HEIGHT'] ?? 5000),
    'convert_to_webp' => filter_var($_ENV['UPLOAD_CONVERT_TO_WEBP'] ?? true, FILTER_VALIDATE_BOOL),
    'webp_quality' => (int) ($_ENV['UPLOAD_WEBP_QUALITY'] ?? 82),
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'],
    'allowed_mimes' => [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
    ],
    'folders' => [
        'usuarios' => 'usuarios',
        'propietarios' => 'propietarios',
        'animales' => 'animales',
        'productos' => 'productos',
        'medicamentos' => 'medicamentos',
        'empresas' => 'empresas',
        'consultas' => 'documentos',
        'documentos' => 'documentos',
    ],
];
