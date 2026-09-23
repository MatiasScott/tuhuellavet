<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

class AdminService
{
    public function createUser(array $d, int $env, int $by): array
    {
        return Database::transaction(function (PDO $db) use ($d, $env, $by) {
            $email = trim((string)($d['email'] ?? ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL))
                throw new RuntimeException('Correo inválido.');
            $pass = bin2hex(random_bytes(32));
            if (strlen($pass) < 8) $pass = 'Vet#' . bin2hex(random_bytes(4));
            $db->prepare(
                'INSERT INTO usuarios(nombres,apellidos,email,telefono,password_hash,requiere_cambio_password,activo) 
                VALUES(:n,:a,:e,:t,:p,1,1)'
            )
                ->execute([
                    'n' => trim((string)$d['nombres']),
                    'a' => trim((string)$d['apellidos']),
                    'e' => $email,
                    't' => trim((string)($d['telefono'] ?? '')) ?: null,
                    'p' => password_hash($pass, PASSWORD_DEFAULT)
                ]);
            $id = (int)$db->lastInsertId();
            $role = (int)($d['rol_id'] ?? 0);
            if ($role && empty(auth_user()['is_super_admin'])) {
                $rs = $db->prepare(
                    'SELECT codigo 
                    FROM roles 
                    WHERE id=:r'
                );
                $rs->execute(['r' => $role]);
                if ($rs->fetchColumn() !== 'CLIENTE') throw new RuntimeException('El Administrador solo puede crear usuarios con rol Cliente.');
            }
            if ($role) {
                $db->prepare('INSERT INTO usuarios_entornos(usuario_id,entorno_id,activo) VALUES(:u,:e,1)')->execute(['u' => $id, 'e' => $env]);
                $db->prepare('INSERT INTO usuarios_entornos_roles(usuario_id,entorno_id,rol_id) VALUES(:u,:e,:r)')->execute(['u' => $id, 'e' => $env, 'r' => $role]);
            }
            (new AuditService())->log($by, $env, 'USUARIOS', 'CREAR', 'usuarios', $id);
            return ['id' => $id];
        });
    }

