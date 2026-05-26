<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

final class Auditoria extends Model
{
    private ?array $columns = null;

    public function list(array $filters, int $limit = 300): array
    {
        $columns = $this->columns();
        $fechaCol = in_array('fecha_evento', $columns, true) ? 'a.fecha_evento' : 'a.created_at';

        $selectParts = [
            'a.id',
            in_array('usuario_id', $columns, true) ? 'a.usuario_id' : 'NULL AS usuario_id',
            in_array('empresa_id', $columns, true) ? 'a.empresa_id' : 'NULL AS empresa_id',
            in_array('modulo', $columns, true) ? 'a.modulo' : '"sistema" AS modulo',
            in_array('accion', $columns, true) ? 'a.accion' : '"evento" AS accion',
            in_array('tabla_afectada', $columns, true) ? 'a.tabla_afectada' : 'NULL AS tabla_afectada',
            in_array('registro_id', $columns, true) ? 'a.registro_id' : 'NULL AS registro_id',
            in_array('ip', $columns, true) ? 'a.ip' : 'NULL AS ip',
            in_array('user_agent', $columns, true) ? 'a.user_agent' : 'NULL AS user_agent',
            in_array('datos_anteriores', $columns, true) ? 'a.datos_anteriores' : 'NULL AS datos_anteriores',
            in_array('datos_nuevos', $columns, true) ? 'a.datos_nuevos' : 'NULL AS datos_nuevos',
            $fechaCol . ' AS fecha_evento',
            'CONCAT(COALESCE(u.nombres, ""), " ", COALESCE(u.apellidos, "")) AS usuario_nombre',
            'e.nombre AS empresa_nombre',
        ];

        $sql = 'SELECT ' . implode(', ', $selectParts) . ' FROM auditoria a LEFT JOIN usuarios u ON u.id = a.usuario_id LEFT JOIN empresas e ON e.id = a.empresa_id WHERE 1=1';
        $params = [];

        if (($filters['q'] ?? '') !== '') {
            $sql .= ' AND ('
                . 'a.modulo LIKE :q OR '
                . 'a.accion LIKE :q OR '
                . 'COALESCE(a.tabla_afectada, "") LIKE :q OR '
                . 'COALESCE(CONCAT(u.nombres, " ", u.apellidos), "") LIKE :q'
                . ')';
            $params[':q'] = '%' . trim((string) $filters['q']) . '%';
        }

        if ((int) ($filters['usuario_id'] ?? 0) > 0 && in_array('usuario_id', $columns, true)) {
            $sql .= ' AND a.usuario_id = :usuario_id';
            $params[':usuario_id'] = (int) $filters['usuario_id'];
        }

        if ((int) ($filters['empresa_id'] ?? 0) > 0 && in_array('empresa_id', $columns, true)) {
            $sql .= ' AND a.empresa_id = :empresa_id';
            $params[':empresa_id'] = (int) $filters['empresa_id'];
        }

        if (($filters['modulo'] ?? '') !== '' && in_array('modulo', $columns, true)) {
            $sql .= ' AND a.modulo = :modulo';
            $params[':modulo'] = (string) $filters['modulo'];
        }

        if (($filters['accion'] ?? '') !== '' && in_array('accion', $columns, true)) {
            $sql .= ' AND a.accion = :accion';
            $params[':accion'] = (string) $filters['accion'];
        }

        if (($filters['fecha_desde'] ?? '') !== '') {
            $sql .= ' AND DATE(' . $fechaCol . ') >= :fecha_desde';
            $params[':fecha_desde'] = (string) $filters['fecha_desde'];
        }

        if (($filters['fecha_hasta'] ?? '') !== '') {
            $sql .= ' AND DATE(' . $fechaCol . ') <= :fecha_hasta';
            $params[':fecha_hasta'] = (string) $filters['fecha_hasta'];
        }

        $sql .= ' ORDER BY ' . $fechaCol . ' DESC LIMIT ' . (int) $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function usuarios(): array
    {
        $stmt = $this->pdo->query('SELECT id, CONCAT(COALESCE(nombres, ""), " ", COALESCE(apellidos, "")) AS nombre FROM usuarios WHERE estado = 1 ORDER BY nombres ASC LIMIT 300');

        return $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function empresas(): array
    {
        $stmt = $this->pdo->query('SELECT id, nombre FROM empresas WHERE estado = 1 ORDER BY nombre ASC LIMIT 50');

        return $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function modulos(): array
    {
        if (!in_array('modulo', $this->columns(), true)) {
            return [];
        }

        $stmt = $this->pdo->query('SELECT DISTINCT modulo FROM auditoria WHERE modulo IS NOT NULL AND modulo <> "" ORDER BY modulo ASC');

        return $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    public function acciones(): array
    {
        if (!in_array('accion', $this->columns(), true)) {
            return [];
        }

        $stmt = $this->pdo->query('SELECT DISTINCT accion FROM auditoria WHERE accion IS NOT NULL AND accion <> "" ORDER BY accion ASC');

        return $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    }

    private function columns(): array
    {
        if (is_array($this->columns)) {
            return $this->columns;
        }

        $stmt = $this->pdo->query('SHOW COLUMNS FROM auditoria');
        $rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $this->columns = array_values(array_filter(array_map(static fn (array $row): string => (string) ($row['Field'] ?? ''), $rows)));

        return $this->columns;
    }
}
