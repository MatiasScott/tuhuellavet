<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

final class DashboardData extends Model
{
    public function roleAndContext(int $userId, int $empresaId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT ue.rol_id, r.nombre AS rol_nombre, e.tipo AS empresa_tipo FROM usuarios_empresas ue INNER JOIN roles r ON r.id = ue.rol_id INNER JOIN empresas e ON e.id = ue.empresa_id WHERE ue.usuario_id = :usuario_id AND ue.empresa_id = :empresa_id LIMIT 1');
        $stmt->execute([
            ':usuario_id' => $userId,
            ':empresa_id' => $empresaId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function widgetsByRoleAndContext(int $rolId, string $contexto): array
    {
        $stmt = $this->pdo->prepare('SELECT w.id, w.codigo, w.nombre, w.contexto, w.modulo_origen, dwr.orden FROM dashboard_widgets w INNER JOIN dashboard_widget_roles dwr ON dwr.widget_id = w.id WHERE dwr.rol_id = :rol_id AND dwr.visible = 1 AND w.activo = 1 AND (w.contexto = "global" OR w.contexto = :contexto) ORDER BY dwr.orden ASC, w.id ASC');
        $stmt->execute([
            ':rol_id' => $rolId,
            ':contexto' => $contexto,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function totalAnimales(int $empresaId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM animales WHERE empresa_id = :empresa_id');
        $stmt->execute([':empresa_id' => $empresaId]);

        return (int) $stmt->fetchColumn();
    }

    public function totalPropietarios(int $empresaId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM propietarios WHERE empresa_id = :empresa_id');
        $stmt->execute([':empresa_id' => $empresaId]);

        return (int) $stmt->fetchColumn();
    }

    public function consultasHoy(int $empresaId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM consultas WHERE empresa_id = :empresa_id AND DATE(fecha_consulta) = CURDATE()');
        $stmt->execute([':empresa_id' => $empresaId]);

        return (int) $stmt->fetchColumn();
    }

    public function hospitalizacionesActivas(int $empresaId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM hospitalizaciones WHERE empresa_id = :empresa_id AND estado = "activa"');
        $stmt->execute([':empresa_id' => $empresaId]);

        return (int) $stmt->fetchColumn();
    }

    public function vacunasProximas7Dias(int $empresaId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM animal_vacunas av INNER JOIN animales a ON a.id = av.animal_id WHERE a.empresa_id = :empresa_id AND av.proxima_aplicacion IS NOT NULL AND av.proxima_aplicacion BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)');
        $stmt->execute([':empresa_id' => $empresaId]);

        return (int) $stmt->fetchColumn();
    }

    public function examenesMes(int $empresaId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM examenes_laboratorio WHERE empresa_id = :empresa_id AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())');
        $stmt->execute([':empresa_id' => $empresaId]);

        return (int) $stmt->fetchColumn();
    }

    public function cirugiasMes(int $empresaId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM cirugias WHERE empresa_id = :empresa_id AND YEAR(fecha) = YEAR(CURDATE()) AND MONTH(fecha) = MONTH(CURDATE())');
        $stmt->execute([':empresa_id' => $empresaId]);

        return (int) $stmt->fetchColumn();
    }

    public function eventosTimeline7Dias(int $empresaId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM vw_historial_clinico_timeline WHERE empresa_id = :empresa_id AND fecha_evento >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
        $stmt->execute([':empresa_id' => $empresaId]);

        return (int) $stmt->fetchColumn();
    }
}