<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

class PatientService
{
    public function create(
        array $d,
        array $files,
        int $env,
        int $by
    ): int {
        return Database::transaction(function (PDO $db) use (
            $d,
            $files,
            $env,
            $by
        ) {
            // Validar especie y raza.
            $speciesId = (int) ($d['especie_id'] ?? 0);

            $this->validateSpecies($db, $speciesId);

            $breedId = !empty($d['raza_id'])
                ? (int) $d['raza_id']
                : null;

            $this->validateBreed($db, $breedId, $speciesId);

            // Validar propietario dentro del entorno activo.
            $ownerId = !empty($d['propietario_entorno_id'])
                ? (int) $d['propietario_entorno_id']
                : null;

            $this->validateOwner($db, $ownerId, $env);

            // Registrar paciente.
            $stmt = $db->prepare(
                'INSERT INTO animales (
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
                    observaciones,
                    activo
                ) VALUES (
                    :e,
                    :p,
                    :es,
                    :r,
                    :s,
                    :c,
                    :n,
                    :f,
                    :fa,
                    :co,
                    :m,
                    :a,
                    :o,
                    1
                )'
            );

            $stmt->execute([
                'e'  => $env,
                'p'  => $ownerId,
                'es' => $speciesId,
                'r'  => $breedId,
                's'  => !empty($d['sexo_id'])
                    ? (int) $d['sexo_id']
                    : null,
                'c'  => $this->nullableString($d['codigo'] ?? null),
                'n'  => $this->nullableString($d['nombre'] ?? null),
                'f'  => $this->nullableString(
                    $d['fecha_nacimiento'] ?? null
                ),
                'fa' => !empty($d['fecha_nacimiento_aproximada'])
                    ? 1
                    : 0,
                'co' => $this->nullableString($d['color'] ?? null),
                'm'  => $this->nullableString($d['microchip'] ?? null),
                'a'  => $this->nullableString($d['arete'] ?? null),
                'o'  => $this->nullableString($d['observaciones'] ?? null),
            ]);

            $id = (int) $db->lastInsertId();

            // Registrar peso inicial sin sobrescribir historial.
            if (
                isset($d['peso_kg'])
                && trim((string) $d['peso_kg']) !== ''
            ) {
                $this->insertWeight(
                    $db,
                    $id,
                    (float) $d['peso_kg'],
                    $by,
                    'REGISTRO_INICIAL',
                    'Peso inicial'
                );
            }

            // Registrar fotografía, si existe.
            if (
                isset($files['foto'])
                && ($files['foto']['error'] ?? UPLOAD_ERR_NO_FILE)
                === UPLOAD_ERR_OK
            ) {
                $file = $files['foto'];

                $mime = (new \finfo(FILEINFO_MIME_TYPE))
                    ->file($file['tmp_name']);

                if (
                    in_array(
                        $mime,
                        ['image/jpeg', 'image/png', 'image/webp'],
                        true
                    )
                ) {
                    $extension = match ($mime) {
                        'image/png'  => 'png',
                        'image/webp' => 'webp',
                        default      => 'jpg',
                    };

                    $filename = 'patient_'
                        . $id
                        . '_'
                        . bin2hex(random_bytes(8))
                        . '.'
                        . $extension;

                    $directory = STORAGE_PATH . '/uploads/patients';

                    if (!is_dir($directory)) {
                        if (
                            !mkdir($directory, 0775, true)
                            && !is_dir($directory)
                        ) {
                            throw new RuntimeException(
                                'No se pudo crear el directorio de fotografías.'
                            );
                        }
                    }

                    $destination = $directory . '/' . $filename;

                    if (
                        !move_uploaded_file(
                            $file['tmp_name'],
                            $destination
                        )
                    ) {
                        throw new RuntimeException(
                            'No se pudo guardar la fotografía del paciente.'
                        );
                    }

                    $photoStmt = $db->prepare(
                        'UPDATE animales
                         SET foto_principal_path = :path
                         WHERE id = :id
                           AND entorno_id = :env'
                    );

                    $photoStmt->execute([
                        'path' => $filename,
                        'id'   => $id,
                        'env'  => $env,
                    ]);
                }
            }

            // Mantener la auditoría existente.
            (new AuditService())->log(
                $by,
                $env,
                'PACIENTES',
                'CREAR',
                'animales',
                $id,
                null,
                ['especie_id' => $speciesId]
            );

            return $id;
        });
    }

    public function update(
        int $id,
        array $d,
        int $env,
        int $by
    ): void {
        $db = Database::connection();

        // 1. Buscar el paciente únicamente en el entorno activo.
        $stmt = $db->prepare(
            'SELECT *
             FROM animales
             WHERE id = :id
               AND entorno_id = :env
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute([
            'id'  => $id,
            'env' => $env,
        ]);

        $old = $stmt->fetch();

        if (!$old) {
            throw new RuntimeException(
                'Paciente no encontrado.'
            );
        }

        // 2. Validar propietario.
        $ownerId = !empty($d['propietario_entorno_id'])
            ? (int) $d['propietario_entorno_id']
            : null;

        $this->validateOwner($db, $ownerId, $env);

        // 3. Validar especie.
        $speciesId = (int) (
            $d['especie_id'] ?? $old['especie_id']
        );

        $this->validateSpecies($db, $speciesId);

        // 4. Validar raza y su relación con la especie.
        $breedId = !empty($d['raza_id'])
            ? (int) $d['raza_id']
            : null;

        $this->validateBreed(
            $db,
            $breedId,
            $speciesId
        );

        // 5. Actualizar los datos del paciente.
        // El entorno forma parte del WHERE para evitar
        // modificaciones entre entornos.
        $updateStmt = $db->prepare(
            'UPDATE animales
             SET
                propietario_entorno_id = :p,
                especie_id = :es,
                raza_id = :r,
                sexo_id = :s,
                codigo = :c,
                nombre = :n,
                fecha_nacimiento = :f,
                fecha_nacimiento_aproximada = :fa,
                color = :co,
                microchip = :m,
                arete = :a,
                observaciones = :o
             WHERE id = :id
               AND entorno_id = :env
               AND deleted_at IS NULL'
        );

        $updateStmt->execute([
            'p'   => $ownerId,
            'es'  => $speciesId,
            'r'   => $breedId,
            's'   => !empty($d['sexo_id'])
                ? (int) $d['sexo_id']
                : null,
            'c'   => $this->nullableString(
                $d['codigo'] ?? $old['codigo']
            ),
            'n'   => $this->nullableString(
                $d['nombre'] ?? $old['nombre']
            ),
            'f'   => $this->nullableString(
                $d['fecha_nacimiento'] ?? $old['fecha_nacimiento']
            ),
            'fa'  => !empty($d['fecha_nacimiento_aproximada'])
                ? 1
                : 0,
            'co'  => $this->nullableString(
                $d['color'] ?? $old['color']
            ),
            'm'   => $this->nullableString(
                $d['microchip'] ?? $old['microchip']
            ),
            'a'   => $this->nullableString(
                $d['arete'] ?? $old['arete']
            ),
            'o'   => $this->nullableString(
                $d['observaciones'] ?? $old['observaciones']
            ),
            'id'  => $id,
            'env' => $env,
        ]);

        // 6. Registrar auditoría.
        (new AuditService())->log(
            $by,
            $env,
            'PACIENTES',
            'EDITAR',
            'animales',
            $id,
            $old,
            $d
        );
    }

    public function addWeight(
        int $id,
        float $kg,
        int $env,
        int $by,
        string $obs = ''
    ): void {
        $db = Database::connection();

        // Verificar que el paciente pertenece al entorno.
        $stmt = $db->prepare(
            'SELECT 1
             FROM animales
             WHERE id = :id
               AND entorno_id = :env
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute([
            'id'  => $id,
            'env' => $env,
        ]);

        if (!$stmt->fetchColumn()) {
            throw new RuntimeException(
                'Paciente no encontrado.'
            );
        }

        // Insertar nuevo peso; nunca sobrescribir los anteriores.
        $this->insertWeight(
            $db,
            $id,
            $kg,
            $by,
            'MANUAL',
            $obs !== '' ? $obs : 'Registro manual'
        );

        (new AuditService())->log(
            $by,
            $env,
            'PACIENTES',
            'PESO',
            'animales_pesos',
            $id
        );
    }

    public function delete(
        int $id,
        int $env,
        int $by
    ): void {
        $db = Database::connection();

        // Eliminación lógica: conservar expediente e historial.
        $stmt = $db->prepare(
            'UPDATE animales
             SET activo = 0,
                 deleted_at = NOW()
             WHERE id = :id
               AND entorno_id = :env
               AND deleted_at IS NULL'
        );

        $stmt->execute([
            'id'  => $id,
            'env' => $env,
        ]);

        (new AuditService())->log(
            $by,
            $env,
            'PACIENTES',
            'ELIMINAR',
            'animales',
            $id
        );
    }

    /**
     * Verifica que la especie exista y esté activa.
     */
    private function validateSpecies(
        PDO $db,
        int $speciesId
    ): void {
        if ($speciesId <= 0) {
            throw new RuntimeException(
                'La especie es obligatoria.'
            );
        }

        $stmt = $db->prepare(
            'SELECT 1
             FROM especies
             WHERE id = :id
               AND activo = 1
             LIMIT 1'
        );

        $stmt->execute([
            'id' => $speciesId,
        ]);

        if (!$stmt->fetchColumn()) {
            throw new RuntimeException(
                'La especie seleccionada no es válida.'
            );
        }
    }

    /**
     * Verifica que la raza pertenezca a la especie.
     * La raza es opcional.
     */
    private function validateBreed(
        PDO $db,
        ?int $breedId,
        int $speciesId
    ): void {
        if ($breedId === null) {
            return;
        }

        $stmt = $db->prepare(
            'SELECT 1
             FROM razas
             WHERE id = :id
               AND especie_id = :species
               AND activo = 1
             LIMIT 1'
        );

        $stmt->execute([
            'id'      => $breedId,
            'species' => $speciesId,
        ]);

        if (!$stmt->fetchColumn()) {
            throw new RuntimeException(
                'La raza seleccionada no pertenece a la especie.'
            );
        }
    }

    /**
     * Verifica que el propietario esté vinculado
     * al entorno activo. El propietario es opcional.
     */
    private function validateOwner(
        PDO $db,
        ?int $ownerId,
        int $env
    ): void {
        if ($ownerId === null) {
            return;
        }

        $stmt = $db->prepare(
            'SELECT 1
             FROM propietarios_entornos
             WHERE id = :id
               AND entorno_id = :env
               AND activo = 1
             LIMIT 1'
        );

        $stmt->execute([
            'id'  => $ownerId,
            'env' => $env,
        ]);

        if (!$stmt->fetchColumn()) {
            throw new RuntimeException(
                'El propietario no pertenece al entorno.'
            );
        }
    }

    /**
     * Registra un peso nuevo.
     */
    private function insertWeight(
        PDO $db,
        int $id,
        float $kg,
        int $by,
        string $origin,
        string $obs
    ): void {
        if (!is_finite($kg) || $kg <= 0) {
            throw new RuntimeException(
                'El peso debe ser mayor a cero.'
            );
        }

        $stmt = $db->prepare(
            'INSERT INTO animales_pesos (
                animal_id,
                peso_kg,
                registrado_por,
                origen,
                observacion
             ) VALUES (
                :animal,
                :weight,
                :user,
                :origin,
                :observation
             )'
        );

        $stmt->execute([
            'animal'      => $id,
            'weight'      => $kg,
            'user'        => $by,
            'origin'      => $origin,
            'observation' => $obs,
        ]);
    }

    /**
     * Convierte cadenas vacías en NULL.
     */
    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
