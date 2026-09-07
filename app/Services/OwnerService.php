<?php

namespace App\Services;

use App\Core\Database;

use PDO;
use RuntimeException;

class OwnerService
{
    public function create(
        array $data,
        int $environmentId,
        int $createdBy
    ): array {
        return Database::transaction(
            function (PDO $db) use (
                $data,
                $environmentId,
                $createdBy
            ) {
                $email = trim(
                    strtolower(
                        $data['email']
                        ?? ''
                    )
                );

                $createAccess = !empty(
                    $data[
                        'crear_acceso'
                    ]
                );

                $userId = null;

                /*
                 * Si se solicita acceso,
                 * el correo es obligatorio.
                 */
                if (
                    $createAccess
                    && $email === ''
                ) {
                    throw new RuntimeException(
                        'Para crear acceso al sistema debes ingresar un correo.'
                    );
                }

                /*
                 * Crear o vincular usuario.
                 */
                if ($createAccess) {
                    $userId
                        = $this->resolveUser(
                            $db,
                            $data,
                            $email
                        );
                }

                /*
                 * Evitar identificación
                 * duplicada si existe.
                 */
                $identification
                    = trim(
                        $data[
                            'identificacion'
                        ]
                        ?? ''
                    );

                if (
                    $identification !== ''
                ) {
                    $stmt
                        = $db->prepare(
                            '
                            SELECT id

                            FROM propietarios

                            WHERE identificacion
                                = :identificacion

                              AND deleted_at
                                IS NULL

                            LIMIT 1
                            '
                        );

                    $stmt->execute([
                        'identificacion'
                            => $identification,
                    ]);

                    if (
                        $stmt->fetchColumn()
                    ) {
                        throw new RuntimeException(
                            'Ya existe un propietario con esta identificación.'
                        );
                    }
                }

                /*
                 * Crear propietario.
                 */
                $stmt
                    = $db->prepare(
                        '
                        INSERT INTO propietarios
                        (
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
                        )
                        VALUES
                        (
                            :usuario_id,
                            :tipo_identificacion_id,
                            :identificacion,
                            :nombres,
                            :apellidos,
                            :email,
                            :telefono,
                            :celular,
                            :direccion,
                            1
                        )
                        '
                    );

                $stmt->execute([
                    'usuario_id'
                        => $userId,

                    'tipo_identificacion_id'
                        => !empty(
                            $data[
                                'tipo_identificacion_id'
                            ]
                        )
                            ? (int)
                                $data[
                                    'tipo_identificacion_id'
                                ]
                            : null,

                    'identificacion'
                        => $identification
                            ?: null,

                    'nombres'
                        => trim(
                            $data['nombres']
                            ?? ''
                        ),

                    'apellidos'
                        => trim(
                            $data['apellidos']
                            ?? ''
                        )
                            ?: null,

                    'email'
                        => $email
                            ?: null,

                    'telefono'
                        => trim(
                            $data['telefono']
                            ?? ''
                        )
                            ?: null,

                    'celular'
                        => trim(
                            $data['celular']
                            ?? ''
                        )
                            ?: null,

                    'direccion'
                        => trim(
                            $data['direccion']
                            ?? ''
                        )
                            ?: null,
                ]);

                $ownerId
                    = (int)
                        $db->lastInsertId();

                /*
                 * Vincular al entorno.
                 */
                $stmt
                    = $db->prepare(
                        '
                        INSERT INTO
                            propietarios_entornos
                        (
                            propietario_id,
                            entorno_id,
                            activo
                        )
                        VALUES
                        (
                            :propietario,
                            :entorno,
                            1
                        )
                        '
                    );

                $stmt->execute([
                    'propietario'
                        => $ownerId,

                    'entorno'
                        => $environmentId,
                ]);

                /*
                 * Usuario Cliente
                 * en este entorno.
                 */
                if ($userId) {
                    $this->assignClientRole(
                        $db,
                        $userId,
                        $environmentId
                    );
                }

                (new AuditService())
                    ->log(
                        $createdBy,
                        $environmentId,
                        'PROPIETARIOS',
                        'CREAR',
                        'propietarios',
                        $ownerId,
                        null,
                        [
                            'nombres'
                                => $data[
                                    'nombres'
                                ]
                                ?? null,

                            'apellidos'
                                => $data[
                                    'apellidos'
                                ]
                                ?? null,

                            'email'
                                => $email,

                            'usuario_id'
                                => $userId,
                        ]
                    );

                return [
                    'owner_id'
                        => $ownerId,

                    'user_id'
                        => $userId,

                    'temporary_password'
                        => $data[
                            '_temporary_password'
                        ]
                        ?? null,
                ];
            }
        );
    }

