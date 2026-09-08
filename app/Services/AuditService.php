<?php

namespace App\Services;

use App\Core\Database;

class AuditService
{
    public function log(?int $uid, ?int $eid, string $module, string $action, ?string $table = null, ?int $rid = null, ?array $before = null, ?array $after = null): void
    {
        $db = Database::connection();
        $s = $db->prepare('INSERT INTO auditoria (usuario_id,entorno_id,modulo,accion,tabla_afectada,registro_id,datos_anteriores,datos_nuevos,ip_address,user_agent) VALUES (:u,:e,:m,:a,:t,:r,:b,:n,:ip,:ua)');
        $s->execute(['u' => $uid, 'e' => $eid, 'm' => $module, 'a' => $action, 't' => $table, 'r' => $rid, 'b' => $before ? json_encode($before, JSON_UNESCAPED_UNICODE) : null, 'n' => $after ? json_encode($after, JSON_UNESCAPED_UNICODE) : null, 'ip' => $_SERVER['REMOTE_ADDR'] ?? null, 'ua' => $_SERVER['HTTP_USER_AGENT'] ?? null]);
    }
}
