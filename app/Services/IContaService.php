<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;
use PDO;
use App\Core\Database;

class IContaService
{
    private string $apiUrl;
    private string $apiKey;
    private int $timeout;
    private bool $enabled;
    private string $environment;

    public function __construct()
    {
        $this->apiUrl = rtrim(
            (string) (
                $_ENV['ICONTA_API_URL']
                ?? 'https://test.iconta.ec:15443'
            ),
            '/'
        );

        $this->apiKey = preg_replace(
            '/\s+/',
            '',
            (string) ($_ENV['ICONTA_API_KEY'] ?? '')
        ) ?? '';

        $this->timeout = max(
            1,
            (int) ($_ENV['ICONTA_TIMEOUT'] ?? 30)
        );

        $this->enabled = filter_var(
            $_ENV['ICONTA_ENABLED'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        $this->environment = strtolower(
            trim((string) ($_ENV['ICONTA_ENVIRONMENT'] ?? 'test'))
        );
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function environment(): string
    {
        return $this->environment;
    }

    /**
     * Consulta las cuentas contables disponibles en iConta.
     */
    public function searchAccountingAccounts(
        int $records = 100,
        int $page = 1
    ): array {
        if ($records <= 0) {
            throw new RuntimeException(
                'La cantidad de registros debe ser mayor a cero.'
            );
        }

        if ($page <= 0) {
            throw new RuntimeException(
                'El número de página debe ser mayor a cero.'
            );
        }

        $response = $this->request(
            'POST',
            '/api/external/cuentacontable/consulta',
            [
                'NumeroRegistros' => $records,
                'NumeroPagina' => $page,
            ]
        );

        $accounts = $response['listaCuentaContable']
            ?? $response['ListaCuentaContable']
            ?? null;

        if (!is_array($accounts)) {
            throw new RuntimeException(
                'iConta no devolvió la lista de cuentas contables.'
            );
        }

        return $response;
    }

    /**
     * Consulta las formas de pago disponibles en iConta.
     */
    public function searchPaymentMethods(
        int $records = 100,
        int $page = 1
    ): array {
        $this->assertConfiguration();

        $records = max(1, $records);
        $page = max(1, $page);

        $response = $this->request(
            'POST',
            '/api/external/catalogo/compra',
            [
                'NumeroRegistros' => $records,
                'NumeroPagina' => $page,
            ]
        );

        $paymentMethods = $response['listaFormaPago']
            ?? $response['ListaFormaPago']
            ?? null;

        if (!is_array($paymentMethods)) {
            throw new RuntimeException(
                'iConta no devolvió la lista de formas de pago.'
            );
        }

        return $paymentMethods;
    }

    /**
     * Consulta las formas de pago disponibles para ventas en iConta.
     */
    public function searchSalePaymentMethods(): array
    {
        $this->assertConfiguration();

        $response = $this->request(
            'POST',
            '/api/external/catalogo/venta',
            [
                'RetornaProductos' => false,
                'RetornaFormaPago' => true,
            ]
        );

        $paymentMethods = $response['listaFormaPago']
            ?? $response['ListaFormaPago']
            ?? null;

        if (!is_array($paymentMethods)) {
            throw new RuntimeException(
                'iConta no devolvió la lista de formas de pago para ventas.'
            );
        }

        return $paymentMethods;
    }

    /**
     * Consulta las tarifas de IVA disponibles en iConta.
     */
    public function searchVatRates(
        int $records = 100,
        int $page = 1
    ): array {
        $this->assertConfiguration();

        $records = max(1, $records);
        $page = max(1, $page);

        $response = $this->request(
            'POST',
            '/api/external/impuestovaloragregado/consulta',
            [
                'NumeroRegistros' => $records,
                'NumeroPagina' => $page,
            ]
        );

        $rates = $response['listaImpuestoValorAgregado']
            ?? $response['ListaImpuestoValorAgregado']
            ?? null;

        if (!is_array($rates)) {
            throw new RuntimeException(
                'iConta no devolvió la lista de impuestos de valor agregado.'
            );
        }

        return $rates;
    }

    /**
     * Consulta los puntos de emisión habilitados en iConta
     * para el usuario/empresa asociados al token actual.
     */
    public function searchEmissionPoints(
        int $records = 100,
        int $page = 1
    ): array {
        $this->assertConfiguration();

        $records = max(1, $records);
        $page = max(1, $page);

        $response = $this->request(
            'POST',
            '/api/external/producto/puntoemision/consultar',
            [
                'NumeroRegistros' => $records,
                'NumeroPagina' => $page,
            ]
        );

        $emissionPoints = $response['ListaPuntoEmision']
            ?? $response['listaPuntoEmision']
            ?? null;

        if (!is_array($emissionPoints)) {
            throw new RuntimeException(
                'iConta no devolvió la lista de puntos de emisión.'
            );
        }

        return $emissionPoints;
    }

    /**
     * Consulta personas registradas en iConta.
     *
     * No crea ni modifica información.
     */
    public function searchPersons(
        string $identification = '',
        string $name = '',
        string $detail = '',
        string $value = '',
        int $records = 25,
        int $page = 1
    ): array {
        $records = max(1, min($records, 100));
        $page = max(1, $page);

        return $this->request(
            'POST',
            '/api/external/persona/consulta',
            [
                'Identificacion' => trim($identification),
                'NombrePersona'  => trim($name),
                'Detalle'        => trim($detail),
                'Valor'          => trim($value),
                'NumeroRegistros' => $records,
                'NumeroPagina'    => $page,
            ]
        );
    }

    /**
     * Busca una persona por identificación.
     */
    public function findPersonByIdentification(
        string $identification
    ): ?array {
        $identification = trim($identification);

        if ($identification === '') {
            throw new RuntimeException(
                'La identificación es obligatoria.'
            );
        }

        if (mb_strlen($identification) > 20) {
            throw new RuntimeException(
                'La identificación no puede superar los 20 caracteres.'
            );
        }

        $response = $this->searchPersons(
            $identification,
            '',
            '',
            '',
            25,
            1
        );

        $persons = $response['listaPersona'] ?? [];

        if (!is_array($persons) || $persons === []) {
            return null;
        }

        /*
         * Aunque iConta recibe Identificacion como filtro,
         * comprobamos que el resultado corresponda exactamente
         * con la identificación solicitada.
         */
        foreach ($persons as $item) {
            $remoteIdentification = trim(
                (string) (
                    $item['persona']['identificacion']
                    ?? ''
                )
            );

            if ($remoteIdentification === $identification) {
                return $item;
            }
        }

        return null;
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
     * 1. Obtener datos fiscales + datos generales
     *    del propietario necesarios para iConta.
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

            ti.codigo AS tipo_identificacion_codigo,

            p.es_persona_natural,
            p.nombres,
            p.segundo_nombre,
            p.apellidos,
            p.apellido_materno,
            p.codigo_pais,
            p.codigo_provincia,
            p.codigo_canton,
            p.codigo_parroquia,
            p.referencia

        FROM propietarios_datos_fiscales pdf

        INNER JOIN propietarios p
            ON p.id = pdf.propietario_id

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
            (string) ($fiscal['identificacion'] ?? '')
        );

        if ($identification === '') {
            throw new RuntimeException(
                'La identificación fiscal del cliente está vacía.'
            );
        }

        /*
     * Por ahora detenemos aquí el flujo.
     *
     * El siguiente bloque realizará:
     * - mapeo del tipo de identificación;
     * - búsqueda en iconta_personas;
     * - búsqueda remota en iConta;
     * - creación como cliente cuando no exista.
     */
        /*
 * 2. Revisar primero nuestro vínculo local con iConta.
 */
        $linkStmt = $db->prepare(
            '
    SELECT
        id,
        datos_fiscales_id,
        iconta_id_persona,
        iconta_id_cliente,
        estado,
        request_payload,
        response_payload,
        intentos,
        ultimo_error,
        sincronizado_at

    FROM iconta_personas

    WHERE datos_fiscales_id = :datos_fiscales_id

    LIMIT 1
    '
        );

        $linkStmt->execute([
            'datos_fiscales_id' => $fiscalDataId,
        ]);

        $link = $linkStmt->fetch(PDO::FETCH_ASSOC);

        /*
 * 3. Buscar la persona en iConta por identificación.
 *
 * Aunque exista un vínculo local, verificamos contra
 * iConta antes de decidir crear o actualizar.
 */
        $remotePerson = $this->findPersonByIdentification(
            $identification
        );

        if ($remotePerson !== null) {
            $personData = $remotePerson['persona'] ?? null;

            if (!is_array($personData)) {
                throw new RuntimeException(
                    'iConta encontró la persona, pero no devolvió el objeto Persona.'
                );
            }

            $remotePersonId = (int) (
                $personData['idPersona']
                ?? $personData['IdPersona']
                ?? 0
            );

            if ($remotePersonId <= 0) {
                throw new RuntimeException(
                    'iConta encontró la persona, pero no devolvió IdPersona.'
                );
            }

            /*
     * Verificar si la persona encontrada ya está
     * registrada como cliente en iConta.
     */
            $isClient = $this->isTrue(
                $remotePerson['esCliente']
                    ?? $remotePerson['EsCliente']
                    ?? false
            );

            $clientData = $remotePerson['cliente']
                ?? $remotePerson['Cliente']
                ?? null;

            $remoteClientId = null;

            if (is_array($clientData)) {
                $clientId = (int) (
                    $clientData['id']
                    ?? $clientData['Id']
                    ?? 0
                );

                if ($clientId > 0) {
                    $remoteClientId = $clientId;
                }
            }

            /*
     * Si iConta indica que es cliente, debe existir
     * también un identificador válido del cliente.
     */
            if ($isClient && $remoteClientId === null) {
                throw new RuntimeException(
                    'iConta encontró la persona como cliente, pero no devolvió el IdCliente.'
                );
            }

            /*
            * Si la persona ya es cliente, únicamente
            * actualizamos nuestro vínculo local.
            */
            if ($isClient) {
                $this->savePersonLink(
                    $fiscalDataId,
                    $remotePersonId,
                    $remoteClientId,
                    'SINCRONIZADA',
                    null,
                    $remotePerson,
                    null
                );

                return [
                    'source' => 'REMOTE_SEARCH',
                    'created' => false,
                    'client' => true,
                    'fiscal' => $fiscal,
                    'person' => $remotePerson,
                    'iconta_id_persona' => $remotePersonId,
                    'iconta_id_cliente' => $remoteClientId,
                ];
            }

            /*
 * Una persona existente puede tener otros roles
 * administrativos dentro de iConta.
 *
 * Nuestro payload de Tu Huella Vet está diseñado
 * exclusivamente para clientes y no debe modificar
 * accidentalmente un proveedor o vendedor existente.
 */
            $isProvider = $this->isTrue(
                $remotePerson['esProveedor']
                    ?? $remotePerson['EsProveedor']
                    ?? false
            );

            $isSeller = $this->isTrue(
                $remotePerson['esVendedor']
                    ?? $remotePerson['EsVendedor']
                    ?? false
            );

            if ($isProvider || $isSeller) {
                $existingRoles = [];

                if ($isProvider) {
                    $existingRoles[] = 'PROVEEDOR';
                }

                if ($isSeller) {
                    $existingRoles[] = 'VENDEDOR';
                }

                $message =
                    'La persona ya existe en iConta con el rol '
                    . implode(' y ', $existingRoles)
                    . ' y todavía no está registrada como cliente. '
                    . 'La conversión automática fue detenida para evitar modificar sus roles existentes.';

                $this->savePersonLink(
                    $fiscalDataId,
                    $remotePersonId,
                    null,
                    'REQUIERE_REVISION',
                    null,
                    $remotePerson,
                    $message
                );

                throw new RuntimeException(
                    $message
                );
            }

            /*
            * La persona existe en iConta, pero todavía
            * no está registrada como cliente.
            *
            * Utilizamos el IdPersona existente para modificar
            * esa misma persona y habilitarla como cliente.
            */
            $savePayload = $this->buildPersonSavePayload(
                $fiscal,
                $remotePersonId
            );

            try {
                $saveResponse = $this->savePerson(
                    $savePayload
                );

                $savedPerson = $saveResponse['Persona']
                    ?? $saveResponse['persona']
                    ?? null;

                if (!is_array($savedPerson)) {
                    throw new RuntimeException(
                        'iConta modificó el registro, pero no devolvió el objeto Persona.'
                    );
                }

                $savedPersonId = (int) (
                    $savedPerson['IdPersona']
                    ?? $savedPerson['idPersona']
                    ?? 0
                );

                $savedClientId = (int) (
                    $saveResponse['IdCliente']
                    ?? $saveResponse['idCliente']
                    ?? 0
                );

                if ($savedPersonId <= 0) {
                    throw new RuntimeException(
                        'iConta no devolvió un IdPersona válido después de modificar la persona.'
                    );
                }

                /*
     * Seguridad adicional:
     * la modificación debe conservar exactamente
     * el mismo IdPersona encontrado previamente.
     */
                if ($savedPersonId !== $remotePersonId) {
                    throw new RuntimeException(
                        'iConta devolvió un IdPersona diferente al modificar la persona existente.'
                    );
                }

                if ($savedClientId <= 0) {
                    throw new RuntimeException(
                        'iConta no devolvió un IdCliente válido después de habilitar la persona como cliente.'
                    );
                }

                $this->savePersonLink(
                    $fiscalDataId,
                    $savedPersonId,
                    $savedClientId,
                    'SINCRONIZADA',
                    $savePayload,
                    $saveResponse,
                    null
                );

                return [
                    'source' => 'REMOTE_UPDATED_TO_CLIENT',
                    'created' => false,
                    'client' => true,
                    'fiscal' => $fiscal,
                    'person' => $savedPerson,
                    'response' => $saveResponse,
                    'iconta_id_persona' => $savedPersonId,
                    'iconta_id_cliente' => $savedClientId,
                ];
            } catch (\Throwable $e) {
                /*
     * Conservamos el IdPersona porque sabemos
     * que la persona sí existe en iConta.
     */
                $this->savePersonLink(
                    $fiscalDataId,
                    $remotePersonId,
                    null,
                    'ERROR',
                    $savePayload,
                    null,
                    $e->getMessage()
                );

                throw $e;
            }
        }

        /*
 * 4. La persona no existe en iConta.
 *
 * Construir el payload para crearla directamente
 * como cliente.
 */
        $savePayload = $this->buildPersonSavePayload(
            $fiscal,
            0
        );

        /*
        * 5. Crear la persona directamente como cliente.
        */
        try {
            $saveResponse = $this->savePerson(
                $savePayload
            );

            $savedPerson = $saveResponse['Persona']
                ?? $saveResponse['persona']
                ?? null;

            if (!is_array($savedPerson)) {
                throw new RuntimeException(
                    'iConta creó el registro, pero no devolvió el objeto Persona.'
                );
            }

            $icontaPersonId = (int) (
                $savedPerson['IdPersona']
                ?? $savedPerson['idPersona']
                ?? 0
            );

            $icontaClientId = (int) (
                $saveResponse['IdCliente']
                ?? $saveResponse['idCliente']
                ?? 0
            );

            if ($icontaPersonId <= 0) {
                throw new RuntimeException(
                    'iConta no devolvió un IdPersona válido después de guardar.'
                );
            }

            if ($icontaClientId <= 0) {
                throw new RuntimeException(
                    'iConta no devolvió un IdCliente válido después de guardar.'
                );
            }

            $this->savePersonLink(
                $fiscalDataId,
                $icontaPersonId,
                $icontaClientId,
                'SINCRONIZADA',
                $savePayload,
                $saveResponse,
                null
            );

            return [
                'source' => 'CREATED',
                'created' => true,
                'client' => true,
                'fiscal' => $fiscal,
                'person' => $savedPerson,
                'response' => $saveResponse,
                'iconta_id_persona' => $icontaPersonId,
                'iconta_id_cliente' => $icontaClientId,
            ];
        } catch (\Throwable $e) {
            $this->savePersonLink(
                $fiscalDataId,
                null,
                null,
                'ERROR',
                $savePayload,
                null,
                $e->getMessage()
            );

            throw $e;
        }
    }

    private function buildPersonSavePayload(
        array $fiscal,
        int $personId = 0
    ): array {
        $identification = trim(
            (string) ($fiscal['identificacion'] ?? '')
        );

        if ($identification === '') {
            throw new RuntimeException(
                'La identificación fiscal del cliente está vacía.'
            );
        }

        /*
     * Para sincronizar correctamente con iConta debemos
     * conocer explícitamente si el propietario es una
     * persona natural o jurídica.
     *
     * No convertimos NULL automáticamente en false porque
     * eso podría transformar un RUC sin clasificar en una
     * persona jurídica por accidente.
     */
        if (
            !array_key_exists('es_persona_natural', $fiscal)
            || $fiscal['es_persona_natural'] === null
            || $fiscal['es_persona_natural'] === ''
        ) {
            throw new RuntimeException(
                'Debe indicar si el propietario es una persona natural o jurídica antes de sincronizar con iConta.'
            );
        }

        $isNatural = (int) $fiscal['es_persona_natural'] === 1;

        $localIdentificationType = strtoupper(
            trim(
                (string) (
                    $fiscal['tipo_identificacion_codigo']
                    ?? ''
                )
            )
        );

        if ($localIdentificationType === '') {
            throw new RuntimeException(
                'El tipo de identificación fiscal del cliente está vacío.'
            );
        }

        $this->validateIdentificationType(
            $localIdentificationType,
            $isNatural
        );

        $icontaIdentificationType =
            $this->mapIdentificationType(
                $localIdentificationType,
                $isNatural
            );

        $personPayload = [
            'IdPersona' => $personId,

            'CodigoTipoIdentificacion'
            => $icontaIdentificationType,

            'Identificacion'
            => $identification,

            'PrimerNombre'
            => trim(
                (string) ($fiscal['nombres'] ?? '')
            ),

            'SegundoNombre'
            => trim(
                (string) ($fiscal['segundo_nombre'] ?? '')
            ),

            'ApellidoPaterno'
            => trim(
                (string) ($fiscal['apellidos'] ?? '')
            ),

            'ApellidoMaterno'
            => trim(
                (string) ($fiscal['apellido_materno'] ?? '')
            ),

            'RazonSocial'
            => trim(
                (string) ($fiscal['razon_social'] ?? '')
            ),

            'NombreComercial'
            => '',

            'Email'
            => trim(
                (string) ($fiscal['email'] ?? '')
            ),

            'Direccion'
            => trim(
                (string) ($fiscal['direccion'] ?? '')
            ),

            'EsNatural'
            => $isNatural,

            'CodPais'
            => trim(
                (string) ($fiscal['codigo_pais'] ?? '')
            ),

            'CodProvincia'
            => trim(
                (string) ($fiscal['codigo_provincia'] ?? '')
            ),

            'CodCanton'
            => trim(
                (string) ($fiscal['codigo_canton'] ?? '')
            ),

            'CodParroquia'
            => trim(
                (string) ($fiscal['codigo_parroquia'] ?? '')
            ),

            'Telefono'
            => trim(
                (string) ($fiscal['telefono'] ?? '')
            ),

            'Referencia'
            => trim(
                (string) ($fiscal['referencia'] ?? '')
            ),

            'Activo'
            => true,

            'ListaDatosAdicionales'
            => [],
        ];

        return [
            'EsVendedor' => false,
            'EsProveedor' => false,
            'EsCliente' => true,

            'Persona' => $personPayload,

            'Cliente' => [
                'Id' => 0,

                /*
             * Cuenta de clientes comerciales confirmada
             * mediante una creación real en iConta.
             */
                'CodigoCuentaContable' => '11251',

                /*
             * Segmento:
             * 5347 = SIN SEGMENTAR.
             */
                'IdSegmento' => 5347,

                /*
             * Estado activo de cliente en iConta.
             */
                'CodigoEstado' => 'A',

                /*
             * Tu Huella Vet no administra información
             * bancaria de sus clientes.
             */
                'TieneBanco' => false,
                'CodigoInstitucionBancaria' => '',
                'CodigoTipoCuenta' => '',
                'NumeroCuenta' => '',
            ],
        ];
    }

    private function savePersonLink(
        int $fiscalDataId,
        ?int $icontaPersonId,
        ?int $icontaClientId,
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
        INSERT INTO iconta_personas (
            datos_fiscales_id,
            iconta_id_persona,
            iconta_id_cliente,
            estado,
            request_payload,
            response_payload,
            intentos,
            ultimo_error,
            sincronizado_at
        )
        VALUES (
            :datos_fiscales_id,
            :iconta_id_persona,
            :iconta_id_cliente,
            :estado,
            :request_payload,
            :response_payload,
            :intentos,
            :ultimo_error,
            NOW()
        )

        ON DUPLICATE KEY UPDATE
            iconta_id_persona = VALUES(iconta_id_persona),
            iconta_id_cliente = VALUES(iconta_id_cliente),
            estado = VALUES(estado),
            request_payload = VALUES(request_payload),
            response_payload = VALUES(response_payload),
            intentos = intentos + 1,
            ultimo_error = VALUES(ultimo_error),
            sincronizado_at = NOW()
        '
        );

        $stmt->execute([
            'datos_fiscales_id' => $fiscalDataId,
            'iconta_id_persona' => $icontaPersonId,
            'iconta_id_cliente' => $icontaClientId,
            'estado' => $status,
            'request_payload' => $requestJson,
            'response_payload' => $responseJson,
            'intentos' => 1,
            'ultimo_error' => $error,
        ]);
    }

    private function mapIdentificationType(
        string $localType,
        bool $isNatural
    ): string {
        $localType = strtoupper(
            trim($localType)
        );

        return match ($localType) {
            'CEDULA' => 'C',

            'RUC' => $isNatural
                ? 'N'
                : 'R',

            'PASAPORTE' => 'P',

            'CONSUMIDOR_FINAL' => 'X',

            default => throw new RuntimeException(
                'El tipo de identificación '
                    . $localType
                    . ' no posee equivalencia configurada para iConta.'
            ),
        };
    }

    private function validateIdentificationType(
        string $localType,
        bool $isNatural
    ): void {
        $localType = strtoupper(
            trim($localType)
        );

        if ($localType === 'CEDULA' && !$isNatural) {
            throw new RuntimeException(
                'Una identificación tipo CÉDULA debe corresponder a una persona natural.'
            );
        }

        if ($localType === 'PASAPORTE' && !$isNatural) {
            throw new RuntimeException(
                'Una identificación tipo PASAPORTE debe corresponder a una persona natural.'
            );
        }

        if (
            $localType === 'CONSUMIDOR_FINAL'
            && $isNatural
        ) {
            throw new RuntimeException(
                'CONSUMIDOR FINAL no debe registrarse como persona natural en iConta.'
            );
        }
    }

    public function savePerson(array $data): array
    {
        if ($data === []) {
            throw new RuntimeException(
                'Los datos de la persona son obligatorios.'
            );
        }

        if (!isset($data['Persona']) || !is_array($data['Persona'])) {
            throw new RuntimeException(
                'El objeto Persona es obligatorio para registrar en iConta.'
            );
        }

        $identification = trim(
            (string) ($data['Persona']['Identificacion'] ?? '')
        );

        if ($identification === '') {
            throw new RuntimeException(
                'La identificación de la persona es obligatoria.'
            );
        }

        if (mb_strlen($identification) > 20) {
            throw new RuntimeException(
                'La identificación no puede superar los 20 caracteres.'
            );
        }

        if (!array_key_exists('EsCliente', $data)) {
            throw new RuntimeException(
                'EsCliente es obligatorio.'
            );
        }

        if (!array_key_exists('EsProveedor', $data)) {
            throw new RuntimeException(
                'EsProveedor es obligatorio.'
            );
        }

        if (!array_key_exists('EsVendedor', $data)) {
            throw new RuntimeException(
                'EsVendedor es obligatorio.'
            );
        }

        $response = $this->request(
            'POST',
            '/api/external/persona/guarda',
            $data
        );

        $person = $response['Persona']
            ?? $response['persona']
            ?? null;

        if (!is_array($person)) {
            throw new RuntimeException(
                'iConta no devolvió el objeto Persona después de guardar la persona.'
            );
        }

        /*
        * Normalizar la respuesta internamente.
        *
        * Algunas respuestas de iConta utilizan "Persona"
        * y otras "persona". Desde este punto nuestro servicio
        * trabajará siempre con la clave "Persona".
        */
        $response['Persona'] = $person;

        unset($response['persona']);

        $remotePersonId = (int) (
            $response['Persona']['IdPersona']
            ?? $response['Persona']['idPersona']
            ?? 0
        );

        if ($remotePersonId <= 0) {
            throw new RuntimeException(
                'iConta no devolvió un IdPersona válido.'
            );
        }

        return $response;
    }

    /**
     * Prueba no destructiva de comunicación/autenticación.
     */
    public function testConnection(): array
    {
        $response = $this->searchPersons(
            '',
            '',
            '',
            '',
            1,
            1
        );

        return [
            'success' => true,
            'environment' => $this->environment,
            'api_url' => $this->apiUrl,
            'total_items' => isset($response['sF_TotalItems'])
                ? (int) $response['sF_TotalItems']
                : null,
            'returned_items' => isset($response['listaPersona'])
                && is_array($response['listaPersona'])
                ? count($response['listaPersona'])
                : 0,
        ];
    }

    /**
     * Obtiene el valor unitario neto que se enviará a iConta.
     *
     * La venta conserva el precio comercial original en precio_unitario.
     * Si dicho precio ya incluía IVA, se elimina únicamente el componente
     * tributario. El descuento permanece separado y se procesa posteriormente.
     */
    private function normalizeInvoiceUnitPrice(array $detail): float
    {
        $unitPrice = round(
            (float) ($detail['precio_unitario'] ?? 0),
            4
        );

        if ($unitPrice < 0) {
            throw new RuntimeException(
                'El precio unitario del detalle no puede ser negativo.'
            );
        }

        $taxRate = round(
            (float) ($detail['impuesto_porcentaje'] ?? 0),
            4
        );

        if ($taxRate < 0) {
            throw new RuntimeException(
                'El porcentaje de impuesto del detalle no es válido.'
            );
        }

        $includesTax = $this->isTrue(
            $detail['precio_incluye_impuesto'] ?? false
        );

        if (
            $includesTax
            && $taxRate > 0
        ) {
            $unitPrice = $unitPrice / (
                1 + ($taxRate / 100)
            );
        }

        return round($unitPrice, 6);
    }

    /**
     * Valida que un detalle pueda ser reconstruido por iConta
     * sin alterar los importes fiscales consolidados localmente.
     *
     * iConta recibe cantidad, valor unitario neto, porcentaje
     * de descuento e IVA; por tanto, vuelve a calcular base,
     * impuesto y total.
     */
    private function validateInvoiceDetailReconciliation(
        array $detail,
        float $unitPrice,
        float $discountPercentage
    ): void {
        $quantity = round(
            (float) ($detail['cantidad'] ?? 0),
            2
        );

        if ($quantity <= 0) {
            throw new RuntimeException(
                'No se puede conciliar un detalle con cantidad inválida.'
            );
        }

        $taxRate = round(
            (float) (
                $detail['impuesto_porcentaje']
                ?? 0
            ),
            4
        );

        if ($taxRate < 0) {
            throw new RuntimeException(
                'No se puede conciliar un detalle con impuesto inválido.'
            );
        }

        $localBase = round(
            (float) (
                $detail['base_imponible']
                ?? 0
            ),
            2
        );

        $localTax = round(
            (float) (
                $detail['impuesto']
                ?? 0
            ),
            2
        );

        $localTotal = round(
            (float) (
                $detail['total']
                ?? 0
            ),
            2
        );

        /*
         * Reconstruimos el importe utilizando los mismos
         * datos que se enviarán a iConta.
         */
        $grossBase = round(
            $unitPrice * $quantity,
            2
        );

        $discountAmount = round(
            $grossBase
                * ($discountPercentage / 100),
            2
        );

        $remoteBase = round(
            $grossBase - $discountAmount,
            2
        );

        $remoteTax = round(
            $remoteBase
                * ($taxRate / 100),
            2
        );

        $remoteTotal = round(
            $remoteBase + $remoteTax,
            2
        );

        $baseMatches =
            abs($remoteBase - $localBase) < 0.005;

        $taxMatches =
            abs($remoteTax - $localTax) < 0.005;

        $totalMatches =
            abs($remoteTotal - $localTotal) < 0.005;

        if (
            !$baseMatches
            || !$taxMatches
            || !$totalMatches
        ) {
            $itemCode = trim(
                (string) (
                    $detail['codigo_item']
                    ?? ''
                )
            );

            $description = trim(
                (string) (
                    $detail['descripcion']
                    ?? ''
                )
            );

            $itemLabel = $itemCode !== ''
                ? $itemCode
                : (
                    $description !== ''
                    ? $description
                    : 'SIN_IDENTIFICAR'
                );

            throw new RuntimeException(
                'El detalle "'
                    . $itemLabel
                    . '" no puede enviarse a iConta sin alterar sus valores fiscales. '
                    . 'Tu Huella Vet: base '
                    . number_format($localBase, 2, '.', '')
                    . ', IVA '
                    . number_format($localTax, 2, '.', '')
                    . ', total '
                    . number_format($localTotal, 2, '.', '')
                    . '. iConta calcularía: base '
                    . number_format($remoteBase, 2, '.', '')
                    . ', IVA '
                    . number_format($remoteTax, 2, '.', '')
                    . ', total '
                    . number_format($remoteTotal, 2, '.', '')
                    . '.'
            );
        }
    }

    /**
     * Convierte el descuento monetario almacenado en la venta
     * al porcentaje de descuento requerido por iConta.
     */
    private function invoiceDiscountPercentage(array $detail): float
    {
        $subtotal = round(
            (float) ($detail['subtotal'] ?? 0),
            2
        );

        $discount = round(
            (float) ($detail['descuento'] ?? 0),
            2
        );

        if ($subtotal < 0) {
            throw new RuntimeException(
                'El subtotal del detalle no puede ser negativo.'
            );
        }

        if ($discount < 0) {
            throw new RuntimeException(
                'El descuento del detalle no puede ser negativo.'
            );
        }

        if ($discount <= 0) {
            return 0.0;
        }

        if ($subtotal <= 0) {
            throw new RuntimeException(
                'No se puede calcular un descuento sobre un subtotal igual a cero.'
            );
        }

        if ($discount > $subtotal) {
            throw new RuntimeException(
                'El descuento del detalle supera su subtotal.'
            );
        }

        $percentage = ($discount / $subtotal) * 100;

        /*
     * iConta documenta porcentaje_descuento como:
     * > 0 y < 100.
     *
     * Un descuento del 100% no puede representarse mediante
     * ese campo y debe bloquearse antes de enviar la factura.
     */
        if ($percentage >= 100) {
            throw new RuntimeException(
                'iConta no permite enviar un descuento del 100% en un detalle.'
            );
        }

        return round($percentage, 6);
    }

    /**
     * Construye los detalles que se enviarán a iConta.
     */
    private function buildInvoiceDetails(array $details): array
    {
        if ($details === []) {
            throw new RuntimeException(
                'La factura no contiene detalles para enviar a iConta.'
            );
        }

        $result = [];

        foreach ($details as $detail) {
            if (!is_array($detail)) {
                continue;
            }

            /*
         * Código del producto o servicio.
         */
            $itemCode = trim(
                (string) ($detail['codigo_item'] ?? '')
            );

            if ($itemCode === '') {
                throw new RuntimeException(
                    'Uno de los detalles no tiene código de producto o servicio.'
                );
            }

            /*
         * iConta admite máximo 25 caracteres.
         * No truncamos porque podríamos provocar colisiones.
         */
            if (mb_strlen($itemCode) > 25) {
                throw new RuntimeException(
                    'El código "' . $itemCode
                        . '" supera los 25 caracteres permitidos por iConta.'
                );
            }

            $description = trim(
                (string) ($detail['descripcion'] ?? '')
            );

            if ($description === '') {
                throw new RuntimeException(
                    'Uno de los detalles no tiene descripción.'
                );
            }

            $quantity = round(
                (float) ($detail['cantidad'] ?? 0),
                2
            );

            if ($quantity <= 0) {
                throw new RuntimeException(
                    'La cantidad de un detalle debe ser mayor a cero.'
                );
            }

            $unitPrice = $this->normalizeInvoiceUnitPrice(
                $detail
            );

            if ($unitPrice <= 0) {
                throw new RuntimeException(
                    'El valor unitario enviado a iConta debe ser mayor a cero.'
                );
            }

            $discountPercentage =
                $this->invoiceDiscountPercentage($detail);

            $this->validateInvoiceDetailReconciliation(
                $detail,
                $unitPrice,
                $discountPercentage
            );

            $taxCode = strtoupper(
                trim(
                    (string) (
                        $detail['impuesto_codigo']
                        ?? ''
                    )
                )
            );

            $taxRate = round(
                (float) (
                    $detail['impuesto_porcentaje']
                    ?? 0
                ),
                4
            );

            /*
 * El snapshot puede almacenar:
 *
 * IVA    + porcentaje de la tarifa
 *
 * o códigos históricos/específicos como:
 *
 * IVA_0
 * IVA_15
 *
 * Para iConta resolvemos la tarifa utilizando ambos
 * valores y rechazamos combinaciones contradictorias.
 */
            $appliedVat = match (true) {
                $taxCode === 'IVA'
                    && abs($taxRate) <= 0.0001
                => '0',

                $taxCode === 'IVA'
                    && abs($taxRate - 15.0) <= 0.0001
                => '15',

                $taxCode === 'IVA_0'
                    && abs($taxRate) <= 0.0001
                => '0',

                $taxCode === 'IVA_15'
                    && abs($taxRate - 15.0) <= 0.0001
                => '15',

                default => throw new RuntimeException(
                    'La tarifa tributaria "'
                        . ($taxCode !== '' ? $taxCode : 'SIN_CODIGO')
                        . '" con porcentaje '
                        . number_format($taxRate, 4, '.', '')
                        . '% no tiene un mapeo válido para iConta.'
                ),
            };

            $invoiceDetail = [
                'codigo_producto' => $itemCode,

                /*
                * Inicialmente permitiremos a iConta verificar/crear
                * el producto. Posteriormente podremos cambiar esto
                * cuando sincronicemos formalmente su catálogo.
                */
                'verifica_crea_producto' => true,

                'nombre_producto' => mb_substr(
                    $description,
                    0,
                    250
                ),

                'cantidad' => number_format(
                    $quantity,
                    2,
                    '.',
                    ''
                ),
                'valor_unitario' => number_format(
                    $unitPrice,
                    6,
                    '.',
                    ''
                ),

                'porcentaje_descuento' => number_format(
                    $discountPercentage,
                    6,
                    '.',
                    ''
                ),

                'aplica_porcentaje_servicio' => false,

                'iva_aplicado' => $appliedVat,

                'detalle' => mb_substr(
                    $description,
                    0,
                    250
                ),
            ];

            $result[] = $invoiceDetail;
        }

        if ($result === []) {
            throw new RuntimeException(
                'No existen detalles válidos para enviar a iConta.'
            );
        }

        return $result;
    }

    /**
     * Obtiene los detalles fiscales almacenados de una venta.
     *
     * Los importes e impuestos se leen desde venta_detalles porque
     * representan el snapshot existente al momento de crear la venta.
     */
    private function invoiceSaleDetails(
        PDO $db,
        int $saleId
    ): array {
        if ($saleId <= 0) {
            throw new RuntimeException(
                'La venta indicada no es válida.'
            );
        }

        $stmt = $db->prepare(
            '
        SELECT
            vd.id,
            vd.venta_id,
            vd.servicio_id,
            vd.producto_id,

            COALESCE(
                p.codigo,
                s.codigo
            ) AS codigo_item,

            vd.impuesto_tarifa_id,
            vd.impuesto_codigo,
            vd.impuesto_nombre,
            vd.impuesto_porcentaje,

            vd.descripcion,
            vd.cantidad,
            vd.precio_unitario,
            vd.precio_incluye_impuesto,

            vd.subtotal,
            vd.base_imponible,
            vd.descuento,
            vd.impuesto,
            vd.total

        FROM venta_detalles vd

        LEFT JOIN productos p
            ON p.id = vd.producto_id

        LEFT JOIN servicios s
            ON s.id = vd.servicio_id

        WHERE vd.venta_id = :venta

        ORDER BY vd.id
        '
        );

        $stmt->execute([
            'venta' => $saleId,
        ]);

        $details = $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

        if ($details === []) {
            throw new RuntimeException(
                'La venta no contiene detalles para facturar.'
            );
        }

        return $details;
    }

    /**
     * Obtiene los pagos vigentes de una venta y los prepara
     * en el formato requerido por iConta.
     */
    private function invoiceSalePayments(
        PDO $db,
        int $saleId
    ): array {
        if ($saleId <= 0) {
            throw new RuntimeException(
                'La venta indicada no es válida.'
            );
        }

        $stmt = $db->prepare(
            '
        SELECT
            pg.id,
            pg.monto,
            pg.fecha_pago,
            pg.referencia,

            mp.codigo AS metodo_codigo,
            mp.nombre AS metodo_nombre,
            mp.iconta_codigo

        FROM pagos pg

        INNER JOIN metodos_pago mp
            ON mp.id = pg.metodo_pago_id

        WHERE pg.venta_id = :venta
          AND pg.estado = "REGISTRADO"
          AND pg.anulado_at IS NULL

        ORDER BY pg.id
        '
        );

        $stmt->execute([
            'venta' => $saleId,
        ]);

        $payments = $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );

        if ($payments === []) {
            return [];
        }

        $result = [];

        foreach ($payments as $payment) {
            $amount = round(
                (float) ($payment['monto'] ?? 0),
                2
            );

            if ($amount <= 0) {
                throw new RuntimeException(
                    'La venta contiene un pago con monto no válido.'
                );
            }

            $iContaCode = trim(
                (string) (
                    $payment['iconta_codigo']
                    ?? ''
                )
            );

            if ($iContaCode === '') {
                throw new RuntimeException(
                    'El método de pago "'
                        . ($payment['metodo_nombre'] ?? 'SIN NOMBRE')
                        . '" no tiene configurado su código de forma de pago en iConta.'
                );
            }

            if (mb_strlen($iContaCode) > 25) {
                throw new RuntimeException(
                    'El código iConta del método de pago "'
                        . ($payment['metodo_nombre'] ?? 'SIN NOMBRE')
                        . '" supera los 25 caracteres permitidos.'
                );
            }

            $reference = trim(
                (string) (
                    $payment['referencia']
                    ?? ''
                )
            );

            $methodCode = strtoupper(
                trim(
                    (string) (
                        $payment['metodo_codigo']
                        ?? ''
                    )
                )
            );

            /*
 * iConta requiere el documento/referencia para
 * transferencias bancarias.
 */
            if (
                $methodCode === 'TRANSFERENCIA'
                && $reference === ''
            ) {
                throw new RuntimeException(
                    'El pago #'
                        . (int) $payment['id']
                        . ' realizado mediante transferencia requiere una referencia para generar la factura en iConta.'
                );
            }

            if (mb_strlen($reference) > 50) {
                throw new RuntimeException(
                    'La referencia del pago #'
                        . (int) $payment['id']
                        . ' supera los 50 caracteres permitidos por iConta.'
                );
            }

            $invoicePayment = [
                'codigo_forma_pago' => $iContaCode,
                'valor' => number_format(
                    $amount,
                    2,
                    '.',
                    ''
                ),
            ];

            /*
         * iConta utiliza documento para el número de cheque,
         * transferencia u otra referencia bancaria.
         *
         * Si existe una referencia local la conservamos.
         */
            if ($reference !== '') {
                $invoicePayment['documento'] = $reference;
            }

            $result[] = $invoicePayment;
        }

        return $result;
    }

