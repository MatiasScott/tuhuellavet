<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

final class Vacuna extends Model
{
    public function catalogoByEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, empresa_id, nombre, descripcion, estado FROM catalogo_vacunas WHERE empresa_id = :empresa_id OR empresa_id IS NULL ORDER BY id DESC');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createCatalogo(array $payload): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO catalogo_vacunas (empresa_id, nombre, descripcion, estado) VALUES (:empresa_id, :nombre, :descripcion, :estado)');
        $stmt->execute($payload);

        return (int) $this->pdo->lastInsertId();
    }

    public function findCatalogoById(int $id, int $empresaId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, empresa_id, nombre, descripcion, estado FROM catalogo_vacunas WHERE id = :id AND (empresa_id = :empresa_id OR empresa_id IS NULL) LIMIT 1');
        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function updateCatalogo(int $id, int $empresaId, array $payload): bool
    {
        $stmt = $this->pdo->prepare('UPDATE catalogo_vacunas SET nombre = :nombre, descripcion = :descripcion, estado = :estado, updated_at = NOW() WHERE id = :id AND (empresa_id = :empresa_id OR empresa_id IS NULL)');

        return $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
            ':nombre' => $payload['nombre'],
            ':descripcion' => $payload['descripcion'],
            ':estado' => $payload['estado'],
        ]);
    }

    public function deleteCatalogo(int $id, int $empresaId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM catalogo_vacunas WHERE id = :id AND (empresa_id = :empresa_id OR empresa_id IS NULL)');

        return $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
        ]);
    }

    public function createLegacyVacuna(array $payload): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO vacunas (empresa_id, nombre, descripcion) VALUES (:empresa_id, :nombre, :descripcion)');
        $stmt->execute($payload);

        return (int) $this->pdo->lastInsertId();
    }

    public function animalesByEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, nombre FROM animales WHERE empresa_id = :empresa_id ORDER BY nombre ASC');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function consultasByEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT c.id, c.fecha_consulta, a.nombre AS animal FROM consultas c INNER JOIN animales a ON a.id = c.animal_id WHERE c.empresa_id = :empresa_id ORDER BY c.fecha_consulta DESC LIMIT 200');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function aplicacionesByEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT av.id, av.fecha_aplicacion, av.proxima_aplicacion, av.dosis, av.laboratorio, av.lote, av.observaciones, a.nombre AS animal, COALESCE(cv.nombre, v.nombre) AS vacuna, CONCAT(u.nombres, " ", COALESCE(u.apellidos, "")) AS usuario FROM animal_vacunas av INNER JOIN animales a ON a.id = av.animal_id LEFT JOIN catalogo_vacunas cv ON cv.id = av.catalogo_vacuna_id LEFT JOIN vacunas v ON v.id = av.vacuna_id LEFT JOIN usuarios u ON u.id = av.usuario_id WHERE a.empresa_id = :empresa_id ORDER BY av.id DESC LIMIT 200');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createAplicacion(array $payload): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO animal_vacunas (animal_id, vacuna_id, catalogo_vacuna_id, dosis, laboratorio, lote, consulta_id, fecha_aplicacion, proxima_aplicacion, observaciones, usuario_id) VALUES (:animal_id, :vacuna_id, :catalogo_vacuna_id, :dosis, :laboratorio, :lote, :consulta_id, :fecha_aplicacion, :proxima_aplicacion, :observaciones, :usuario_id)');
        $stmt->execute($payload);

        return (int) $this->pdo->lastInsertId();
    }
}
