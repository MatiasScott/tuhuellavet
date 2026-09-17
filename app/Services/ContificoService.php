<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

class ContificoService
{
    private string $baseUrl;
    private string $apiKey;
    private string $posToken;
    private int $timeout;
    private bool $enabled;

    public function __construct()
    {
        $this->baseUrl = rtrim(
            (string) (
                $_ENV['CONTIFICO_API_URL']
                ?? 'https://api.contifico.com/sistema/api/v2/'
            ),
            '/'
        ) . '/';

        $this->apiKey = trim(
            (string) (
                $_ENV['CONTIFICO_API_KEY']
                ?? ''
            )
        );

        $this->posToken = trim(
            (string) (
                $_ENV['CONTIFICO_POS_TOKEN']
                ?? ''
            )
        );

        $this->timeout = max(
            5,
            (int) (
                $_ENV['CONTIFICO_TIMEOUT']
                ?? 30
            )
        );

        $this->enabled = filter_var(
            $_ENV['CONTIFICO_ENABLED']
                ?? false,
            FILTER_VALIDATE_BOOLEAN
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Configuración
    |--------------------------------------------------------------------------
    */

    public function isConfigured(): bool
    {
        return $this->enabled
            && $this->baseUrl !== ''
            && $this->apiKey !== ''
            && $this->posToken !== '';
    }


    private function ensureConfigured(): void
    {
        if (!$this->enabled) {
            throw new RuntimeException(
                'La integración con Contífico está deshabilitada.'
            );
        }

        if ($this->apiKey === '') {
            throw new RuntimeException(
                'CONTIFICO_API_KEY no está configurado.'
            );
        }

        if ($this->posToken === '') {
            throw new RuntimeException(
                'CONTIFICO_POS_TOKEN no está configurado.'
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | Cliente HTTP
    |--------------------------------------------------------------------------
    */

    private function request(
        string $method,
        string $endpoint,
        array $query = [],
        ?array $payload = null
    ): array {
        $this->ensureConfigured();

        $endpoint = ltrim(
            $endpoint,
            '/'
        );

        $url = $this->baseUrl
            . $endpoint;

        if (!empty($query)) {
            $url .= '?'
                . http_build_query(
                    $query,
                    '',
                    '&',
                    PHP_QUERY_RFC3986
                );
        }

        $curl = curl_init();

        if ($curl === false) {
            throw new RuntimeException(
                'No fue posible inicializar cURL.'
            );
        }

        $headers = [
            'AUTHORIZATION: ' . $this->apiKey,
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        $options = [
            CURLOPT_URL
                => $url,

            CURLOPT_RETURNTRANSFER
                => true,

            CURLOPT_CUSTOMREQUEST
                => strtoupper($method),

            CURLOPT_HTTPHEADER
                => $headers,

            CURLOPT_CONNECTTIMEOUT
                => 10,

            CURLOPT_TIMEOUT
                => $this->timeout,

            CURLOPT_FOLLOWLOCATION
                => false,
        ];

        if ($payload !== null) {
            $json = json_encode(
                $payload,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            );

            if ($json === false) {
                throw new RuntimeException(
                    'No fue posible convertir la petición a JSON.'
                );
            }

            $options[
                CURLOPT_POSTFIELDS
            ] = $json;
        }

        curl_setopt_array(
            $curl,
            $options
        );

        $response = curl_exec(
            $curl
        );

        if ($response === false) {
            $error = curl_error(
                $curl
            );

            curl_close(
                $curl
            );

            throw new RuntimeException(
                'Error de conexión con Contífico: '
                . $error
            );
        }

        $statusCode = (int)
            curl_getinfo(
                $curl,
                CURLINFO_HTTP_CODE
            );

        curl_close(
            $curl
        );

        $decoded = null;

        if ($response !== '') {
            $decoded = json_decode(
                $response,
                true
            );
        }

        /*
         * Algunas respuestas podrían no
         * contener JSON válido.
         */
        if (
            $response !== ''
            && $decoded === null
            && json_last_error()
                !== JSON_ERROR_NONE
        ) {
            $decoded = [
                'raw' => $response,
            ];
        }

        if (
            $statusCode < 200
            || $statusCode >= 300
        ) {
            throw new RuntimeException(
                $this->buildApiError(
                    $statusCode,
                    $decoded
                )
            );
        }

        return [
            'status' => $statusCode,

            'data' => $decoded,

            'raw' => $response,
        ];
    }


    private function buildApiError(
        int $statusCode,
        mixed $response
    ): string {
        $message =
            'Contífico respondió HTTP '
            . $statusCode;

        if (is_array($response)) {
            $possibleMessages = [
                $response['detail']
                    ?? null,

                $response['message']
                    ?? null,

                $response['error']
                    ?? null,

                $response['non_field_errors'][0]
                    ?? null,
            ];

            foreach (
                $possibleMessages
                as $possibleMessage
            ) {
                if (
                    is_string(
                        $possibleMessage
                    )
                    && trim(
                        $possibleMessage
                    ) !== ''
                ) {
                    return $message
                        . ': '
                        . $possibleMessage;
                }
            }

            $json = json_encode(
                $response,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
            );

            if ($json !== false) {
                return $message
                    . ': '
                    . $json;
            }
        }

        return $message;
    }


    /*
    |--------------------------------------------------------------------------
    | Prueba segura de conexión
    |--------------------------------------------------------------------------
    |
    | Solo realiza una consulta GET.
    | NO crea personas.
    | NO crea documentos.
    | NO emite facturas.
    |
    */

    public function testConnection(): array
    {
        return $this->request(
            'GET',
            'persona/',
            [
                'page' => 1,
            ]
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Personas
    |--------------------------------------------------------------------------
    */

    public function getPeople(
        int $page = 1
    ): array {
        return $this->request(
            'GET',
            'persona/',
            [
                'page' => max(
                    1,
                    $page
                ),
            ]
        );
    }


    public function getPerson(
        string $integrationId
    ): array {
        return $this->request(
            'GET',
            'persona/'
                . rawurlencode(
                    $integrationId
                )
                . '/'
        );
    }


    public function createPerson(
        array $data
    ): array {
        return $this->request(
            'POST',
            'persona/',
            [
                'pos'
                    => $this->posToken,
            ],
            $data
        );
    }


    public function updatePerson(
        string $integrationId,
        array $data
    ): array {
        return $this->request(
            'PUT',
            'persona/'
                . rawurlencode(
                    $integrationId
                )
                . '/',
            [],
            $data
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Productos / servicios
    |--------------------------------------------------------------------------
    */

    public function getProducts(
        int $page = 1
    ): array {
        return $this->request(
            'GET',
            'producto/',
            [
                'page'
                    => max(
                        1,
                        $page
                    ),
            ]
        );
    }


    public function getProduct(
        string $integrationId
    ): array {
        return $this->request(
            'GET',
            'producto/'
                . rawurlencode(
                    $integrationId
                )
                . '/'
        );
    }


    public function createProduct(
        array $data
    ): array {
        return $this->request(
            'POST',
            'producto/',
            [
                'pos'
                    => $this->posToken,
            ],
            $data
        );
    }


    public function updateProduct(
        string $integrationId,
        array $data
    ): array {
        return $this->request(
            'PUT',
            'producto/'
                . rawurlencode(
                    $integrationId
                )
                . '/',
            [],
            $data
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Documentos
    |--------------------------------------------------------------------------
    */

    public function getDocuments(
        int $page = 1
    ): array {
        return $this->request(
            'GET',
            'documento/',
            [
                'page'
                    => max(
                        1,
                        $page
                    ),
            ]
        );
    }


    public function getDocument(
        string $integrationId
    ): array {
        return $this->request(
            'GET',
            'documento/'
                . rawurlencode(
                    $integrationId
                )
                . '/'
        );
    }


    /*
     * IMPORTANTE:
     *
     * Este método puede crear un documento
     * real en Contífico.
     *
     * No lo llamaremos desde testConnection().
     */
    public function createDocument(
        array $data
    ): array {
        return $this->request(
            'POST',
            'documento/',
            [],
            $data
        );
    }


    public function updateDocument(
        string $integrationId,
        array $data
    ): array {
        return $this->request(
            'PUT',
            'documento/'
                . rawurlencode(
                    $integrationId
                ),
            [],
            $data
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Formas de pago de documento
    |--------------------------------------------------------------------------
    */

    public function getDocumentPaymentMethods(
        string $integrationId
    ): array {
        return $this->request(
            'GET',
            'documento/'
                . rawurlencode(
                    $integrationId
                )
                . '/forma_pago'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Cola de documentos fiscales
    |--------------------------------------------------------------------------
    */

    public function processPending(
        int $limit = 20
    ): array {
        $db = Database::connection();

        $limit = max(
            1,
            min(
                $limit,
                100
            )
        );

        $stmt = $db->prepare(
            "
            SELECT
                cd.*,
                df.venta_id

            FROM contifico_documentos cd

            INNER JOIN documentos_fiscales df
                ON df.id =
                    cd.documento_fiscal_id

            WHERE cd.estado =
                'PENDIENTE'

            ORDER BY cd.id

            LIMIT {$limit}
            "
        );

        $stmt->execute();

        $rows = $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

        $sent = 0;
        $failed = 0;

        foreach ($rows as $row) {
            try {
                /*
                 * TODAVÍA NO emitimos automáticamente.
                 *
                 * En el siguiente paso construiremos
                 * el payload desde:
                 *
                 * ventas
                 * venta_detalles
                 * propietarios
                 * documentos_fiscales
                 *
                 * y entonces llamaremos:
                 *
                 * $this->createDocument($payload);
                 */

                throw new RuntimeException(
                    'Documento pendiente de construir y validar antes de emisión.'
                );

            } catch (Throwable $e) {
                $update = $db->prepare(
                    '
                    UPDATE contifico_documentos

                    SET
                        intentos =
                            intentos + 1,

                        ultimo_error =
                            :error

                    WHERE id = :id
                    '
                );

                $update->execute([
                    'error'
                        => $e->getMessage(),

                    'id'
                        => $row['id'],
                ]);

                $failed++;
            }
        }

        return [
            'processed'
                => count($rows),

            'sent'
                => $sent,

            'failed'
                => $failed,
        ];
    }
}