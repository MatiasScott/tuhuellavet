<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

final class Formula extends Model
{
    public function listByEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT f.id, f.nombre, f.descripcion, f.expresion_formula, f.categoria, f.estado, f.created_by, f.created_at, COALESCE(COUNT(v.id), 0) AS total_variables FROM formulas f LEFT JOIN formulas_variables v ON v.formula_id = f.id WHERE f.empresa_id = :empresa_id GROUP BY f.id ORDER BY f.id DESC LIMIT 300');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id, int $empresaId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, empresa_id, nombre, descripcion, expresion_formula, categoria, estado, created_by FROM formulas WHERE id = :id AND empresa_id = :empresa_id LIMIT 1');
        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function variablesByFormula(int $formulaId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, formula_id, variable, etiqueta, tipo_input, obligatorio, valor_default FROM formulas_variables WHERE formula_id = :formula_id ORDER BY variable ASC');
        $stmt->execute([':formula_id' => $formulaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $payload): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO formulas (empresa_id, nombre, descripcion, expresion_formula, formula, categoria, estado, created_by, created_at, updated_at) VALUES (:empresa_id, :nombre, :descripcion, :expresion_formula, :formula, :categoria, :estado, :created_by, NOW(), NOW())');
        $stmt->execute($payload);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, int $empresaId, array $payload): bool
    {
        $stmt = $this->pdo->prepare('UPDATE formulas SET nombre = :nombre, descripcion = :descripcion, expresion_formula = :expresion_formula, formula = :formula, categoria = :categoria, estado = :estado, updated_by = :updated_by, updated_at = NOW() WHERE id = :id AND empresa_id = :empresa_id');

        return $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
            ':nombre' => $payload['nombre'],
            ':descripcion' => $payload['descripcion'],
            ':expresion_formula' => $payload['expresion_formula'],
            ':formula' => $payload['formula'],
            ':categoria' => $payload['categoria'],
            ':estado' => $payload['estado'],
            ':updated_by' => $payload['updated_by'],
        ]);
    }

    public function delete(int $id, int $empresaId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM formulas WHERE id = :id AND empresa_id = :empresa_id');

        return $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
        ]);
    }

    public function setEstado(int $id, int $empresaId, int $estado, int $updatedBy): bool
    {
        $stmt = $this->pdo->prepare('UPDATE formulas SET estado = :estado, updated_by = :updated_by, updated_at = NOW() WHERE id = :id AND empresa_id = :empresa_id');

        return $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
            ':estado' => $estado,
            ':updated_by' => $updatedBy,
        ]);
    }

    public function replaceVariables(int $formulaId, array $variables): void
    {
        $delete = $this->pdo->prepare('DELETE FROM formulas_variables WHERE formula_id = :formula_id');
        $delete->execute([':formula_id' => $formulaId]);

        if ($variables === []) {
            return;
        }

        $insert = $this->pdo->prepare('INSERT INTO formulas_variables (formula_id, variable, etiqueta, tipo_input, obligatorio, valor_default, created_at, updated_at) VALUES (:formula_id, :variable, :etiqueta, :tipo_input, :obligatorio, :valor_default, NOW(), NOW())');

        foreach ($variables as $variable) {
            $insert->execute([
                ':formula_id' => $formulaId,
                ':variable' => $variable['variable'],
                ':etiqueta' => $variable['etiqueta'],
                ':tipo_input' => $variable['tipo_input'],
                ':obligatorio' => $variable['obligatorio'],
                ':valor_default' => $variable['valor_default'],
            ]);
        }
    }
}
