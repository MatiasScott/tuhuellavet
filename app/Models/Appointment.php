<?php

namespace App\Models;

use App\Core\Model;

class Appointment extends Model
{
    public function countToday(
        int $environmentId
    ): int {
        $stmt = $this->db->prepare(
            '
            SELECT COUNT(*)

            FROM citas

            WHERE entorno_id = :entorno
              AND DATE(fecha_inicio) = CURDATE()
              AND deleted_at IS NULL
            '
        );

        $stmt->execute([
            'entorno' => $environmentId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function nextToday(
        int $environmentId,
        int $limit = 6
    ): array {
        $limit = max(
            1,
            min($limit, 20)
        );

        $sql = "
            SELECT
                c.id,
                c.fecha_inicio,
                c.fecha_fin,
                c.motivo,

                a.id AS animal_id,
                a.nombre AS animal,

                e.nombre_comun AS especie,

                r.nombre AS raza,

                ec.nombre AS estado

            FROM citas c

            INNER JOIN animales a
                ON a.id = c.animal_id

            INNER JOIN especies e
                ON e.id = a.especie_id

            LEFT JOIN razas r
                ON r.id = a.raza_id

            INNER JOIN estados_cita ec
                ON ec.id = c.estado_cita_id

            WHERE c.entorno_id = :entorno

              AND DATE(
                    c.fecha_inicio
                  ) = CURDATE()

              AND c.deleted_at IS NULL

            ORDER BY
                c.fecha_inicio ASC

            LIMIT {$limit}
        ";

        $stmt = $this->db->prepare(
            $sql
        );

        $stmt->execute([
            'entorno' => $environmentId,
        ]);

        return $stmt->fetchAll();
    }
}