    private function resolveUser(
        PDO $db,
        array &$data,
        string $email
    ): int {
        $stmt = $db->prepare(
            '
            SELECT id

            FROM usuarios

            WHERE LOWER(email)
                = LOWER(:email)

              AND deleted_at IS NULL

            LIMIT 1
            '
        );

        $stmt->execute([
            'email' => $email,
        ]);

        $existingUser
            = $stmt->fetchColumn();

        /*
         * Ya existe usuario.
         */
        if ($existingUser) {
            /*
             * Verificar que no esté
             * vinculado a otro propietario.
             */
            $check
                = $db->prepare(
                    '
                    SELECT id

                    FROM propietarios

                    WHERE usuario_id
                        = :usuario

                      AND deleted_at
                        IS NULL

                    LIMIT 1
                    '
                );

            $check->execute([
                'usuario'
                    => $existingUser,
            ]);

            if (
                $check->fetchColumn()
            ) {
                throw new RuntimeException(
                    'El usuario de ese correo ya está asociado a otro propietario.'
                );
            }

            return (int)
                $existingUser;
        }

        /*
         * Crear contraseña temporal.
         */
        $temporaryPassword
            = $this
                ->generateTemporaryPassword();

        $data[
            '_temporary_password'
        ] = $temporaryPassword;

        $stmt = $db->prepare(
            '
            INSERT INTO usuarios
            (
                nombres,
                apellidos,
                email,
                password_hash,
                requiere_cambio_password,
                activo
            )
            VALUES
            (
                :nombres,
                :apellidos,
                :email,
                :password,
                1,
                1
            )
            '
        );

        $stmt->execute([
            'nombres'
                => trim(
                    $data['nombres']
                    ?? ''
                ),

            'apellidos'
                => trim(
                    $data['apellidos']
                    ?? ''
                )
                    ?: '',

            'email'
                => $email,

            'password'
                => password_hash(
                    $temporaryPassword,
                    PASSWORD_DEFAULT
                ),
        ]);

        return (int)
            $db->lastInsertId();
    }

    private function assignClientRole(
        PDO $db,
        int $userId,
        int $environmentId
    ): void {
        /*
         * Vincular usuario al entorno.
         */
        $stmt = $db->prepare(
            '
            INSERT INTO usuarios_entornos
            (
                usuario_id,
                entorno_id,
                activo,
                es_predeterminado
            )
            VALUES
            (
                :usuario,
                :entorno,
                1,
                0
            )

            ON DUPLICATE KEY UPDATE
                activo = 1
            '
        );

        $stmt->execute([
            'usuario'
                => $userId,

            'entorno'
                => $environmentId,
        ]);

        /*
         * Buscar rol CLIENTE.
         */
        $roleId = (int)
            $db
                ->query(
                    '
                    SELECT id

                    FROM roles

                    WHERE codigo
                        = "CLIENTE"

                    LIMIT 1
                    '
                )
                ->fetchColumn();

        if (!$roleId) {
            throw new RuntimeException(
                'No existe el rol CLIENTE.'
            );
        }

        $stmt = $db->prepare(
            '
            INSERT IGNORE INTO
                usuarios_entornos_roles
            (
                usuario_id,
                entorno_id,
                rol_id
            )
            VALUES
            (
                :usuario,
                :entorno,
                :rol
            )
            '
        );

        $stmt->execute([
            'usuario'
                => $userId,

            'entorno'
                => $environmentId,

            'rol'
                => $roleId,
        ]);
    }

    private function generateTemporaryPassword(): string
    {
        return
            'Vet!'
            . bin2hex(
                random_bytes(4)
            )
            . '9a';
    }

