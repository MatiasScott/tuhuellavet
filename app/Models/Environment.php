<?php

namespace App\Models;

use App\Core\Model;

class Environment extends Model
{
    public function forUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            "SELECT e.id,e.nombre,e.codigo,e.logo_path,e.color_primario,e.color_secundario,
                    e.es_productivo,e.permite_facturacion_real,
                    te.codigo AS tipo_codigo,te.nombre AS tipo_nombre,
                    ue.es_predeterminado
             FROM usuarios_entornos ue
             INNER JOIN entornos e ON e.id=ue.entorno_id
             INNER JOIN tipos_entorno te ON te.id=e.tipo_entorno_id
             WHERE ue.usuario_id=:usuario
               AND ue.activo=1
               AND e.activo=1
               AND e.deleted_at IS NULL
             ORDER BY ue.es_predeterminado DESC,e.nombre"
        );
        $stmt->execute(['usuario' => $userId]);
        return $stmt->fetchAll();
    }

    public function findForUser(int $userId, int $environmentId): ?array
    {
        foreach ($this->forUser($userId) as $environment) {
            if ((int)$environment['id'] === $environmentId) return $environment;
        }
        return null;
    }

    public function allActive(): array
    {
        return $this->db->query(
            "SELECT e.*,te.codigo AS tipo_codigo,te.nombre AS tipo_nombre
             FROM entornos e
             INNER JOIN tipos_entorno te ON te.id=e.tipo_entorno_id
             WHERE e.activo=1 AND e.deleted_at IS NULL
             ORDER BY e.nombre"
        )->fetchAll();
    }
}
