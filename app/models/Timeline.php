<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

final class Timeline extends Model
{
    public function animalesByEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT a.id, a.nombre, CONCAT(p.nombres, " ", COALESCE(p.apellidos, "")) AS propietario FROM animales a LEFT JOIN propietarios p ON p.id = a.propietario_id WHERE a.empresa_id = :empresa_id ORDER BY a.nombre ASC');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function propietariosByEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, CONCAT(nombres, " ", COALESCE(apellidos, "")) AS nombre FROM propietarios WHERE empresa_id = :empresa_id ORDER BY nombres ASC');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findByFilters(int $empresaId, ?int $animalId, ?int $propietarioId, ?string $fechaInicio, ?string $fechaFin): array
    {
        $sql = 'SELECT t.modulo, t.referencia_id, t.fecha_evento, t.titulo, t.detalle, a.nombre AS animal, CONCAT(p.nombres, " ", COALESCE(p.apellidos, "")) AS propietario FROM vw_historial_clinico_timeline t INNER JOIN animales a ON a.id = t.animal_id LEFT JOIN propietarios p ON p.id = a.propietario_id WHERE t.empresa_id = :empresa_id';

        $params = [':empresa_id' => $empresaId];

        if ($animalId !== null) {
            $sql .= ' AND t.animal_id = :animal_id';
            $params[':animal_id'] = $animalId;
        }

        if ($propietarioId !== null) {
            $sql .= ' AND p.id = :propietario_id';
            $params[':propietario_id'] = $propietarioId;
        }

        if ($fechaInicio !== null) {
            $sql .= ' AND t.fecha_evento >= :fecha_inicio';
            $params[':fecha_inicio'] = $fechaInicio . ' 00:00:00';
        }

        if ($fechaFin !== null) {
            $sql .= ' AND t.fecha_evento <= :fecha_fin';
            $params[':fecha_fin'] = $fechaFin . ' 23:59:59';
        }

        $sql .= ' ORDER BY t.fecha_evento DESC LIMIT 500';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
