<?php

namespace App\Services;

use App\Core\Database;

class NotificationService
{
    public function pending(int $limit = 50): array
    {
        $limit = max(1, min($limit, 200));
        return Database::connection()->query("SELECT n.*,tn.codigo AS tipo_codigo,cn.codigo AS canal_codigo,p.email AS propietario_email,p.celular AS propietario_celular,u.email AS usuario_email,u.telefono AS usuario_telefono FROM notificaciones n JOIN tipos_notificacion tn ON tn.id=n.tipo_notificacion_id JOIN canales_notificacion cn ON cn.id=n.canal_id LEFT JOIN propietarios p ON p.id=n.destinatario_propietario_id LEFT JOIN usuarios u ON u.id=n.destinatario_usuario_id WHERE n.estado='PENDIENTE' AND (n.fecha_programada IS NULL OR n.fecha_programada<=NOW()) ORDER BY COALESCE(n.fecha_programada,n.created_at) LIMIT {$limit}")->fetchAll();
    }
    public function processBatch(int $limit = 50): array
    {
        $ok = 0;
        $failed = 0;
        foreach ($this->pending($limit) as $n) {
            try {
                $this->deliver($n);
                Database::connection()->prepare("UPDATE notificaciones SET estado='ENVIADA',fecha_envio=NOW(),ultimo_error=NULL WHERE id=:id")->execute(['id' => $n['id']]);
                $ok++;
            } catch (\Throwable $e) {
                Database::connection()->prepare("UPDATE notificaciones SET intentos=intentos+1,ultimo_error=:e,estado=IF(intentos+1>=5,'FALLIDA','PENDIENTE') WHERE id=:id")->execute(['e' => mb_substr($e->getMessage(), 0, 500), 'id' => $n['id']]);
                $failed++;
            }
        }
        return ['sent' => $ok, 'failed' => $failed];
    }
    private function deliver(array $n): void
    {
        if ($n['canal_codigo'] === 'INTERNA') return;
        if ($n['canal_codigo'] === 'EMAIL') {
            $to = $n['propietario_email'] ?? $n['usuario_email'] ?? null;
            if (!$to) throw new \RuntimeException('Destinatario sin correo.');
            $headers = 'From: ' . ($_ENV['MAIL_FROM_ADDRESS'] ?? 'noreply@localhost');
            if (!@mail($to, $n['asunto'] ?: 'Notificación Tu Huella Vet', $n['mensaje'], $headers)) throw new \RuntimeException('No fue posible enviar el correo.');
            return;
        }
        if ($n['canal_codigo'] === 'WHATSAPP') {
            $to = $n['propietario_celular'] ?? $n['usuario_telefono'] ?? null;
            $url = $_ENV['WHATSAPP_API_URL'] ?? '';
            $token = $_ENV['WHATSAPP_TOKEN'] ?? '';
            if (!$to || !$url || !$token) throw new \RuntimeException('WhatsApp no está configurado o falta número.');
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Content-Type: application/json'], CURLOPT_POSTFIELDS => json_encode(['to' => $to, 'message' => $n['mensaje']], JSON_UNESCAPED_UNICODE)]);
            $resp = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);
            if ($resp === false || $code < 200 || $code >= 300) throw new \RuntimeException('WhatsApp falló: ' . ($err ?: 'HTTP ' . $code));
            return;
        }
        throw new \RuntimeException('Canal no soportado.');
    }
}