    /**
     * Obtiene la información principal de una venta que será
     * utilizada para construir la factura de iConta.
     */
    private function invoiceSale(
        PDO $db,
        int $saleId
    ): array {
        if ($saleId <= 0) {
            throw new RuntimeException(
                'La venta indicada no es válida.'
            );
        }

        $stmt = $db->prepare(
            '
        SELECT
            v.id,
            v.numero,
            v.entorno_id,
            v.propietario_entorno_id,
            v.animal_id,
            v.datos_fiscales_id,
            v.fecha,
            v.subtotal,
            v.descuento,
            v.impuestos,
            v.total,
            v.estado,
            v.es_simulada,

            pdf.identificacion,
            pdf.razon_social,
            pdf.direccion AS direccion_fiscal,
            pdf.email AS email_fiscal,
            pdf.telefono AS telefono_fiscal,

            ti.codigo AS tipo_identificacion_codigo

        FROM ventas v

        LEFT JOIN propietarios_datos_fiscales pdf
            ON pdf.id = v.datos_fiscales_id

        LEFT JOIN tipos_identificacion ti
            ON ti.id = pdf.tipo_identificacion_id

        WHERE v.id = :venta

        LIMIT 1
        '
        );

        $stmt->execute([
            'venta' => $saleId,
        ]);

        $sale = $stmt->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$sale) {
            throw new RuntimeException(
                'La venta no existe.'
            );
        }

