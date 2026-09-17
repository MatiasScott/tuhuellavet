<?php

namespace App\Models;

use App\Core\Model;

class Formula extends Model
{
    public function publishedForEnvironment(
        int $environmentId,
        ?int $speciesId = null,
        ?int $drugId = null
    ): array {
        $sql = '
            SELECT
                f.id AS formula_id,
                f.codigo,
                f.nombre,
                f.descripcion,

                fv.id AS formula_version_id,
                fv.numero_version,
                fv.expresion,

                um.nombre AS unidad_resultado,
                um.simbolo AS unidad_resultado_simbolo,

                cf.nombre AS categoria

            FROM formulas f

            INNER JOIN formula_entornos fe
                ON fe.formula_id = f.id

            INNER JOIN formula_versiones fv
                ON fv.formula_id = f.id

            INNER JOIN estados_formula_version efv
                ON efv.id = fv.estado_id

            INNER JOIN categorias_formula cf
                ON cf.id = f.categoria_formula_id

            LEFT JOIN unidades_medida um
                ON um.id = f.unidad_resultado_id

            WHERE fe.entorno_id = :entorno

              AND f.activo = 1

              AND f.deleted_at IS NULL

              AND efv.codigo = "PUBLICADA"
        ';

        $params = [
            'entorno' => $environmentId,
        ];

        if ($speciesId) {
            $sql .= '
                AND (
                    NOT EXISTS (
                        SELECT 1
                        FROM formula_especies fesp
                        WHERE fesp.formula_id = f.id
                    )

                    OR EXISTS (
                        SELECT 1
                        FROM formula_especies fesp
                        WHERE fesp.formula_id = f.id
                          AND fesp.especie_id = :especie
                    )
                )
            ';

            $params['especie']
                = $speciesId;
        }

        if ($drugId) {
            $sql .= '
                AND EXISTS (
                    SELECT 1

                    FROM farmaco_formulas ff

                    WHERE ff.formula_id = f.id
                      AND ff.farmaco_id = :farmaco
                )
            ';

            $params['farmaco']
                = $drugId;
        }

        $sql .= '
            ORDER BY
                f.nombre,
                fv.numero_version DESC
        ';

        $stmt = $this->db->prepare(
            $sql
        );

        $stmt->execute(
            $params
        );

        $rows = $stmt->fetchAll();

        /*
         * Solo la versión publicada
         * más alta por Fórmula.
         */
        $result = [];

        foreach ($rows as $row) {
            $formulaId
                = (int)
                    $row['formula_id'];

            if (
                isset(
                    $result[$formulaId]
                )
            ) {
                continue;
            }

            $result[$formulaId]
                = $row;
        }

        return array_values(
            $result
        );
    }


    public function version(
        int $versionId,
        int $environmentId
    ): ?array {
        $stmt = $this->db->prepare(
            '
            SELECT
                fv.*,

                f.id AS formula_id,
                f.codigo AS formula_codigo,
                f.nombre AS formula_nombre,
                f.unidad_resultado_id,

                um.nombre
                    AS unidad_resultado,

                um.simbolo
                    AS unidad_resultado_simbolo

            FROM formula_versiones fv

            INNER JOIN formulas f
                ON f.id = fv.formula_id

            INNER JOIN formula_entornos fe
                ON fe.formula_id = f.id

            INNER JOIN estados_formula_version efv
                ON efv.id = fv.estado_id

            LEFT JOIN unidades_medida um
                ON um.id = f.unidad_resultado_id

            WHERE fv.id = :version

              AND fe.entorno_id = :entorno

              AND f.activo = 1

              AND f.deleted_at IS NULL

              AND efv.codigo = "PUBLICADA"

            LIMIT 1
            '
        );

        $stmt->execute([
            'version'
                => $versionId,

            'entorno'
                => $environmentId,
        ]);

        return $stmt->fetch()
            ?: null;
    }


    public function variables(
        int $versionId
    ): array {
        $stmt = $this->db->prepare(
            '
            SELECT
                fv.id,
                fv.codigo,
                fv.etiqueta,
                fv.descripcion,
                fv.obligatorio,
                fv.valor_minimo,
                fv.valor_maximo,
                fv.valor_default,
                fv.orden,

                tvf.codigo
                    AS tipo_codigo,

                ovf.codigo
                    AS origen_codigo,

                ovf.nombre
                    AS origen_nombre,

                um.nombre
                    AS unidad_nombre,

                um.simbolo
                    AS unidad_simbolo

            FROM formula_variables fv

            INNER JOIN tipos_variable_formula tvf
                ON tvf.id =
                   fv.tipo_variable_id

            INNER JOIN origenes_variable_formula ovf
                ON ovf.id =
                   fv.origen_variable_id

            LEFT JOIN unidades_medida um
                ON um.id =
                   fv.unidad_medida_id

            WHERE fv.formula_version_id
                = :version

            ORDER BY
                fv.orden,
                fv.id
            '
        );

        $stmt->execute([
            'version'
                => $versionId,
        ]);

        return $stmt->fetchAll();
    }
}
