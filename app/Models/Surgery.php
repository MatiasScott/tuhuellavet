<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Surgery extends Model
{
    public function list(
        int $environmentId,
        string $search = ''
    ): array {
        $sql = '
            SELECT
                c.*,
                a.id AS animal_id,
                a.nombre AS paciente,
                pq.nombre AS procedimiento,
                CONCAT(u.nombres, " ", u.apellidos) AS medico
            FROM cirugias c
            INNER JOIN eventos_clinicos ec
                ON ec.id = c.evento_clinico_id
            INNER JOIN animales a
                ON a.id = ec.animal_id
            INNER JOIN procedimientos_quirurgicos pq
                ON pq.id = c.procedimiento_quirurgico_id
            INNER JOIN usuarios u
                ON u.id = c.medico_responsable_id
            WHERE a.entorno_id = :entorno
              AND a.deleted_at IS NULL
        ';

        $params = [
            'entorno' => $environmentId,
        ];

        if ($search !== '') {
            $sql .= '
                AND CONCAT_WS(
                    " ",
                    a.nombre,
                    pq.nombre,
                    c.diagnostico_preoperatorio
                ) LIKE :buscar
            ';

            $params['buscar'] =
                '%' . $search . '%';
        }

        $sql .= '
            ORDER BY c.fecha_inicio DESC
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function find(
        int $eventId,
        int $environmentId
    ): array|false {
        $stmt = $this->db->prepare(
            '
            SELECT
                c.*,
                ec.fecha_evento,
                a.id AS animal_id,
                a.nombre AS paciente,
                e.nombre_comun AS especie,
                pq.nombre AS procedimiento,
                CONCAT(
                    u.nombres,
                    " ",
                    u.apellidos
                ) AS medico
            FROM cirugias c
            INNER JOIN eventos_clinicos ec
                ON ec.id = c.evento_clinico_id
            INNER JOIN animales a
                ON a.id = ec.animal_id
            INNER JOIN especies e
                ON e.id = a.especie_id
            INNER JOIN procedimientos_quirurgicos pq
                ON pq.id = c.procedimiento_quirurgico_id
            INNER JOIN usuarios u
                ON u.id = c.medico_responsable_id
            WHERE c.evento_clinico_id = :evento
              AND a.entorno_id = :entorno
              AND a.deleted_at IS NULL
            LIMIT 1
            '
        );

        $stmt->execute([
            'evento' => $eventId,
            'entorno' => $environmentId,
        ]);

        return $stmt->fetch();
    }

    public function anesthesia(
        int $eventId
    ): array {
        $stmt = $this->db->prepare(
            '
            SELECT
                ca.*,
                ta.nombre AS tipo_anestesia,
                CONCAT(
                    u.nombres,
                    " ",
                    u.apellidos
                ) AS responsable
            FROM cirugia_anestesias ca
            LEFT JOIN tipos_anestesia ta
                ON ta.id = ca.tipo_anestesia_id
            INNER JOIN usuarios u
                ON u.id = ca.responsable_id
            WHERE ca.cirugia_evento_id = :evento
            ORDER BY ca.id
            '
        );

        $stmt->execute([
            'evento' => $eventId,
        ]);

        return $stmt->fetchAll();
    }

    public function team(
        int $eventId
    ): array {
        $stmt = $this->db->prepare(
            '
            SELECT
                ce.cirugia_evento_id,
                ce.usuario_id,
                ce.funcion_id,
                ce.created_at,
                feq.codigo AS funcion_codigo,
                feq.nombre AS funcion,
                CONCAT(
                    u.nombres,
                    " ",
                    u.apellidos
                ) AS integrante
            FROM cirugia_equipo ce
            INNER JOIN usuarios u
                ON u.id = ce.usuario_id
            INNER JOIN funciones_equipo_quirurgico feq
                ON feq.id = ce.funcion_id
            WHERE ce.cirugia_evento_id = :evento
            ORDER BY
                feq.id,
                u.apellidos,
                u.nombres
            '
        );

        $stmt->execute([
            'evento' => $eventId,
        ]);

        return $stmt->fetchAll();
    }

    public function evolutions(
        int $eventId
    ): array {
        $stmt = $this->db->prepare(
            '
            SELECT
                ce.*,
                CONCAT(
                    u.nombres,
                    " ",
                    u.apellidos
                ) AS registrado_por_nombre
            FROM cirugia_evoluciones ce
            INNER JOIN usuarios u
                ON u.id = ce.registrado_por
            WHERE ce.cirugia_evento_id = :evento
            ORDER BY ce.fecha_hora DESC, ce.id DESC
            '
        );

        $stmt->execute([
            'evento' => $eventId,
        ]);

        return $stmt->fetchAll();
    }

    public function files(
        int $eventId
    ): array {
        $stmt = $this->db->prepare(
            '
            SELECT
                a.*,
                ca.descripcion
            FROM cirugia_archivos ca
            INNER JOIN archivos a
                ON a.id = ca.archivo_id
            WHERE ca.cirugia_evento_id = :evento
              AND a.deleted_at IS NULL
            ORDER BY a.created_at DESC
            '
        );

        $stmt->execute([
            'evento' => $eventId,
        ]);

        return $stmt->fetchAll();
    }
}
