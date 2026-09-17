<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

class PatientService
{
    public function create(array $d, array $files, int $env, int $by): int
    {
        return Database::transaction(function (PDO $db) use ($d, $files, $env, $by) {
            $species = (int)($d['especie_id'] ?? 0);
            if (!$species) throw new RuntimeException('La especie es obligatoria.');
            $owner = !empty($d['propietario_entorno_id']) ? (int)$d['propietario_entorno_id'] : null;
            if ($owner) {
                $s = $db->prepare('SELECT 1 FROM propietarios_entornos WHERE id=:id AND entorno_id=:e AND activo=1');
                $s->execute(['id' => $owner, 'e' => $env]);
                if (!$s->fetchColumn()) throw new RuntimeException('El propietario no pertenece al entorno.');
            }
            $s = $db->prepare('INSERT INTO animales(entorno_id,propietario_entorno_id,especie_id,raza_id,sexo_id,codigo,nombre,fecha_nacimiento,fecha_nacimiento_aproximada,color,microchip,arete,observaciones,activo) VALUES(:e,:p,:es,:r,:s,:c,:n,:f,:fa,:co,:m,:a,:o,1)');
            $s->execute(['e' => $env, 'p' => $owner, 'es' => $species, 'r' => !empty($d['raza_id']) ? (int)$d['raza_id'] : null, 's' => !empty($d['sexo_id']) ? (int)$d['sexo_id'] : null, 'c' => trim((string)($d['codigo'] ?? '')) ?: null, 'n' => trim((string)($d['nombre'] ?? '')) ?: null, 'f' => trim((string)($d['fecha_nacimiento'] ?? '')) ?: null, 'fa' => !empty($d['fecha_nacimiento_aproximada']) ? 1 : 0, 'co' => trim((string)($d['color'] ?? '')) ?: null, 'm' => trim((string)($d['microchip'] ?? '')) ?: null, 'a' => trim((string)($d['arete'] ?? '')) ?: null, 'o' => trim((string)($d['observaciones'] ?? '')) ?: null]);
            $id = (int)$db->lastInsertId();
            if (!empty($d['peso_kg'])) $this->insertWeight($db, $id, (float)$d['peso_kg'], $by, 'REGISTRO_INICIAL', 'Peso inicial');
            if (isset($files['foto']) && ($files['foto']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $file = $files['foto'];
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
                if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
                    $ext = match ($mime) {
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                        default => 'jpg'
                    };
                    $name = 'patient_' . $id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                    $dir = STORAGE_PATH . '/uploads/patients';
                    if (!is_dir($dir)) mkdir($dir, 0775, true);
                    move_uploaded_file($file['tmp_name'], $dir . '/' . $name);
                    $s = $db->prepare('UPDATE animales SET foto_principal_path=:p WHERE id=:id');
                    $s->execute(['p' => $name, 'id' => $id]);
                }
            }
            (new AuditService())->log($by, $env, 'PACIENTES', 'CREAR', 'animales', $id, null, ['especie_id' => $species]);
            return $id;
        });
    }
    public function update(int $id, array $d, int $env, int $by): void
    {
        $db = Database::connection();
        $s = $db->prepare('SELECT * FROM animales WHERE id=:id AND entorno_id=:e AND deleted_at IS NULL');
        $s->execute(['id' => $id, 'e' => $env]);
        $old = $s->fetch();
        $owner = !empty($d['propietario_entorno_id'])
            ? (int) $d['propietario_entorno_id']
            : null;

        if ($owner !== null) {
            $check = $db->prepare(
                'SELECT 1
         FROM propietarios_entornos
         WHERE id = :id
           AND entorno_id = :entorno
           AND activo = 1
         LIMIT 1'
            );

            $check->execute([
                'p'      => $owner,
                'entorno' => $env,
            ]);

            if (!$check->fetchColumn()) {
                throw new RuntimeException(
                    'El propietario no pertenece al entorno.'
                );
            }
        };
        if (!$old) throw new RuntimeException('Paciente no encontrado.');
        $s = $db->prepare('UPDATE animales SET propietario_entorno_id=:p,especie_id=:es,raza_id=:r,sexo_id=:s,codigo=:c,nombre=:n,fecha_nacimiento=:f,fecha_nacimiento_aproximada=:fa,color=:co,microchip=:m,arete=:a,observaciones=:o WHERE id=:id');
        $s->execute(['p' => !empty($d['propietario_entorno_id']) ? (int)$d['propietario_entorno_id'] : null, 'es' => (int)($d['especie_id'] ?? $old['especie_id']), 'r' => !empty($d['raza_id']) ? (int)$d['raza_id'] : null, 's' => !empty($d['sexo_id']) ? (int)$d['sexo_id'] : null, 'c' => trim((string)($d['codigo'] ?? $old['codigo'])) ?: null, 'n' => trim((string)($d['nombre'] ?? $old['nombre'])) ?: null, 'f' => trim((string)($d['fecha_nacimiento'] ?? $old['fecha_nacimiento'])) ?: null, 'fa' => !empty($d['fecha_nacimiento_aproximada']) ? 1 : 0, 'co' => trim((string)($d['color'] ?? $old['color'])) ?: null, 'm' => trim((string)($d['microchip'] ?? $old['microchip'])) ?: null, 'a' => trim((string)($d['arete'] ?? $old['arete'])) ?: null, 'o' => trim((string)($d['observaciones'] ?? $old['observaciones'])) ?: null, 'id' => $id]);
        (new AuditService())->log($by, $env, 'PACIENTES', 'EDITAR', 'animales', $id, $old, $d);
    }
    public function addWeight(int $id, float $kg, int $env, int $by, string $obs = ''): void
    {
        $db = Database::connection();
        $s = $db->prepare('SELECT 1 FROM animales WHERE id=:id AND entorno_id=:e AND deleted_at IS NULL');
        $s->execute(['id' => $id, 'e' => $env]);
        if (!$s->fetchColumn()) throw new RuntimeException('Paciente no encontrado.');
        $this->insertWeight($db, $id, $kg, $by, 'MANUAL', $obs ?: 'Registro manual');
        (new AuditService())->log($by, $env, 'PACIENTES', 'PESO', 'animales_pesos', $id);
    }
    public function delete(int $id, int $env, int $by): void
    {
        $s = Database::connection()->prepare('UPDATE animales SET activo=0,deleted_at=NOW() WHERE id=:id AND entorno_id=:e');
        $s->execute(['id' => $id, 'e' => $env]);
        (new AuditService())->log($by, $env, 'PACIENTES', 'ELIMINAR', 'animales', $id);
    }
    private function insertWeight(PDO $db, int $id, float $kg, int $by, string $origin, string $obs): void
    {
        if ($kg <= 0) throw new RuntimeException('El peso debe ser mayor a cero.');
        $s = $db->prepare('INSERT INTO animales_pesos(animal_id,peso_kg,registrado_por,origen,observacion) VALUES(:a,:p,:u,:o,:ob)');
        $s->execute(['a' => $id, 'p' => $kg, 'u' => $by, 'o' => $origin, 'ob' => $obs]);
    }
}
