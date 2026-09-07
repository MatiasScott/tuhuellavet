<?php

namespace App\Services;

use App\Core\Database;

use PDO;
use RuntimeException;

class PatientService
{
    public function create(
        array $data,
        array $files,
        int $environmentId,
        int $createdBy
    ): int {
        return Database::transaction(
            function (PDO $db) use (
                $data,
                $files,
                $environmentId,
                $createdBy
            ) {
                $this->validate(
                    $db,
                    $data,
                    $environmentId
                );

                $photoPath = null;

                if (
                    !empty(
                        $files[
                            'foto'
                        ][
                            'tmp_name'
                        ]
                    )
                ) {
                    $photoPath
                        = $this->savePhoto(
                            $files[
                                'foto'
                            ]
                        );
                }

                $stmt = $db->prepare(
                    '
                    INSERT INTO animales
                    (
                        entorno_id,
                        propietario_entorno_id,
                        especie_id,
                        raza_id,
                        sexo_id,
                        codigo,
                        nombre,
                        fecha_nacimiento,
                        fecha_nacimiento_aproximada,
                        color,
                        microchip,
                        arete,
                        foto_principal_path,
                        observaciones,
                        activo
                    )
                    VALUES
                    (
                        :entorno,
                        :propietario,
                        :especie,
                        :raza,
                        :sexo,
                        :codigo,
                        :nombre,
                        :nacimiento,
                        :aproximada,
                        :color,
                        :microchip,
                        :arete,
                        :foto,
                        :observaciones,
                        1
                    )
                    '
                );

                $stmt->execute([
                    'entorno'
                        => $environmentId,

                    'propietario'
                        => !empty(
                            $data[
                                'propietario_entorno_id'
                            ]
                        )
                            ? (int)
                                $data[
                                    'propietario_entorno_id'
                                ]
                            : null,

                    'especie'
                        => (int)
                            $data[
                                'especie_id'
                            ],

                    'raza'
                        => !empty(
                            $data[
                                'raza_id'
                            ]
                        )
                            ? (int)
                                $data[
                                    'raza_id'
                                ]
                            : null,

                    'sexo'
                        => !empty(
                            $data[
                                'sexo_id'
                            ]
                        )
                            ? (int)
                                $data[
                                    'sexo_id'
                                ]
                            : null,

                    'codigo'
                        => trim(
                            $data[
                                'codigo'
                            ]
                            ?? ''
                        )
                            ?: null,

                    'nombre'
                        => trim(
                            $data[
                                'nombre'
                            ]
                            ?? ''
                        )
                            ?: null,

                    'nacimiento'
                        => !empty(
                            $data[
                                'fecha_nacimiento'
                            ]
                        )
                            ? $data[
                                'fecha_nacimiento'
                            ]
                            : null,

                    'aproximada'
                        => !empty(
                            $data[
                                'fecha_nacimiento_aproximada'
                            ]
                        )
                            ? 1
                            : 0,

                    'color'
                        => trim(
                            $data[
                                'color'
                            ]
                            ?? ''
                        )
                            ?: null,

                    'microchip'
                        => trim(
                            $data[
                                'microchip'
                            ]
                            ?? ''
                        )
                            ?: null,

                    'arete'
                        => trim(
                            $data[
                                'arete'
                            ]
                            ?? ''
                        )
                            ?: null,

                    'foto'
                        => $photoPath,

                    'observaciones'
                        => trim(
                            $data[
                                'observaciones'
                            ]
                            ?? ''
                        )
                            ?: null,
                ]);

                $patientId = (int)
                    $db->lastInsertId();

                /*
                 * Peso inicial.
                 */
                if (
                    isset(
                        $data[
                            'peso_kg'
                        ]
                    )
                    && $data[
                        'peso_kg'
                    ] !== ''
                ) {
                    $this->insertWeight(
                        $db,
                        $patientId,
                        (float)
                            $data[
                                'peso_kg'
                            ],
                        $createdBy,
                        'REGISTRO_INICIAL',
                        null
                    );
                }

                (new AuditService())
                    ->log(
                        $createdBy,
                        $environmentId,
                        'PACIENTES',
                        'CREAR',
                        'animales',
                        $patientId,
                        null,
                        [
                            'nombre'
                                => $data[
                                    'nombre'
                                ]
                                ?? null,

                            'especie_id'
                                => $data[
                                    'especie_id'
                                ]
                                ?? null,

                            'propietario_entorno_id'
                                => $data[
                                    'propietario_entorno_id'
                                ]
                                ?? null,
                        ]
                    );

                return $patientId;
            }
        );
    }


