<?php

namespace App\Models;

use App\Core\Model;

class Catalog extends Model
{
    public function identificationTypes(): array
    {
        return $this->db
            ->query(
                '
                SELECT
                    id,
                    codigo,
                    nombre

                FROM tipos_identificacion

                WHERE activo = 1

                ORDER BY nombre
                '
            )
            ->fetchAll();
    }

    public function species(): array
    {
        return $this->db
            ->query(
                '
                SELECT
                    e.id,
                    e.codigo,
                    e.nombre_comun,
                    e.nombre_cientifico,
                    ca.nombre AS categoria

                FROM especies e

                INNER JOIN categorias_animales ca
                    ON ca.id = e.categoria_id

                WHERE e.activo = 1

                ORDER BY
                    ca.nombre,
                    e.nombre_comun
                '
            )
            ->fetchAll();
    }

    public function breeds(
        ?int $speciesId = null
    ): array {
        $sql = '
            SELECT
                id,
                especie_id,
                nombre

            FROM razas

            WHERE activo = 1
        ';

        $params = [];

        if ($speciesId) {
            $sql .= '
                AND especie_id = :especie
            ';

            $params['especie']
                = $speciesId;
        }

        $sql .= '
            ORDER BY nombre
        ';

        $stmt = $this->db->prepare(
            $sql
        );

        $stmt->execute(
            $params
        );

        return $stmt->fetchAll();
    }

    public function sexes(): array
    {
        return $this->db
            ->query(
                '
                SELECT
                    id,
                    codigo,
                    nombre

                FROM sexos_animales

                ORDER BY id
                '
            )
            ->fetchAll();
    }

    public function ownersForEnvironment(
        int $environmentId
    ): array {
        $stmt = $this->db->prepare(
            '
            SELECT
                pe.id
                    AS propietario_entorno_id,

                p.id
                    AS propietario_id,

                p.nombres,
                p.apellidos,
                p.identificacion

            FROM propietarios_entornos pe

            INNER JOIN propietarios p
                ON p.id = pe.propietario_id

            WHERE pe.entorno_id = :entorno

              AND pe.activo = 1

              AND p.activo = 1

              AND p.deleted_at IS NULL

            ORDER BY
                p.apellidos,
                p.nombres
            '
        );

        $stmt->execute([
            'entorno'
                => $environmentId,
        ]);

        return $stmt->fetchAll();
    }
}