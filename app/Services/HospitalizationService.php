<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

class HospitalizationService
{
    public function create(array $d, int $env, int $by): int
    {
        return Database::transaction(function (PDO $db) use ($d, $env, $by) {
            $animal = (int)($d['animal_id'] ?? 0);
            $this->patient($db, $animal, $env);
            $s = $db->prepare(
                "SELECT 1 FROM hospitalizaciones h 
                JOIN eventos_clinicos ec ON ec.id=h.evento_clinico_id 
                JOIN estados_hospitalizacion eh ON eh.id=h.estado_hospitalizacion_id 
                WHERE ec.animal_id=:a AND eh.codigo='ACTIVA' LIMIT 1"
            );
            $s->execute(['a' => $animal]);
            if ($s->fetchColumn())
                throw new RuntimeException('El paciente ya tiene una hospitalización activa.');
            $type = $this->id($db, 'tipos_evento_clinico', 'HOSPITALIZACION');
            $status = $this->id($db, 'estados_hospitalizacion', 'ACTIVA');
            $date = $this->dt($d['fecha_ingreso'] ?? null);
            $s = $db->prepare(
                'INSERT INTO eventos_clinicos
                (animal_id,tipo_evento_id,responsable_id,fecha_evento,titulo) 
                VALUES(:a,:t,:u,:f,"Hospitalización")'
            );
            $s->execute(['a' => $animal, 't' => $type, 'u' => $by, 'f' => $date]);
            $event = (int)$db->lastInsertId();
            $s = $db->prepare(
                'INSERT INTO hospitalizaciones
                    (evento_clinico_id, estado_hospitalizacion_id, fecha_ingreso, motivo_ingreso,       indicaciones_generales)
                VALUES
                    (:e, :s, :f, :m, :g)'
            );
            $s->execute([
                'e' => $event,
                's' => $status,
                'f' => $date,
                'm' => trim((string)($d['motivo_ingreso'] ?? '')) ?: null,
                'g' => trim((string)($d['indicaciones_generales'] ?? '')) ?: null,
            ]);
            if (!empty($d['peso_kg'])) $this->weight($db, $animal, (float)$d['peso_kg'], $by, $date);
            if ($this->hasSigns($d)) $this->sign($db, $event, $d, $by, $date);
            (new AuditService())->log($by, $env, 'HOSPITALIZACION', 'CREAR', 'hospitalizaciones', $event);
            return $event;
        });
    }

    public function addSigns(int $event, array $d, int $env, int $by): void
    {
        Database::transaction(function (PDO $db) use ($event, $d, $env, $by) {
            $h = $this->hospital($db, $event, $env, true);
            $date = $this->dt($d['fecha_hora'] ?? null);
            $this->sign($db, $event, $d, $by, $date);
            if (!empty($d['peso_kg'])) $this->weight($db, (int)$h['animal_id'], (float)$d['peso_kg'], $by, $date);
            (new AuditService())->log($by, $env, 'HOSPITALIZACION', 'REGISTRAR_SIGNOS', 'hospitalizacion_signos', $event);
        });
    }

    public function addEvolution(int $event, array $d, int $env, int $by): void
    {
        Database::transaction(function (PDO $db) use ($event, $d, $env, $by) {
            $this->hospital($db, $event, $env, true);
            $e = trim((string)($d['evolucion'] ?? ''));
            if ($e === '')
                throw new RuntimeException('La evolución es obligatoria.');
            $s = $db->prepare(
                'INSERT INTO hospitalizacion_evoluciones
                (hospitalizacion_evento_id,registrado_por,fecha_hora,evolucion,observaciones) 
                VALUES(:h,:u,:f,:e,:o)'
            );
            $s->execute([
                'h' => $event,
                'u' => $by,
                'f' => $this->dt($d['fecha_hora'] ?? null),
                'e' => $e,
                'o' => trim((string)($d['observaciones'] ?? '')) ?: null
            ]);
            (new AuditService())->log($by, $env, 'HOSPITALIZACION', 'REGISTRAR_EVOLUCION', 'hospitalizacion_evoluciones', (int)$db->lastInsertId());
        });
    }