    public function update(
        int $patientId,
        array $data,
        array $files,
        int $environmentId,
        int $updatedBy
    ): void {
        Database::transaction(
            function (PDO $db) use (
                $patientId,
                $data,
                $files,
                $environmentId,
                $updatedBy
            ) {
                $this->validate(
                    $db,
                    $data,
                    $environmentId
                );

                $stmt = $db->prepare(
                    '
                    SELECT *

                    FROM animales

                    WHERE id = :id

                      AND entorno_id
                        = :entorno

                      AND deleted_at
                        IS NULL

                    LIMIT 1
                    '
                );

                $stmt->execute([
                    'id'
                        => $patientId,

                    'entorno'
                        => $environmentId,
                ]);

                $before
                    = $stmt->fetch();

                if (!$before) {
                    throw new RuntimeException(
                        'Paciente no encontrado.'
                    );
                }

                $photoPath
                    = $before[
                        'foto_principal_path'
                    ];

                if (
                    !empty(
                        $files[
                            'foto'
                        ][
                            'tmp_name'
                        ]
                    )
                ) {
                    $photoPath
                        = $this->savePhoto(
                            $files[
                                'foto'
                            ]
                        );
                }

                $stmt = $db->prepare(
                    '
                    UPDATE animales

                    SET
                        propietario_entorno_id
                            = :propietario,

                        especie_id
                            = :especie,

                        raza_id
                            = :raza,

                        sexo_id
                            = :sexo,

                        codigo
                            = :codigo,

                        nombre
                            = :nombre,

                        fecha_nacimiento
                            = :nacimiento,

                        fecha_nacimiento_aproximada
                            = :aproximada,

                        color
                            = :color,

                        microchip
                            = :microchip,

                        arete
                            = :arete,

                        foto_principal_path
                            = :foto,

                        observaciones
                            = :observaciones

                    WHERE id = :id

                      AND entorno_id
                        = :entorno
                    '
                );

                $stmt->execute([
                    'propietario'
                        => !empty(
                            $data[
                                'propietario_entorno_id'
                            ]
                        )
                            ? (int)
                                $data[
                                    'propietario_entorno_id'
                                ]
                            : null,

                    'especie'
                        => (int)
                            $data[
                                'especie_id'
                            ],

                    'raza'
                        => !empty(
                            $data[
                                'raza_id'
                            ]
                        )
                            ? (int)
                                $data[
                                    'raza_id'
                                ]
                            : null,

                    'sexo'
                        => !empty(
                            $data[
                                'sexo_id'
                            ]
                        )
                            ? (int)
                                $data[
                                    'sexo_id'
                                ]
                            : null,

                    'codigo'
                        => trim(
                            $data['codigo']
                            ?? ''
                        )
                            ?: null,

                    'nombre'
                        => trim(
                            $data['nombre']
                            ?? ''
                        )
                            ?: null,

                    'nacimiento'
                        => !empty(
                            $data[
                                'fecha_nacimiento'
                            ]
                        )
                            ? $data[
                                'fecha_nacimiento'
                            ]
                            : null,

                    'aproximada'
                        => !empty(
                            $data[
                                'fecha_nacimiento_aproximada'
                            ]
                        )
                            ? 1
                            : 0,

                    'color'
                        => trim(
                            $data['color']
                            ?? ''
                        )
                            ?: null,

                    'microchip'
                        => trim(
                            $data[
                                'microchip'
                            ]
                            ?? ''
                        )
                            ?: null,

                    'arete'
                        => trim(
                            $data['arete']
                            ?? ''
                        )
                            ?: null,

                    'foto'
                        => $photoPath,

                    'observaciones'
                        => trim(
                            $data[
                                'observaciones'
                            ]
                            ?? ''
                        )
                            ?: null,

                    'id'
                        => $patientId,

                    'entorno'
                        => $environmentId,
                ]);

                (new AuditService())
                    ->log(
                        $updatedBy,
                        $environmentId,
                        'PACIENTES',
                        'EDITAR',
                        'animales',
                        $patientId,
                        $before,
                        $data
                    );
            }
        );
    }


