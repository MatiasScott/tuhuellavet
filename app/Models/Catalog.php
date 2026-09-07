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

    public function drugs(): array
    {
        return $this->db
            ->query(
                '
            SELECT
                id,
                nombre

            FROM farmacos

            WHERE activo = 1

            ORDER BY nombre
            '
            )
            ->fetchAll();
    }


    public function drugPresentations(): array
    {
        return $this->db
            ->query(
                '
            SELECT
                fp.id,
                fp.farmaco_id,
                fp.nombre_comercial,
                fp.descripcion,
                ff.nombre AS forma_farmaceutica

            FROM farmaco_presentaciones fp

            INNER JOIN formas_farmaceuticas ff
                ON ff.id = fp.forma_farmaceutica_id

            WHERE fp.activo = 1

            ORDER BY
                fp.farmaco_id,
                fp.nombre_comercial,
                ff.nombre
            '
            )
            ->fetchAll();
    }


    public function administrationRoutes(): array
    {
        return $this->db
            ->query(
                '
            SELECT
                id,
                codigo,
                nombre

            FROM vias_administracion

            WHERE activo = 1

            ORDER BY nombre
            '
            )
            ->fetchAll();
    }


    public function administrationFrequencies(): array
    {
        return $this->db
            ->query(
                '
            SELECT
                id,
                codigo,
                nombre,
                intervalo_horas

            FROM frecuencias_administracion

            WHERE activo = 1

            ORDER BY
                intervalo_horas IS NULL,
                intervalo_horas,
                nombre
            '
            )
            ->fetchAll();
    }


    public function measurementUnits(): array
    {
        return $this->db
            ->query(
                '
            SELECT
                id,
                codigo,
                nombre,
                simbolo,
                categoria

            FROM unidades_medida

            WHERE activo = 1

            ORDER BY
                categoria,
                nombre
            '
            )
            ->fetchAll();
    }


    public function timeUnits(): array
    {
        return $this->db
            ->query(
                '
            SELECT
                id,
                codigo,
                nombre

            FROM unidades_tiempo

            WHERE activo = 1

            ORDER BY id
            '
            )
            ->fetchAll();
    }


    public function treatmentTypes(): array
    {
        return $this->db
            ->query(
                '
            SELECT
                id,
                codigo,
                nombre

            FROM tipos_tratamiento

            ORDER BY id
            '
            )
            ->fetchAll();
    }

    public function vaccines(): array
    {
        return $this->db
            ->query(
                '
            SELECT
                v.id,
                v.nombre,
                v.descripcion,

                lf.nombre AS laboratorio

            FROM vacunas v

            LEFT JOIN laboratorios_farmaceuticos lf
                ON lf.id = v.laboratorio_id

            WHERE v.activo = 1

            ORDER BY v.nombre
            '
            )
            ->fetchAll();
    }


    public function pharmaceuticalLaboratories(): array
    {
        return $this->db
            ->query(
                '
            SELECT
                id,
                nombre,
                pais

            FROM laboratorios_farmaceuticos

            WHERE activo = 1

            ORDER BY nombre
            '
            )
            ->fetchAll();
    }
}
