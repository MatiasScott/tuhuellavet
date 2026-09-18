<?php

namespace App\Models;

use App\Core\Model;

class Owner extends Model
{
    public function countByEnvironment(int $e): int
    {
        $s = $this->db->prepare('SELECT COUNT(*) FROM propietarios_entornos pe JOIN propietarios p ON p.id=pe.propietario_id WHERE pe.entorno_id=:e AND pe.activo=1 AND p.activo=1 AND p.deleted_at IS NULL');
        $s->execute(['e' => $e]);
        return (int)$s->fetchColumn();
    }
    public function listByEnvironment(int $e, string $q = ''): array
    {
        $sql = 'SELECT p.*,pe.id AS propietario_entorno_id,COUNT(a.id) AS animales_count FROM propietarios_entornos pe JOIN propietarios p ON p.id=pe.propietario_id LEFT JOIN animales a ON a.propietario_entorno_id=pe.id AND a.activo=1 AND a.deleted_at IS NULL WHERE pe.entorno_id=:e AND pe.activo=1 AND p.activo=1 AND p.deleted_at IS NULL';
        $p = ['e' => $e];
        if ($q !== '') {
            $sql .= ' AND CONCAT_WS(" ",p.nombres,p.apellidos,p.email,p.identificacion,p.celular) LIKE :q';
            $p['q'] = '%' . $q . '%';
        }
        $sql .= ' GROUP BY p.id,pe.id ORDER BY p.apellidos,p.nombres LIMIT 200';
        $s = $this->db->prepare($sql);
        $s->execute($p);
        return $s->fetchAll();
    }
    public function options(int $e): array
    {
        $s = $this->db->prepare('SELECT pe.id,p.nombres,p.apellidos,p.identificacion FROM propietarios_entornos pe JOIN propietarios p ON p.id=pe.propietario_id WHERE pe.entorno_id=:e AND pe.activo=1 AND p.activo=1 AND p.deleted_at IS NULL ORDER BY p.apellidos,p.nombres');
        $s->execute(['e' => $e]);
        return $s->fetchAll();
    }

    /**
     * Obtener propietario por ID y entorno.
     */
    public function find(
        int $id,
        int $environmentId
    ): ?array {

        $stmt = $this->db->prepare(
            'SELECT
            p.*,
            pe.id AS propietario_entorno_id
         FROM propietarios p
         INNER JOIN propietarios_entornos pe
            ON pe.propietario_id = p.id
         WHERE p.id = :id
           AND pe.entorno_id = :env
           AND pe.activo = 1
           AND p.activo = 1
           AND p.deleted_at IS NULL
         LIMIT 1'
        );

        $stmt->execute([
            'id'  => $id,
            'env' => $environmentId,
        ]);

        return $stmt->fetch() ?: null;
    }


    /**
     * Obtener pacientes asociados al propietario
     * dentro del entorno activo.
     */
    public function patients(
        int $ownerEnvironmentId,
        int $environmentId
    ): array {

        $stmt = $this->db->prepare(
            'SELECT
            a.id,
            a.nombre,
            a.codigo,
            a.fecha_nacimiento,
            a.foto_principal_path,
            es.nombre_comun AS especie,
            r.nombre AS raza,
            sx.nombre AS sexo
         FROM animales a
         INNER JOIN especies es
            ON es.id = a.especie_id
         LEFT JOIN razas r
            ON r.id = a.raza_id
         LEFT JOIN sexos_animales sx
            ON sx.id = a.sexo_id
         INNER JOIN propietarios_entornos pe
            ON pe.id = a.propietario_entorno_id
         WHERE a.propietario_entorno_id = :owner_env
           AND a.entorno_id = :env
           AND pe.entorno_id = :env_owner
           AND a.activo = 1
           AND a.deleted_at IS NULL
         ORDER BY a.nombre'
        );

        $stmt->execute([
            'owner_env' => $ownerEnvironmentId,
            'env'       => $environmentId,
            'env_owner' => $environmentId,
        ]);

        return $stmt->fetchAll();
    }
    /**
     * Comprueba duplicados globales de propietarios.
     */
    public function existsByField(
        string $field,
        string $value,
        ?int $excludeId = null
    ): bool {
        $allowed = [
            'identificacion',
            'email',
            'celular',
        ];

        if (!in_array($field, $allowed, true)) {
            throw new \InvalidArgumentException(
                'Campo de validación no permitido.'
            );
        }

        $sql = "SELECT id
            FROM propietarios
            WHERE {$field} = :value";

        $params = [
            'value' => $value,
        ];

        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }
}
