<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use PDOException;
use RuntimeException;

class OwnerService
{
    /**
     * Crear propietario.
     *
     * Puede:
     * - crear solamente la ficha del propietario;
     * - crear una cuenta CLIENTE nueva;
     * - vincular una cuenta existente y agregarle CLIENTE
     *   sin eliminar sus demás roles.
     */
    public function create(
        array $d,
        int $env,
        int $by
    ): array {
        return Database::transaction(function (PDO $db) use ($d, $env, $by) {

            // =====================================================
            // 1. NORMALIZAR DATOS
            // =====================================================

            $names = $this->nullableString(
                $d['nombres'] ?? null
            );

            if ($names === null) {
                throw new RuntimeException(
                    'Los nombres son obligatorios.'
                );
            }

            $lastNames = $this->nullableString(
                $d['apellidos'] ?? null
            );

            $identification = $this->normalizeIdentification(
                $d['identificacion'] ?? null
            );

            $email = $this->normalizeEmail(
                $d['email'] ?? null
            );

            $phone = $this->nullableString(
                $d['telefono'] ?? null
            );

            $mobile = $this->normalizeMobile(
                $d['celular'] ?? null
            );

            $address = $this->nullableString(
                $d['direccion'] ?? null
            );

            $identificationTypeId = !empty($d['tipo_identificacion_id'])
                ? (int) $d['tipo_identificacion_id']
                : null;

            /*
         * Modos:
         *
         * create = crear/vincular acceso Cliente.
         * none   = registrar propietario sin acceso.
         */
            $accessMode = (string) (
                $d['modo_acceso'] ?? 'create'
            );

            if (!in_array($accessMode, ['create', 'none'], true)) {
                throw new RuntimeException(
                    'El modo de acceso seleccionado no es válido.'
                );
            }

            // =====================================================
            // 2. VALIDACIONES GENERALES
            // =====================================================

            if (
                $email !== null
                && !filter_var($email, FILTER_VALIDATE_EMAIL)
            ) {
                throw new RuntimeException(
                    'El correo electrónico no tiene un formato válido.'
                );
            }

            if ($accessMode === 'create' && $email === null) {
                throw new RuntimeException(
                    'El correo electrónico es obligatorio para crear '
                        . 'o vincular el acceso al portal.'
                );
            }

            $this->validateUnique(
                $db,
                $identification,
                $email,
                $mobile
            );

            // =====================================================
            // 3. DATOS DE LA CUENTA
            // =====================================================

            $userId = null;
            $userCreated = false;
            $userLinked = false;

            // =====================================================
            // 4. CREAR O VINCULAR ACCESO
            // =====================================================

            if ($accessMode === 'create') {

                /*
             * Bloqueamos la cuenta encontrada durante la
             * transacción para evitar vinculaciones concurrentes.
             */
                $stmt = $db->prepare(
                    'SELECT
                    u.id,
                    u.nombres,
                    u.apellidos,
                    u.email,
                    u.activo,
                    u.deleted_at
                 FROM usuarios u
                 WHERE u.email = :email
                 LIMIT 1
                 FOR UPDATE'
                );

                $stmt->execute([
                    'email' => $email,
                ]);

                $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);

                // -------------------------------------------------
                // 4A. USUARIO EXISTENTE
                // -------------------------------------------------

                if ($existingUser) {

                    if (!empty($existingUser['deleted_at'])) {
                        throw new RuntimeException(
                            'Existe una cuenta dada de baja con este correo. '
                                . 'Debe ser revisada desde Administración antes '
                                . 'de poder vincularla como propietario.'
                        );
                    }

                    if ((int) $existingUser['activo'] !== 1) {
                        throw new RuntimeException(
                            'Existe una cuenta inactiva con este correo. '
                                . 'Debe activarse desde Administración antes '
                                . 'de vincularla como propietario.'
                        );
                    }

                    $userId = (int) $existingUser['id'];

                    /*
                 * Un usuario solamente puede corresponder a una
                 * ficha de propietario.
                 */
                    $stmt = $db->prepare(
                        'SELECT id
                     FROM propietarios
                     WHERE usuario_id = :user_id
                     LIMIT 1
                     FOR UPDATE'
                    );

                    $stmt->execute([
                        'user_id' => $userId,
                    ]);

                    $existingOwnerId = $stmt->fetchColumn();

                    if ($existingOwnerId) {
                        throw new RuntimeException(
                            'Esta cuenta ya está vinculada a una ficha '
                                . 'de propietario.'
                        );
                    }

                    $userLinked = true;
                }

                // -------------------------------------------------
                // 4B. USUARIO NUEVO
                // -------------------------------------------------

                else {

                    /*
                 * No mostramos ni enviamos una contraseña temporal.
                 * El usuario establecerá su contraseña mediante
                 * la invitación.
                 */
                    $initialSecret = bin2hex(random_bytes(32));

                    $stmt = $db->prepare(
                        'INSERT INTO usuarios (
                        nombres,
                        apellidos,
                        email,
                        telefono,
                        password_hash,
                        requiere_cambio_password,
                        activo
                    ) VALUES (
                        :names,
                        :last_names,
                        :email,
                        :phone,
                        :password,
                        1,
                        1
                    )'
                    );

                    $stmt->execute([
                        'names' => $names,
                        'last_names' => $lastNames ?? '',
                        'email' => $email,
                        'phone' => $mobile ?? $phone,
                        'password' => password_hash(
                            $initialSecret,
                            PASSWORD_DEFAULT
                        ),
                    ]);

                    $userId = (int) $db->lastInsertId();
                    $userCreated = true;
                }

                // =================================================
                // 5. OBTENER ROL CLIENTE
                // =================================================

                $stmt = $db->prepare(
                    "SELECT id
                 FROM roles
                 WHERE codigo = 'CLIENTE'
                   AND activo = 1
                   AND es_global = 0
                 LIMIT 1"
                );

                $stmt->execute();

                $roleId = (int) $stmt->fetchColumn();

                if ($roleId <= 0) {
                    throw new RuntimeException(
                        'No existe un rol CLIENTE activo para entornos.'
                    );
                }

                // =================================================
                // 6. GARANTIZAR ACCESO AL ENTORNO
                // =================================================

                /*
             * Si ya existe, lo reactivamos.
             * Si no existe, lo creamos.
             *
             * No modificamos es_predeterminado de una cuenta
             * existente.
             */
                $stmt = $db->prepare(
                    'INSERT INTO usuarios_entornos (
                    usuario_id,
                    entorno_id,
                    activo
                 ) VALUES (
                    :user_id,
                    :environment_id,
                    1
                 )
                 ON DUPLICATE KEY UPDATE
                    activo = 1'
                );

                $stmt->execute([
                    'user_id' => $userId,
                    'environment_id' => $env,
                ]);

                // =================================================
                // 7. AGREGAR CLIENTE SIN QUITAR OTROS ROLES
                // =================================================

                $stmt = $db->prepare(
                    'INSERT INTO usuarios_entornos_roles (
                    usuario_id,
                    entorno_id,
                    rol_id
                 ) VALUES (
                    :user_id,
                    :environment_id,
                    :role_id
                 )
                 ON DUPLICATE KEY UPDATE
                    rol_id = VALUES(rol_id)'
                );

