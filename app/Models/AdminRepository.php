<?php

namespace App\Models;

use App\Core\Model;

class AdminRepository extends Model
{
    public function users(int $environmentId): array
    {
        $stmt = $this->db->prepare(
            'SELECT
            u.id,
            u.nombres,
            u.apellidos,
            u.email,
            u.telefono,
            u.foto_path,
            u.activo,
            u.requiere_cambio_password,
            u.email_verificado_at,
            u.ultimo_login_at,
            ue.activo AS acceso_entorno_activo,
            GROUP_CONCAT(
                DISTINCT r.nombre
                ORDER BY r.nombre
                SEPARATOR ", "
            ) AS roles

         FROM usuarios u

         INNER JOIN usuarios_entornos ue
            ON ue.usuario_id = u.id
           AND ue.entorno_id = :entorno

         LEFT JOIN usuarios_entornos_roles uer
            ON uer.usuario_id = u.id
           AND uer.entorno_id = ue.entorno_id

         LEFT JOIN roles r
            ON r.id = uer.rol_id

         WHERE u.deleted_at IS NULL

         GROUP BY
            u.id,
            u.nombres,
            u.apellidos,
            u.email,
            u.telefono,
            u.foto_path,
            u.activo,
            u.requiere_cambio_password,
            u.email_verificado_at,
            u.ultimo_login_at,
            ue.activo

         ORDER BY
            u.apellidos,
            u.nombres'
        );

        $stmt->execute([
            'entorno' => $environmentId
        ]);

        return $stmt->fetchAll();
    }
    public function companies(): array
    {
        return $this->db->query('SELECT e.*,COUNT(en.id) AS entornos_count FROM empresas e LEFT JOIN entornos en ON en.empresa_id=e.id WHERE e.deleted_at IS NULL GROUP BY e.id ORDER BY e.nombre')->fetchAll();
    }
    public function roles(): array
    {
        return $this->db->query('SELECT * FROM roles WHERE activo=1 ORDER BY id')->fetchAll();
    }
    public function permissions(): array
    {
        return $this->db->query('SELECT p.id,p.codigo,m.nombre AS modulo,a.nombre AS accion FROM permisos p JOIN modulos m ON m.id=p.modulo_id JOIN acciones_permiso a ON a.id=p.accion_id WHERE p.activo=1 ORDER BY m.orden,a.id')->fetchAll();
    }
    public function rolePermissions(int $role): array
    {
        $s = $this->db->prepare('SELECT permiso_id FROM rol_permisos WHERE rol_id=:r');
        $s->execute(['r' => $role]);
        return array_map('intval', array_column($s->fetchAll(), 'permiso_id'));
    }
    public function audit(int $env, int $limit = 300): array
    {
        $s = $this->db->prepare('SELECT a.*,CONCAT(u.nombres," ",u.apellidos) AS usuario FROM auditoria a LEFT JOIN usuarios u ON u.id=a.usuario_id WHERE a.entorno_id=:e OR a.entorno_id IS NULL ORDER BY a.created_at DESC LIMIT ' . $limit);
        $s->execute(['e' => $env]);
        return $s->fetchAll();
    }
    public function userInEnvironment(int $userId, int $env): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT
            u.id,
            u.nombres,
            u.apellidos,
            u.email,
            u.telefono,
            u.activo,
            ue.activo AS acceso_entorno_activo,
            (
                SELECT COUNT(*)
                FROM usuarios_roles_globales urg
                WHERE urg.usuario_id = u.id
            ) AS roles_globales,
            (
                SELECT COUNT(*)
                FROM usuarios_entornos_roles uer
                WHERE uer.usuario_id = u.id
                  AND uer.entorno_id = :env_roles_count
            ) AS cantidad_roles,
            (
                SELECT uer.rol_id
                FROM usuarios_entornos_roles uer
                WHERE uer.usuario_id = u.id
                  AND uer.entorno_id = :env_roles_id
                ORDER BY uer.rol_id
                LIMIT 1
            ) AS rol_id
         FROM usuarios u
         INNER JOIN usuarios_entornos ue
            ON ue.usuario_id = u.id
           AND ue.entorno_id = :env
         WHERE u.id = :id
           AND u.deleted_at IS NULL
         LIMIT 1'
        );

        $stmt->execute([
            'env_roles_count' => $env,
            'env_roles_id' => $env,
            'env' => $env,
            'id' => $userId
        ]);

        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function environmentRoles(): array
    {
        return $this->db->query(
            "SELECT id, codigo, nombre
         FROM roles
         WHERE activo = 1
           AND es_global = 0
           AND codigo <> 'SUPER_ADMINISTRADOR'
         ORDER BY nombre"
        )->fetchAll(\PDO::FETCH_ASSOC);
    }
}
