<?php

namespace App\Models;

use App\Core\Model;

class PreventiveCare extends Model
{
    public function vaccinationsByEnvironment(
        int $environmentId,
        string $search = ''
    ): array {
        $sql = '
            SELECT
                vac.id,
                vac.evento_clinico_id,
                vac.dosis,
                vac.lote,
                vac.fecha_revacunacion,
                vac.observaciones,
                vac.created_at,

                v.nombre AS vacuna,

                um.simbolo AS unidad,

                a.id AS animal_id,
                a.nombre AS paciente,

                e.nombre_comun AS especie,
                r.nombre AS raza,

                p.nombres AS propietario_nombres,
                p.apellidos AS propietario_apellidos,

                CONCAT(
                    u.nombres,
                    " ",
                    u.apellidos
                ) AS aplicada_por_nombre,

                ec.fecha_evento

            FROM vacunaciones vac

            INNER JOIN eventos_clinicos ec
                ON ec.id = vac.evento_clinico_id

            INNER JOIN animales a
                ON a.id = ec.animal_id

            INNER JOIN especies e
                ON e.id = a.especie_id

            LEFT JOIN razas r
                ON r.id = a.raza_id

            LEFT JOIN propietarios_entornos pe
                ON pe.id = a.propietario_entorno_id

            LEFT JOIN propietarios p
                ON p.id = pe.propietario_id

            INNER JOIN vacunas v
                ON v.id = vac.vacuna_id

            LEFT JOIN unidades_medida um
                ON um.id = vac.unidad_dosis_id

            INNER JOIN usuarios u
                ON u.id = vac.aplicada_por

            WHERE a.entorno_id = :entorno
              AND a.deleted_at IS NULL
        ';

        $params = [
            'entorno' => $environmentId,
        ];

        if ($search !== '') {
            $sql .= '
                AND (
                    a.nombre LIKE :q
                    OR v.nombre LIKE :q
                    OR vac.lote LIKE :q
                    OR p.nombres LIKE :q
                    OR p.apellidos LIKE :q
                )
            ';

            $params['q']
                = '%' . $search . '%';
        }

        $sql .= '
            ORDER BY
                ec.fecha_evento DESC,
                vac.id DESC

            LIMIT 100
        ';

        $stmt = $this->db->prepare(
            $sql
        );

        $stmt->execute(
            $params
        );

        return $stmt->fetchAll();
    }


    public function dewormingsByEnvironment(
        int $environmentId,
        string $search = ''
    ): array {
        $sql = '
            SELECT
                d.id,
                d.evento_clinico_id,
                d.dosis,
                d.proxima_desparasitacion,
                d.observaciones,
                d.created_at,

                f.nombre AS farmaco,

                um.simbolo AS unidad,

                a.id AS animal_id,
                a.nombre AS paciente,

                e.nombre_comun AS especie,
                r.nombre AS raza,

                p.nombres AS propietario_nombres,
                p.apellidos AS propietario_apellidos,

                CONCAT(
                    u.nombres,
                    " ",
                    u.apellidos
                ) AS aplicada_por_nombre,

                ec.fecha_evento

            FROM desparasitaciones d

            INNER JOIN eventos_clinicos ec
                ON ec.id = d.evento_clinico_id

            INNER JOIN animales a
                ON a.id = ec.animal_id

            INNER JOIN especies e
                ON e.id = a.especie_id

            LEFT JOIN razas r
                ON r.id = a.raza_id

            LEFT JOIN propietarios_entornos pe
                ON pe.id = a.propietario_entorno_id

            LEFT JOIN propietarios p
                ON p.id = pe.propietario_id

            INNER JOIN farmacos f
                ON f.id = d.farmaco_id

            LEFT JOIN unidades_medida um
                ON um.id = d.unidad_dosis_id

            INNER JOIN usuarios u
                ON u.id = d.aplicada_por

            WHERE a.entorno_id = :entorno
              AND a.deleted_at IS NULL
        ';

        $params = [
            'entorno' => $environmentId,
        ];

        if ($search !== '') {
            $sql .= '
                AND (
                    a.nombre LIKE :q
                    OR f.nombre LIKE :q
                    OR p.nombres LIKE :q
                    OR p.apellidos LIKE :q
                )
            ';

            $params['q']
                = '%' . $search . '%';
        }

        $sql .= '
            ORDER BY
                ec.fecha_evento DESC,
                d.id DESC

            LIMIT 100
        ';

        $stmt = $this->db->prepare(
            $sql
        );

        $stmt->execute(
            $params
        );

        return $stmt->fetchAll();
    }


    public function upcomingVaccinations(
        int $environmentId,
        int $days = 30
    ): array {
        $days = max(
            1,
            min(
                $days,
                365
            )
        );

        $stmt = $this->db->prepare(
            "
            SELECT
                vac.id,
                vac.fecha_revacunacion,

                v.nombre AS vacuna,

                a.id AS animal_id,
                a.nombre AS paciente,

                p.nombres AS propietario_nombres,
                p.apellidos AS propietario_apellidos

            FROM vacunaciones vac

            INNER JOIN eventos_clinicos ec
                ON ec.id = vac.evento_clinico_id

            INNER JOIN animales a
                ON a.id = ec.animal_id

            INNER JOIN vacunas v
                ON v.id = vac.vacuna_id

            LEFT JOIN propietarios_entornos pe
                ON pe.id = a.propietario_entorno_id

            LEFT JOIN propietarios p
                ON p.id = pe.propietario_id

            WHERE a.entorno_id = :entorno

              AND vac.fecha_revacunacion
                  BETWEEN CURDATE()
                  AND DATE_ADD(
                      CURDATE(),
                      INTERVAL {$days} DAY
                  )

              AND a.activo = 1
              AND a.deleted_at IS NULL

            ORDER BY vac.fecha_revacunacion
            "
        );

        $stmt->execute([
            'entorno'
                => $environmentId,
        ]);

        return $stmt->fetchAll();
    }


    public function upcomingDewormings(
        int $environmentId,
        int $days = 30
    ): array {
        $days = max(
            1,
            min(
                $days,
                365
            )
        );

        $stmt = $this->db->prepare(
            "
            SELECT
                d.id,
                d.proxima_desparasitacion,

                f.nombre AS farmaco,

                a.id AS animal_id,
                a.nombre AS paciente,

                p.nombres AS propietario_nombres,
                p.apellidos AS propietario_apellidos

            FROM desparasitaciones d

            INNER JOIN eventos_clinicos ec
                ON ec.id = d.evento_clinico_id

            INNER JOIN animales a
                ON a.id = ec.animal_id

            INNER JOIN farmacos f
                ON f.id = d.farmaco_id

            LEFT JOIN propietarios_entornos pe
                ON pe.id = a.propietario_entorno_id

            LEFT JOIN propietarios p
                ON p.id = pe.propietario_id

            WHERE a.entorno_id = :entorno

              AND d.proxima_desparasitacion
                  BETWEEN CURDATE()
                  AND DATE_ADD(
                      CURDATE(),
                      INTERVAL {$days} DAY
                  )

              AND a.activo = 1
              AND a.deleted_at IS NULL

            ORDER BY d.proxima_desparasitacion
            "
        );

        $stmt->execute([
            'entorno'
                => $environmentId,
        ]);

        return $stmt->fetchAll();
    }
}