        if (($sale['estado'] ?? '') !== 'FINALIZADA') {
            throw new RuntimeException(
                'Solo se pueden enviar a iConta ventas finalizadas.'
            );
        }

        if (!empty($sale['es_simulada'])) {
            throw new RuntimeException(
                'Una venta simulada no puede enviarse a iConta.'
            );
        }

        $fiscalDataId = (int) (
            $sale['datos_fiscales_id']
            ?? 0
        );

        if ($fiscalDataId <= 0) {
            throw new RuntimeException(
                'La venta no tiene datos fiscales asociados.'
            );
        }

        $identification = trim(
            (string) (
                $sale['identificacion']
                ?? ''
            )
        );

        if ($identification === '') {
            throw new RuntimeException(
                'Los datos fiscales de la venta no tienen identificación.'
            );
        }

        return $sale;
    }

    /**
     * Obtiene una configuración obligatoria del entorno.
     */
    private function environmentConfiguration(
        PDO $db,
        int $environmentId,
        string $key
    ): string {
        if ($environmentId <= 0) {
            throw new RuntimeException(
                'El entorno indicado no es válido.'
            );
        }

        $key = trim($key);

        if ($key === '') {
            throw new RuntimeException(
                'La clave de configuración del entorno no es válida.'
            );
        }

        $stmt = $db->prepare(
            '
        SELECT valor
        FROM configuraciones_entorno
        WHERE entorno_id = :entorno
          AND clave = :clave
        LIMIT 1
        '
        );

        $stmt->execute([
            'entorno' => $environmentId,
            'clave' => $key,
        ]);

        $value = $stmt->fetchColumn();

        if ($value === false) {
            throw new RuntimeException(
                'No está configurado "' . $key
                    . '" para el entorno seleccionado.'
            );
        }

        $value = trim((string) $value);

        if ($value === '') {
            throw new RuntimeException(
                'La configuración "' . $key
                    . '" del entorno está vacía.'
            );
        }

        return $value;
    }

    /**
     * Construye el payload de una venta para factura/genera de iConta.
     *
     * Este método no realiza ninguna petición HTTP.
     */
    private function buildInvoicePayload(
        PDO $db,
        int $saleId,
        int $fiscalDocumentId
    ): array {
        if ($saleId <= 0) {
            throw new RuntimeException(
                'La venta indicada no es válida.'
            );
        }

        if ($fiscalDocumentId <= 0) {
            throw new RuntimeException(
                'El documento fiscal indicado no es válido.'
            );
        }

        $sale = $this->invoiceSale(
            $db,
            $saleId
        );

        $environmentId = (int) (
            $sale['entorno_id']
            ?? 0
        );

        if ($environmentId <= 0) {
            throw new RuntimeException(
                'La venta no tiene un entorno válido.'
            );
        }

        /*
     * Configuración operativa del entorno.
     */
        $establishment = $this->environmentConfiguration(
            $db,
            $environmentId,
            'ICONTA_ESTABLECIMIENTO'
        );

        $emissionPoint = $this->environmentConfiguration(
            $db,
            $environmentId,
            'ICONTA_PUNTO_EMISION'
        );

        $electronicValue = $this->environmentConfiguration(
            $db,
            $environmentId,
            'ICONTA_ES_ELECTRONICA'
        );

        if (
            mb_strlen($establishment) !== 3
            || !ctype_digit($establishment)
        ) {
            throw new RuntimeException(
                'ICONTA_ESTABLECIMIENTO debe contener exactamente 3 dígitos.'
            );
        }

        if (
            mb_strlen($emissionPoint) !== 3
            || !ctype_digit($emissionPoint)
        ) {
            throw new RuntimeException(
                'ICONTA_PUNTO_EMISION debe contener exactamente 3 dígitos.'
            );
        }

        $isElectronic = $this->isTrue(
            $electronicValue
        );

        /*
     * Cliente.
     */
        $identification = trim(
            (string) (
                $sale['identificacion']
                ?? ''
            )
        );

        $identificationType = strtoupper(
            trim(
                (string) (
                    $sale['tipo_identificacion_codigo']
                    ?? ''
                )
            )
        );

        $client = [];

        switch ($identificationType) {
            case 'CEDULA':
                $client['cedula'] = $identification;
                break;

            case 'RUC':
                $client['ruc'] = $identification;
                break;

            case 'PASAPORTE':
                $client['pasaporte'] = $identification;
                break;

            default:
                throw new RuntimeException(
                    'El tipo de identificación "'
                        . $identificationType
                        . '" no está soportado para facturación en iConta.'
                );
        }

        $businessName = trim(
            (string) (
                $sale['razon_social']
                ?? ''
            )
        );

        if ($businessName === '') {
            throw new RuntimeException(
                'Los datos fiscales no tienen razón social para facturar.'
            );
        }

        $client['razon_social'] = $businessName;

        $email = trim(
            (string) (
                $sale['email_fiscal']
                ?? ''
            )
        );

        if ($email !== '') {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException(
                    'El correo fiscal no tiene un formato válido.'
                );
            }

            $client['email'] = $email;
        }

        $phone = trim(
            (string) (
                $sale['telefono_fiscal']
                ?? ''
            )
        );

        if ($phone !== '') {
            $client['telefono'] = $phone;
        }

        $address = trim(
            (string) (
                $sale['direccion_fiscal']
                ?? ''
            )
        );

        if ($address !== '') {
            $client['direccion'] = $address;
        }

        /*
     * Detalles.
     */
        $saleDetails = $this->invoiceSaleDetails(
            $db,
            $saleId
        );

        $invoiceDetails = $this->buildInvoiceDetails(
            $saleDetails
        );

        /*
     * Pagos vigentes.
     */
        $payments = $this->invoiceSalePayments(
            $db,
            $saleId
        );

        /*
     * Verificación adicional de pagos.
     *
     * BillingService ya impide sobrepagos, pero repetimos la
     * validación antes de enviar información a un tercero.
     */
        $invoiceTotal = round(
            (float) (
                $sale['total']
                ?? 0
            ),
            2
        );

        if ($invoiceTotal <= 0) {
            throw new RuntimeException(
                'El total de la venta debe ser mayor a cero para facturar.'
            );
        }

        $paymentsTotal = 0.0;

        foreach ($payments as $payment) {
            $paymentsTotal += (float) (
                $payment['valor']
                ?? 0
            );
        }

        $paymentsTotal = round(
            $paymentsTotal,
            2
        );

        if ($paymentsTotal > $invoiceTotal) {
            throw new RuntimeException(
                'La suma de pagos de la venta supera el total de la factura.'
            );
        }

        /*
     * Identificador estable e idempotente para iConta.
     *
     * No usamos un valor aleatorio: si se reintenta el mismo
     * documento fiscal, se conserva el mismo id_sistema.
     */
        $systemId = 'THV-FAC-' . $fiscalDocumentId;

        if (mb_strlen($systemId) > 50) {
            throw new RuntimeException(
                'El identificador de sistema de la factura supera los 50 caracteres.'
            );
        }

        $date = new \DateTimeImmutable('now');

        $issueDate = $date->format('d/m/Y');

        $payload = [
            'id_sistema' => $systemId,
            'fecha_emision' => $issueDate,
            'fecha_vencimiento' => $issueDate,
            'es_electronica' => $isElectronic,
            'establecimiento' => $establishment,
            'punto_emision' => $emissionPoint,
            'cliente' => $client,
            'detalles' => $invoiceDetails,
        ];

        /*
     * iConta permite omitir pagos.
     */
        if ($payments !== []) {
            $payload['pagos'] = $payments;
        }

        return $payload;
    }

    private function request(
        string $method,
        string $endpoint,
        ?array $body = null
    ): array {
        $this->assertConfiguration();

        $url = $this->apiUrl . '/' . ltrim($endpoint, '/');

        $ch = curl_init();

        if ($ch === false) {
            throw new RuntimeException(
                'No fue posible inicializar cURL.'
            );
        }

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ];

        curl_setopt_array(
            $ch,
            [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => strtoupper($method),
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_CONNECTTIMEOUT => min($this->timeout, 10),
                CURLOPT_TIMEOUT => $this->timeout,
            ]
        );

        if ($body !== null) {
            $json = json_encode(
                $body,
                JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
            );

            curl_setopt(
                $ch,
                CURLOPT_POSTFIELDS,
                $json
            );
        }

        $rawResponse = curl_exec($ch);

        if ($rawResponse === false) {
            $error = curl_error($ch);
            $number = curl_errno($ch);

            curl_close($ch);

            throw new RuntimeException(
                'Error de conexión con iConta'
                    . ($number ? " ({$number})" : '')
                    . ': '
                    . ($error !== '' ? $error : 'error desconocido')
            );
        }

        $httpCode = (int) curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

        curl_close($ch);

        $response = json_decode(
            $rawResponse,
            true
        );

        /*
 * Primero procesamos el estado HTTP.
 *
 * Algunos errores de autenticación pueden devolver
 * texto, HTML o incluso un body vacío.
 */
        if ($httpCode < 200 || $httpCode >= 300) {
            $message = '';

            if (is_array($response)) {
                $message = $this->extractErrorMessage($response);

                /*
     * Si iConta devuelve un JSON de error con una estructura
     * que todavía no conocemos, conservamos una representación
     * limitada del body para poder diagnosticar el rechazo.
     */
                if ($message === '') {
                    try {
                        $encodedResponse = json_encode(
                            $response,
                            JSON_UNESCAPED_UNICODE
                                | JSON_UNESCAPED_SLASHES
                                | JSON_THROW_ON_ERROR
                        );

                        $message = mb_substr(
                            $encodedResponse,
                            0,
                            2000
                        );
                    } catch (\Throwable $jsonError) {
                        $message = '';
                    }
                }
            } else {
                $plainResponse = trim(
                    strip_tags((string) $rawResponse)
                );

                /*
         * Limitamos la respuesta para no imprimir
         * accidentalmente contenido excesivo.
         */
                if ($plainResponse !== '') {
                    $message = mb_substr(
                        $plainResponse,
                        0,
                        500
                    );
                }
            }

            throw new RuntimeException(
                'iConta respondió HTTP '
                    . $httpCode
                    . ($message !== ''
                        ? ': ' . $message
                        : ' sin detalle adicional.')
            );
        }

        /*
        * A partir de aquí una respuesta exitosa
        * debe contener JSON válido.
        */
        if (!is_array($response)) {
            throw new RuntimeException(
                'iConta devolvió HTTP '
                    . $httpCode
                    . ' pero la respuesta no contiene JSON válido.'
            );
        }

        /*
         * iConta puede responder HTTP 200 y reportar
         * el error dentro del JSON.
         */
        if (
            array_key_exists('sF_ExisteError', $response)
            && $this->isTrue($response['sF_ExisteError'])
        ) {
            $message = $this->extractErrorMessage(
                $response
            );

            /*
     * Algunos endpoints de iConta explican un error
     * funcional mediante "observacion" aunque
     * sF_Error venga vacío o null.
     */
            if ($message === '') {
                $message = trim(
                    (string) (
                        $response['observacion']
                        ?? $response['Observacion']
                        ?? ''
                    )
                );
            }

            /*
     * Si tampoco existe un mensaje convencional,
     * conservamos una representación limitada de la
     * respuesta para poder diagnosticar el rechazo.
     */
            if ($message === '') {
                try {
                    $encodedResponse = json_encode(
                        $response,
                        JSON_UNESCAPED_UNICODE
                            | JSON_UNESCAPED_SLASHES
                            | JSON_THROW_ON_ERROR
                    );

                    $message = mb_substr(
                        $encodedResponse,
                        0,
                        2000
                    );
                } catch (\Throwable $jsonError) {
                    $message = '';
                }
            }

            throw new RuntimeException(
                'iConta reportó un error'
                    . ($message !== ''
                        ? ': ' . $message
                        : '.')
            );
        }
        return $response;
    }

    /**
     * Genera una factura en iConta.
     *
     * Recibe un payload previamente construido y validado.
     */
    public function generateInvoice(array $payload): array
    {
        $this->assertConfiguration();

        if ($payload === []) {
            throw new RuntimeException(
                'El payload de la factura está vacío.'
            );
        }

        $response = $this->request(
            'POST',
            '/api/external/factura/genera',
            $payload
        );

        /*
     * La documentación utiliza snake_case, pero normalizamos
     * también variantes por si el API responde con otro casing.
     */
        $invoiceId = $response['id_factura']
            ?? $response['IdFactura']
            ?? $response['idFactura']
            ?? null;

        $invoiceNumber = $response['numero_comprobante']
            ?? $response['NumeroComprobante']
            ?? $response['numeroComprobante']
            ?? null;

        $hasError = $response['SF_ExisteError']
            ?? $response['sf_existe_error']
            ?? $response['sfExisteError']
            ?? false;

        $errorMessage = $response['SF_Error']
            ?? $response['sf_error']
            ?? $response['sfError']
            ?? null;

        if ($this->isTrue($hasError)) {
            $message = trim(
                (string) $errorMessage
            );

            throw new RuntimeException(
                $message !== ''
                    ? 'iConta rechazó la factura: ' . $message
                    : 'iConta rechazó la generación de la factura.'
            );
        }

        if (
            $invoiceId === null
            || $invoiceId === ''
            || (is_numeric($invoiceId) && (int) $invoiceId <= 0)
        ) {
            throw new RuntimeException(
                'iConta no devolvió un identificador válido para la factura.'
            );
        }

        /*
     * Conservamos la respuesta original y añadimos una
     * representación normalizada para el resto del sistema.
     */
        $response['IdFactura'] = $invoiceId;
        $response['NumeroComprobante'] = $invoiceNumber !== null
            ? trim((string) $invoiceNumber)
            : null;

        return $response;
    }

    /**
     * Procesa un documento fiscal de tipo FACTURA pendiente de envío a iConta.
     *
     * El documento debe existir previamente en iconta_documentos.
     */
    public function processInvoice(int $iContaDocumentId): array
    {
        $this->assertConfiguration();

        if ($iContaDocumentId <= 0) {
            throw new RuntimeException(
                'El documento de iConta indicado no es válido.'
            );
        }

        $db = Database::connection();

        /*
     * Cargamos el documento pendiente junto con la venta.
     */
        $stmt = $db->prepare(
            '
        SELECT
            idoc.id,
            idoc.documento_fiscal_id,
            idoc.iconta_id_documento,
            idoc.estado AS iconta_estado,
            idoc.intentos,

            df.venta_id,
            df.tipo_documento,
            df.estado AS documento_estado,

            v.datos_fiscales_id

        FROM iconta_documentos idoc

        INNER JOIN documentos_fiscales df
            ON df.id = idoc.documento_fiscal_id

        INNER JOIN ventas v
            ON v.id = df.venta_id

        WHERE idoc.id = :id

        LIMIT 1
        '
        );

        $stmt->execute([
            'id' => $iContaDocumentId,
        ]);

        $document = $stmt->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$document) {
            throw new RuntimeException(
                'El documento pendiente de iConta no existe.'
            );
        }

        if (
            strtoupper(
                trim(
                    (string) (
                        $document['tipo_documento']
                        ?? ''
                    )
                )
            ) !== 'FACTURA'
        ) {
            throw new RuntimeException(
                'El documento indicado no corresponde a una factura.'
            );
        }

        /*
     * Si ya tenemos un ID remoto, no debemos generar nuevamente
     * la factura. Esto protege frente a duplicados locales.
     */
        $existingRemoteId = trim(
            (string) (
                $document['iconta_id_documento']
                ?? ''
            )
        );

        if ($existingRemoteId !== '') {
            throw new RuntimeException(
                'El documento ya tiene una factura asociada en iConta.'
            );
        }

        $saleId = (int) (
            $document['venta_id']
            ?? 0
        );

        $fiscalDocumentId = (int) (
            $document['documento_fiscal_id']
            ?? 0
        );

        $fiscalDataId = (int) (
            $document['datos_fiscales_id']
            ?? 0
        );

        if ($saleId <= 0) {
            throw new RuntimeException(
                'El documento no tiene una venta válida.'
            );
        }

        if ($fiscalDocumentId <= 0) {
            throw new RuntimeException(
                'El documento fiscal asociado no es válido.'
            );
        }

        if ($fiscalDataId <= 0) {
            throw new RuntimeException(
                'La venta no tiene datos fiscales válidos.'
            );
        }

        $payload = null;

        $attemptIncremented = false;

        try {
            /*
             * Primero construimos y validamos completamente la factura
             * utilizando el snapshot fiscal almacenado de la venta.
             *
             * Esto incluye la conciliación de base, impuesto y total
             * antes de realizar operaciones remotas en iConta.
             */
            $payload = $this->buildInvoicePayload(
                $db,
                $saleId,
                $fiscalDocumentId
            );

            /*
            * Solo después de superar todas las validaciones locales
            * garantizamos que el cliente exista y esté sincronizado
            * correctamente en iConta.
            */
            $person = $this->ensurePerson(
                $fiscalDataId
            );

            /*
         * Guardamos el request antes de llamar al API.
         *
         * También incrementamos intentos aquí porque a partir
         * de este punto comienza un intento real de generación.
         */
            $updateAttempt = $db->prepare(
                '
            UPDATE iconta_documentos
            SET request_payload = :request_payload,
                intentos = intentos + 1,
                ultimo_error = NULL
            WHERE id = :id
            '
            );

            $updateAttempt->execute([
                'request_payload' => json_encode(
                    $payload,
                    JSON_UNESCAPED_UNICODE
                        | JSON_UNESCAPED_SLASHES
                        | JSON_THROW_ON_ERROR
                ),
                'id' => $iContaDocumentId,
            ]);

            $attemptIncremented = true;

            /*
            * Llamada remota.
            */
            $response = $this->generateInvoice(
                $payload
            );

            $remoteInvoiceId = trim(
                (string) (
                    $response['IdFactura']
                    ?? ''
                )
            );

            if ($remoteInvoiceId === '') {
                throw new RuntimeException(
                    'iConta no devolvió el identificador de la factura.'
                );
            }

            $invoiceNumber = trim(
                (string) (
                    $response['NumeroComprobante']
                    ?? ''
                )
            );

            $responseJson = json_encode(
                $response,
                JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
            );

            /*
         * Persistimos el éxito en una transacción local corta.
         */
            $db->beginTransaction();

            try {
                $updateIConta = $db->prepare(
                    '
                UPDATE iconta_documentos
                SET iconta_id_documento = :remote_id,
                    estado = :estado,
                    response_payload = :response_payload,
                    ultimo_error = NULL,
                    enviado_at = NOW()
                WHERE id = :id
                '
                );

                $updateIConta->execute([
                    'remote_id' => $remoteInvoiceId,
                    'estado' => 'GENERADA',
                    'response_payload' => $responseJson,
                    'id' => $iContaDocumentId,
                ]);

                $updateFiscal = $db->prepare(
                    '
                UPDATE documentos_fiscales
                SET numero_documento = :numero_documento,
                    estado = :estado,
                    fecha_emision = COALESCE(
                        fecha_emision,
                        NOW()
                    )
                WHERE id = :id
                '
                );

                $updateFiscal->execute([
                    'numero_documento' => (
                        $invoiceNumber !== ''
                        ? $invoiceNumber
                        : null
                    ),
                    'estado' => 'GENERADA',
                    'id' => $fiscalDocumentId,
                ]);

                $db->commit();
            } catch (\Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }

                throw $e;
            }

            return [
                'iconta_documento_id' => $iContaDocumentId,
                'documento_fiscal_id' => $fiscalDocumentId,
                'venta_id' => $saleId,
                'persona' => $person,
                'id_factura' => $remoteInvoiceId,
                'numero_comprobante' => (
                    $invoiceNumber !== ''
                    ? $invoiceNumber
                    : null
                ),
                'estado' => 'GENERADA',
                'response' => $response,
            ];
        } catch (\Throwable $e) {
            /*
         * Registramos el error sin destruir el documento.
         *
         * Si el request ya había sido guardado, intentos ya fue
         * incrementado. Si falló antes (por ejemplo ensurePerson
         * o validación del payload), incrementamos aquí.
         */
            try {
                $requestJson = $payload !== null
                    ? json_encode(
                        $payload,
                        JSON_UNESCAPED_UNICODE
                            | JSON_UNESCAPED_SLASHES
                            | JSON_THROW_ON_ERROR
                    )
                    : null;

                $db->beginTransaction();

                try {
                    /*
         * Marcamos como ERROR el documento de integración.
         */
                    $errorStmt = $db->prepare(
                        '
            UPDATE iconta_documentos
            SET estado = :estado,
                request_payload = COALESCE(
                    :request_payload,
                    request_payload
                ),
                ultimo_error = :ultimo_error,
                intentos = intentos + :increment_attempt
            WHERE id = :id
            '
                    );

                    $errorStmt->execute([
                        'estado' => 'ERROR',
                        'request_payload' => $requestJson,
                        'ultimo_error' => mb_substr(
                            $e->getMessage(),
                            0,
                            65535
                        ),
                        'increment_attempt' => 0,
                        'id' => $iContaDocumentId,
                    ]);

                    /*
         * El documento fiscal local debe reflejar el mismo
         * estado de error que la integración con iConta.
         */
                    $errorFiscalStmt = $db->prepare(
                        '
            UPDATE documentos_fiscales
            SET estado = :estado
            WHERE id = :id
            '
                    );

                    $errorFiscalStmt->execute([
                        'estado' => 'ERROR',
                        'id' => $fiscalDocumentId,
                    ]);

                    $db->commit();
                } catch (\Throwable $logError) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }

                    throw $logError;
                }
            } catch (\Throwable $logError) {
                /*
     * No reemplazamos el error original por un posible
     * error secundario al registrar el fallo.
     */
            }

            throw $e;
        }
    }

    /**
     * Envía una factura ya generada en iConta al proceso
     * de autorización electrónica del SRI.
     *
     * Este método NO genera nuevamente la factura.
     */
    public function sendInvoiceToSri(int $iContaDocumentId): array
    {
        $this->assertConfiguration();

        if ($iContaDocumentId <= 0) {
            throw new RuntimeException(
                'El documento de iConta indicado no es válido.'
            );
        }

        $db = Database::connection();

        $stmt = $db->prepare(
            '
            SELECT
                idoc.id,
                idoc.documento_fiscal_id,
                idoc.iconta_id_documento,
                idoc.estado AS iconta_estado,

                df.venta_id,
                df.tipo_documento,
                df.numero_documento,
                df.clave_acceso,
                df.estado AS documento_estado

            FROM iconta_documentos idoc

            INNER JOIN documentos_fiscales df
                ON df.id = idoc.documento_fiscal_id

            WHERE idoc.id = :id

            LIMIT 1
            '
        );

        $stmt->execute([
            'id' => $iContaDocumentId,
        ]);

        $document = $stmt->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$document) {
            throw new RuntimeException(
                'El documento de iConta no existe.'
            );
        }

        if (
            strtoupper(
                trim(
                    (string) (
                        $document['tipo_documento']
                        ?? ''
                    )
                )
            ) !== 'FACTURA'
        ) {
            throw new RuntimeException(
                'El documento indicado no corresponde a una factura.'
            );
        }

        $remoteInvoiceId = trim(
            (string) (
                $document['iconta_id_documento']
                ?? ''
            )
        );

        if (
            $remoteInvoiceId === ''
            || !ctype_digit($remoteInvoiceId)
            || (int) $remoteInvoiceId <= 0
        ) {
            throw new RuntimeException(
                'La factura todavía no tiene un identificador válido en iConta.'
            );
        }

        $iContaState = strtoupper(
            trim(
                (string) (
                    $document['iconta_estado']
                    ?? ''
                )
            )
        );

        /*
         * Solo una factura que ya fue generada puede iniciar
         * el proceso electrónico.
         *
         * Permitimos ENVIADA_SRI para que una respuesta anterior
         * correctamente persistida pueda consultarse sin confundirla
         * con una factura pendiente de generación.
         */
        if (
            !in_array(
                $iContaState,
                [
                    'GENERADA',
                    'ENVIADA_SRI',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'La factura no se encuentra en un estado válido para enviarla al SRI.'
            );
        }

        /*
         * Si ya tenemos clave de acceso, consideramos que el envío
         * fue registrado anteriormente.
         *
         * No repetimos enviafacturasri porque este endpoint inicia
         * un proceso externo y no debemos duplicarlo.
         */
        $existingAccessKey = trim(
            (string) (
                $document['clave_acceso']
                ?? ''
            )
        );

        if ($existingAccessKey !== '') {
            throw new RuntimeException(
                'La factura ya fue registrada para envío al SRI.'
            );
        }

        $payload = [
            'id_factura' => (int) $remoteInvoiceId,
        ];

        $response = $this->request(
            'POST',
            '/api/external/factura/enviafacturasri',
            $payload
        );

        $hasError = $response['sF_ExisteError']
            ?? $response['SF_ExisteError']
            ?? $response['sf_existe_error']
            ?? $response['sfExisteError']
            ?? false;

        $errorMessage = $response['sF_Error']
            ?? $response['SF_Error']
            ?? $response['sf_error']
            ?? $response['sfError']
            ?? null;

        if ($this->isTrue($hasError)) {
            $message = trim(
                (string) $errorMessage
            );

            throw new RuntimeException(
                $message !== ''
                    ? 'iConta rechazó el envío al SRI: ' . $message
                    : 'iConta rechazó el envío de la factura al SRI.'
            );
        }

        $accessKey = trim(
            (string) (
                $response['clave_acceso']
                ?? $response['ClaveAcceso']
                ?? $response['claveAcceso']
                ?? ''
            )
        );

        if ($accessKey === '') {
            throw new RuntimeException(
                'iConta no devolvió la clave de acceso de la factura.'
            );
        }

        if (mb_strlen($accessKey) > 100) {
            throw new RuntimeException(
                'La clave de acceso devuelta por iConta supera la longitud permitida.'
            );
        }

        $observation = trim(
            (string) (
                $response['observacion']
                ?? $response['Observacion']
                ?? ''
            )
        );

        /*
         * Persistimos primero el resultado remoto.
         *
         * ENVIADA_SRI significa únicamente que iConta registró
         * satisfactoriamente la factura en el proceso de envío.
         * NO significa que el SRI ya la haya autorizado.
         */
        $db->beginTransaction();

        try {
            $updateFiscal = $db->prepare(
                '
                UPDATE documentos_fiscales
                SET clave_acceso = :clave_acceso,
                    estado = :estado
                WHERE id = :id
                '
            );

            $updateFiscal->execute([
                'clave_acceso' => $accessKey,
                'estado' => 'ENVIADA_SRI',
                'id' => (int) $document['documento_fiscal_id'],
            ]);

            $updateIConta = $db->prepare(
                '
                UPDATE iconta_documentos
                SET estado = :estado,
                    response_payload = :response_payload,
                    ultimo_error = NULL
                WHERE id = :id
                '
            );

            $updateIConta->execute([
                'estado' => 'ENVIADA_SRI',
                'response_payload' => json_encode(
                    $response,
                    JSON_UNESCAPED_UNICODE
                        | JSON_UNESCAPED_SLASHES
                        | JSON_THROW_ON_ERROR
                ),
                'id' => $iContaDocumentId,
            ]);

            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            /*
             * No cambiamos el documento a ERROR:
             * el endpoint remoto pudo haber aceptado ya el envío.
             * El estado debe reconciliarse posteriormente mediante
             * factura/estado.
             */
            throw $e;
        }

        return [
            'iconta_documento_id' => $iContaDocumentId,
            'documento_fiscal_id' => (
                (int) $document['documento_fiscal_id']
            ),
            'venta_id' => (
                (int) $document['venta_id']
            ),
            'id_factura' => $remoteInvoiceId,
            'clave_acceso' => $accessKey,
            'observacion' => (
                $observation !== ''
                ? $observation
                : null
            ),
            'estado' => 'ENVIADA_SRI',
            'response' => $response,
        ];
    }

    /**
     * Consulta el estado actual de una factura electrónica en iConta.
     *
     * Este método es únicamente de consulta:
     * no modifica la base de datos local y no vuelve a enviar
     * la factura al SRI.
     */
    public function getInvoiceSriStatus(
        int $iContaDocumentId,
        bool $returnRide = false,
        bool $returnXml = false
    ): array {
        $this->assertConfiguration();

        if ($iContaDocumentId <= 0) {
            throw new RuntimeException(
                'El documento de iConta indicado no es válido.'
            );
        }

        $db = Database::connection();

        $stmt = $db->prepare(
            '
            SELECT
                idoc.id,
                idoc.documento_fiscal_id,
                idoc.iconta_id_documento,

                df.tipo_documento

            FROM iconta_documentos idoc

            INNER JOIN documentos_fiscales df
                ON df.id = idoc.documento_fiscal_id

            WHERE idoc.id = :id

            LIMIT 1
            '
        );

        $stmt->execute([
            'id' => $iContaDocumentId,
        ]);

        $document = $stmt->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$document) {
            throw new RuntimeException(
                'El documento de iConta no existe.'
            );
        }

        if (
            strtoupper(
                trim(
                    (string) (
                        $document['tipo_documento']
                        ?? ''
                    )
                )
            ) !== 'FACTURA'
        ) {
            throw new RuntimeException(
                'El documento indicado no corresponde a una factura.'
            );
        }

        $remoteInvoiceId = trim(
            (string) (
                $document['iconta_id_documento']
                ?? ''
            )
        );

        if (
            $remoteInvoiceId === ''
            || !ctype_digit($remoteInvoiceId)
            || (int) $remoteInvoiceId <= 0
        ) {
            throw new RuntimeException(
                'La factura no tiene un identificador válido en iConta.'
            );
        }

        $payload = [
            'id_factura' => (int) $remoteInvoiceId,
            'retorna_ride' => $returnRide ? 'SI' : 'NO',
            'retorna_xml' => $returnXml ? 'SI' : 'NO',
        ];

        $response = $this->request(
            'POST',
            '/api/external/factura/estado',
            $payload
        );

        $hasError = $response['sF_ExisteError']
            ?? $response['SF_ExisteError']
            ?? $response['sf_existe_error']
            ?? $response['sfExisteError']
            ?? false;

        $errorMessage = $response['sF_Error']
            ?? $response['SF_Error']
            ?? $response['sf_error']
            ?? $response['sfError']
            ?? null;

        if ($this->isTrue($hasError)) {
            $message = trim(
                (string) $errorMessage
            );

            throw new RuntimeException(
                $message !== ''
                    ? 'iConta no pudo consultar el estado de la factura: '
                    . $message
                    : 'iConta no pudo consultar el estado de la factura.'
            );
        }

        $status = strtoupper(
            trim(
                (string) (
                    $response['estado_factura']
                    ?? $response['EstadoFactura']
                    ?? $response['estadoFactura']
                    ?? ''
                )
            )
        );

        if ($status === '') {
            throw new RuntimeException(
                'iConta no devolvió el estado de la factura.'
            );
        }

        $allowedStatuses = [
            'POR_FIRMAR',
            'ESPERA_ENVIO',
            'ESPERA_AUTORIZACION',
            'NEGADO',
            'AUTORIZADO',
            'ANULADO',
        ];

        if (!in_array($status, $allowedStatuses, true)) {
            throw new RuntimeException(
                'iConta devolvió un estado de factura no reconocido: '
                    . $status
                    . '.'
            );
        }

        /*
         * Normalizamos únicamente los datos principales.
         * RIDE y XML se conservan en la respuesta original.
         */
        $response['EstadoFactura'] = $status;

        $authorization = trim(
            (string) (
                $response['autorizacion']
                ?? $response['Autorizacion']
                ?? ''
            )
        );

        $response['Autorizacion'] = (
            $authorization !== ''
            ? $authorization
            : null
        );

        return $response;
    }

    /**
     * Consulta el estado SRI de una factura y sincroniza
     * su estado, autorización, RIDE y XML.
     *
     * Los archivos solamente se solicitan cuando iConta
     * informa que la factura está AUTORIZADA.
     */
    public function syncInvoiceSriStatus(
        int $iContaDocumentId
    ): array {
        $this->assertConfiguration();

        if ($iContaDocumentId <= 0) {
            throw new RuntimeException(
                'El documento de iConta indicado no es válido.'
            );
        }

        $db = Database::connection();

        $stmt = $db->prepare(
            '
            SELECT
                idoc.id,
                idoc.documento_fiscal_id,
                idoc.iconta_id_documento,
                idoc.estado AS iconta_estado,

                df.venta_id,
                df.tipo_documento,
                df.estado AS documento_estado,
                df.clave_acceso,
                df.autorizacion,
                df.archivo_pdf_id,
                df.archivo_xml_id,

                v.creado_por

            FROM iconta_documentos idoc

            INNER JOIN documentos_fiscales df
                ON df.id = idoc.documento_fiscal_id

            INNER JOIN ventas v
                ON v.id = df.venta_id

            WHERE idoc.id = :id

            LIMIT 1
            '
        );

        $stmt->execute([
            'id' => $iContaDocumentId,
        ]);

        $document = $stmt->fetch(
            PDO::FETCH_ASSOC
        );

        if (!$document) {
            throw new RuntimeException(
                'El documento de iConta no existe.'
            );
        }

        if (
            strtoupper(
                trim(
                    (string) (
                        $document['tipo_documento']
                        ?? ''
                    )
                )
            ) !== 'FACTURA'
        ) {
            throw new RuntimeException(
                'El documento indicado no corresponde a una factura.'
            );
        }

        $remoteInvoiceId = trim(
            (string) (
                $document['iconta_id_documento']
                ?? ''
            )
        );

        if (
            $remoteInvoiceId === ''
            || !ctype_digit($remoteInvoiceId)
            || (int) $remoteInvoiceId <= 0
        ) {
            throw new RuntimeException(
                'La factura no tiene un identificador válido en iConta.'
            );
        }

        $userId = (int) (
            $document['creado_por']
            ?? 0
        );

        if ($userId <= 0) {
            throw new RuntimeException(
                'La venta no tiene un usuario creador válido para almacenar los archivos fiscales.'
            );
        }

        $requestPayload = [
            'id_factura' => (int) $remoteInvoiceId,
            'retorna_ride' => 'NO',
            'retorna_xml' => 'NO',
        ];

        /*
         * Solo contendrá rutas de archivos creados durante
         * ESTA ejecución.
         */
        $createdPhysicalPaths = [];

        try {
            /*
             * Primera consulta:
             * solamente necesitamos conocer el estado.
             */
            $response = $this->getInvoiceSriStatus(
                $iContaDocumentId,
                false,
                false
            );

            $status = strtoupper(
                trim(
                    (string) (
                        $response['EstadoFactura']
                        ?? ''
                    )
                )
            );

            $authorization = trim(
                (string) (
                    $response['Autorizacion']
                    ?? ''
                )
            );

            if (
                $authorization !== ''
                && mb_strlen($authorization) > 100
            ) {
                throw new RuntimeException(
                    'La autorización devuelta por iConta supera la longitud permitida.'
                );
            }

            $currentPdfId = (
                !empty($document['archivo_pdf_id'])
                ? (int) $document['archivo_pdf_id']
                : null
            );

            $currentXmlId = (
                !empty($document['archivo_xml_id'])
                ? (int) $document['archivo_xml_id']
                : null
            );

            $rideData = null;
            $xmlData = null;

            /*
             * Solamente una factura AUTORIZADA puede tener
             * RIDE/XML definitivos.
             */
            if ($status === 'AUTORIZADO') {
                $needRide = $currentPdfId === null;
                $needXml = $currentXmlId === null;

                /*
                 * Si falta al menos uno de los archivos hacemos
                 * una segunda consulta solicitando únicamente
                 * lo necesario.
                 */
                if ($needRide || $needXml) {
                    $requestPayload = [
                        'id_factura' => (int) $remoteInvoiceId,
                        'retorna_ride' => (
                            $needRide
                            ? 'SI'
                            : 'NO'
                        ),
                        'retorna_xml' => (
                            $needXml
                            ? 'SI'
                            : 'NO'
                        ),
                    ];

                    $fileResponse = $this->getInvoiceSriStatus(
                        $iContaDocumentId,
                        $needRide,
                        $needXml
                    );

                    $fileStatus = strtoupper(
                        trim(
                            (string) (
                                $fileResponse['EstadoFactura']
                                ?? ''
                            )
                        )
                    );

                    /*
                     * Evitamos almacenar archivos si el estado
                     * cambió entre las dos consultas.
                     */
                    if ($fileStatus !== 'AUTORIZADO') {
                        throw new RuntimeException(
                            'La factura dejó de estar AUTORIZADA al solicitar sus archivos fiscales. Estado actual: '
                                . $fileStatus
                                . '.'
                        );
                    }

                    $fileAuthorization = trim(
                        (string) (
                            $fileResponse['Autorizacion']
                            ?? ''
                        )
                    );

                    if ($fileAuthorization !== '') {
                        if (
                            mb_strlen(
                                $fileAuthorization
                            ) > 100
                        ) {
                            throw new RuntimeException(
                                'La autorización devuelta por iConta supera la longitud permitida.'
                            );
                        }

                        $authorization = $fileAuthorization;
                    }

                    if ($needRide) {
                        $rideData = $this->extractSriFile(
                            $fileResponse,
                            'factura_ride',
                            'RIDE-'
                                . $remoteInvoiceId
                                . '.pdf'
                        );
                    }

                    if ($needXml) {
                        $xmlData = $this->extractSriFile(
                            $fileResponse,
                            'factura_xml',
                            'FACTURA-'
                                . $remoteInvoiceId
                                . '.xml'
                        );
                    }

                    /*
                     * Conservamos la respuesta que contiene los
                     * archivos para el evento técnico.
                     */
                    $response = $fileResponse;
                }

                /*
                 * Una factura AUTORIZADA debe tener número de
                 * autorización antes de considerarla confirmada.
                 */
                if ($authorization === '') {
                    throw new RuntimeException(
                        'iConta informó que la factura está AUTORIZADA, pero no devolvió el número de autorización.'
                    );
                }
            }

            /*
             * A partir de aquí toda la persistencia SQL participa
             * en una única transacción.
             */
            $db->beginTransaction();

            try {
                $fileService = new FileService();

                $pdfId = $currentPdfId;
                $xmlId = $currentXmlId;

                if ($rideData !== null) {
                    $ridePhysicalPath = null;

                    $pdfId = $fileService->storeGenerated(
                        $rideData['contenido'],
                        $rideData['nombre_archivo'],
                        $userId,
                        'fiscal/ride',
                        ['application/pdf'],
                        $ridePhysicalPath
                    );

                    if (
                        $ridePhysicalPath !== null
                        && $ridePhysicalPath !== ''
                    ) {
                        $createdPhysicalPaths[] =
                            $ridePhysicalPath;
                    }
                }

                if ($xmlData !== null) {
                    $xmlPhysicalPath = null;

                    $xmlId = $fileService->storeGenerated(
                        $xmlData['contenido'],
                        $xmlData['nombre_archivo'],
                        $userId,
                        'fiscal/xml',
                        [
                            'application/xml',
                            'text/xml',
                        ],
                        $xmlPhysicalPath
                    );

                    if (
                        $xmlPhysicalPath !== null
                        && $xmlPhysicalPath !== ''
                    ) {
                        $createdPhysicalPaths[] =
                            $xmlPhysicalPath;
                    }
                }

                /*
                 * Si está AUTORIZADA, al terminar esta parte
                 * deben existir ambos archivos.
                 */
                if (
                    $status === 'AUTORIZADO'
                    && (
                        $pdfId === null
                        || $xmlId === null
                    )
                ) {
                    throw new RuntimeException(
                        'La factura está AUTORIZADA pero no fue posible completar sus archivos RIDE y XML.'
                    );
                }

                $updateFiscal = $db->prepare(
                    '
                    UPDATE documentos_fiscales
                    SET estado = :estado,
                        autorizacion = COALESCE(
                            :nueva_autorizacion,
                            autorizacion
                        ),
                        archivo_pdf_id = COALESCE(
                            :archivo_pdf_id,
                            archivo_pdf_id
                        ),
                        archivo_xml_id = COALESCE(
                            :archivo_xml_id,
                            archivo_xml_id
                        )
                    WHERE id = :id
                    '
                );

                $updateFiscal->execute([
                    'estado' => $status,
                    'nueva_autorizacion' => (
                        $authorization !== ''
                        ? $authorization
                        : null
                    ),
                    'archivo_pdf_id' => $pdfId,
                    'archivo_xml_id' => $xmlId,
                    'id' => (
                        (int) $document['documento_fiscal_id']
                    ),
                ]);

                $updateIConta = $db->prepare(
                    '
                    UPDATE iconta_documentos
                    SET estado = :estado,
                        ultimo_error = NULL,
                        confirmado_at = CASE
                            WHEN :confirmar = 1
                            THEN COALESCE(
                                confirmado_at,
                                NOW()
                            )
                            ELSE confirmado_at
                        END
                    WHERE id = :id
                    '
                );

                $updateIConta->execute([
                    'estado' => $status,
                    'confirmar' => (
                        $status === 'AUTORIZADO'
                        ? 1
                        : 0
                    ),
                    'id' => $iContaDocumentId,
                ]);

                $eventId = $this->logDocumentEvent(
                    $iContaDocumentId,
                    'CONSULTA_ESTADO',
                    $status,
                    '/api/external/factura/estado',
                    $requestPayload,
                    $response,
                    true,
                    null
                );

                $db->commit();

                /*
                 * Después del COMMIT estas rutas ya pertenecen
                 * definitivamente a registros de archivos.
                 */
                $createdPhysicalPaths = [];
            } catch (\Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }

                /*
                 * El rollback revierte INSERT INTO archivos,
                 * pero no puede eliminar los archivos físicos.
                 */
                if (!empty($createdPhysicalPaths)) {
                    $fileService = $fileService
                        ?? new FileService();

                    foreach (
                        array_reverse(
                            $createdPhysicalPaths
                        ) as $physicalPath
                    ) {
                        try {
                            $fileService
                                ->compensatePhysicalFile(
                                    $physicalPath
                                );
                        } catch (\Throwable $cleanupError) {
                            error_log(
                                'No fue posible compensar el archivo fiscal '
                                    . $physicalPath
                                    . ': '
                                    . $cleanupError->getMessage()
                            );
                        }
                    }

                    $createdPhysicalPaths = [];
                }

                throw $e;
            }

            return [
                'iconta_documento_id' =>
                $iContaDocumentId,

                'documento_fiscal_id' => (
                    (int) $document['documento_fiscal_id']
                ),

                'venta_id' => (
                    (int) $document['venta_id']
                ),

                'id_factura' =>
                $remoteInvoiceId,

                'estado_anterior' => strtoupper(
                    trim(
                        (string) (
                            $document['iconta_estado']
                            ?? ''
                        )
                    )
                ),

                'estado' => $status,

                'autorizacion' => (
                    $authorization !== ''
                    ? $authorization
                    : null
                ),

                'archivo_pdf_id' => $pdfId,
                'archivo_xml_id' => $xmlId,

                'evento_id' => $eventId,

                'response' => $response,
            ];
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            /*
             * Seguridad adicional por si una excepción escapó
             * después de crear físicamente algún archivo.
             */
            if (!empty($createdPhysicalPaths)) {
                $fileService = new FileService();

                foreach (
                    array_reverse(
                        $createdPhysicalPaths
                    ) as $physicalPath
                ) {
                    try {
                        $fileService
                            ->compensatePhysicalFile(
                                $physicalPath
                            );
                    } catch (\Throwable $cleanupError) {
                        error_log(
                            'No fue posible compensar el archivo fiscal '
                                . $physicalPath
                                . ': '
                                . $cleanupError->getMessage()
                        );
                    }
                }

                $createdPhysicalPaths = [];
            }

            /*
             * Conservamos evidencia del fallo fuera de la
             * transacción revertida.
             */
            try {
                $this->logDocumentEvent(
                    $iContaDocumentId,
                    'CONSULTA_ESTADO',
                    null,
                    '/api/external/factura/estado',
                    $requestPayload,
                    null,
                    false,
                    mb_substr(
                        $e->getMessage(),
                        0,
                        65535
                    )
                );
            } catch (\Throwable $logError) {
                /*
                 * Nunca sustituimos el error original por
                 * un fallo del registro técnico.
                 */
            }

            throw $e;
        }
    }

    /**
     * Extrae y valida un archivo Base64 devuelto por iConta.
     *
     * @return array{
     *     nombre_archivo: string,
     *     contenido: string
     * }
     */
    private function extractSriFile(
        array $response,
        string $responseKey,
        string $defaultName
    ): array {
        $file = $response[$responseKey]
            ?? $response[$responseKey === 'factura_ride'
                ? 'FacturaRide'
                : 'FacturaXml']
            ?? null;

        if (!is_array($file)) {
            throw new RuntimeException(
                'iConta no devolvió el archivo '
                    . $responseKey
                    . ' esperado.'
            );
        }

        $fileName = trim(
            (string) (
                $file['nombre_archivo']
                ?? $file['NombreArchivo']
                ?? ''
            )
        );

        /*
         * Nunca permitimos que el nombre remoto determine
         * una ruta local.
         */
        $fileName = basename(
            str_replace('\\', '/', $fileName)
        );

        if ($fileName === '' || $fileName === '.' || $fileName === '..') {
            $fileName = $defaultName;
        }

        if (mb_strlen($fileName) > 255) {
            throw new RuntimeException(
                'El nombre del archivo devuelto por iConta supera los 255 caracteres.'
            );
        }

        $encodedContent = trim(
            (string) (
                $file['archivo']
                ?? $file['Archivo']
                ?? ''
            )
        );

        if ($encodedContent === '') {
            throw new RuntimeException(
                'iConta devolvió '
                    . $fileName
                    . ' sin contenido.'
            );
        }

        /*
         * Algunas APIs anteponen data:...;base64, aunque
         * iConta documenta directamente el contenido Base64.
         * Si apareciera ese prefijo, conservamos únicamente
         * la parte codificada.
         */
        if (
            preg_match(
                '/^data:[^;]+;base64,(.*)$/s',
                $encodedContent,
                $matches
            ) === 1
        ) {
            $encodedContent = $matches[1];
        }

        /*
         * Permitimos saltos de línea del Base64, pero no
         * caracteres ajenos a su representación.
         */
        $encodedContent = preg_replace(
            '/\s+/',
            '',
            $encodedContent
        );

        if (
            !is_string($encodedContent)
            || $encodedContent === ''
        ) {
            throw new RuntimeException(
                'El contenido Base64 de '
                    . $fileName
                    . ' no es válido.'
            );
        }

        $content = base64_decode(
            $encodedContent,
            true
        );

        if (
            $content === false
            || $content === ''
        ) {
            throw new RuntimeException(
                'No fue posible decodificar el archivo '
                    . $fileName
                    . ' devuelto por iConta.'
            );
        }

        return [
            'nombre_archivo' => $fileName,
            'contenido' => $content,
        ];
    }

    /**
     * Registra un evento técnico asociado a un documento de iConta.
     *
     * Si existe una transacción activa, participa en ella utilizando
     * la misma conexión PDO de la aplicación.
     */
    private function logDocumentEvent(
        int $iContaDocumentId,
        string $eventType,
        ?string $state = null,
        ?string $endpoint = null,
        ?array $requestPayload = null,
        ?array $responsePayload = null,
        bool $successful = true,
        ?string $errorMessage = null
    ): int {
        if ($iContaDocumentId <= 0) {
            throw new RuntimeException(
                'El documento de iConta del evento no es válido.'
            );
        }

        $eventType = strtoupper(
            trim($eventType)
        );

        if ($eventType === '') {
            throw new RuntimeException(
                'El tipo de evento de iConta es obligatorio.'
            );
        }

        if (mb_strlen($eventType) > 40) {
            throw new RuntimeException(
                'El tipo de evento de iConta supera la longitud permitida.'
            );
        }

        $state = $state !== null
            ? strtoupper(trim($state))
            : null;

        if ($state === '') {
            $state = null;
        }

        if (
            $state !== null
            && mb_strlen($state) > 40
        ) {
            throw new RuntimeException(
                'El estado del evento de iConta supera la longitud permitida.'
            );
        }

        $endpoint = $endpoint !== null
            ? trim($endpoint)
            : null;

        if ($endpoint === '') {
            $endpoint = null;
        }

        if (
            $endpoint !== null
            && mb_strlen($endpoint) > 150
        ) {
            throw new RuntimeException(
                'El endpoint del evento de iConta supera la longitud permitida.'
            );
        }

        $requestJson = $requestPayload !== null
            ? json_encode(
                $requestPayload,
                JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
            )
            : null;

        $responseJson = $responsePayload !== null
            ? json_encode(
                $responsePayload,
                JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
            )
            : null;

        $errorMessage = $errorMessage !== null
            ? trim($errorMessage)
            : null;

        if ($errorMessage === '') {
            $errorMessage = null;
        }

        $db = Database::connection();

        $stmt = $db->prepare(
            '
            INSERT INTO iconta_documento_eventos
            (
                iconta_documento_id,
                tipo_evento,
                estado,
                endpoint,
                request_payload,
                response_payload,
                exitoso,
                error_mensaje
            )
            VALUES
            (
                :iconta_documento_id,
                :tipo_evento,
                :estado,
                :endpoint,
                :request_payload,
                :response_payload,
                :exitoso,
                :error_mensaje
            )
            '
        );

        $stmt->execute([
            'iconta_documento_id' => $iContaDocumentId,
            'tipo_evento' => $eventType,
            'estado' => $state,
            'endpoint' => $endpoint,
            'request_payload' => $requestJson,
            'response_payload' => $responseJson,
            'exitoso' => $successful ? 1 : 0,
            'error_mensaje' => $errorMessage,
        ]);

        return (int) $db->lastInsertId();
    }

    private function assertConfiguration(): void
    {
        if (!$this->enabled) {
            throw new RuntimeException(
                'La integración con iConta está deshabilitada.'
            );
        }

        if ($this->apiUrl === '') {
            throw new RuntimeException(
                'ICONTA_API_URL no está configurado.'
            );
        }

        if ($this->apiKey === '') {
            throw new RuntimeException(
                'ICONTA_API_KEY no está configurado.'
            );
        }
    }

    private function extractErrorMessage(array $response): string
    {
        $candidates = [
            $response['sF_Error'] ?? null,
            $response['message'] ?? null,
            $response['Message'] ?? null,
            $response['error'] ?? null,
            $response['Error'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (
                is_string($candidate)
                && trim($candidate) !== ''
            ) {
                return trim($candidate);
            }
        }

        return '';
    }

    private function isTrue(mixed $value): bool
    {
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }

        if (is_string($value)) {
            return in_array(
                strtolower(trim($value)),
                ['true', 'yes', 'si', 'sí'],
                true
            );
        }

        return false;
    }
}
