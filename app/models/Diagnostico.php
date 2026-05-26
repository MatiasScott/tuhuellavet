<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

final class Diagnostico extends Model
{
    public function catalogo(): array
    {
        $stmt = $this->pdo->query('SELECT id, codigo, nombre, tipo, descripcion, estado FROM catalogo_diagnosticos ORDER BY id DESC');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createCatalogo(array $payload): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO catalogo_diagnosticos (codigo, nombre, tipo, descripcion, estado) VALUES (:codigo, :nombre, :tipo, :descripcion, :estado)');
        $stmt->execute($payload);

        return (int) $this->pdo->lastInsertId();
    }

    public function findCatalogoById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, codigo, nombre, tipo, descripcion, estado FROM catalogo_diagnosticos WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function updateCatalogo(int $id, array $payload): bool
    {
        $stmt = $this->pdo->prepare('UPDATE catalogo_diagnosticos SET codigo = :codigo, nombre = :nombre, tipo = :tipo, descripcion = :descripcion, estado = :estado, updated_at = NOW() WHERE id = :id');

        return $stmt->execute([
            ':id' => $id,
            ':codigo' => $payload['codigo'],
            ':nombre' => $payload['nombre'],
            ':tipo' => $payload['tipo'],
            ':descripcion' => $payload['descripcion'],
            ':estado' => $payload['estado'],
        ]);
    }

    public function deleteCatalogo(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM catalogo_diagnosticos WHERE id = :id');

        return $stmt->execute([':id' => $id]);
    }

    public function consultasByEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT c.id, c.fecha_consulta, a.nombre AS animal FROM consultas c INNER JOIN animales a ON a.id = c.animal_id WHERE c.empresa_id = :empresa_id ORDER BY c.fecha_consulta DESC LIMIT 200');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function asignacionesByEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT cd.id, c.id AS consulta_id, c.fecha_consulta, a.nombre AS animal, d.nombre AS diagnostico, d.tipo, cd.observacion, CONCAT(u.nombres, " ", COALESCE(u.apellidos, "")) AS usuario FROM consulta_diagnosticos cd INNER JOIN consultas c ON c.id = cd.consulta_id INNER JOIN animales a ON a.id = c.animal_id INNER JOIN catalogo_diagnosticos d ON d.id = cd.diagnostico_id INNER JOIN usuarios u ON u.id = cd.usuario_id WHERE c.empresa_id = :empresa_id ORDER BY cd.id DESC LIMIT 200');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createAsignacion(array $payload): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO consulta_diagnosticos (consulta_id, diagnostico_id, usuario_id, observacion) VALUES (:consulta_id, :diagnostico_id, :usuario_id, :observacion)');
        $stmt->execute($payload);

        return (int) $this->pdo->lastInsertId();
    }
}