    public function saveRolePermissions(int $role, array $permissionIds, int $env, int $by): void
    {
        Database::transaction(function (PDO $db) use ($role, $permissionIds, $env, $by) {
            $s = $db->prepare('SELECT protegido,codigo 
            FROM roles 
            WHERE id=:r');
            $s->execute(['r' => $role]);
            $rr = $s->fetch();
            if (!$rr)
                throw new RuntimeException('Rol no encontrado.');
            if ($rr['codigo'] === 'SUPER_ADMINISTRADOR')
                throw new RuntimeException('El Super Administrador no requiere matriz de permisos.');
            $db->prepare(
                'DELETE FROM rol_permisos 
                WHERE rol_id=:r'
            )->execute(['r' => $role]);
            $i = $db->prepare(
                'INSERT INTO rol_permisos(rol_id,permiso_id) 
                VALUES(:r,:p)'
            );
            foreach (array_unique(array_map('intval', $permissionIds)) as $p) if ($p > 0) $i->execute(['r' => $role, 'p' => $p]);
            (new AuditService())->log($by, $env, 'PERMISOS', 'ASIGNAR', 'rol_permisos', $role);
        });
    }

    public function createCompany(array $d, int $by): int
    {
        $db = Database::connection();
        $db->prepare(
            'INSERT INTO empresas(nombre,nombre_comercial,razon_social,identificacion_fiscal,email,telefono,direccion,activo) 
            VALUES(:n,:nc,:r,:i,:e,:t,:d,1)'
        )
            ->execute(
                [
                    'n' => trim((string)$d['nombre']),
                    'nc' => trim((string)($d['nombre_comercial'] ?? '')) ?: null,
                    'r' => trim((string)($d['razon_social'] ?? '')) ?: null,
                    'i' => trim((string)($d['identificacion_fiscal'] ?? '')) ?: null,
                    'e' => trim((string)($d['email'] ?? '')) ?: null,
                    't' => trim((string)($d['telefono'] ?? '')) ?: null,
                    'd' => trim((string)($d['direccion'] ?? '')) ?: null
                ]
            );
        $id = (int)$db->lastInsertId();
        (new AuditService())->log($by, null, 'EMPRESAS', 'CREAR', 'empresas', $id);
        return $id;
    }

    public function createRole(array $d, int $env, int $by): int
    {
        $db = Database::connection();
        $code = strtoupper(trim((string)($d['codigo'] ?? '')));
        $name = trim((string)($d['nombre'] ?? ''));
        if (!$code || !$name)
            throw new RuntimeException('Código y nombre son obligatorios.');
        $db->prepare(
            'INSERT INTO roles(codigo,nombre,descripcion,es_global,protegido,activo) 
            VALUES(:c,:n,:d,0,0,1)'
        )
            ->execute(
                [
                    'c' => $code,
                    'n' => $name,
                    'd' => trim((string)($d['descripcion'] ?? '')) ?: null
                ]
            );
        $id = (int)$db->lastInsertId();
        (new AuditService())->log($by, $env, 'ROLES', 'CREAR', 'roles', $id);
        return $id;
    }

    public function createEnvironment(array $d, int $by): int
    {
        $db = Database::connection();
        $company = (int)($d['empresa_id'] ?? 0);
        $type = (int)($d['tipo_entorno_id'] ?? 0);
        if (!$company || !$type)
            throw new RuntimeException('Empresa y tipo son obligatorios.');
        $db->prepare(
            'INSERT INTO entornos(empresa_id,tipo_entorno_id,nombre,codigo,descripcion,es_productivo,permite_facturacion_real,activo) 
            VALUES(:e,:t,:n,:c,:d,:p,:f,1)'
        )
            ->execute(
                [
                    'e' => $company,
                    't' => $type,
                    'n' => trim((string)$d['nombre']),
                    'c' => strtoupper(trim((string)$d['codigo'])),
                    'd' => trim((string)($d['descripcion'] ?? '')) ?: null,
                    'p' => !empty($d['es_productivo']) ? 1 : 0,
                    'f' => !empty($d['permite_facturacion_real']) ? 1 : 0
                ]
            );
        $id = (int)$db->lastInsertId();
        (new AuditService())->log($by, null, 'EMPRESAS', 'CREAR_ENTORNO', 'entornos', $id);
        return $id;
    }

    public function updateUser(
        int $userId,
        array $data,
        int $env,
        int $by
    ): void {

        Database::transaction(function (PDO $db) use (
            $userId,
            $data,
            $env,
            $by
        ) {

            $stmt = $db->prepare(
                'SELECT
                u.id,
                u.nombres,
                u.apellidos,
                u.email,
                u.telefono,
                u.activo
             FROM usuarios u
             INNER JOIN usuarios_entornos ue
                ON ue.usuario_id = u.id
               AND ue.entorno_id = :env
             WHERE u.id = :id
               AND u.deleted_at IS NULL
             LIMIT 1
             FOR UPDATE'
            );

            $stmt->execute([
                'env' => $env,
                'id' => $userId
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                throw new RuntimeException(
                    'El usuario no existe en este entorno.'
                );
            }

            $isSuperAdmin = !empty(auth_user()['is_super_admin']);

            // No permitir modificar cuentas con roles globales
            // desde la administración de un entorno.
            $stmt = $db->prepare(
                'SELECT COUNT(*)
             FROM usuarios_roles_globales
             WHERE usuario_id = :id'
            );

            $stmt->execute(['id' => $userId]);

            $hasGlobalRoles = (int) $stmt->fetchColumn() > 0;

            $isOwnAccount = $userId === $by;

            $isGlobalProfileEdit = (
                $hasGlobalRoles &&
                $isSuperAdmin &&
                $isOwnAccount
            );

            if ($hasGlobalRoles && !$isGlobalProfileEdit) {

                throw new RuntimeException(
                    'Las cuentas con roles globales deben administrarse '
                        . 'desde la administración global.'
                );
            }

            $names = trim((string) ($data['nombres'] ?? ''));
            $lastNames = trim((string) ($data['apellidos'] ?? ''));
            $email = (string) $user['email'];
            $phone = trim((string) ($data['telefono'] ?? ''));

            if ($names === '' || $lastNames === '') {
                throw new RuntimeException(
                    'Los nombres y apellidos son obligatorios.'
                );
            }

            if (
                mb_strlen($names) > 120 ||
                mb_strlen($lastNames) > 120
            ) {
                throw new RuntimeException(
                    'Los nombres o apellidos exceden la longitud permitida.'
                );
            }

            if (
                !filter_var($email, FILTER_VALIDATE_EMAIL) ||
                strlen($email) > 180
            ) {
                throw new RuntimeException(
                    'El correo electrónico no es válido.'
                );
            }

            if (mb_strlen($phone) > 30) {
                throw new RuntimeException(
                    'El teléfono excede la longitud permitida.'
                );
            }

            if ($isGlobalProfileEdit) {

                /*
     * Actualizar exclusivamente los datos personales.
     *
     * No utilizamos:
     * - email
     * - activo
     * - rol_id
     * - password_hash
     */

                $stmt = $db->prepare(
                    'UPDATE usuarios
         SET
            nombres = :nombres,
            apellidos = :apellidos,
            telefono = :telefono
         WHERE id = :id
           AND activo = 1
           AND deleted_at IS NULL'
                );

                $stmt->execute([
                    'nombres' => $names,
                    'apellidos' => $lastNames,
                    'telefono' => $phone !== '' ? $phone : null,
                    'id' => $userId
                ]);

                (new AuditService())->log(
                    $by,
                    $env,
                    'USUARIOS',
                    'EDITAR_PERFIL_GLOBAL',
                    'usuarios',
                    $userId
                );

                return;
            }

            $active = !empty($data['activo']) ? 1 : 0;

            if ($userId === $by && $active === 0) {
                throw new RuntimeException(
                    'No puedes desactivar tu propia cuenta.'
                );
            }

            // Un administrador convencional no puede editar
            // cuentas que tengan roles distintos de CLIENTE.
            if (!$isSuperAdmin) {

                $stmt = $db->prepare(
                    "SELECT COUNT(*)
                 FROM usuarios_entornos_roles uer
                 INNER JOIN roles r
                    ON r.id = uer.rol_id
                 WHERE uer.usuario_id = :id
                   AND uer.entorno_id = :env
                   AND r.codigo <> 'CLIENTE'"
                );

                $stmt->execute([
                    'id' => $userId,
                    'env' => $env
                ]);

                if ((int) $stmt->fetchColumn() > 0) {
                    throw new RuntimeException(
                        'Solo puedes editar usuarios con rol Cliente.'
                    );
                }
            }

            $stmt = $db->prepare(
                'UPDATE usuarios
             SET nombres = :nombres,
                 apellidos = :apellidos,
                 email = :email,
                 telefono = :telefono,
                 activo = :activo
             WHERE id = :id'
            );

            $stmt->execute([
                'nombres' => $names,
                'apellidos' => $lastNames,
                'email' => $email,
                'telefono' => $phone !== '' ? $phone : null,
                'activo' => $active,
                'id' => $userId
            ]);

            // La asignación de roles se realiza únicamente
            // cuando el formulario envía un rol válido.
            $roleId = (int) ($data['rol_id'] ?? 0);

            if ($roleId > 0) {

                $stmt = $db->prepare(
                    'SELECT id, codigo
                 FROM roles
                 WHERE id = :id
                   AND activo = 1
                   AND es_global = 0
                   AND codigo <> :super_code
                 LIMIT 1'
                );

                $stmt->execute([
                    'id' => $roleId,
                    'super_code' => 'SUPER_ADMINISTRADOR'
                ]);

                $role = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$role) {
                    throw new RuntimeException(
                        'El rol seleccionado no está permitido.'
                    );
                }

                if (
                    !$isSuperAdmin &&
                    $role['codigo'] !== 'CLIENTE'
                ) {
                    throw new RuntimeException(
                        'El Administrador solo puede asignar el rol Cliente.'
                    );
                }

                $stmt = $db->prepare(
                    'SELECT rol_id
                 FROM usuarios_entornos_roles
                 WHERE usuario_id = :id
                   AND entorno_id = :env
                 FOR UPDATE'
                );

                $stmt->execute([
                    'id' => $userId,
                    'env' => $env
                ]);

                $currentRoles = $stmt->fetchAll(
                    PDO::FETCH_COLUMN
                );

                if (count($currentRoles) > 1) {
                    throw new RuntimeException(
                        'Este usuario tiene varios roles en el entorno. '
                            . 'La asignación múltiple debe editarse '
                            . 'desde un formulario específico.'
                    );
                }

                if ($userId === $by) {
                    $currentRole = $currentRoles
                        ? (int) $currentRoles[0]
                        : 0;

                    if ($currentRole !== $roleId) {
                        throw new RuntimeException(
                            'No puedes modificar tu propio rol.'
                        );
                    }
                }

                if (!$currentRoles) {

                    $stmt = $db->prepare(
                        'INSERT INTO usuarios_entornos_roles
                        (usuario_id, entorno_id, rol_id)
                     VALUES (:id, :env, :role)'
                    );

                    $stmt->execute([
                        'id' => $userId,
                        'env' => $env,
                        'role' => $roleId
                    ]);
                } elseif ((int) $currentRoles[0] !== $roleId) {

                    $stmt = $db->prepare(
                        'UPDATE usuarios_entornos_roles
                     SET rol_id = :role
                     WHERE usuario_id = :id
                       AND entorno_id = :env'
                    );

                    $stmt->execute([
                        'role' => $roleId,
                        'id' => $userId,
                        'env' => $env
                    ]);
                }
            }

            (new AuditService())->log(
                $by,
                $env,
                'USUARIOS',
                'EDITAR',
                'usuarios',
                $userId
            );
        });
    }

    public function resendUserInvitation(
        int $userId,
        int $env,
        int $by
    ): void {
        if ($userId <= 0 || $env <= 0 || $by <= 0) {
            throw new RuntimeException(
                'La solicitud de invitación no es válida.'
            );
        }

        $db = Database::connection();

        $stmt = $db->prepare(
            'SELECT
            u.id,
            u.activo,
            ue.activo AS acceso_entorno_activo
         FROM usuarios u
         INNER JOIN usuarios_entornos ue
            ON ue.usuario_id = u.id
           AND ue.entorno_id = :env
         WHERE u.id = :id
           AND u.deleted_at IS NULL
         LIMIT 1'
        );

        $stmt->execute([
            'env' => $env,
            'id' => $userId
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new RuntimeException(
                'El usuario no pertenece al entorno activo.'
            );
        }

        if (
            (int) $user['activo'] !== 1 ||
            (int) $user['acceso_entorno_activo'] !== 1
        ) {
            throw new RuntimeException(
                'No se pueden enviar invitaciones a usuarios inactivos.'
            );
        }

        // Proteger cuentas con roles globales.
        $stmt = $db->prepare(
            'SELECT COUNT(*)
         FROM usuarios_roles_globales
         WHERE usuario_id = :id'
        );

        $stmt->execute([
            'id' => $userId
        ]);

        if ((int) $stmt->fetchColumn() > 0) {
            throw new RuntimeException(
                'Las cuentas con roles globales deben gestionarse '
                    . 'desde la administración global.'
            );
        }

        // Los administradores convencionales solo pueden
        // gestionar usuarios cuyo único rol sea CLIENTE.
        if (empty(auth_user()['is_super_admin'])) {

            $stmt = $db->prepare(
                'SELECT
                COUNT(*) AS total_roles,
                COALESCE(
                    SUM(CASE WHEN r.codigo = :codigo THEN 1 ELSE 0 END),
                    0
                ) AS roles_cliente
             FROM usuarios_entornos_roles uer
             INNER JOIN roles r
                ON r.id = uer.rol_id
             WHERE uer.usuario_id = :id
               AND uer.entorno_id = :env'
            );

            $stmt->execute([
                'codigo' => 'CLIENTE',
                'id' => $userId,
                'env' => $env
            ]);

            $roles = $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                (int) $roles['total_roles'] !== 1 ||
                (int) $roles['roles_cliente'] !== 1
            ) {
                throw new RuntimeException(
                    'Solo puedes reenviar invitaciones a usuarios Cliente.'
                );
            }
        }

        $stmt = $db->prepare(
            'SELECT COUNT(*)
     FROM password_reset_tokens
     WHERE usuario_id = :id
       AND created_at >= DATE_SUB(
           NOW(),
           INTERVAL 2 MINUTE
       )'
        );

        $stmt->execute([
            'id' => $userId
        ]);

        if ((int) $stmt->fetchColumn() > 0) {
            throw new RuntimeException(
                'Ya se generó un enlace recientemente. '
                    . 'Espera dos minutos antes de solicitar otro.'
            );
        }

        // El correo se envía fuera de una transacción
        // para evitar mantener bloqueos durante la conexión SMTP.
        (new UserInvitationService())->send($userId);

        (new AuditService())->log(
            $by,
            $env,
            'USUARIOS',
            'REENVIAR_INVITACION',
            'usuarios',
            $userId
        );
    }
}
