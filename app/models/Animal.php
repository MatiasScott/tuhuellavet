<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

final class Animal extends Model
{
    public function byEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT a.id, a.nombre, a.sexo, a.fecha_nacimiento, a.peso_actual, a.foto, p.nombres AS propietario_nombre, e.nombre AS especie, r.nombre AS raza, TIMESTAMPDIFF(YEAR, a.fecha_nacimiento, CURDATE()) AS edad_anios FROM animales a INNER JOIN especies e ON e.id = a.especie_id LEFT JOIN razas r ON r.id = a.raza_id LEFT JOIN propietarios p ON p.id = a.propietario_id WHERE a.empresa_id = :empresa_id ORDER BY a.id DESC');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id, int $empresaId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM animales WHERE id = :id AND empresa_id = :empresa_id LIMIT 1');
        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function create(array $payload): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO animales (empresa_id, propietario_id, especie_id, raza_id, codigo, nombre, sexo, fecha_nacimiento, peso_actual, color, microchip, foto, observaciones, estado) VALUES (:empresa_id, :propietario_id, :especie_id, :raza_id, :codigo, :nombre, :sexo, :fecha_nacimiento, :peso_actual, :color, :microchip, :foto, :observaciones, :estado)');
        $stmt->execute($payload);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, int $empresaId, array $payload): bool
    {
        $payload['id'] = $id;
        $payload['empresa_id'] = $empresaId;

        $stmt = $this->pdo->prepare('UPDATE animales SET propietario_id = :propietario_id, especie_id = :especie_id, raza_id = :raza_id, codigo = :codigo, nombre = :nombre, sexo = :sexo, fecha_nacimiento = :fecha_nacimiento, peso_actual = :peso_actual, color = :color, microchip = :microchip, foto = :foto, observaciones = :observaciones, estado = :estado, updated_at = NOW() WHERE id = :id AND empresa_id = :empresa_id');

        return $stmt->execute($payload);
    }

    public function propietariosByEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, CONCAT(nombres, " ", COALESCE(apellidos, "")) AS nombre FROM propietarios WHERE empresa_id = :empresa_id AND estado = 1 ORDER BY nombres ASC');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function especies(): array
    {
        $stmt = $this->pdo->query('SELECT id, nombre FROM especies ORDER BY nombre ASC');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function razas(): array
    {
        $stmt = $this->pdo->query('SELECT id, especie_id, nombre FROM razas ORDER BY nombre ASC');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function insertPesoHistorico(int $animalId, ?int $consultaId, float $peso, ?int $usuarioId, string $observacion = 'Actualizacion de peso'): void
    {
        $stmt = $this->pdo->prepare('INSERT INTO animal_pesos (animal_id, consulta_id, peso, fecha_registro, usuario_id, observacion) VALUES (:animal_id, :consulta_id, :peso, NOW(), :usuario_id, :observacion)');
        $stmt->execute([
            ':animal_id' => $animalId,
            ':consulta_id' => $consultaId,
            ':peso' => $peso,
            ':usuario_id' => $usuarioId,
            ':observacion' => $observacion,
        ]);
    }
}
