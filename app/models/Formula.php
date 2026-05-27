<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;
use RuntimeException;

final class Formula extends Model
{
    private array $tableExistsCache = [];
    private array $columnsCache = [];

    public function listByEmpresa(int $empresaId): array
    {
        if (!$this->tableExists('formulas')) {
            return [];
        }

        $formulaColumns = $this->columns('formulas');
        $hasVariables = $this->tableExists('formulas_variables');

        $select = [
            'f.id',
            $this->columnOrDefault($formulaColumns, 'nombre', '""') . ' AS nombre',
            $this->columnOrDefault($formulaColumns, 'descripcion', '""') . ' AS descripcion',
            (in_array('expresion_formula', $formulaColumns, true) ? 'f.expresion_formula' : $this->columnOrDefault($formulaColumns, 'formula', '""')) . ' AS expresion_formula',
            $this->columnOrDefault($formulaColumns, 'categoria', '"general"') . ' AS categoria',
            $this->columnOrDefault($formulaColumns, 'estado', '1') . ' AS estado',
            $this->columnOrDefault($formulaColumns, 'created_by', 'NULL') . ' AS created_by',
            $this->columnOrDefault($formulaColumns, 'created_at', 'NULL') . ' AS created_at',
        ];

        if ($hasVariables) {
            $select[] = 'COALESCE(COUNT(v.id), 0) AS total_variables';
        } else {
            $select[] = '0 AS total_variables';
        }

        $sql = 'SELECT ' . implode(', ', $select) . ' FROM formulas f';
        if ($hasVariables) {
            $sql .= ' LEFT JOIN formulas_variables v ON v.formula_id = f.id';
        }

        $params = [];
        if (in_array('empresa_id', $formulaColumns, true)) {
            $sql .= ' WHERE f.empresa_id = :empresa_id';
            $params[':empresa_id'] = $empresaId;
        }

        if ($hasVariables) {
            $sql .= ' GROUP BY f.id';
        }

        $sql .= ' ORDER BY f.id DESC LIMIT 300';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $id, int $empresaId): ?array
    {
        if (!$this->tableExists('formulas')) {
            return null;
        }

        $formulaColumns = $this->columns('formulas');
        $select = [
            'id',
            in_array('empresa_id', $formulaColumns, true) ? 'empresa_id' : 'NULL AS empresa_id',
            $this->columnOrDefault($formulaColumns, 'nombre', '""') . ' AS nombre',
            $this->columnOrDefault($formulaColumns, 'descripcion', '""') . ' AS descripcion',
            (in_array('expresion_formula', $formulaColumns, true) ? 'expresion_formula' : $this->columnOrDefault($formulaColumns, 'formula', '""')) . ' AS expresion_formula',
            $this->columnOrDefault($formulaColumns, 'categoria', '"general"') . ' AS categoria',
            $this->columnOrDefault($formulaColumns, 'estado', '1') . ' AS estado',
            $this->columnOrDefault($formulaColumns, 'created_by', 'NULL') . ' AS created_by',
        ];

        $sql = 'SELECT ' . implode(', ', $select) . ' FROM formulas WHERE id = :id';
        $params = [':id' => $id];
        if (in_array('empresa_id', $formulaColumns, true)) {
            $sql .= ' AND empresa_id = :empresa_id';
            $params[':empresa_id'] = $empresaId;
        }
        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function variablesByFormula(int $formulaId): array
    {
        if (!$this->tableExists('formulas_variables')) {
            return [];
        }

        $stmt = $this->pdo->prepare('SELECT id, formula_id, variable, etiqueta, tipo_input, obligatorio, valor_default FROM formulas_variables WHERE formula_id = :formula_id ORDER BY variable ASC');
        $stmt->execute([':formula_id' => $formulaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $payload): int
    {
        if (!$this->tableExists('formulas')) {
            throw new RuntimeException('La tabla formulas no existe. Ejecuta la migracion 20260526_03_formulas_auditoria.sql.');
        }

        $columns = $this->columns('formulas');
        $data = [
            'empresa_id' => $payload['empresa_id'] ?? null,
            'nombre' => $payload['nombre'] ?? null,
            'descripcion' => $payload['descripcion'] ?? null,
            'expresion_formula' => $payload['expresion_formula'] ?? null,
            'formula' => $payload['formula'] ?? ($payload['expresion_formula'] ?? null),
            'categoria' => $payload['categoria'] ?? null,
            'estado' => $payload['estado'] ?? 1,
            'created_by' => $payload['created_by'] ?? null,
            'updated_by' => $payload['updated_by'] ?? null,
        ];

        $insertCols = [];
        $insertPlaceholders = [];
        $insertValues = [];

        foreach ($data as $column => $value) {
            if (!in_array($column, $columns, true)) {
                continue;
            }
            $insertCols[] = $column;
            $insertPlaceholders[] = ':' . $column;
            $insertValues[':' . $column] = $value;
        }

        if (in_array('created_at', $columns, true)) {
            $insertCols[] = 'created_at';
            $insertPlaceholders[] = 'NOW()';
        }
        if (in_array('updated_at', $columns, true)) {
            $insertCols[] = 'updated_at';
            $insertPlaceholders[] = 'NOW()';
        }

        $sql = sprintf('INSERT INTO formulas (%s) VALUES (%s)', implode(', ', $insertCols), implode(', ', $insertPlaceholders));
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($insertValues);

        return (int) $this->pdo->lastInsertId();
    }

    public function update(int $id, int $empresaId, array $payload): bool
    {
        if (!$this->tableExists('formulas')) {
            return false;
        }

        $columns = $this->columns('formulas');
        $set = [];
        $params = [':id' => $id];

        $data = [
            'nombre' => $payload['nombre'] ?? null,
            'descripcion' => $payload['descripcion'] ?? null,
            'expresion_formula' => $payload['expresion_formula'] ?? null,
            'formula' => $payload['formula'] ?? ($payload['expresion_formula'] ?? null),
            'categoria' => $payload['categoria'] ?? null,
            'estado' => $payload['estado'] ?? 1,
            'updated_by' => $payload['updated_by'] ?? null,
        ];

        foreach ($data as $column => $value) {
            if (!in_array($column, $columns, true)) {
                continue;
            }
            $set[] = $column . ' = :' . $column;
            $params[':' . $column] = $value;
        }

        if (in_array('updated_at', $columns, true)) {
            $set[] = 'updated_at = NOW()';
        }

        $sql = 'UPDATE formulas SET ' . implode(', ', $set) . ' WHERE id = :id';
        if (in_array('empresa_id', $columns, true)) {
            $sql .= ' AND empresa_id = :empresa_id';
            $params[':empresa_id'] = $empresaId;
        }

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($params);
    }

    public function delete(int $id, int $empresaId): bool
    {
        if (!$this->tableExists('formulas')) {
            return false;
        }

        $columns = $this->columns('formulas');
        $sql = 'DELETE FROM formulas WHERE id = :id';
        $params = [':id' => $id];
        if (in_array('empresa_id', $columns, true)) {
            $sql .= ' AND empresa_id = :empresa_id';
            $params[':empresa_id'] = $empresaId;
        }

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($params);
    }

    public function setEstado(int $id, int $empresaId, int $estado, int $updatedBy): bool
    {
        if (!$this->tableExists('formulas')) {
            return false;
        }

        $columns = $this->columns('formulas');
        $set = [];
        $params = [
            ':id' => $id,
            ':estado' => $estado,
            ':updated_by' => $updatedBy,
        ];

        if (in_array('estado', $columns, true)) {
            $set[] = 'estado = :estado';
        }
        if (in_array('updated_by', $columns, true)) {
            $set[] = 'updated_by = :updated_by';
        }
        if (in_array('updated_at', $columns, true)) {
            $set[] = 'updated_at = NOW()';
        }

        if ($set === []) {
            return false;
        }

        $sql = 'UPDATE formulas SET ' . implode(', ', $set) . ' WHERE id = :id';
        if (in_array('empresa_id', $columns, true)) {
            $sql .= ' AND empresa_id = :empresa_id';
            $params[':empresa_id'] = $empresaId;
        }

        $stmt = $this->pdo->prepare($sql);

        return $stmt->execute($params);
    }

    public function replaceVariables(int $formulaId, array $variables): void
    {
        if (!$this->tableExists('formulas_variables')) {
            return;
        }

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

    private function tableExists(string $tableName): bool
    {
        if (array_key_exists($tableName, $this->tableExistsCache)) {
            return $this->tableExistsCache[$tableName];
        }

        $stmt = $this->pdo->prepare('SHOW TABLES LIKE :table_name');
        $stmt->execute([':table_name' => $tableName]);
        $exists = $stmt->fetchColumn() !== false;
        $this->tableExistsCache[$tableName] = $exists;

        return $exists;
    }

    private function columns(string $tableName): array
    {
        if (array_key_exists($tableName, $this->columnsCache)) {
            return $this->columnsCache[$tableName];
        }

        if (!$this->tableExists($tableName)) {
            $this->columnsCache[$tableName] = [];
            return [];
        }

        $stmt = $this->pdo->query('SHOW COLUMNS FROM ' . $tableName);
        $rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $columns = array_values(array_filter(array_map(static fn (array $row): string => (string) ($row['Field'] ?? ''), $rows)));
        $this->columnsCache[$tableName] = $columns;

        return $columns;
    }

    private function columnOrDefault(array $columns, string $columnName, string $defaultExpression): string
    {
        return in_array($columnName, $columns, true) ? 'f.' . $columnName : $defaultExpression;
    }
}
