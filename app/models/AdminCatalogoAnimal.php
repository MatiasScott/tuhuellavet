<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

final class AdminCatalogoAnimal extends Model
{
    public function categorias(): array
    {
        $stmt = $this->pdo->query('SELECT id, nombre, created_at FROM categorias_animales ORDER BY nombre ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function especies(): array
    {
        $stmt = $this->pdo->query('SELECT e.id, e.nombre, e.categoria_id, c.nombre AS categoria_nombre FROM especies e LEFT JOIN categorias_animales c ON c.id = e.categoria_id ORDER BY e.nombre ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function razas(): array
    {
        $stmt = $this->pdo->query('SELECT r.id, r.nombre, r.especie_id, e.nombre AS especie_nombre FROM razas r INNER JOIN especies e ON e.id = r.especie_id ORDER BY e.nombre ASC, r.nombre ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createCategoria(string $nombre): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO categorias_animales (nombre) VALUES (:nombre)');
        $stmt->execute([':nombre' => $nombre]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateCategoria(int $id, string $nombre): bool
    {
        $stmt = $this->pdo->prepare('UPDATE categorias_animales SET nombre = :nombre WHERE id = :id');
        return $stmt->execute([':id' => $id, ':nombre' => $nombre]);
    }

    public function createEspecie(int $categoriaId, string $nombre): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO especies (categoria_id, nombre) VALUES (:categoria_id, :nombre)');
        $stmt->execute([':categoria_id' => $categoriaId, ':nombre' => $nombre]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateEspecie(int $id, int $categoriaId, string $nombre): bool
    {
        $stmt = $this->pdo->prepare('UPDATE especies SET categoria_id = :categoria_id, nombre = :nombre WHERE id = :id');
        return $stmt->execute([':id' => $id, ':categoria_id' => $categoriaId, ':nombre' => $nombre]);
    }

    public function createRaza(int $especieId, string $nombre): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO razas (especie_id, nombre) VALUES (:especie_id, :nombre)');
        $stmt->execute([':especie_id' => $especieId, ':nombre' => $nombre]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateRaza(int $id, int $especieId, string $nombre): bool
    {
        $stmt = $this->pdo->prepare('UPDATE razas SET especie_id = :especie_id, nombre = :nombre WHERE id = :id');
        return $stmt->execute([':id' => $id, ':especie_id' => $especieId, ':nombre' => $nombre]);
    }

    public function hasEstadoColumn(string $table): bool
    {
        $allowed = ['categorias_animales', 'especies', 'razas'];
        if (!in_array($table, $allowed, true)) {
            return false;
        }

        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table_name AND column_name = :column_name');
        $stmt->execute([
            ':table_name' => $table,
            ':column_name' => 'estado',
        ]);

        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function setEstado(string $table, int $id, int $estado): bool
    {
        $allowed = ['categorias_animales', 'especies', 'razas'];
        if (!in_array($table, $allowed, true) || !$this->hasEstadoColumn($table)) {
            return false;
        }

        $sql = sprintf('UPDATE %s SET estado = :estado WHERE id = :id', $table);
        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute([
            ':estado' => $estado,
            ':id' => $id,
        ]);
    }
}