                $stmt->execute([
                    'user_id' => $userId,
                    'environment_id' => $env,
                    'role_id' => $roleId,
                ]);
            }

            // =====================================================
            // 8. CREAR PROPIETARIO
            // =====================================================

            $stmt = $db->prepare(
                'INSERT INTO propietarios (
                usuario_id,
                tipo_identificacion_id,
                identificacion,
                nombres,
                apellidos,
                email,
                telefono,
                celular,
                direccion,
                activo
             ) VALUES (
                :user_id,
                :identification_type,
                :identification,
                :names,
                :last_names,
                :email,
                :phone,
                :mobile,
                :address,
                1
             )'
            );

            $stmt->execute([
                'user_id' => $userId,
                'identification_type' => $identificationTypeId,
                'identification' => $identification,
                'names' => $names,
                'last_names' => $lastNames,
                'email' => $email,
                'phone' => $phone,
                'mobile' => $mobile,
                'address' => $address,
            ]);

            $ownerId = (int) $db->lastInsertId();

            // =====================================================
            // 9. VINCULAR PROPIETARIO AL ENTORNO
            // =====================================================

            $stmt = $db->prepare(
                'INSERT INTO propietarios_entornos (
                propietario_id,
                entorno_id,
                activo
             ) VALUES (
                :owner_id,
                :environment_id,
                1
             )'
            );

            $stmt->execute([
                'owner_id' => $ownerId,
                'environment_id' => $env,
            ]);

            // =====================================================
            // 10. CREAR DATOS FISCALES PRINCIPALES
            // =====================================================

            $createFiscalData = isset($d['crear_datos_fiscales'])
                && (string) $d['crear_datos_fiscales'] === '1';

            $fiscalDataId = null;

            if ($createFiscalData) {

                $sameFiscalData = isset($d['datos_fiscales_mismos'])
                    && (string) $d['datos_fiscales_mismos'] === '1';


                // -------------------------------------------------
                // 10A. USAR DATOS DEL PROPIETARIO
                // -------------------------------------------------

                if ($sameFiscalData) {

                    $fiscalIdentificationTypeId = $identificationTypeId;
                    $fiscalIdentification = $identification;

                    $fiscalBusinessName = trim(
                        $names . ' ' . ($lastNames ?? '')
                    );

                    $fiscalAddress = $address;
                    $fiscalEmail = $email;
                    $fiscalPhone = $mobile ?? $phone;
                }


                // -------------------------------------------------
                // 10B. DATOS FISCALES DIFERENTES
                // -------------------------------------------------

                else {

                    $fiscalIdentificationTypeId =
                        !empty($d['fiscal_tipo_identificacion_id'])
                        ? (int) $d['fiscal_tipo_identificacion_id']
                        : null;

                    $fiscalIdentification =
                        $this->normalizeIdentification(
                            $d['fiscal_identificacion'] ?? null
                        );

                    $fiscalBusinessName =
                        $this->nullableString(
                            $d['fiscal_razon_social'] ?? null
                        );

                    $fiscalAddress =
                        $this->nullableString(
                            $d['fiscal_direccion'] ?? null
                        );

                    $fiscalEmail =
                        $this->normalizeEmail(
                            $d['fiscal_email'] ?? null
                        );

                    $fiscalPhone =
                        $this->nullableString(
                            $d['fiscal_telefono'] ?? null
                        );
                }


                // -------------------------------------------------
                // 10C. VALIDACIONES
                // -------------------------------------------------

                if ($fiscalIdentificationTypeId === null) {
                    throw new RuntimeException(
                        'Debe seleccionar el tipo de identificación fiscal.'
                    );
                }

                if ($fiscalIdentification === null) {
                    throw new RuntimeException(
                        'La identificación fiscal es obligatoria.'
                    );
                }

                if ($fiscalBusinessName === null) {
                    throw new RuntimeException(
                        'La razón social es obligatoria.'
                    );
                }

                if (
                    $fiscalEmail !== null
                    && !filter_var($fiscalEmail, FILTER_VALIDATE_EMAIL)
                ) {
                    throw new RuntimeException(
                        'El correo de facturación no tiene un formato válido.'
                    );
                }


                // -------------------------------------------------
                // 10D. VALIDAR TIPO DE IDENTIFICACIÓN
                // -------------------------------------------------

                $stmt = $db->prepare(
                    'SELECT id
         FROM tipos_identificacion
         WHERE id = :id
         LIMIT 1'
                );

                $stmt->execute([
                    'id' => $fiscalIdentificationTypeId,
                ]);

                if (!$stmt->fetchColumn()) {
                    throw new RuntimeException(
                        'El tipo de identificación fiscal seleccionado no existe.'
                    );
                }


                // -------------------------------------------------
                // 10E. CREAR REGISTRO FISCAL PRINCIPAL
                // -------------------------------------------------

                $stmt = $db->prepare(
                    'INSERT INTO propietarios_datos_fiscales (
            propietario_id,
            tipo_identificacion_id,
            identificacion,
            razon_social,
            direccion,
            email,
            telefono,
            es_principal,
            activo
         ) VALUES (
            :owner_id,
            :identification_type,
            :identification,
            :business_name,
            :address,
            :email,
            :phone,
            1,
            1
         )'
                );

                $stmt->execute([
                    'owner_id' => $ownerId,
                    'identification_type' => $fiscalIdentificationTypeId,
                    'identification' => $fiscalIdentification,
                    'business_name' => $fiscalBusinessName,
                    'address' => $fiscalAddress,
                    'email' => $fiscalEmail,
                    'phone' => $fiscalPhone,
                ]);

                $fiscalDataId = (int) $db->lastInsertId();
            }

            // =====================================================
            // 10. AUDITORÍA
            // =====================================================

            (new AuditService())->log(
                $by,
                $env,
                'PROPIETARIOS',
                'CREAR',
                'propietarios',
                $ownerId,
                null,
                [
                    'usuario_id' => $userId,
                    'modo_acceso' => $accessMode,
                    'usuario_creado' => $userCreated,
                    'usuario_vinculado' => $userLinked,
                    'dato_fiscal_id' => $fiscalDataId,
                ]
            );

            // =====================================================
            // 11. RESULTADO
            // =====================================================

            return [
                'id' => $ownerId,
                'user_id' => $userId,
                'user_created' => $userCreated,
                'user_linked' => $userLinked,
                'access_mode' => $accessMode,
                'fiscal_data_id' => $fiscalDataId,
            ];
        });
    }

    /**
     * Actualizar propietario.
     */
    public function update(
        int $id,
        array $d,
        int $env,
        int $by
    ): void {
        Database::transaction(function (PDO $db) use (
            $id,
            $d,
            $env,
            $by
        ) {

            // =====================================================
            // 1. BUSCAR PROPIETARIO
            // =====================================================

            $stmt = $db->prepare(
                'SELECT p.*
                 FROM propietarios p
                 INNER JOIN propietarios_entornos pe
                    ON pe.propietario_id = p.id
                 WHERE p.id = :id
                   AND pe.entorno_id = :env
                   AND pe.activo = 1
                   AND p.deleted_at IS NULL
                 LIMIT 1'
            );

            $stmt->execute([
                'id'  => $id,
                'env' => $env,
            ]);

            $old = $stmt->fetch();

            if (!$old) {
                throw new RuntimeException(
                    'Propietario no encontrado.'
                );
            }

            // =====================================================
            // 2. NORMALIZAR DATOS
            // =====================================================

            $names = $this->nullableString(
                $d['nombres'] ?? $old['nombres']
            );

            if ($names === null) {
                throw new RuntimeException(
                    'Los nombres son obligatorios.'
                );
            }

            $lastNames = $this->nullableString(
                $d['apellidos'] ?? $old['apellidos']
            );

            $identification = $this->normalizeIdentification(
                $d['identificacion'] ?? $old['identificacion']
            );

            $email = $this->normalizeEmail(
                $d['email'] ?? $old['email']
            );

            $phone = $this->nullableString(
                $d['telefono'] ?? $old['telefono']
            );

            $mobile = $this->normalizeMobile(
                $d['celular'] ?? $old['celular']
            );

            $address = $this->nullableString(
                $d['direccion'] ?? $old['direccion']
            );

            $identificationTypeId = array_key_exists(
                'tipo_identificacion_id',
                $d
            )
                ? (
                    !empty($d['tipo_identificacion_id'])
                    ? (int) $d['tipo_identificacion_id']
                    : null
                )
                : $old['tipo_identificacion_id'];

            // =====================================================
            // 3. VALIDAR CORREO
            // =====================================================

            if (
                $email !== null
                && !filter_var($email, FILTER_VALIDATE_EMAIL)
            ) {
                throw new RuntimeException(
                    'El correo electrónico no tiene un formato válido.'
                );
            }

            // =====================================================
            // 4. VALIDAR DUPLICADOS
            // =====================================================

            $this->validateUnique(
                $db,
                $identification,
                $email,
                $mobile,
                $id
            );

            // =====================================================
            // 5. ACTUALIZAR PROPIETARIO
            // =====================================================

            $stmt = $db->prepare(
                'UPDATE propietarios
                 SET
                    tipo_identificacion_id = :identification_type,
                    identificacion = :identification,
                    nombres = :names,
                    apellidos = :last_names,
                    email = :email,
                    telefono = :phone,
                    celular = :mobile,
                    direccion = :address
                 WHERE id = :id
                   AND deleted_at IS NULL'
            );

            $stmt->execute([
                'identification_type' => $identificationTypeId,
                'identification'      => $identification,
                'names'               => $names,
                'last_names'          => $lastNames,
                'email'               => $email,
                'phone'               => $phone,
                'mobile'              => $mobile,
                'address'             => $address,
                'id'                  => $id,
            ]);

            // =====================================================
            // 6. AUDITORÍA
            // =====================================================

            (new AuditService())->log(
                $by,
                $env,
                'PROPIETARIOS',
                'EDITAR',
                'propietarios',
                $id,
                $old,
                $d
            );
        });
    }

    /**
     * Eliminación lógica.
     *
     * Conservamos los registros y sus relaciones históricas.
     */
    public function delete(
        int $id,
        int $env,
        int $by
    ): void {
        $db = Database::connection();

        $stmt = $db->prepare(
            'UPDATE propietarios p
             INNER JOIN propietarios_entornos pe
                ON pe.propietario_id = p.id
             SET
                p.deleted_at = NOW(),
                p.activo = 0,
                pe.activo = 0
             WHERE p.id = :id
               AND pe.entorno_id = :env'
        );

        $stmt->execute([
            'id'  => $id,
            'env' => $env,
        ]);

        (new AuditService())->log(
            $by,
            $env,
            'PROPIETARIOS',
            'ELIMINAR',
            'propietarios',
            $id
        );
    }

    /**
     * Verificar identificación, correo y celular únicos.
     *
     * La búsqueda es global, independientemente del entorno.
     * Incluye registros dados de baja para evitar reutilizar
     * identificaciones vinculadas a historiales anteriores.
     */
    private function validateUnique(
        PDO $db,
        ?string $identification,
        ?string $email,
        ?string $mobile,
        ?int $excludeId = null
    ): void {
        $fields = [
            [
                'column' => 'identificacion',
                'value' => $identification,
                'message' => 'Ya existe un propietario con esta identificación.',
            ],
            [
                'column' => 'email',
                'value' => $email,
                'message' => 'Ya existe un propietario con este correo electrónico.',
            ],
            [
                'column' => 'celular',
                'value' => $mobile,
                'message' => 'Ya existe un propietario con este número de celular.',
            ],
        ];

        foreach ($fields as $field) {

            if ($field['value'] === null || $field['value'] === '') {
                continue;
            }

            // Nombre de columna procedente de una lista interna fija.
            $sql = 'SELECT id
                    FROM propietarios
                    WHERE ' . $field['column'] . ' = :value';

            $params = [
                'value' => $field['value'],
            ];

            if ($excludeId !== null) {

                $sql .= ' AND id <> :exclude_id';

                $params['exclude_id'] = $excludeId;
            }

            $sql .= ' LIMIT 1';

            $stmt = $db->prepare($sql);

            $stmt->execute($params);

            if ($stmt->fetchColumn()) {
                throw new RuntimeException(
                    $field['message']
                );
            }
        }
    }

    /**
     * Normalizar identificación.
     *
     * Elimina espacios, puntos y guiones.
     */
    private function normalizeIdentification(
        mixed $value
    ): ?string {
        $value = $this->nullableString($value);

        if ($value === null) {
            return null;
        }

        $value = preg_replace(
            '/[\s.\-]+/u',
            '',
            $value
        );

        return $value !== '' ? $value : null;
    }

    /**
     * Normalizar correo electrónico.
     */
    private function normalizeEmail(
        mixed $value
    ): ?string {
        $value = $this->nullableString($value);

        return $value !== null
            ? strtolower($value)
            : null;
    }

    /**
     * Normalizar celular.
     *
     * Formatos admitidos:
     * 0991234567
     * +593991234567
     * 593991234567
     *
     * Se almacenan como 0991234567.
     */
    private function normalizeMobile(
        mixed $value
    ): ?string {
        $value = $this->nullableString($value);

        if ($value === null) {
            return null;
        }

        // Conservar únicamente dígitos.
        $digits = preg_replace('/\D+/', '', $value);

        // Código internacional de Ecuador.
        if (
            str_starts_with($digits, '593')
            && strlen($digits) === 12
        ) {
            $digits = '0' . substr($digits, 3);
        }

        // Celular ecuatoriano: 09 + 8 dígitos.
        if (!preg_match('/^09\d{8}$/', $digits)) {
            throw new RuntimeException(
                'El celular debe tener 10 dígitos y comenzar con 09.'
            );
        }

        return $digits;
    }

    /**
     * Convertir cadenas vacías en NULL.
     */
    private function nullableString(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== ''
            ? $value
            : null;
    }

    public function createFiscalData(
        int $ownerId,
        array $d,
        int $env,
        int $by
    ): void {
        Database::transaction(function (PDO $db) use (
            $ownerId,
            $d,
            $env,
            $by
        ) {
            $this->assertOwnerInEnvironment(
                $db,
                $ownerId,
                $env
            );

            $identificationTypeId =
                !empty($d['tipo_identificacion_id'])
                ? (int) $d['tipo_identificacion_id']
                : 0;

            $identification = $this->normalizeIdentification(
                $d['identificacion'] ?? null
            );

            $businessName = $this->nullableString(
                $d['razon_social'] ?? null
            );

            $address = $this->nullableString(
                $d['direccion'] ?? null
            );

            $email = $this->normalizeEmail(
                $d['email'] ?? null
            );

            $phone = $this->nullableString(
                $d['telefono'] ?? null
            );

            if ($identificationTypeId <= 0) {
                throw new RuntimeException(
                    'Debe seleccionar el tipo de identificación fiscal.'
                );
            }

            if ($identification === null) {
                throw new RuntimeException(
                    'La identificación fiscal es obligatoria.'
                );
            }

            if ($businessName === null) {
                throw new RuntimeException(
                    'La razón social es obligatoria.'
                );
            }

            if (
                $email !== null
                && !filter_var($email, FILTER_VALIDATE_EMAIL)
            ) {
                throw new RuntimeException(
                    'El correo de facturación no es válido.'
                );
            }

            $this->assertIdentificationType(
                $db,
                $identificationTypeId
            );

            /*
         * Bloqueamos los datos fiscales del propietario.
         */
            $stmt = $db->prepare(
                'SELECT id
             FROM propietarios_datos_fiscales
             WHERE propietario_id = :owner_id
             FOR UPDATE'
            );

            $stmt->execute([
                'owner_id' => $ownerId,
            ]);

            $existing = $stmt->fetchAll(PDO::FETCH_COLUMN);

            /*
         * Si es el primer dato fiscal, obligatoriamente será principal.
         * Si ya existen, respetamos lo seleccionado.
         */
            $isPrincipal = empty($existing)
                || (
                    isset($d['es_principal'])
                    && (string) $d['es_principal'] === '1'
                );

            if ($isPrincipal) {
                $stmt = $db->prepare(
                    'UPDATE propietarios_datos_fiscales
                 SET es_principal = 0
                 WHERE propietario_id = :owner_id'
                );

                $stmt->execute([
                    'owner_id' => $ownerId,
                ]);
            }

            $stmt = $db->prepare(
                'INSERT INTO propietarios_datos_fiscales (
                propietario_id,
                tipo_identificacion_id,
                identificacion,
                razon_social,
                direccion,
                email,
                telefono,
                es_principal,
                activo
             ) VALUES (
                :owner_id,
                :identification_type,
                :identification,
                :business_name,
                :address,
                :email,
                :phone,
                :principal,
                1
             )'
            );

            $stmt->execute([
                'owner_id' => $ownerId,
                'identification_type' => $identificationTypeId,
                'identification' => $identification,
                'business_name' => $businessName,
                'address' => $address,
                'email' => $email,
                'phone' => $phone,
                'principal' => $isPrincipal ? 1 : 0,
            ]);

            $fiscalDataId = (int) $db->lastInsertId();

            (new AuditService())->log(
                $by,
                $env,
                'PROPIETARIOS',
                'CREAR_DATO_FISCAL',
                'propietarios_datos_fiscales',
                $fiscalDataId,
                null,
                [
                    'propietario_id' => $ownerId,
                    'identificacion' => $identification,
                    'razon_social' => $businessName,
                    'es_principal' => $isPrincipal,
                ]
            );
        });
    }

    public function updateFiscalData(
        int $ownerId,
        int $fiscalDataId,
        array $d,
        int $env,
        int $by
    ): void {
        Database::transaction(function (PDO $db) use (
            $ownerId,
            $fiscalDataId,
            $d,
            $env,
            $by
        ) {
            $this->assertOwnerInEnvironment(
                $db,
                $ownerId,
                $env
            );

            $stmt = $db->prepare(
                'SELECT *
             FROM propietarios_datos_fiscales
             WHERE id = :id
               AND propietario_id = :owner_id
             LIMIT 1
             FOR UPDATE'
            );

            $stmt->execute([
                'id' => $fiscalDataId,
                'owner_id' => $ownerId,
            ]);

            $old = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$old) {
                throw new RuntimeException(
                    'Dato fiscal no encontrado.'
                );
            }

            $identificationTypeId =
                !empty($d['tipo_identificacion_id'])
                ? (int) $d['tipo_identificacion_id']
                : 0;

            $identification = $this->normalizeIdentification(
                $d['identificacion'] ?? null
            );

            $businessName = $this->nullableString(
                $d['razon_social'] ?? null
            );

            $address = $this->nullableString(
                $d['direccion'] ?? null
            );

            $email = $this->normalizeEmail(
                $d['email'] ?? null
            );

            $phone = $this->nullableString(
                $d['telefono'] ?? null
            );

            if ($identificationTypeId <= 0) {
                throw new RuntimeException(
                    'Debe seleccionar el tipo de identificación fiscal.'
                );
            }

            if ($identification === null) {
                throw new RuntimeException(
                    'La identificación fiscal es obligatoria.'
                );
            }

            if ($businessName === null) {
                throw new RuntimeException(
                    'La razón social es obligatoria.'
                );
            }

            if (
                $email !== null
                && !filter_var($email, FILTER_VALIDATE_EMAIL)
            ) {
                throw new RuntimeException(
                    'El correo de facturación no es válido.'
                );
            }

            $this->assertIdentificationType(
                $db,
                $identificationTypeId
            );

            $isPrincipal =
                isset($d['es_principal'])
                && (string) $d['es_principal'] === '1';

            if ($isPrincipal) {
                $stmt = $db->prepare(
                    'UPDATE propietarios_datos_fiscales
                 SET es_principal = 0
                 WHERE propietario_id = :owner_id
                   AND id <> :id'
                );

                $stmt->execute([
                    'owner_id' => $ownerId,
                    'id' => $fiscalDataId,
                ]);
            }

            /*
         * Si ya era principal, no permitimos quitarle la marca
         * simplemente desmarcando el checkbox. Para cambiar el
         * principal se debe marcar otro registro como principal.
         */
            if (
                (int) $old['es_principal'] === 1
                && !$isPrincipal
            ) {
                $isPrincipal = true;
            }

            $stmt = $db->prepare(
                'UPDATE propietarios_datos_fiscales
             SET
                tipo_identificacion_id = :identification_type,
                identificacion = :identification,
                razon_social = :business_name,
                direccion = :address,
                email = :email,
                telefono = :phone,
                es_principal = :principal
             WHERE id = :id
               AND propietario_id = :owner_id'
            );

            $stmt->execute([
                'identification_type' => $identificationTypeId,
                'identification' => $identification,
                'business_name' => $businessName,
                'address' => $address,
                'email' => $email,
                'phone' => $phone,
                'principal' => $isPrincipal ? 1 : 0,
                'id' => $fiscalDataId,
                'owner_id' => $ownerId,
            ]);

            (new AuditService())->log(
                $by,
                $env,
                'PROPIETARIOS',
                'EDITAR_DATO_FISCAL',
                'propietarios_datos_fiscales',
                $fiscalDataId,
                $old,
                [
                    'tipo_identificacion_id' => $identificationTypeId,
                    'identificacion' => $identification,
                    'razon_social' => $businessName,
                    'direccion' => $address,
                    'email' => $email,
                    'telefono' => $phone,
                    'es_principal' => $isPrincipal,
                ]
            );
        });
    }

    public function toggleFiscalData(
        int $ownerId,
        int $fiscalDataId,
        int $env,
        int $by
    ): void {
        Database::transaction(function (PDO $db) use (
            $ownerId,
            $fiscalDataId,
            $env,
            $by
        ) {
            $this->assertOwnerInEnvironment(
                $db,
                $ownerId,
                $env
            );

            $stmt = $db->prepare(
                'SELECT *
             FROM propietarios_datos_fiscales
             WHERE id = :id
               AND propietario_id = :owner_id
             LIMIT 1
             FOR UPDATE'
            );

            $stmt->execute([
                'id' => $fiscalDataId,
                'owner_id' => $ownerId,
            ]);

            $old = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$old) {
                throw new RuntimeException(
                    'Dato fiscal no encontrado.'
                );
            }

            $newStatus = (int) $old['activo'] === 1 ? 0 : 1;

            /*
         * No desactivamos el principal mientras existan otros
         * datos fiscales activos.
         */
            if (
                $newStatus === 0
                && (int) $old['es_principal'] === 1
            ) {
                $stmt = $db->prepare(
                    'SELECT COUNT(*)
                 FROM propietarios_datos_fiscales
                 WHERE propietario_id = :owner_id
                   AND activo = 1
                   AND id <> :id'
                );

                $stmt->execute([
                    'owner_id' => $ownerId,
                    'id' => $fiscalDataId,
                ]);

                if ((int) $stmt->fetchColumn() > 0) {
                    throw new RuntimeException(
                        'No puedes desactivar el dato fiscal principal mientras '
                            . 'existan otros datos fiscales activos. '
                            . 'Primero marca otro como principal.'
                    );
                }
            }

            $stmt = $db->prepare(
                'UPDATE propietarios_datos_fiscales
             SET activo = :active
             WHERE id = :id
               AND propietario_id = :owner_id'
            );

            $stmt->execute([
                'active' => $newStatus,
                'id' => $fiscalDataId,
                'owner_id' => $ownerId,
            ]);

            (new AuditService())->log(
                $by,
                $env,
                'PROPIETARIOS',
                $newStatus === 1
                    ? 'ACTIVAR_DATO_FISCAL'
                    : 'DESACTIVAR_DATO_FISCAL',
                'propietarios_datos_fiscales',
                $fiscalDataId,
                $old,
                [
                    'activo' => $newStatus,
                ]
            );
        });
    }

    private function assertOwnerInEnvironment(
        PDO $db,
        int $ownerId,
        int $env
    ): void {
        $stmt = $db->prepare(
            'SELECT p.id
         FROM propietarios p
         INNER JOIN propietarios_entornos pe
            ON pe.propietario_id = p.id
         WHERE p.id = :owner_id
           AND pe.entorno_id = :env
           AND pe.activo = 1
           AND p.activo = 1
           AND p.deleted_at IS NULL
         LIMIT 1'
        );

        $stmt->execute([
            'owner_id' => $ownerId,
            'env' => $env,
        ]);

        if (!$stmt->fetchColumn()) {
            throw new RuntimeException(
                'Propietario no encontrado en el entorno activo.'
            );
        }
    }


    private function assertIdentificationType(
        PDO $db,
        int $identificationTypeId
    ): void {
        $stmt = $db->prepare(
            'SELECT id
         FROM tipos_identificacion
         WHERE id = :id
         LIMIT 1'
        );

        $stmt->execute([
            'id' => $identificationTypeId,
        ]);

        if (!$stmt->fetchColumn()) {
            throw new RuntimeException(
                'El tipo de identificación seleccionado no existe.'
            );
        }
    }
}