    public function update(
        int $ownerId,
        int $environmentId,
        array $data,
        int $updatedBy
    ): void {
        Database::transaction(
            function (PDO $db) use (
                $ownerId,
                $environmentId,
                $data,
                $updatedBy
            ) {
                $stmt
                    = $db->prepare(
                        '
                        SELECT p.*

                        FROM propietarios p

                        INNER JOIN
                            propietarios_entornos pe

                            ON pe.propietario_id
                               = p.id

                        WHERE p.id = :id

                          AND pe.entorno_id
                            = :entorno

                          AND p.deleted_at
                            IS NULL

                        LIMIT 1
                        '
                    );

                $stmt->execute([
                    'id' => $ownerId,
                    'entorno'
                        => $environmentId,
                ]);

                $before
                    = $stmt->fetch();

                if (!$before) {
                    throw new RuntimeException(
                        'Propietario no encontrado.'
                    );
                }

                $stmt
                    = $db->prepare(
                        '
                        UPDATE propietarios

                        SET
                            tipo_identificacion_id
                                = :tipo,

                            identificacion
                                = :identificacion,

                            nombres
                                = :nombres,

                            apellidos
                                = :apellidos,

                            email
                                = :email,

                            telefono
                                = :telefono,

                            celular
                                = :celular,

                            direccion
                                = :direccion

                        WHERE id = :id
                        '
                    );

                $stmt->execute([
                    'tipo'
                        => !empty(
                            $data[
                                'tipo_identificacion_id'
                            ]
                        )
                            ? (int)
                                $data[
                                    'tipo_identificacion_id'
                                ]
                            : null,

                    'identificacion'
                        => trim(
                            $data[
                                'identificacion'
                            ]
                            ?? ''
                        )
                            ?: null,

                    'nombres'
                        => trim(
                            $data[
                                'nombres'
                            ]
                            ?? ''
                        ),

                    'apellidos'
                        => trim(
                            $data[
                                'apellidos'
                            ]
                            ?? ''
                        )
                            ?: null,

                    'email'
                        => trim(
                            strtolower(
                                $data[
                                    'email'
                                ]
                                ?? ''
                            )
                        )
                            ?: null,

                    'telefono'
                        => trim(
                            $data[
                                'telefono'
                            ]
                            ?? ''
                        )
                            ?: null,

                    'celular'
                        => trim(
                            $data[
                                'celular'
                            ]
                            ?? ''
                        )
                            ?: null,

                    'direccion'
                        => trim(
                            $data[
                                'direccion'
                            ]
                            ?? ''
                        )
                            ?: null,

                    'id'
                        => $ownerId,
                ]);

                (new AuditService())
                    ->log(
                        $updatedBy,
                        $environmentId,
                        'PROPIETARIOS',
                        'EDITAR',
                        'propietarios',
                        $ownerId,
                        $before,
                        $data
                    );
            }
        );
    }

    public function delete(
        int $ownerId,
        int $environmentId,
        int $deletedBy
    ): void {
        Database::transaction(
            function (PDO $db) use (
                $ownerId,
                $environmentId,
                $deletedBy
            ) {
                /*
                 * No eliminar si
                 * tiene animales activos.
                 */
                $stmt
                    = $db->prepare(
                        '
                        SELECT COUNT(*)

                        FROM animales a

                        INNER JOIN
                            propietarios_entornos pe

                            ON pe.id
                               = a.propietario_entorno_id

                        WHERE pe.propietario_id
                            = :propietario

                          AND pe.entorno_id
                            = :entorno

                          AND a.activo = 1

                          AND a.deleted_at
                            IS NULL
                        '
                    );

                $stmt->execute([
                    'propietario'
                        => $ownerId,

                    'entorno'
                        => $environmentId,
                ]);

                if (
                    (int)
                        $stmt->fetchColumn()
                    > 0
                ) {
                    throw new RuntimeException(
                        'No puedes eliminar un propietario que mantiene pacientes activos.'
                    );
                }

                $stmt
                    = $db->prepare(
                        '
                        UPDATE
                            propietarios_entornos

                        SET activo = 0

                        WHERE propietario_id
                            = :propietario

                          AND entorno_id
                            = :entorno
                        '
                    );

                $stmt->execute([
                    'propietario'
                        => $ownerId,

                    'entorno'
                        => $environmentId,
                ]);

                (new AuditService())
                    ->log(
                        $deletedBy,
                        $environmentId,
                        'PROPIETARIOS',
                        'ELIMINAR_ENTORNO',
                        'propietarios_entornos',
                        $ownerId
                    );
            }
        );
    }
}