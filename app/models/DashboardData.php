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

    public function pacientesRecientes(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, nombre, created_at FROM animales WHERE empresa_id = :empresa_id ORDER BY id DESC LIMIT 8');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function vacunasProximasDetalle(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT a.nombre AS animal, COALESCE(cv.nombre, v.nombre, "Vacuna") AS vacuna, av.proxima_aplicacion FROM animal_vacunas av INNER JOIN animales a ON a.id = av.animal_id LEFT JOIN catalogo_vacunas cv ON cv.id = av.catalogo_vacuna_id LEFT JOIN vacunas v ON v.id = av.vacuna_id WHERE a.empresa_id = :empresa_id AND av.proxima_aplicacion IS NOT NULL AND av.proxima_aplicacion BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 15 DAY) ORDER BY av.proxima_aplicacion ASC LIMIT 8');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function hospitalizacionesActivasDetalle(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT h.id, a.nombre AS animal, h.fecha_ingreso, h.estado FROM hospitalizaciones h INNER JOIN animales a ON a.id = h.animal_id WHERE h.empresa_id = :empresa_id AND h.estado = "activa" ORDER BY h.fecha_ingreso DESC LIMIT 8');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function cirugiasRecientes(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT c.id, a.nombre AS animal, c.procedimiento_quirurgico, c.fecha FROM cirugias c INNER JOIN animales a ON a.id = c.animal_id WHERE c.empresa_id = :empresa_id ORDER BY c.fecha DESC LIMIT 8');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function timelineReciente(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT modulo, fecha_evento, titulo, detalle FROM vw_historial_clinico_timeline WHERE empresa_id = :empresa_id ORDER BY fecha_evento DESC LIMIT 12');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}