<?php

namespace App\Core;

class App
{
    public function __construct(private array $config) {}

    public function run(): void
    {
        Session::start();

        $router = new Router();

        foreach (
            ['web.php', 'auth.php', 'admin.php', 'cliente.php', 'academico.php']
            as $f
        ) {
            $p = APP_PATH . '/Routes/' . $f;

            if (file_exists($p)) {
                require $p;
            }
        }

        try {
            $router->dispatch(Request::capture());
        } catch (\Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | Registrar error real
            |--------------------------------------------------------------------------
            |
            | El detalle queda únicamente en el log del servidor.
            | Nunca se muestra al usuario en producción.
            |
            */

            error_log(
                sprintf(
                    "[%s] %s in %s:%d\n%s",
                    get_class($e),
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine(),
                    $e->getTraceAsString()
                )
            );

            http_response_code(500);

            /*
            |--------------------------------------------------------------------------
            | Desarrollo
            |--------------------------------------------------------------------------
            */

            if (!empty($this->config['debug'])) {
                echo '<pre>'
                    . htmlspecialchars(
                        (string) $e,
                        ENT_QUOTES,
                        'UTF-8'
                    )
                    . '</pre>';

                return;
            }

            /*
            |--------------------------------------------------------------------------
            | Producción
            |--------------------------------------------------------------------------
            */

            View::render(
                'errors/500',
                [
                    'message' => 'Ocurrió un error interno.',
                ],
                'layouts/auth'
            );
        }
    }
}
