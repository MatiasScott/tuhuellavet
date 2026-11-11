<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use PDO;
use RuntimeException;

class HaciendaService
{
    public const MODULES = ['lotes', 'produccion', 'genealogia', 'alimentacion'];

    public function rows(string $module, int $env, array $filters = []): array
    {
        EnvironmentModules::assertFarm($env);
        $sql = match ($module) {
            'lotes' => 'SELECT l.*,e.nombre_comun AS especie FROM lotes_animales l JOIN especies e ON e.id=l.especie_id WHERE l.entorno_id=:env ORDER BY l.id DESC',
            'genealogia' => 'SELECT g.*,a.nombre AS paciente,p.nombre AS padre,m.nombre AS madre FROM genealogia g
                JOIN animales a ON a.id=g.paciente_id LEFT JOIN animales p ON p.id=g.padre_id
                LEFT JOIN animales m ON m.id=g.madre_id WHERE g.entorno_id=:env ORDER BY g.id DESC',
            'produccion', 'alimentacion' => $this->reportSql($module),
            default => throw new RuntimeException('Modulo de hacienda no valido.'),
        };
        $params = ['env' => $env];
        if (in_array($module, ['produccion', 'alimentacion'], true)) {
            $date = $module === 'produccion' ? 'r.fecha_registro' : "CASE WHEN r.estado='CONSUMIDA' THEN r.confirmado_at ELSE r.fecha_inicio END";
            foreach (['desde' => '>=', 'hasta' => '<='] as $name => $operator) {
                if (!empty($filters[$name])) {
                    $value = $this->date((string) $filters[$name]);
                    $sql .= " AND DATE($date) $operator :$name";
                    $params[$name] = $value;
                }
            }
            if (!empty($filters['desde']) && !empty($filters['hasta']) && $filters['desde'] > $filters['hasta']) {
                throw new RuntimeException('El inicio del reporte no puede ser posterior al fin.');
            }
            foreach (['paciente_id', 'lote_id'] as $field) {
                if (!empty($filters[$field])) {
                    $params[$field] = $this->id($filters[$field]);
                    $sql .= " AND r.$field=:$field";
                }
            }
            $sql .= " ORDER BY ($date) DESC,r.id DESC";
        }
        $stmt = Database::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function reportSql(string $module): string
    {
        $extra = $module === 'alimentacion'
            ? ',p.nombre AS producto,i.nombre AS inventario,lp.numero_lote'
            : '';
        $joins = $module === 'alimentacion'
            ? ' LEFT JOIN productos p ON p.id=r.producto_id LEFT JOIN inventarios i ON i.id=r.inventario_id LEFT JOIN lotes_producto lp ON lp.id=r.lote_producto_id'
            : '';
        $date = $module === 'produccion' ? 'r.fecha_registro' : "CASE WHEN r.estado='CONSUMIDA' THEN r.confirmado_at ELSE r.fecha_inicio END";
        return "SELECT r.*,($date) AS fecha_reporte,COALESCE(NULLIF(a.nombre,''),NULLIF(a.codigo,''),l.nombre,CONCAT('Animal #',r.paciente_id)) AS destino,
            CASE WHEN r.paciente_id IS NULL THEN 'lote' ELSE 'individual' END AS tipo_registro,
            u.simbolo AS unidad $extra FROM $module r
            LEFT JOIN animales a ON a.id=r.paciente_id LEFT JOIN lotes_animales l ON l.id=r.lote_id
            JOIN unidades_medida u ON u.id=r.unidad_id $joins WHERE r.entorno_id=:env";
    }

    public function metrics(string $module, array $rows): array
    {
        if (!in_array($module, ['produccion', 'alimentacion'], true)) {
            return [];
        }
        $groups = [];
        foreach ($rows as $row) {
            $type = $module === 'produccion' ? $row['tipo_producto'] : $row['tipo_alimento'];
            $status = $module === 'produccion' ? '' : $row['estado'];
            $key = json_encode([$row['paciente_id'], $row['lote_id'], $type, $row['unidad_id'], $status]);
            $groups[$key] ??= ['destino' => $row['destino'], 'tipo' => $type, 'unidad' => $row['unidad'], 'estado' => $status, 'cantidad' => 0];
            // Sum integer hundredths; never combine units or planned and actual quantities.
            $groups[$key]['cantidad'] += (int) round((float) ($row[$module === 'produccion' ? 'cantidad' : 'cantidad_consumida']) * 100);
        }
        foreach ($groups as &$group) {
            $group['cantidad'] = number_format($group['cantidad'] / 100, 2, '.', '');
        }
        return array_values($groups);
    }

    public function create(string $module, array $data, int $env, int $by): int
    {
        EnvironmentModules::assertFarm($env);
        return Database::transaction(function (PDO $db) use ($module, $data, $env, $by): int {
            $this->lockEnvironment($db, $env);
            $values = ['entorno_id' => $env];
            if ($module === 'lotes') {
                $species = $this->id($data['especie_id'] ?? null);
                $this->exists($db, 'SELECT id FROM especies WHERE id=? AND activo=1', [$species], 'Especie no valida.');
                $values += [
                    'especie_id' => $species,
                    'codigo' => $this->text($data, 'codigo', 80),
                    'nombre' => $this->text($data, 'nombre', 150),
                    'cantidad' => $this->id($data['cantidad'] ?? null),
                    'metodo_identificacion' => $this->text($data, 'metodo_identificacion', 150),
                    'origen' => $this->text($data, 'origen', 150),
                ];
            } elseif ($module === 'genealogia') {
                return $this->saveGenealogy($db, $data, $env, $by);
            } elseif (in_array($module, ['produccion', 'alimentacion'], true)) {
                $values += $this->target($db, $data, $env);
                $unit = $this->id($data['unidad_id'] ?? null);
                $this->exists($db, 'SELECT id FROM unidades_medida WHERE id=? AND activo=1', [$unit], 'Unidad no valida.');
                $values['unidad_id'] = $unit;
                $values['registrado_por'] = $by;
                if ($module === 'produccion') {
                    $values += [
                        'tipo_producto' => $this->text($data, 'tipo_producto', 100),
                        'cantidad' => $this->quantity($data['cantidad'] ?? null),
                        'fecha_registro' => $this->dateTime((string) ($data['fecha_registro'] ?? '')),
                    ];
                } else {
                    $start = $this->date((string) ($data['fecha_inicio'] ?? ''));
                    $end = empty($data['fecha_fin']) ? null : $this->date((string) $data['fecha_fin']);
                    if ($end !== null && $end < $start) {
                        throw new RuntimeException('La fecha final debe ser igual o posterior al inicio.');
                    }
                    $values += [
                        'tipo_alimento' => $this->text($data, 'tipo_alimento', 150),
                        'cantidad_consumida' => $this->quantity($data['cantidad_consumida'] ?? null),
                        'frecuencia' => $this->text($data, 'frecuencia', 100, false),
                        'fecha_inicio' => $start,
                        'fecha_fin' => $end,
                    ];
                    foreach (['inventario_id', 'producto_id', 'lote_producto_id'] as $field) {
                        $values[$field] = empty($data[$field]) ? null : $this->id($data[$field]);
                    }
                    $this->feedingProduct($db, $values, $env, false);
                }
            } else {
                throw new RuntimeException('Modulo de hacienda no valido.');
            }
            $values['observaciones'] = $this->text($data, 'observaciones', 10000, false);
            $id = $this->insert($db, $module === 'lotes' ? 'lotes_animales' : $module, $values);
            (new AuditService())->log($by, $env, strtoupper($module), 'CREAR', $module, $id, null, $values);
            return $id;
        });
    }

    public function confirmFeeding(int $id, int $env, int $by): int
    {
        EnvironmentModules::assertFarm($env);
        return Database::transaction(function (PDO $db) use ($id, $env, $by): int {
            $this->lockEnvironment($db, $env);
            $stmt = $db->prepare('SELECT * FROM alimentacion WHERE id=? AND entorno_id=? FOR UPDATE');
            $stmt->execute([$id, $env]);
            $row = $stmt->fetch();
            if (!$row || $row['estado'] !== 'PLANIFICADA') {
                throw new RuntimeException('Racion inexistente o consumo ya confirmado.');
            }
            if ($row['fecha_inicio'] > date('Y-m-d')) {
                throw new RuntimeException('No se puede confirmar un consumo futuro.');
            }
            $this->target($db, $row, $env);
            $this->feedingProduct($db, $row, $env, true);
            $type = $this->exists($db, "SELECT id,factor FROM tipos_movimiento_inventario WHERE codigo='CONSUMO_ALIMENTACION'", [], 'Falta el tipo de consumo de alimentos.');
            if ((int) $type['factor'] !== -1) {
                throw new RuntimeException('CONSUMO_ALIMENTACION debe tener factor -1.');
            }
            $movement = (new InventoryService())->movement([
                'inventario_id' => $row['inventario_id'],
                'producto_id' => $row['producto_id'],
                'lote_id' => $row['lote_producto_id'],
                'tipo_movimiento_id' => $type['id'],
                'cantidad' => $row['cantidad_consumida'],
                'referencia_tipo' => 'ALIMENTACION',
                'referencia_id' => $id,
                'observaciones' => 'Consumo confirmado de racion ' . $id,
            ], $env, $by);
            $stmt = $db->prepare("UPDATE alimentacion SET estado='CONSUMIDA',movimiento_id=?,confirmado_por=?,confirmado_at=NOW() WHERE id=? AND entorno_id=?");
            $stmt->execute([$movement, $by, $id, $env]);
            (new AuditService())->log($by, $env, 'ALIMENTACION', 'CONFIRMAR_CONSUMO', 'alimentacion', $id, $row, ['movimiento_id' => $movement]);
            return $movement;
        });
    }

    public function updatePlan(int $id, array $data, int $env, int $by): void
    {
        EnvironmentModules::assertFarm($env);
        Database::transaction(function (PDO $db) use ($id, $data, $env, $by): void {
            $this->lockEnvironment($db, $env);
            $old = $this->exists($db, 'SELECT * FROM alimentacion WHERE id=? AND entorno_id=? FOR UPDATE', [$id, $env], 'Racion no encontrada.');
            if ($old['estado'] !== 'PLANIFICADA') {
                throw new RuntimeException('Un consumo confirmado no puede modificarse.');
            }
            $values = $old;
            foreach (['producto_id', 'inventario_id', 'lote_producto_id'] as $field) {
                $values[$field] = empty($data[$field]) ? null : $this->id($data[$field]);
            }
            $values['cantidad_consumida'] = $this->quantity($data['cantidad_consumida'] ?? $old['cantidad_consumida']);
            $this->feedingProduct($db, $values, $env, false);
            $stmt = $db->prepare('UPDATE alimentacion SET producto_id=?,inventario_id=?,lote_producto_id=?,cantidad_consumida=? WHERE id=? AND entorno_id=?');
            $stmt->execute([$values['producto_id'], $values['inventario_id'], $values['lote_producto_id'], $values['cantidad_consumida'], $id, $env]);
            (new AuditService())->log($by, $env, 'ALIMENTACION', 'EDITAR_PLAN', 'alimentacion', $id, $old, $values);
        });
    }

    public function updateLot(int $id, array $data, int $env, int $by): void
    {
        EnvironmentModules::assertFarm($env);
        Database::transaction(function (PDO $db) use ($id, $data, $env, $by): void {
            $this->lockEnvironment($db, $env);
            $old = $this->lot($id, $env);
            $values = [
                'nombre' => $this->text($data, 'nombre', 150),
                'cantidad' => $this->id($data['cantidad'] ?? null),
                'metodo_identificacion' => $this->text($data, 'metodo_identificacion', 150),
                'origen' => $this->text($data, 'origen', 150),
                'observaciones' => $this->text($data, 'observaciones', 10000, false),
            ];
            $stmt = $db->prepare('UPDATE lotes_animales SET nombre=:nombre,cantidad=:cantidad,metodo_identificacion=:metodo_identificacion,origen=:origen,observaciones=:observaciones WHERE id=:id AND entorno_id=:env');
            $stmt->execute($values + ['id' => $id, 'env' => $env]);
            (new AuditService())->log($by, $env, 'LOTES_ANIMALES', 'EDITAR', 'lotes_animales', $id, $old, $values);
        });
    }

    private function feedingProduct(PDO $db, array $data, int $env, bool $required): void
    {
        if (empty($data['producto_id']) && empty($data['inventario_id']) && empty($data['lote_producto_id']) && !$required) {
            return;
        }
        $product = $this->exists($db, 'SELECT p.* FROM productos p JOIN inventario_productos ip ON ip.producto_id=p.id
            JOIN inventarios i ON i.id=ip.inventario_id WHERE p.id=? AND p.entorno_id=? AND i.id=?
            AND i.entorno_id=? AND i.activo=1 AND ip.activo=1 AND p.activo=1 AND p.deleted_at IS NULL',
            [$data['producto_id'], $env, $data['inventario_id'], $env], 'Seleccione producto e inventario exclusivos de Hacienda.');
        if ((int) $product['unidad_base_id'] !== (int) $data['unidad_id']) {
            throw new RuntimeException('La cantidad debe usar la unidad base del producto; no se realizan conversiones automaticas.');
        }
        if (!empty($data['lote_producto_id'])) {
            $this->exists($db, 'SELECT id FROM lotes_producto WHERE id=? AND producto_id=?', [$data['lote_producto_id'], $data['producto_id']], 'El lote de inventario no corresponde al producto.');
        }
    }

    private function target(PDO $db, array $data, int $env): array
    {
        $patient = empty($data['paciente_id']) ? null : $this->id($data['paciente_id']);
        $lot = empty($data['lote_id']) ? null : $this->id($data['lote_id']);
        if (($patient === null) === ($lot === null)) {
            throw new RuntimeException('Seleccione un animal individual o un lote, no ambos.');
        }
        $this->exists($db, $patient !== null
            ? 'SELECT id FROM animales WHERE id=? AND entorno_id=? AND activo=1 AND deleted_at IS NULL'
            : 'SELECT id FROM lotes_animales WHERE id=? AND entorno_id=? AND activo=1',
            [$patient ?? $lot, $env], 'Animal o lote no disponible en este entorno.');
        return ['paciente_id' => $patient, 'lote_id' => $lot];
    }

    private function saveGenealogy(PDO $db, array $data, int $env, int $by): int
    {
        $patient = $this->id($data['paciente_id'] ?? null);
        $father = empty($data['padre_id']) ? null : $this->id($data['padre_id']);
        $mother = empty($data['madre_id']) ? null : $this->id($data['madre_id']);
        $animals = [];
        foreach (array_unique(array_filter([$patient, $father, $mother])) as $id) {
            $animals[$id] = $this->exists($db, 'SELECT a.*,s.codigo AS sexo FROM animales a LEFT JOIN sexos_animales s ON s.id=a.sexo_id WHERE a.id=? AND a.entorno_id=? AND a.deleted_at IS NULL AND a.activo=1', [$id, $env], 'Parentesco fuera del entorno o animal inactivo.');
            if ((int) $animals[$id]['especie_id'] !== (int) $animals[$patient]['especie_id']) {
                throw new RuntimeException('Los progenitores deben ser de la misma especie.');
            }
        }
        if ($patient === $father || $patient === $mother || ($father !== null && $father === $mother)) {
            throw new RuntimeException('Parentesco invalido: animal y progenitores deben ser distintos.');
        }
        if (($father !== null && $animals[$father]['sexo'] !== 'MACHO') || ($mother !== null && $animals[$mother]['sexo'] !== 'HEMBRA')) {
            throw new RuntimeException('Revise el sexo registrado del padre y de la madre.');
        }
        $stmt = $db->prepare('SELECT paciente_id,padre_id,madre_id FROM genealogia WHERE entorno_id=?');
        $stmt->execute([$env]);
        $graph = [];
        foreach ($stmt->fetchAll() as $row) {
            $graph[(int) $row['paciente_id']] = array_map('intval', array_filter([$row['padre_id'], $row['madre_id']]));
        }
        $graph[$patient] = array_values(array_filter([$father, $mother]));
        $ancestors = static function (?int $start) use ($graph): array {
            $seen = [];
            $queue = $start === null ? [] : [$start];
            while ($queue) {
                $node = array_pop($queue);
                if (isset($seen[$node])) {
                    continue;
                }
                $seen[$node] = true;
                array_push($queue, ...($graph[$node] ?? []));
            }
            return array_keys($seen);
        };
        foreach ($graph as $child => $parents) {
            $paternal = $ancestors($parents[0] ?? null);
            $maternal = $ancestors($parents[1] ?? null);
            if (in_array($child, [...$paternal, ...$maternal], true)) {
                throw new RuntimeException('El parentesco crearia un ciclo genealogico.');
            }
            if (array_intersect($paternal, $maternal)) {
                throw new RuntimeException('Los progenitores comparten linaje conocido: riesgo de consanguinidad.');
            }
        }
        $values = [
            'entorno_id' => $env, 'paciente_id' => $patient, 'padre_id' => $father, 'madre_id' => $mother,
            'generacion' => $this->id($data['generacion'] ?? 1),
            'observaciones' => $this->text($data, 'observaciones', 10000, false),
        ];
        $stmt = $db->prepare('SELECT * FROM genealogia WHERE paciente_id=? AND entorno_id=?');
        $stmt->execute([$patient, $env]);
        $old = $stmt->fetch();
        if ($old) {
            $stmt = $db->prepare('UPDATE genealogia SET padre_id=:padre_id,madre_id=:madre_id,generacion=:generacion,observaciones=:observaciones WHERE paciente_id=:paciente_id AND entorno_id=:entorno_id');
            $stmt->execute($values);
            $id = (int) $old['id'];
        } else {
            $id = $this->insert($db, 'genealogia', $values);
        }
        (new AuditService())->log($by, $env, 'GENEALOGIA', $old ? 'EDITAR' : 'CREAR', 'genealogia', $id, $old ?: null, $values);
        return $id;
    }

    public function lot(int $id, int $env): array
    {
        EnvironmentModules::assertFarm($env);
        return $this->exists(Database::connection(), 'SELECT l.*,e.nombre_comun AS especie FROM lotes_animales l JOIN especies e ON e.id=l.especie_id WHERE l.id=? AND l.entorno_id=?', [$id, $env], 'Lote no encontrado.');
    }

    public function lotHistory(int $id, int $env): array
    {
        $this->lot($id, $env);
        $stmt = Database::connection()->prepare('SELECT ec.id AS evento_id,ec.fecha_evento,ec.titulo,eg.* FROM eventos_clinicos ec JOIN examenes_clinicos_generales eg ON eg.evento_clinico_id=ec.id WHERE ec.lote_animal_id=? ORDER BY ec.fecha_evento DESC,ec.id DESC');
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    public function lotExam(int $id, array $data, int $env, int $by): int
    {
        EnvironmentModules::assertFarm($env);
        return Database::transaction(function (PDO $db) use ($id, $data, $env, $by): int {
            $this->lot($id, $env);
            $type = $this->exists($db, "SELECT id FROM tipos_evento_clinico WHERE codigo='CONSULTA_EXTERNA'", [], 'Falta el tipo de consulta.');
            $event = $this->insert($db, 'eventos_clinicos', [
                'lote_animal_id' => $id, 'tipo_evento_id' => $type['id'], 'responsable_id' => $by,
                'fecha_evento' => $this->dateTime((string) ($data['fecha_evento'] ?? '')),
                'titulo' => $this->text($data, 'titulo', 200),
            ]);
            (new ConsultationService())->createClinicalExam($db, $event, $data);
            (new AuditService())->log($by, $env, 'LOTES_ANIMALES', 'EXAMEN', 'eventos_clinicos', $event, null, ['lote_animal_id' => $id]);
            return $event;
        });
    }

    private function lockEnvironment(PDO $db, int $env): void
    {
        $stmt = $db->prepare('SELECT id FROM entornos WHERE id=? FOR UPDATE');
        $stmt->execute([$env]);
    }

    private function exists(PDO $db, string $sql, array $params, string $message): array
    {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException($message);
        }
        return $row;
    }

    private function insert(PDO $db, string $table, array $values): int
    {
        $fields = implode(',', array_keys($values));
        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $stmt = $db->prepare("INSERT INTO $table ($fields) VALUES ($placeholders)");
        $stmt->execute(array_values($values));
        return (int) $db->lastInsertId();
    }

    private function id(mixed $value): int
    {
        if (!is_scalar($value) || !preg_match('/^[1-9][0-9]{0,8}$/D', (string) $value)) {
            throw new RuntimeException('Seleccione un identificador o cantidad entera positiva valida.');
        }
        return (int) $value;
    }

    private function text(array $data, string $field, int $max, bool $required = true): ?string
    {
        $raw = $data[$field] ?? '';
        if (!is_scalar($raw) && $raw !== null) {
            throw new RuntimeException('Valor no valido para ' . $field . '.');
        }
        $value = trim((string) $raw);
        if (($required && $value === '') || mb_strlen($value) > $max) {
            throw new RuntimeException('Revise el campo ' . $field . ' (maximo ' . $max . ' caracteres).');
        }
        return $value === '' ? null : $value;
    }

    private function quantity(mixed $value): string
    {
        if (!is_scalar($value) || !preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', (string) $value) || (float) $value <= 0) {
            throw new RuntimeException('La cantidad debe ser positiva, con hasta ocho enteros y dos decimales.');
        }
        return (string) $value;
    }

    private function date(string $value): string
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new RuntimeException('Fecha no valida.');
        }
        return $value;
    }

    private function dateTime(string $value): string
    {
        $value = str_replace('T', ' ', $value);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d H:i', $value);
        if (!$date || $date->format('Y-m-d H:i') !== $value) {
            throw new RuntimeException('Fecha y hora no validas.');
        }
        return $date->format('Y-m-d H:i:s');
    }
}