    public function addWeight(
        int $patientId,
        float $weight,
        ?string $observation,
        int $environmentId,
        int $createdBy
    ): void {
        if ($weight <= 0) {
            throw new RuntimeException(
                'El peso debe ser mayor a cero.'
            );
        }

        Database::transaction(
            function (PDO $db) use (
                $patientId,
                $weight,
                $observation,
                $environmentId,
                $createdBy
            ) {
                $stmt = $db->prepare(
                    '
                    SELECT id

                    FROM animales

                    WHERE id = :id

                      AND entorno_id
                        = :entorno

                      AND activo = 1

                      AND deleted_at
                        IS NULL

                    LIMIT 1
                    '
                );

                $stmt->execute([
                    'id'
                        => $patientId,

                    'entorno'
                        => $environmentId,
                ]);

                if (
                    !$stmt->fetchColumn()
                ) {
                    throw new RuntimeException(
                        'Paciente no encontrado.'
                    );
                }

                $this->insertWeight(
                    $db,
                    $patientId,
                    $weight,
                    $createdBy,
                    'ACTUALIZACION_MANUAL',
                    $observation
                );

                (new AuditService())
                    ->log(
                        $createdBy,
                        $environmentId,
                        'PACIENTES',
                        'ACTUALIZAR_PESO',
                        'animales_pesos',
                        $patientId,
                        null,
                        [
                            'peso_kg'
                                => $weight,
                        ]
                    );
            }
        );
    }


    public function delete(
        int $patientId,
        int $environmentId,
        int $deletedBy
    ): void {
        $db = Database::connection();

        $stmt = $db->prepare(
            '
            SELECT *

            FROM animales

            WHERE id = :id

              AND entorno_id
                = :entorno

              AND deleted_at
                IS NULL

            LIMIT 1
            '
        );

        $stmt->execute([
            'id'
                => $patientId,

            'entorno'
                => $environmentId,
        ]);

        $before = $stmt->fetch();

        if (!$before) {
            throw new RuntimeException(
                'Paciente no encontrado.'
            );
        }

        /*
         * Soft delete.
         * No destruimos historial clínico.
         */
        $stmt = $db->prepare(
            '
            UPDATE animales

            SET
                activo = 0,
                deleted_at = NOW()

            WHERE id = :id

              AND entorno_id
                = :entorno
            '
        );

        $stmt->execute([
            'id'
                => $patientId,

            'entorno'
                => $environmentId,
        ]);

        (new AuditService())
            ->log(
                $deletedBy,
                $environmentId,
                'PACIENTES',
                'ELIMINAR',
                'animales',
                $patientId,
                $before,
                null
            );
    }


