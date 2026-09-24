<?php

namespace App\Models;

use App\Core\Model;

class Grooming extends Model
{
    public function byEnvironment(
        int $environmentId,
        string $search = ''
    ): array {
        $sql = '
            SELECT
                pe.evento_clinico_id,
                pe.servicio_id,
                pe.proxima_peluqueria,
                pe.observaciones,
                pe.created_at,
                pe.updated_at,

                s.codigo AS servicio_codigo,
                s.nombre AS servicio,
                s.precio_base,

                ec.fecha_evento,
                ec.responsable_id,

                a.id AS animal_id,
                a.nombre AS paciente,

                e.nombre_comun AS especie,

                r.nombre AS raza,

                p.id AS propietario_id,
                p.nombres AS propietario_nombres,
                p.apellidos AS propietario_apellidos,

                CONCAT(
                    u.nombres,
                    " ",
                    u.apellidos
                ) AS responsable_nombre

            FROM peluquerias pe

            INNER JOIN eventos_clinicos ec
                ON ec.id = pe.evento_clinico_id

            INNER JOIN animales a
                ON a.id = ec.animal_id

            INNER JOIN especies e
                ON e.id = a.especie_id

            LEFT JOIN razas r
                ON r.id = a.raza_id

            LEFT JOIN propietarios_entornos pen
                ON pen.id = a.propietario_entorno_id

            LEFT JOIN propietarios p
                ON p.id = pen.propietario_id

            INNER JOIN servicios s
                ON s.id = pe.servicio_id

            INNER JOIN usuarios u
                ON u.id = ec.responsable_id

            WHERE a.entorno_id = :entorno
              AND a.deleted_at IS NULL
              AND ec.anulado_at IS NULL
        ';

        $params = [
            'entorno' => $environmentId,
        ];

        if ($search !== '') {
            $sql .= '
                AND (
                    a.nombre LIKE :q
                    OR s.nombre LIKE :q
                    OR p.nombres LIKE :q
                    OR p.apellidos LIKE :q
                )
            ';

            $params['q'] = '%' . $search . '%';
        }

        $sql .= '
            ORDER BY
                ec.fecha_evento DESC,
                pe.evento_clinico_id DESC

            LIMIT 100
        ';

        $stmt = $this->db->prepare($sql);

        $stmt->execute($params);

        return $stmt->fetchAll();
    }


    public function upcoming(
        int $environmentId,
        int $days = 30
    ): array {
        $days = max(
            1,
            min($days, 365)
        );

        $stmt = $this->db->prepare(
            "
            SELECT
                pe.evento_clinico_id,
                pe.proxima_peluqueria,

                s.nombre AS servicio,

                a.id AS animal_id,
                a.nombre AS paciente,

                p.nombres AS propietario_nombres,
                p.apellidos AS propietario_apellidos

            FROM peluquerias pe

            INNER JOIN eventos_clinicos ec
                ON ec.id = pe.evento_clinico_id

            INNER JOIN animales a
                ON a.id = ec.animal_id

            INNER JOIN servicios s
                ON s.id = pe.servicio_id

            LEFT JOIN propietarios_entornos pen
                ON pen.id = a.propietario_entorno_id

            LEFT JOIN propietarios p
                ON p.id = pen.propietario_id

            WHERE a.entorno_id = :entorno

              AND pe.proxima_peluqueria
                  BETWEEN CURDATE()
                  AND DATE_ADD(
                      CURDATE(),
                      INTERVAL {$days} DAY
                  )

              AND a.activo = 1
              AND a.deleted_at IS NULL
              AND ec.anulado_at IS NULL

            ORDER BY pe.proxima_peluqueria ASC
            "
        );

        $stmt->execute([
            'entorno' => $environmentId,
        ]);

        return $stmt->fetchAll();
    }
}
