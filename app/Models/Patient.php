<?php
namespace App\Models;

use App\Core\Model;

class Patient extends Model
{
    public function countByEnvironment(int $environmentId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM animales WHERE entorno_id=:entorno AND activo=1 AND deleted_at IS NULL'
        );
        $stmt->execute(['entorno' => $environmentId]);
        return (int)$stmt->fetchColumn();
    }

    public function recentByEnvironment(int $environmentId, int $limit=5): array
    {
        $limit = max(1,min($limit,20));
        $stmt = $this->db->prepare(
            "SELECT a.id,a.nombre,a.foto_principal_path,e.nombre_comun AS especie,
                    r.nombre AS raza,p.nombres AS propietario_nombres,p.apellidos AS propietario_apellidos
             FROM animales a
             INNER JOIN especies e ON e.id=a.especie_id
             LEFT JOIN razas r ON r.id=a.raza_id
             LEFT JOIN propietarios_entornos pe ON pe.id=a.propietario_entorno_id
             LEFT JOIN propietarios p ON p.id=pe.propietario_id
             WHERE a.entorno_id=:entorno AND a.activo=1 AND a.deleted_at IS NULL
             ORDER BY a.created_at DESC
             LIMIT {$limit}"
        );
        $stmt->execute(['entorno' => $environmentId]);
        return $stmt->fetchAll();
    }

    public function allByEnvironment(int $environmentId, string $search=''): array
    {
        $sql = "
            SELECT a.id,a.codigo,a.nombre,a.fecha_nacimiento,a.color,a.foto_principal_path,
                   e.nombre_comun AS especie,r.nombre AS raza,s.nombre AS sexo,
                   p.nombres AS propietario_nombres,p.apellidos AS propietario_apellidos,
                   (SELECT ap.peso_kg FROM animales_pesos ap WHERE ap.animal_id=a.id
                    ORDER BY ap.fecha_registro DESC,ap.id DESC LIMIT 1) AS peso_actual
            FROM animales a
            INNER JOIN especies e ON e.id=a.especie_id
            LEFT JOIN razas r ON r.id=a.raza_id
            LEFT JOIN sexos_animales s ON s.id=a.sexo_id
            LEFT JOIN propietarios_entornos pe ON pe.id=a.propietario_entorno_id
            LEFT JOIN propietarios p ON p.id=pe.propietario_id
            WHERE a.entorno_id=:entorno AND a.activo=1 AND a.deleted_at IS NULL
        ";
        $params = ['entorno' => $environmentId];

        if ($search !== '') {
            $sql .= " AND (a.nombre LIKE :q OR a.codigo LIKE :q OR e.nombre_comun LIKE :q OR r.nombre LIKE :q OR p.nombres LIKE :q OR p.apellidos LIKE :q)";
            $params['q'] = "%{$search}%";
        }

        $sql .= " ORDER BY a.nombre LIMIT 100";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