    private function validate(
        PDO $db,
        array $data,
        int $environmentId
    ): void {
        if (
            empty(
                $data[
                    'especie_id'
                ]
            )
        ) {
            throw new RuntimeException(
                'La especie es obligatoria.'
            );
        }

        /*
         * Propietario debe pertenecer
         * al mismo entorno.
         */
        if (
            !empty(
                $data[
                    'propietario_entorno_id'
                ]
            )
        ) {
            $stmt = $db->prepare(
                '
                SELECT COUNT(*)

                FROM propietarios_entornos

                WHERE id = :id

                  AND entorno_id
                    = :entorno

                  AND activo = 1
                '
            );

            $stmt->execute([
                'id'
                    => (int)
                        $data[
                            'propietario_entorno_id'
                        ],

                'entorno'
                    => $environmentId,
            ]);

            if (
                (int)
                    $stmt->fetchColumn()
                === 0
            ) {
                throw new RuntimeException(
                    'El propietario seleccionado no pertenece al entorno actual.'
                );
            }
        }

        /*
         * La raza debe pertenecer
         * a la especie.
         */
        if (
            !empty(
                $data['raza_id']
            )
        ) {
            $stmt = $db->prepare(
                '
                SELECT COUNT(*)

                FROM razas

                WHERE id = :raza

                  AND especie_id
                    = :especie

                  AND activo = 1
                '
            );

            $stmt->execute([
                'raza'
                    => (int)
                        $data[
                            'raza_id'
                        ],

                'especie'
                    => (int)
                        $data[
                            'especie_id'
                        ],
            ]);

            if (
                (int)
                    $stmt->fetchColumn()
                === 0
            ) {
                throw new RuntimeException(
                    'La raza seleccionada no pertenece a la especie.'
                );
            }
        }
    }


    private function insertWeight(
        PDO $db,
        int $patientId,
        float $weight,
        int $createdBy,
        string $origin,
        ?string $observation
    ): void {
        if ($weight <= 0) {
            throw new RuntimeException(
                'El peso debe ser mayor a cero.'
            );
        }

        $stmt = $db->prepare(
            '
            INSERT INTO animales_pesos
            (
                animal_id,
                peso_kg,
                registrado_por,
                origen,
                fecha_registro,
                observacion
            )
            VALUES
            (
                :animal,
                :peso,
                :usuario,
                :origen,
                NOW(),
                :observacion
            )
            '
        );

        $stmt->execute([
            'animal'
                => $patientId,

            'peso'
                => $weight,

            'usuario'
                => $createdBy,

            'origen'
                => $origin,

            'observacion'
                => $observation,
        ]);
    }


    private function savePhoto(
        array $file
    ): string {
        if (
            ($file['error'] ?? UPLOAD_ERR_NO_FILE)
            !== UPLOAD_ERR_OK
        ) {
            throw new RuntimeException(
                'No se pudo cargar la fotografía.'
            );
        }

        if (
            ($file['size'] ?? 0)
            > 5 * 1024 * 1024
        ) {
            throw new RuntimeException(
                'La fotografía no puede superar 5 MB.'
            );
        }

        $finfo = new \finfo(
            FILEINFO_MIME_TYPE
        );

        $mime = $finfo->file(
            $file['tmp_name']
        );

        $extensions = [
            'image/jpeg'
                => 'jpg',

            'image/png'
                => 'png',

            'image/webp'
                => 'webp',
        ];

        if (
            !isset(
                $extensions[$mime]
            )
        ) {
            throw new RuntimeException(
                'Solo se permiten imágenes JPG, PNG o WEBP.'
            );
        }

        $directory
            = STORAGE_PATH
            . '/uploads/pacientes';

        if (
            !is_dir($directory)
        ) {
            mkdir(
                $directory,
                0775,
                true
            );
        }

        $filename
            = bin2hex(
                random_bytes(16)
            )
            . '.'
            . $extensions[$mime];

        $destination
            = $directory
            . '/'
            . $filename;

        if (
            !move_uploaded_file(
                $file['tmp_name'],
                $destination
            )
        ) {
            throw new RuntimeException(
                'No se pudo guardar la fotografía.'
            );
        }

        /*
         * Solo guardamos el nombre,
         * no una ruta absoluta.
         */
        return $filename;
    }
}