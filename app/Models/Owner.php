<?php
namespace App\Models;

use App\Core\Model;

class Owner extends Model
{
    public function countByEnvironment(int $environmentId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
             FROM propietarios_entornos pe
             INNER JOIN propietarios p ON p.id=pe.propietario_id
             WHERE pe.entorno_id=:entorno AND pe.activo=1
               AND p.activo=1 AND p.deleted_at IS NULL'
        );
        $stmt->execute(['entorno' => $environmentId]);
        return (int)$stmt->fetchColumn();
    }

    public function listByEnvironment(int $environmentId, string $search=''): array
    {
        $sql = "
            SELECT p.id,p.nombres,p.apellidos,p.email,p.telefono,p.celular,p.foto_path,
                   p.identificacion,pe.id AS propietario_entorno_id,COUNT(a.id) AS animales_count
            FROM propietarios_entornos pe
            INNER JOIN propietarios p ON p.id=pe.propietario_id
            LEFT JOIN animales a ON a.propietario_entorno_id=pe.id
                AND a.deleted_at IS NULL AND a.activo=1
            WHERE pe.entorno_id=:entorno AND pe.activo=1
              AND p.activo=1 AND p.deleted_at IS NULL
        ";
        $params = ['entorno' => $environmentId];

        if ($search !== '') {
            $sql .= " AND (p.nombres LIKE :q OR p.apellidos LIKE :q OR p.email LIKE :q OR p.identificacion LIKE :q)";
            $params['q'] = "%{$search}%";
        }

        $sql .= " GROUP BY p.id,pe.id ORDER BY p.apellidos,p.nombres LIMIT 100";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
