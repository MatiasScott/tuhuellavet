<?php

namespace App\Models;

use App\Core\Model;

class Consultation extends Model
{
    public function find(
        int $eventId,
        int $environmentId
    ): ?array {
        $stmt = $this->db->prepare(
            '
            SELECT
                ec.id AS evento_id,
                ec.animal_id,
                ec.fecha_evento,
                ec.titulo,
                ec.observaciones,

                ce.motivo_consulta,
                ce.anamnesis,
                ce.antecedentes,
                ce.recomendaciones,

                eg.alimentacion,
                eg.historial_reproductivo,
                eg.frecuencia_cardiaca,
                eg.frecuencia_respiratoria,
                eg.temperatura_c,
                eg.tiempo_llenado_capilar_seg,
                eg.ganglios_linfaticos,
                eg.condicion_corporal,
                eg.vomitos,
                eg.diarrea,
                eg.tos,
                eg.observaciones AS examen_observaciones,

                a.nombre AS paciente_nombre,
                e.nombre_comun AS especie,
                r.nombre AS raza,

                CONCAT(
                    u.nombres,
                    " ",
                    u.apellidos
                ) AS responsable_nombre

            FROM eventos_clinicos ec

            INNER JOIN animales a
                ON a.id = ec.animal_id

            INNER JOIN consultas_externas ce
                ON ce.evento_clinico_id = ec.id

            LEFT JOIN examenes_clinicos_generales eg
                ON eg.evento_clinico_id = ec.id

            INNER JOIN especies e
                ON e.id = a.especie_id

            LEFT JOIN razas r
                ON r.id = a.raza_id

            INNER JOIN usuarios u
                ON u.id = ec.responsable_id

            WHERE ec.id = :evento
              AND a.entorno_id = :entorno

            LIMIT 1
            '
        );

        $stmt->execute([
            'evento' => $eventId,
            'entorno' => $environmentId,
        ]);

        return $stmt->fetch() ?: null;
    }

    public function listByEnvironment(
        int $environmentId,
        string $search = ''
    ): array {
        $sql = '
            SELECT
                ec.id,
                ec.fecha_evento,

                a.id AS animal_id,
                a.nombre AS paciente,

                e.nombre_comun AS especie,
                r.nombre AS raza,

                ce.motivo_consulta,

                CONCAT(
                    u.nombres,
                    " ",
                    u.apellidos
                ) AS responsable

            FROM eventos_clinicos ec

            INNER JOIN tipos_evento_clinico tec
                ON tec.id = ec.tipo_evento_id

            INNER JOIN animales a
                ON a.id = ec.animal_id

            INNER JOIN especies e
                ON e.id = a.especie_id

            LEFT JOIN razas r
                ON r.id = a.raza_id

            INNER JOIN consultas_externas ce
                ON ce.evento_clinico_id = ec.id

            INNER JOIN usuarios u
                ON u.id = ec.responsable_id

            WHERE a.entorno_id = :entorno
              AND tec.codigo = "CONSULTA_EXTERNA"
        ';

        $params = [
            'entorno' => $environmentId,
        ];

        if ($search !== '') {
            $sql .= '
                AND (
                    a.nombre LIKE :q
                    OR ce.motivo_consulta LIKE :q
                    OR e.nombre_comun LIKE :q
                    OR r.nombre LIKE :q
                )
            ';

            $params['q'] = '%' . $search . '%';
        }

        $sql .= '
            ORDER BY ec.fecha_evento DESC
            LIMIT 100
        ';

        $stmt = $this->db->prepare($sql);

        $stmt->execute($params);

        return $stmt->fetchAll();
    }
}
