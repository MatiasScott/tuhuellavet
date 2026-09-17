<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Ruta raíz temporal para cargar la aplicación
|--------------------------------------------------------------------------
|
| No definimos BASE_PATH aquí porque bootstrap/app.php ya es responsable
| de definir esa constante para toda la aplicación.
|
*/

$projectRoot = dirname(__DIR__);

require $projectRoot
    . '/vendor/autoload.php';


/*
|--------------------------------------------------------------------------
| Bootstrap real de Tu Huella Vet
|--------------------------------------------------------------------------
*/

require $projectRoot
    . '/bootstrap/app.php';


/*
|--------------------------------------------------------------------------
| Protección del entorno QA
|--------------------------------------------------------------------------
|
| Por ahora utilizamos la base de desarrollo configurada en .env.
| Los tests nunca deben ejecutarse contra producción.
|
*/

$appEnv = strtolower(
    (string) (
        $_ENV['APP_ENV']
        ?? getenv('APP_ENV')
        ?: 'development'
    )
);

if (
    in_array(
        $appEnv,
        [
            'production',
            'prod',
        ],
        true
    )
) {
    throw new RuntimeException(
        'QA BLOQUEADO: PHPUnit no puede ejecutarse con APP_ENV=production.'
    );
}

$databaseName = (string) ($_ENV['DB_DATABASE'] ?? '');

if (
    $appEnv !== 'testing'
    || $databaseName !== 'tuhuellavet_qa'
) {
    throw new RuntimeException(
        'QA BLOQUEADO: PHPUnit requiere APP_ENV=testing y DB_DATABASE=tuhuellavet_qa.'
    );
}