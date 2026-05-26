<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

final class Hospitalizacion extends Model
{
    public function animalesByEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, nombre, peso_actual FROM animales WHERE empresa_id = :empresa_id ORDER BY nombre ASC');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function consultasByEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT c.id, c.fecha_consulta, a.nombre AS animal FROM consultas c INNER JOIN animales a ON a.id = c.animal_id WHERE c.empresa_id = :empresa_id ORDER BY c.fecha_consulta DESC LIMIT 200');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function tamanos(): array
    {
        $stmt = $this->pdo->query('SELECT id, nombre, peso_min, peso_max, estado FROM tamanos_animales ORDER BY id DESC');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createTamano(array $payload): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO tamanos_animales (nombre, peso_min, peso_max, estado) VALUES (:nombre, :peso_min, :peso_max, :estado)');
        $stmt->execute($payload);

        return (int) $this->pdo->lastInsertId();
    }

    public function listByEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT h.id, h.fecha_ingreso, h.fecha_salida, h.estado, h.motivo, h.observaciones, a.nombre AS animal, CONCAT(u.nombres, " ", COALESCE(u.apellidos, "")) AS usuario FROM hospitalizaciones h INNER JOIN animales a ON a.id = h.animal_id LEFT JOIN usuarios u ON u.id = h.usuario_id WHERE h.empresa_id = :empresa_id ORDER BY h.id DESC LIMIT 200');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $payload): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO hospitalizaciones (empresa_id, animal_id, consulta_id, fecha_ingreso, fecha_salida, motivo, estado, observaciones, usuario_id) VALUES (:empresa_id, :animal_id, :consulta_id, :fecha_ingreso, :fecha_salida, :motivo, :estado, :observaciones, :usuario_id)');
        $stmt->execute($payload);

        return (int) $this->pdo->lastInsertId();
    }

    public function findByEmpresa(int $id, int $empresaId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM hospitalizaciones WHERE id = :id AND empresa_id = :empresa_id LIMIT 1');
        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function updateEstado(int $id, int $empresaId, string $estado, ?string $fechaSalida): bool
    {
        $stmt = $this->pdo->prepare('UPDATE hospitalizaciones SET estado = :estado, fecha_salida = :fecha_salida, updated_at = NOW() WHERE id = :id AND empresa_id = :empresa_id');

        return $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
            ':estado' => $estado,
            ':fecha_salida' => $fechaSalida,
        ]);
    }

    public function fluidoterapiaByEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT f.id, f.hospitalizacion_id, f.mantenimiento, f.rehidratacion, f.formula, f.formulas_medicas, f.signos_clinicos, f.observaciones, t.nombre AS tamano, a.nombre AS animal FROM fluidoterapia f INNER JOIN hospitalizaciones h ON h.id = f.hospitalizacion_id INNER JOIN animales a ON a.id = h.animal_id LEFT JOIN tamanos_animales t ON t.id = f.tamano_animal_id WHERE h.empresa_id = :empresa_id ORDER BY f.id DESC LIMIT 200');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createFluidoterapia(array $payload): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO fluidoterapia (hospitalizacion_id, tamano_animal_id, mantenimiento, rehidratacion, formula, formulas_medicas, signos_clinicos, observaciones) VALUES (:hospitalizacion_id, :tamano_animal_id, :mantenimiento, :rehidratacion, :formula, :formulas_medicas, :signos_clinicos, :observaciones)');
        $stmt->execute($payload);

        return (int) $this->pdo->lastInsertId();
    }
}
