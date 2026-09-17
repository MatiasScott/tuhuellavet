<?php

namespace App\Models;

use App\Core\Model;

class Patient extends Model
{
    public function countByEnvironment(int $e): int
    {
        $s = $this->db->prepare('SELECT COUNT(*) FROM animales WHERE entorno_id=:e AND activo=1 AND deleted_at IS NULL');
        $s->execute(['e' => $e]);
        return (int)$s->fetchColumn();
    }
    public function recentByEnvironment(int $e, int $l = 5): array
    {
        $l = max(1, min($l, 20));
        $s = $this->db->prepare("SELECT a.id,a.nombre,a.foto_principal_path,es.nombre_comun AS especie,r.nombre AS raza FROM animales a JOIN especies es ON es.id=a.especie_id LEFT JOIN razas r ON r.id=a.raza_id WHERE a.entorno_id=:e AND a.activo=1 AND a.deleted_at IS NULL ORDER BY a.created_at DESC LIMIT {$l}");
        $s->execute(['e' => $e]);
        return $s->fetchAll();
    }
    public function allByEnvironment(int $e, string $q = ''): array
    {
        $sql = 'SELECT a.*,es.nombre_comun AS especie,r.nombre AS raza,sx.nombre AS sexo,p.nombres AS propietario_nombres,p.apellidos AS propietario_apellidos,(SELECT ap.peso_kg FROM animales_pesos ap WHERE ap.animal_id=a.id ORDER BY ap.fecha_registro DESC,ap.id DESC LIMIT 1) AS peso_actual FROM animales a JOIN especies es ON es.id=a.especie_id LEFT JOIN razas r ON r.id=a.raza_id LEFT JOIN sexos_animales sx ON sx.id=a.sexo_id LEFT JOIN propietarios_entornos pe ON pe.id=a.propietario_entorno_id LEFT JOIN propietarios p ON p.id=pe.propietario_id WHERE a.entorno_id=:e AND a.activo=1 AND a.deleted_at IS NULL';
        $p = ['e' => $e];
        if ($q !== '') {
            $sql .= ' AND CONCAT_WS(" ",a.nombre,a.codigo,es.nombre_comun,r.nombre,p.nombres,p.apellidos) LIKE :q';
            $p['q'] = '%' . $q . '%';
        }
        $s = $this->db->prepare($sql . ' ORDER BY a.nombre LIMIT 250');
        $s->execute($p);
        return $s->fetchAll();
    }
    public function find(int $id, int $e): ?array
    {
        $s = $this->db->prepare('SELECT a.*,es.nombre_comun AS especie,r.nombre AS raza,sx.nombre AS sexo,pe.propietario_id,p.nombres AS propietario_nombres,p.apellidos AS propietario_apellidos,p.email AS propietario_email,p.celular AS propietario_celular,(SELECT ap.peso_kg FROM animales_pesos ap WHERE ap.animal_id=a.id ORDER BY ap.fecha_registro DESC,ap.id DESC LIMIT 1) AS peso_actual FROM animales a JOIN especies es ON es.id=a.especie_id LEFT JOIN razas r ON r.id=a.raza_id LEFT JOIN sexos_animales sx ON sx.id=a.sexo_id LEFT JOIN propietarios_entornos pe ON pe.id=a.propietario_entorno_id LEFT JOIN propietarios p ON p.id=pe.propietario_id WHERE a.id=:id AND a.entorno_id=:e AND a.deleted_at IS NULL LIMIT 1');
        $s->execute(['id' => $id, 'e' => $e]);
        return $s->fetch() ?: null;
    }
    public function weights(int $id, int $environmentId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
            ap.*,
            CONCAT(u.nombres, " ", u.apellidos)
                AS registrado_por_nombre
         FROM animales_pesos ap
         INNER JOIN animales a
            ON a.id = ap.animal_id
         INNER JOIN usuarios u
            ON u.id = ap.registrado_por
         WHERE ap.animal_id = :animal
           AND a.entorno_id = :entorno
           AND a.deleted_at IS NULL
         ORDER BY
            ap.fecha_registro DESC,
            ap.id DESC'
        );

        $stmt->execute([
            'animal'  => $id,
            'entorno' => $environmentId,
        ]);

        return $stmt->fetchAll();
    }
}