    public function addFluid(int $event, array $d, int $env, int $by): void
    {
        Database::transaction(function (PDO $db) use ($event, $d, $env, $by) {
            $h = $this->hospital($db, $event, $env, true);
            $exec = !empty($d['formula_ejecucion_id']) ? (int)$d['formula_ejecucion_id'] : null;
            $formula = null;
            if ($exec) {
                $s = $db->prepare(
                    'SELECT fv.formula_id,fe.animal_id,fe.evento_clinico_id 
                    FROM formula_ejecuciones fe 
                    JOIN formula_versiones fv ON fv.id=fe.formula_version_id 
                    WHERE fe.id=:id'
                );
                $s->execute(['id' => $exec]);
                $x = $s->fetch();
                if (!$x || (int)$x['animal_id'] !== (int)$h['animal_id'] || ($x['evento_clinico_id'] !== null && (int)$x['evento_clinico_id'] !== $event))
                    throw new RuntimeException('La ejecución de fórmula no corresponde a esta hospitalización.');
                $formula = (int)$x['formula_id'];
            }
            $s = $db->prepare(
                'INSERT INTO hospitalizacion_fluidoterapias(
                hospitalizacion_evento_id,categoria_mantenimiento_id,registrado_por,fecha_inicio,fecha_fin,mantenimiento_ml,rehidratacion_ml,porcentaje_deshidratacion,volumen_total_ml,velocidad_ml_hora,formula_id,formula_ejecucion_id,observaciones) 
                VALUES(:h,:c,:u,:fi,:ff,:m,:r,:p,:v,:ve,:fo,:fe,:o)'
            );
            $s->execute([
                'h' => $event,
                'c' => !empty($d['categoria_mantenimiento_id']) ? (int)$d['categoria_mantenimiento_id'] : null,
                'u' => $by,
                'fi' => $this->dt($d['fecha_inicio'] ?? null),
                'ff' => !empty($d['fecha_fin']) ? $this->dt($d['fecha_fin']) : null,
                'm' => $this->num($d['mantenimiento_ml'] ?? null),
                'r' => $this->num($d['rehidratacion_ml'] ?? null),
                'p' => $this->num($d['porcentaje_deshidratacion'] ?? null),
                'v' => $this->num($d['volumen_total_ml'] ?? null),
                've' => $this->num($d['velocidad_ml_hora'] ?? null),
                'fo' => $formula,
                'fe' => $exec,
                'o' => trim((string)($d['observaciones'] ?? '')) ?: null
            ]);
            (new AuditService())->log($by, $env, 'HOSPITALIZACION', 'REGISTRAR_FLUIDOTERAPIA', 'hospitalizacion_fluidoterapias', (int)$db->lastInsertId());
        });
    }

    public function close(int $event, array $d, int $env, int $by): void
    {
        Database::transaction(function (PDO $db) use ($event, $d, $env, $by) {
            $h = $this->hospital($db, $event, $env, true);
            $code = strtoupper(trim((string)($d['estado_codigo'] ?? 'ALTA')));
            if (!in_array($code, ['ALTA', 'TRASLADO', 'FALLECIDO', 'CANCELADA'], true))
                throw new RuntimeException('Estado de cierre inválido.');
            $status = $this->id($db, 'estados_hospitalizacion', $code);
            $exit = $this->dt($d['fecha_salida'] ?? null);
            if (strtotime($exit) < strtotime($h['fecha_ingreso']))
                throw new RuntimeException('La salida no puede ser anterior al ingreso.');
            $s = $db->prepare(
                'UPDATE hospitalizaciones 
                SET estado_hospitalizacion_id=:s,fecha_salida=:f,observaciones_alta=:o,responsable_alta_id=:u 
                WHERE evento_clinico_id=:e'
            );
            $s->execute([
                's' => $status,
                'f' => $exit,
                'o' => trim((string)($d['observaciones_alta'] ?? '')) ?: null,
                'u' => $by,
                'e' => $event
            ]);
            $db->prepare(
                'UPDATE tratamientos 
                SET fecha_fin=:f 
                WHERE evento_clinico_id=:e 
                AND fecha_fin IS NULL'
            )->execute(['f' => $exit, 'e' => $event]);
            $db->prepare(
                'UPDATE hospitalizacion_fluidoterapias 
                SET fecha_fin=:f 
                WHERE hospitalizacion_evento_id=:e 
                AND fecha_fin IS NULL'
            )->execute(['f' => $exit, 'e' => $event]);
            (new AuditService())->log($by, $env, 'HOSPITALIZACION', 'CERRAR', 'hospitalizaciones', $event, ['estado' => 'ACTIVA'], ['estado' => $code]);
        });
    }

    private function hospital(PDO $db, int $event, int $env, bool $active = false): array
    {
        $s = $db->prepare(
            'SELECT h.*,ec.animal_id,eh.codigo AS estado_codigo 
            FROM hospitalizaciones h 
            JOIN eventos_clinicos ec ON ec.id=h.evento_clinico_id 
            JOIN animales a ON a.id=ec.animal_id 
            JOIN estados_hospitalizacion eh ON eh.id=h.estado_hospitalizacion_id 
            WHERE h.evento_clinico_id=:h 
            AND a.entorno_id=:e'
        );
        $s->execute(['h' => $event, 'e' => $env]);
        $h = $s->fetch();
        if (!$h) throw new RuntimeException('Hospitalización no encontrada.');
        if ($active && $h['estado_codigo'] !== 'ACTIVA')
            throw new RuntimeException('La hospitalización ya está cerrada.');
        return $h;
    }

    private function patient(PDO $db, int $a, int $e): void
    {
        $s = $db->prepare(
            'SELECT 1 FROM animales 
            WHERE id=:a 
            AND entorno_id=:e 
            AND activo=1 
            AND deleted_at IS NULL'
        );
        $s->execute(['a' => $a, 'e' => $e]);
        if (!$s->fetchColumn())
            throw new RuntimeException('Paciente no válido.');
    }

    private function id(PDO $db, string $table, string $code): int
    {
        $allowed = ['tipos_evento_clinico', 'estados_hospitalizacion'];
        if (!in_array($table, $allowed, true))
            throw new RuntimeException('Catálogo inválido.');
        $s = $db->prepare(
            "SELECT id FROM {$table} 
            WHERE codigo=:c LIMIT 1"
        );
        $s->execute(['c' => $code]);
        $id = $s->fetchColumn();
        if (!$id)
            throw new RuntimeException('Falta catálogo ' . $code);
        return (int)$id;
    }

    private function sign(PDO $db, int $h, array $d, int $u, string $f): void
    {
        $s = $db->prepare(
            'INSERT INTO hospitalizacion_signos
            (hospitalizacion_evento_id,registrado_por,fecha_hora,temperatura_c,frecuencia_cardiaca,frecuencia_respiratoria,tiempo_llenado_capilar_seg,condicion_corporal,nivel_dolor,apetito,hidratacion,vomitos,diarrea,tos,observaciones) 
            VALUES(:h,:u,:f,:t,:fc,:fr,:tlc,:cc,:d,:a,:hi,:v,:di,:to,:o)'
        );
        $s->execute([
            'h' => $h,
            'u' => $u,
            'f' => $f,
            't' => $this->num($d['temperatura_c'] ?? null),
            'fc' => $this->num($d['frecuencia_cardiaca'] ?? null),
            'fr' => $this->num($d['frecuencia_respiratoria'] ?? null),
            'tlc' => $this->num($d['tiempo_llenado_capilar_seg'] ?? null),
            'cc' => trim((string)($d['condicion_corporal'] ?? '')) ?: null,
            'd' => trim((string)($d['nivel_dolor'] ?? '')) ?: null,
            'a' => trim((string)($d['apetito'] ?? '')) ?: null,
            'hi' => trim((string)($d['hidratacion'] ?? '')) ?: null,
            'v' => !empty($d['vomitos']) ? 1 : 0,
            'di' => !empty($d['diarrea']) ? 1 : 0,
            'to' => !empty($d['tos']) ? 1 : 0,
            'o' => trim((string)($d['observaciones_signos'] ?? $d['observaciones'] ?? '')) ?: null
        ]);
    }

    private function weight(PDO $db, int $a, float $kg, int $u, string $f): void
    {
        if ($kg <= 0)
            throw new RuntimeException('Peso inválido.');
        $db->prepare(
            'INSERT INTO animales_pesos
            (animal_id,peso_kg,registrado_por,origen,fecha_registro,observacion) 
            VALUES(:a,:p,:u,"HOSPITALIZACION",:f,"Peso registrado durante hospitalización")'
        )
            ->execute([
                'a' => $a,
                'p' => $kg,
                'u' => $u,
                'f' => $f
            ]);
    }

    private function hasSigns(array $d): bool
    {
        foreach (['temperatura_c', 'frecuencia_cardiaca', 'frecuencia_respiratoria', 'nivel_dolor', 'hidratacion', 'vomitos', 'diarrea', 'tos'] as $k) if (isset($d[$k]) && $d[$k] !== '') return true;
        return false;
    }

    private function num(mixed $v): ?float
    {
        return ($v === null || $v === '') ? null : (float)$v;
    }

    private function dt(mixed $v): string
    {
        if (!$v) return date('Y-m-d H:i:s');
        $v = str_replace('T', ' ', trim((string)$v));
        return strlen($v) === 16 ? $v . ':00' : $v;
    }
}
