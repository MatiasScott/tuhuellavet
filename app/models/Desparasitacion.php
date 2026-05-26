<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

final class Desparasitacion extends Model
{
    public function animalesByEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, nombre, peso_actual FROM animales WHERE empresa_id = :empresa_id ORDER BY nombre ASC');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listByEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT d.id, d.fecha, d.proxima_fecha, d.farmaco, d.dosis, d.peso_actual, d.observacion, a.nombre AS animal, CONCAT(u.nombres, " ", COALESCE(u.apellidos, "")) AS usuario FROM desparasitaciones d INNER JOIN animales a ON a.id = d.animal_id LEFT JOIN usuarios u ON u.id = d.usuario_id WHERE d.empresa_id = :empresa_id ORDER BY d.id DESC LIMIT 200');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $payload): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO desparasitaciones (empresa_id, animal_id, farmaco, dosis, fecha, proxima_fecha, observacion, peso_actual, usuario_id) VALUES (:empresa_id, :animal_id, :farmaco, :dosis, :fecha, :proxima_fecha, :observacion, :peso_actual, :usuario_id)');
        $stmt->execute($payload);

        return (int) $this->pdo->lastInsertId();
    }
}
