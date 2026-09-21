<?php

namespace App\Models;

use App\Core\Model;

class FormulaAdmin extends Model
{
    public function all(int $env): array
    {
        $sql = '
        SELECT
            f.*,
            cf.nombre AS categoria,
            um.simbolo AS unidad,

            (
                SELECT fv.id
                FROM formula_versiones fv
                WHERE fv.formula_id = f.id
                ORDER BY fv.numero_version DESC
                LIMIT 1
            ) AS ultima_version_id,

            (
                SELECT MAX(fv.numero_version)
                FROM formula_versiones fv
                WHERE fv.formula_id = f.id
            ) AS ultima_version,

            (
                SELECT efv.codigo
                FROM formula_versiones fv
                INNER JOIN estados_formula_version efv
                    ON efv.id = fv.estado_id
                WHERE fv.formula_id = f.id
                ORDER BY fv.numero_version DESC
                LIMIT 1
            ) AS ultimo_estado

        FROM formulas f

        INNER JOIN formula_entornos fe
            ON fe.formula_id = f.id

        INNER JOIN categorias_formula cf
            ON cf.id = f.categoria_formula_id

        LEFT JOIN unidades_medida um
            ON um.id = f.unidad_resultado_id

        WHERE fe.entorno_id = :e
          AND f.deleted_at IS NULL

        ORDER BY f.nombre
    ';

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            'e' => $env
        ]);

        return $stmt->fetchAll();
    }
    public function categories(): array
    {
        return $this->db->query('SELECT id,codigo,nombre FROM categorias_formula WHERE activo=1 ORDER BY nombre')->fetchAll();
    }
    public function variableTypes(): array
    {
        return $this->db->query('SELECT id,codigo,nombre FROM tipos_variable_formula ORDER BY id')->fetchAll();
    }
    public function variableOrigins(): array
    {
        return $this->db->query('SELECT id,codigo,nombre FROM origenes_variable_formula ORDER BY id')->fetchAll();
    }
    public function versions(int $formula): array
    {
        $s = $this->db->prepare('SELECT fv.*,efv.codigo AS estado_codigo,efv.nombre AS estado_nombre FROM formula_versiones fv JOIN estados_formula_version efv ON efv.id=fv.estado_id WHERE fv.formula_id=:f ORDER BY fv.numero_version DESC');
        $s->execute(['f' => $formula]);
        return $s->fetchAll();
    }
    public function versionsForEnvironment(
        int $formulaId,
        int $environmentId
    ): array {

        $stmt = $this->db->prepare(
            '
        SELECT
            fv.id,
            fv.formula_id,
            fv.numero_version,
            fv.expresion,
            fv.notas_version,
            fv.created_at,
            fv.publicada_at,

            efv.codigo AS estado_codigo,
            efv.nombre AS estado_nombre

        FROM formula_versiones fv

        INNER JOIN formulas f
            ON f.id = fv.formula_id

        INNER JOIN formula_entornos fe
            ON fe.formula_id = f.id

        INNER JOIN estados_formula_version efv
            ON efv.id = fv.estado_id

        WHERE fv.formula_id = :formula

          AND fe.entorno_id = :entorno

          AND f.activo = 1

          AND f.deleted_at IS NULL

        ORDER BY fv.numero_version DESC
        '
        );

        $stmt->execute([
            'formula' => $formulaId,
            'entorno' => $environmentId,
        ]);

        return $stmt->fetchAll();
    }

    public function versionForEnvironment(
        int $versionId,
        int $environmentId
    ): ?array {

        $stmt = $this->db->prepare(
            '
        SELECT
            fv.id,
            fv.formula_id,
            fv.numero_version,
            fv.expresion,
            fv.notas_version,
            fv.created_at,

            f.codigo AS formula_codigo,
            f.nombre AS formula_nombre,
            f.descripcion AS formula_descripcion,

            efv.codigo AS estado_codigo,
            efv.nombre AS estado_nombre,

            um.simbolo AS unidad_resultado

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

        LIMIT 1
        '
        );

        $stmt->execute([
            'version' => $versionId,
            'entorno' => $environmentId,
        ]);

        return $stmt->fetch() ?: null;
    }


    public function versionVariables(
        int $versionId,
        int $environmentId
    ): array {

        /*
     * Validar primero el acceso a la versión.
     */

        $version = $this->versionForEnvironment(
            $versionId,
            $environmentId
        );

        if (!$version) {
            throw new \RuntimeException(
                'Versión no encontrada en el entorno actual.'
            );
        }

        $stmt = $this->db->prepare(
            '
        SELECT
            fv.id,
            fv.formula_version_id,

            fv.codigo,
            fv.etiqueta,
            fv.descripcion,

            fv.tipo_variable_id,
            fv.origen_variable_id,
            fv.unidad_medida_id,

            fv.obligatorio,

            fv.valor_minimo,
            fv.valor_maximo,
            fv.valor_default,

            fv.orden,

            tv.codigo AS tipo_codigo,
            tv.nombre AS tipo_nombre,

            ov.codigo AS origen_codigo,
            ov.nombre AS origen_nombre,

            um.simbolo AS unidad_simbolo,
            um.nombre AS unidad_nombre

        FROM formula_variables fv

        INNER JOIN tipos_variable_formula tv
            ON tv.id = fv.tipo_variable_id

        INNER JOIN origenes_variable_formula ov
            ON ov.id = fv.origen_variable_id

        LEFT JOIN unidades_medida um
            ON um.id = fv.unidad_medida_id

        WHERE fv.formula_version_id = :version

        ORDER BY
            fv.orden,
            fv.id
        '
        );

        $stmt->execute([
            'version' => $versionId,
        ]);

        return $stmt->fetchAll();
    }
}
