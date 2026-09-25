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

            $options[CURLOPT_POSTFIELDS] = $json;
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

    public function searchPeople(
        string $search,
        int $page = 1
    ): array {
        $search = trim($search);

        if ($search === '') {
            throw new RuntimeException(
                'El criterio de búsqueda de la persona es obligatorio.'
            );
        }

        return $this->request(
            'GET',
            'persona/',
            [
                'search' => $search,
                'page' => max(1, $page),
            ]
        );
    }

    public function findPersonByIdentification(
        string $identification
    ): ?array {
        $identification = preg_replace(
            '/\s+/',
            '',
            trim($identification)
        );

        if ($identification === '') {
            throw new RuntimeException(
                'La identificación es obligatoria para buscar la persona en Contífico.'
            );
        }

        $response = $this->searchPeople(
            $identification
        );

        $data = $response['data'] ?? [];

        if (!is_array($data)) {
            return null;
        }

        /*
     * Contífico podría devolver directamente
     * el arreglo o una respuesta paginada.
     */
        if (
            isset($data['results'])
            && is_array($data['results'])
        ) {
            $people = $data['results'];
        } elseif (array_is_list($data)) {
            $people = $data;
        } elseif (isset($data['id'])) {
            $people = [$data];
        } else {
            $people = [];
        }

        foreach ($people as $person) {
            if (!is_array($person)) {
                continue;
            }

            $cedula = preg_replace(
                '/\s+/',
                '',
                trim((string) ($person['cedula'] ?? ''))
            );

            $ruc = preg_replace(
                '/\s+/',
                '',
                trim((string) ($person['ruc'] ?? ''))
            );

            if (
                $cedula === $identification
                || $ruc === $identification
            ) {
                return $person;
            }
        }

        return null;
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
        $integrationId = trim($integrationId);

        if ($integrationId === '') {
            throw new RuntimeException(
                'El identificador de la persona de Contífico es obligatorio.'
            );
        }

        return $this->request(
            'PUT',
            'persona/'
                . rawurlencode($integrationId)
                . '/',
            [
                'pos' => $this->posToken,
            ],
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

    public function ensurePerson(
        int $fiscalDataId
    ): array {
        if ($fiscalDataId <= 0) {
            throw new RuntimeException(
                'Los datos fiscales del cliente son obligatorios.'
            );
        }

        $db = Database::connection();

        /*
     * 1. Obtener los datos fiscales locales.
     */
        $stmt = $db->prepare(
            '
        SELECT
            pdf.id,
            pdf.propietario_id,
            pdf.identificacion,
            pdf.razon_social,
            pdf.direccion,
            pdf.email,
            pdf.telefono,
            pdf.activo,
            ti.codigo AS tipo_identificacion_codigo

        FROM propietarios_datos_fiscales pdf

        INNER JOIN tipos_identificacion ti
            ON ti.id = pdf.tipo_identificacion_id

        WHERE pdf.id = :id

        LIMIT 1
        '
        );

        $stmt->execute([
            'id' => $fiscalDataId,
        ]);

        $fiscal = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fiscal) {
            throw new RuntimeException(
                'No se encontraron los datos fiscales del cliente.'
            );
        }

        if ((int) $fiscal['activo'] !== 1) {
            throw new RuntimeException(
                'Los datos fiscales seleccionados están inactivos.'
            );
        }

        $identification = trim(
            (string) $fiscal['identificacion']
        );

        if ($identification === '') {
            throw new RuntimeException(
                'La identificación fiscal del cliente está vacía.'
            );
        }


        /*
     * 2. Revisar primero nuestro vínculo local.
     */
        $linkStmt = $db->prepare(
            '
        SELECT *
        FROM contifico_personas
        WHERE datos_fiscales_id = :datos_fiscales_id
        LIMIT 1
        '
        );

        $linkStmt->execute([
            'datos_fiscales_id' => $fiscalDataId,
        ]);

        $link = $linkStmt->fetch(PDO::FETCH_ASSOC);


        /*
        * Si ya tenemos un ID de Contífico guardado,
        * comprobamos que siga existiendo.
        */
        if (
            $link
            && trim((string) ($link['contifico_id'] ?? '')) !== ''
        ) {
            $contificoId = trim(
                (string) $link['contifico_id']
            );

            try {
                $remoteResponse = $this->getPerson(
                    $contificoId
                );

                $remotePerson =
                    $remoteResponse['data']
                    ?? null;

                if (
                    is_array($remotePerson)
                    && !empty($remotePerson['id'])
                ) {
                    $wasClient = filter_var(
                        $remotePerson['es_cliente'] ?? false,
                        FILTER_VALIDATE_BOOLEAN
                    );

                    $remotePerson =
                        $this->ensureRemotePersonIsClient(
                            $remotePerson
                        );

                    $this->savePersonLink(
                        $fiscalDataId,
                        $contificoId,
                        'SINCRONIZADA',
                        null,
                        $remotePerson,
                        null
                    );

                    return [
                        'source' => 'LOCAL_LINK',
                        'created' => false,
                        'client_activated' => !$wasClient,
                        'person' => $remotePerson,
                    ];
                }
            } catch (Throwable $e) {
                /*
         * Si el vínculo guardado ya no puede
         * recuperarse, no creamos inmediatamente
         * otra persona.
         *
         * Continuamos hacia la búsqueda exacta
         * por identificación.
         */
            }
        }


        /*
        * 3. Buscar en Contífico antes de crear.
        */
        $remotePerson =
            $this->findPersonByIdentification(
                $identification
            );

        if ($remotePerson !== null) {
            $remoteId = trim(
                (string) ($remotePerson['id'] ?? '')
            );

            if ($remoteId === '') {
                throw new RuntimeException(
                    'Contífico encontró la persona, pero no devolvió su identificador.'
                );
            }

            $wasClient = filter_var(
                $remotePerson['es_cliente'] ?? false,
                FILTER_VALIDATE_BOOLEAN
            );

            try {
                $remotePerson =
                    $this->ensureRemotePersonIsClient(
                        $remotePerson
                    );

                $this->savePersonLink(
                    $fiscalDataId,
                    $remoteId,
                    'SINCRONIZADA',
                    null,
                    $remotePerson,
                    null
                );

                return [
                    'source' => 'REMOTE_SEARCH',
                    'created' => false,
                    'client_activated' => !$wasClient,
                    'person' => $remotePerson,
                ];
            } catch (Throwable $e) {
                $this->savePersonLink(
                    $fiscalDataId,
                    $remoteId,
                    'ERROR',
                    null,
                    $remotePerson,
                    $e->getMessage()
                );

                throw $e;
            }
        }


        /*
        * 4. Si no existe, construir el payload.
        *
        * Por ahora únicamente CEDULA está
        * completamente definido.
        */
        $identificationType = strtoupper(
            trim(
                (string)
                $fiscal['tipo_identificacion_codigo']
            )
        );

        if ($identificationType !== 'CEDULA') {
            throw new RuntimeException(
                'La creación automática en Contífico todavía no está habilitada para el tipo de identificación '
                    . $identificationType
                    . '.'
            );
        }


        if (
            $fiscal['razon_social'] === null
            || trim((string) $fiscal['razon_social']) === ''
        ) {
            throw new RuntimeException(
                'La razón social o nombre del cliente es obligatorio para Contífico.'
            );
        }


        $payload = [
            'razon_social'
            => trim((string) $fiscal['razon_social']),

            'tipo'
            => 'N',

            'es_cliente'
            => true,

            'es_proveedor'
            => false,

            'cedula'
            => $identification,
        ];


        /*
     * Campos opcionales.
     */
        $email = trim(
            (string) ($fiscal['email'] ?? '')
        );

        if ($email !== '') {
            $payload['email'] = $email;
        }

        $phone = trim(
            (string) ($fiscal['telefono'] ?? '')
        );

        if ($phone !== '') {
            $payload['telefonos'] = $phone;
        }

        $address = trim(
            (string) ($fiscal['direccion'] ?? '')
        );

        if ($address !== '') {
            $payload['direccion'] = $address;
        }


        /*
     * 5. Crear únicamente después de haber
     * comprobado que no existe.
     */
        try {
            $createdResponse =
                $this->createPerson(
                    $payload
                );

            $createdPerson =
                $createdResponse['data']
                ?? null;

            if (!is_array($createdPerson)) {
                throw new RuntimeException(
                    'Contífico no devolvió una respuesta válida al crear la persona.'
                );
            }

            $remoteId = trim(
                (string) ($createdPerson['id'] ?? '')
            );

            if ($remoteId === '') {
                throw new RuntimeException(
                    'Contífico creó la persona pero no devolvió su identificador.'
                );
            }

            $this->savePersonLink(
                $fiscalDataId,
                $remoteId,
                'SINCRONIZADA',
                $payload,
                $createdPerson,
                null
            );

            return [
                'source' => 'CREATED',
                'created' => true,
                'person' => $createdPerson,
            ];
        } catch (Throwable $e) {

            $this->savePersonLink(
                $fiscalDataId,
                null,
                'ERROR',
                $payload,
                null,
                $e->getMessage()
            );

            throw $e;
        }
    }

    private function savePersonLink(
        int $fiscalDataId,
        ?string $contificoId,
        string $status,
        ?array $requestPayload,
        ?array $responsePayload,
        ?string $error
    ): void {
        $db = Database::connection();

        $requestJson = $requestPayload !== null
            ? json_encode(
                $requestPayload,
                JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
            )
            : null;

        $responseJson = $responsePayload !== null
            ? json_encode(
                $responsePayload,
                JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
            )
            : null;

        $stmt = $db->prepare(
            '
        INSERT INTO contifico_personas (
            datos_fiscales_id,
            contifico_id,
            estado,
            request_payload,
            response_payload,
            ultimo_error,
            sincronizado_at
        )
        VALUES (
            :datos_fiscales_id,
            :contifico_id,
            :estado,
            :request_payload,
            :response_payload,
            :ultimo_error,
            NOW()
        )

        ON DUPLICATE KEY UPDATE
            contifico_id = VALUES(contifico_id),
            estado = VALUES(estado),
            request_payload = VALUES(request_payload),
            response_payload = VALUES(response_payload),
            ultimo_error = VALUES(ultimo_error),
            sincronizado_at = NOW()
        '
        );

        $stmt->execute([
            'datos_fiscales_id' => $fiscalDataId,
            'contifico_id' => $contificoId,
            'estado' => $status,
            'request_payload' => $requestJson,
            'response_payload' => $responseJson,
            'ultimo_error' => $error,
        ]);
    }

    private function buildPersonUpdatePayload(
        array $person,
        array $overrides = []
    ): array {
        $allowed = [
            'ruc',
            'cedula',
            'placa',
            'razon_social',
            'telefonos',
            'direccion',
            'tipo',

            'es_cliente',
            'es_proveedor',
            'es_empleado',
            'es_corporativo',
            'aplicar_cupo',

            'email',
            'es_vendedor',
            'es_extranjero',
            'porcentaje_descuento',

            'adicional1_cliente',
            'adicional2_cliente',
            'adicional3_cliente',
            'adicional4_cliente',

            'adicional1_proveedor',
            'adicional2_proveedor',
            'adicional3_proveedor',
            'adicional4_proveedor',

            'banco_codigo_id',
            'tipo_cuenta',
            'numero_tarjeta',
            'personaasociada_id',
            'nombre_comercial',
            'origen',
            'pvp_default',
            'categoria_id',
            'categoria_nombre',
            'sueldo',
        ];

        $payload = [];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $person)) {
                $payload[$field] = $person[$field];
            }
        }

        foreach ($overrides as $field => $value) {
            if (in_array($field, $allowed, true)) {
                $payload[$field] = $value;
            }
        }

        /*
     * Campos que la documentación marca
     * como obligatorios para actualización.
     */
        if (
            trim((string) ($payload['tipo'] ?? '')) === ''
        ) {
            throw new RuntimeException(
                'Contífico no devolvió el tipo de persona.'
            );
        }

        if (
            trim((string) ($payload['razon_social'] ?? '')) === ''
        ) {
            throw new RuntimeException(
                'Contífico no devolvió la razón social de la persona.'
            );
        }

        if (!array_key_exists('es_cliente', $payload)) {
            throw new RuntimeException(
                'Contífico no devolvió el rol de cliente.'
            );
        }

        if (!array_key_exists('es_proveedor', $payload)) {
            throw new RuntimeException(
                'Contífico no devolvió el rol de proveedor.'
            );
        }

        return $payload;
    }

    private function ensureRemotePersonIsClient(
        array $person
    ): array {
        $integrationId = trim(
            (string) ($person['id'] ?? '')
        );

        if ($integrationId === '') {
            throw new RuntimeException(
                'La persona de Contífico no posee identificador.'
            );
        }

        $isClient = filter_var(
            $person['es_cliente'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        if ($isClient) {
            return $person;
        }

        $payload = $this->buildPersonUpdatePayload(
            $person,
            [
                'es_cliente' => true,
            ]
        );

        $response = $this->updatePerson(
            $integrationId,
            $payload
        );

        $updated = $response['data'] ?? null;

        if (!is_array($updated)) {
            throw new RuntimeException(
                'Contífico no devolvió una respuesta válida al habilitar la persona como cliente.'
            );
        }

        if (
            !filter_var(
                $updated['es_cliente'] ?? false,
                FILTER_VALIDATE_BOOLEAN
            )
        ) {
            throw new RuntimeException(
                'Contífico respondió correctamente, pero la persona no quedó habilitada como cliente.'
            );
        }

        return $updated;
    }
}
