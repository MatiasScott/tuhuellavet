<?php

namespace App\Services;

use App\Core\Database;

class PermissionService
{
    public function hasGlobalSuperAdmin(int $userId): bool
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            "SELECT COUNT(*)
             FROM usuarios_roles_globales urg
             INNER JOIN roles r ON r.id=urg.rol_id
             WHERE urg.usuario_id=:usuario AND r.codigo='SUPER_ADMINISTRADOR' AND r.activo=1"
        );
        $stmt->execute(['usuario' => $userId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function rolesForEnvironment(int $userId, int $environmentId): array
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            "SELECT r.codigo
             FROM usuarios_entornos_roles uer
             INNER JOIN roles r ON r.id=uer.rol_id
             WHERE uer.usuario_id=:usuario AND uer.entorno_id=:entorno AND r.activo=1
             ORDER BY r.nombre"
        );
        $stmt->execute(['usuario' => $userId, 'entorno' => $environmentId]);
        return array_column($stmt->fetchAll(), 'codigo');
    }

    public function permissionsForEnvironment(int $userId, int $environmentId): array
    {
        $db = Database::connection();

        if ($this->hasGlobalSuperAdmin($userId)) {
            return array_column($db->query("SELECT codigo FROM permisos WHERE activo=1 ORDER BY codigo")->fetchAll(), 'codigo');
        }

        $stmt = $db->prepare(
            "SELECT DISTINCT p.codigo
             FROM usuarios_entornos_roles uer
             INNER JOIN roles r ON r.id=uer.rol_id AND r.activo=1
             INNER JOIN rol_permisos rp ON rp.rol_id=r.id
             INNER JOIN permisos p ON p.id=rp.permiso_id AND p.activo=1
             WHERE uer.usuario_id=:usuario AND uer.entorno_id=:entorno
             ORDER BY p.codigo"
        );
        $stmt->execute(['usuario' => $userId, 'entorno' => $environmentId]);
        return array_column($stmt->fetchAll(), 'codigo');
    }

    public function syncPermissions(): void
    {
        $db = Database::connection();

        $db->exec(
            "
        INSERT IGNORE INTO permisos (
            modulo_id,
            accion_id,
            codigo,
            descripcion
        )

        SELECT
            ma.modulo_id,
            ma.accion_id,
            CONCAT(
                LOWER(m.codigo),
                '.',
                LOWER(a.codigo)
            ),
            CONCAT(
                a.nombre,
                ' ',
                m.nombre
            )

        FROM modulo_acciones ma

        INNER JOIN modulos m
            ON m.id = ma.modulo_id

        INNER JOIN acciones_permiso a
            ON a.id = ma.accion_id
        "
        );
    }

    public function registerModule(string $code, string $name, array $actions = ['VER', 'CREAR', 'EDITAR', 'ELIMINAR']): int
    {
        $db = Database::connection();
        $code = strtoupper(trim($code));
        $stmt = $db->prepare('INSERT INTO modulos(codigo,nombre,activo) VALUES(:c,:n,1) ON DUPLICATE KEY UPDATE nombre=VALUES(nombre),activo=1');
        $stmt->execute(['c' => $code, 'n' => $name]);
        $stmt = $db->prepare('SELECT id FROM modulos WHERE codigo=:c');
        $stmt->execute(['c' => $code]);
        $mid = (int)$stmt->fetchColumn();
        $ins = $db->prepare('INSERT IGNORE INTO modulo_acciones(modulo_id,accion_id) SELECT :m,id FROM acciones_permiso WHERE codigo=:a');
        foreach ($actions as $a) $ins->execute(['m' => $mid, 'a' => strtoupper($a)]);
        $this->syncPermissions();
        return $mid;
    }
}
