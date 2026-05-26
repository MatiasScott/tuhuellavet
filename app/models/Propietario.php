<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

final class Propietario extends Model
{
    public function byEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT p.id, p.nombres, p.apellidos, p.identificacion, p.celular, p.email, p.foto, p.portal_cliente_activo, p.estado, COUNT(a.id) AS total_animales FROM propietarios p LEFT JOIN animales a ON a.propietario_id = p.id WHERE p.empresa_id = :empresa_id GROUP BY p.id ORDER BY p.id DESC');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $id, int $empresaId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM propietarios WHERE id = :id AND empresa_id = :empresa_id LIMIT 1');
        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function create(array $payload): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO propietarios (empresa_id, nombres, apellidos, identificacion, telefono, celular, email, direccion, usuario_id, foto, portal_cliente_activo, estado) VALUES (:empresa_id, :nombres, :apellidos, :identificacion, :telefono, :celular, :email, :direccion, :usuario_id, :foto, :portal_cliente_activo, :estado)');
        $stmt->execute([
            ':empresa_id' => $payload['empresa_id'],
            ':nombres' => $payload['nombres'],
            ':apellidos' => $payload['apellidos'],
            ':identificacion' => $payload['identificacion'],
            ':telefono' => $payload['telefono'],
            ':celular' => $payload['celular'],
            ':email' => $payload['email'],
            ':direccion' => $payload['direccion'],
            ':usuario_id' => $payload['usuario_id'],
            ':foto' => $payload['foto'],
            ':portal_cliente_activo' => $payload['portal_cliente_activo'],
            ':estado' => $payload['estado'],
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, int $empresaId, array $payload): bool
    {
        $stmt = $this->pdo->prepare('UPDATE propietarios SET nombres = :nombres, apellidos = :apellidos, identificacion = :identificacion, telefono = :telefono, celular = :celular, email = :email, direccion = :direccion, foto = :foto, portal_cliente_activo = :portal_cliente_activo, estado = :estado, updated_at = NOW() WHERE id = :id AND empresa_id = :empresa_id');

        return $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
            ':nombres' => $payload['nombres'],
            ':apellidos' => $payload['apellidos'],
            ':identificacion' => $payload['identificacion'],
            ':telefono' => $payload['telefono'],
            ':celular' => $payload['celular'],
            ':email' => $payload['email'],
            ':direccion' => $payload['direccion'],
            ':foto' => $payload['foto'],
            ':portal_cliente_activo' => $payload['portal_cliente_activo'],
            ':estado' => $payload['estado'],
        ]);
    }
}
