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

    public function clientContext(int $userId, int $empresaId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT p.id, p.nombres, p.apellidos, p.email, p.foto, COUNT(a.id) AS total_animales FROM propietarios p LEFT JOIN animales a ON a.propietario_id = p.id WHERE p.usuario_id = :usuario_id AND p.empresa_id = :empresa_id GROUP BY p.id LIMIT 1');
        $stmt->execute([
            ':usuario_id' => $userId,
            ':empresa_id' => $empresaId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function clientProfile(int $userId, int $empresaId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM propietarios WHERE usuario_id = :usuario_id AND empresa_id = :empresa_id LIMIT 1');
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

    public function clientMascotas(int $propietarioId, int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT a.id, a.nombre, a.foto, a.sexo, a.fecha_nacimiento, a.peso_actual, e.nombre AS especie, r.nombre AS raza, a.updated_at FROM animales a INNER JOIN especies e ON e.id = a.especie_id LEFT JOIN razas r ON r.id = a.raza_id WHERE a.propietario_id = :propietario_id AND a.empresa_id = :empresa_id ORDER BY a.created_at DESC');
        $stmt->execute([
            ':propietario_id' => $propietarioId,
            ':empresa_id' => $empresaId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function clientAnimal(int $animalId, int $empresaId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM animales WHERE id = :id AND empresa_id = :empresa_id LIMIT 1');
        $stmt->execute([
            ':id' => $animalId,
            ':empresa_id' => $empresaId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function clientConsultas(int $propietarioId, int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT c.id, c.fecha_consulta, c.motivo_consulta, c.diagnostico, a.nombre AS animal FROM consultas c INNER JOIN animales a ON a.id = c.animal_id WHERE a.propietario_id = :propietario_id AND c.empresa_id = :empresa_id ORDER BY c.fecha_consulta DESC LIMIT 8');
        $stmt->execute([
            ':propietario_id' => $propietarioId,
            ':empresa_id' => $empresaId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function clientVacunas(int $propietarioId, int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT av.id, av.fecha_aplicacion, av.proxima_aplicacion, COALESCE(cv.nombre, v.nombre, "Vacuna") AS vacuna, a.nombre AS animal FROM animal_vacunas av INNER JOIN animales a ON a.id = av.animal_id LEFT JOIN catalogo_vacunas cv ON cv.id = av.catalogo_vacuna_id LEFT JOIN vacunas v ON v.id = av.vacuna_id WHERE a.propietario_id = :propietario_id AND a.empresa_id = :empresa_id ORDER BY COALESCE(av.proxima_aplicacion, av.fecha_aplicacion) DESC LIMIT 8');
        $stmt->execute([
            ':propietario_id' => $propietarioId,
            ':empresa_id' => $empresaId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function clientDesparasitaciones(int $propietarioId, int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT d.id, d.fecha, d.proxima_fecha, d.farmaco, a.nombre AS animal FROM desparasitaciones d INNER JOIN animales a ON a.id = d.animal_id WHERE a.propietario_id = :propietario_id AND d.empresa_id = :empresa_id ORDER BY d.fecha DESC LIMIT 8');
        $stmt->execute([
            ':propietario_id' => $propietarioId,
            ':empresa_id' => $empresaId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function clientCirugias(int $propietarioId, int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT c.id, c.fecha, c.procedimiento_quirurgico, c.archivo_pdf, a.nombre AS animal FROM cirugias c INNER JOIN animales a ON a.id = c.animal_id WHERE a.propietario_id = :propietario_id AND c.empresa_id = :empresa_id ORDER BY c.fecha DESC LIMIT 8');
        $stmt->execute([
            ':propietario_id' => $propietarioId,
            ':empresa_id' => $empresaId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function clientExamenes(int $propietarioId, int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT e.id, e.created_at, e.tipo_examen, e.resultado, e.archivo_pdf, a.nombre AS animal FROM examenes_laboratorio e INNER JOIN animales a ON a.id = e.animal_id WHERE a.propietario_id = :propietario_id AND e.empresa_id = :empresa_id ORDER BY e.created_at DESC LIMIT 8');
        $stmt->execute([
            ':propietario_id' => $propietarioId,
            ':empresa_id' => $empresaId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function clientTimeline(int $propietarioId, int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT t.modulo, t.fecha_evento, t.titulo, t.detalle, a.nombre AS animal FROM vw_historial_clinico_timeline t INNER JOIN animales a ON a.id = t.animal_id WHERE a.propietario_id = :propietario_id AND t.empresa_id = :empresa_id ORDER BY t.fecha_evento DESC LIMIT 12');
        $stmt->execute([
            ':propietario_id' => $propietarioId,
            ':empresa_id' => $empresaId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function clientDocuments(int $propietarioId, int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT item.* FROM ( SELECT CONCAT("examen:", e.id) AS documento_id, e.created_at AS fecha_documento, e.tipo_examen AS titulo, e.archivo_pdf AS archivo_pdf, a.nombre AS animal, "Examen" AS origen FROM examenes_laboratorio e INNER JOIN animales a ON a.id = e.animal_id WHERE a.propietario_id = :propietario_id AND e.empresa_id = :empresa_id AND e.archivo_pdf IS NOT NULL AND e.archivo_pdf <> "" UNION ALL SELECT CONCAT("cirugia:", c.id) AS documento_id, c.fecha AS fecha_documento, c.procedimiento_quirurgico AS titulo, c.archivo_pdf AS archivo_pdf, a.nombre AS animal, "Cirugia" AS origen FROM cirugias c INNER JOIN animales a ON a.id = c.animal_id WHERE a.propietario_id = :propietario_id AND c.empresa_id = :empresa_id AND c.archivo_pdf IS NOT NULL AND c.archivo_pdf <> "" ) item ORDER BY item.fecha_documento DESC LIMIT 12');
        $stmt->execute([
            ':propietario_id' => $propietarioId,
            ':empresa_id' => $empresaId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}