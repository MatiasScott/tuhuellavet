<?php

namespace App\Models;

use App\Core\Model;

class Treatment extends Model
{
    public function byEvent(
        int $eventId,
        int $environmentId
    ): array {
        $stmt = $this->db->prepare(
            '
            SELECT
                t.id,
                t.evento_clinico_id,
                t.fecha_inicio,
                t.fecha_fin,
                t.instrucciones_generales,
                t.observaciones,

                tt.codigo
                    AS tipo_codigo,

                tt.nombre
                    AS tipo_nombre,

                CONCAT(
                    u.nombres,
                    " ",
                    u.apellidos
                ) AS indicado_por_nombre

            FROM tratamientos t

            INNER JOIN tipos_tratamiento tt
                ON tt.id =
                   t.tipo_tratamiento_id

            INNER JOIN usuarios u
                ON u.id =
                   t.indicado_por

            INNER JOIN eventos_clinicos ec
                ON ec.id =
                   t.evento_clinico_id

            INNER JOIN animales a
                ON a.id =
                   ec.animal_id

            WHERE t.evento_clinico_id
                = :evento

              AND a.entorno_id
                = :entorno

            ORDER BY
                t.fecha_inicio,
                t.id
            '
        );

        $stmt->execute([
            'evento'
            => $eventId,

            'entorno'
            => $environmentId,
        ]);

        $treatments
            = $stmt->fetchAll();

        foreach (
            $treatments
            as &$treatment
        ) {
            $treatment['medications'] = $this
                ->medications(
                    (int)
                    $treatment['id']
                );
        }

        return $treatments;
    }


    public function medications(
        int $treatmentId
    ): array {
        $stmt = $this->db->prepare(
            '
            SELECT
                tm.*,

                f.nombre
                    AS farmaco_nombre,

                fp.nombre_comercial,

                ff.nombre
                    AS forma_farmaceutica,

                va.nombre
                    AS via_nombre,

                um.simbolo
                    AS dosis_unidad,

                fa.nombre
                    AS frecuencia_nombre,

                ut.nombre
                    AS duracion_unidad,

                fe.resultado
                    AS formula_resultado,

                fur.simbolo
                    AS formula_unidad

            FROM tratamiento_medicamentos tm

            INNER JOIN farmacos f
                ON f.id =
                   tm.farmaco_id

            LEFT JOIN farmaco_presentaciones fp
                ON fp.id =
                   tm.presentacion_id

            LEFT JOIN formas_farmaceuticas ff
                ON ff.id =
                   fp.forma_farmaceutica_id

            LEFT JOIN vias_administracion va
                ON va.id =
                   tm.via_administracion_id

            LEFT JOIN unidades_medida um
                ON um.id =
                   tm.dosis_unidad_id

            LEFT JOIN frecuencias_administracion fa
                ON fa.id =
                   tm.frecuencia_id

            LEFT JOIN unidades_tiempo ut
                ON ut.id =
                   tm.duracion_unidad_id

            LEFT JOIN formula_ejecuciones fe
                ON fe.id =
                   tm.formula_ejecucion_id

            LEFT JOIN unidades_medida fur
                ON fur.id =
                   fe.unidad_resultado_id

            WHERE tm.tratamiento_id
                = :tratamiento

            ORDER BY
                tm.orden,
                tm.id
            '
        );

        $stmt->execute([
            'tratamiento'
            => $treatmentId,
        ]);

        return $stmt->fetchAll();
    }


    public function applications(
        int $treatmentMedicationId
    ): array {
        $stmt = $this->db->prepare(
            '
        SELECT
            ma.*,

            CASE
                WHEN ma.anulado_at IS NULL
                THEN 0
                ELSE 1
            END AS esta_anulado,

            um.simbolo
                AS unidad,

            CONCAT(
                u.nombres,
                " ",
                u.apellidos
            ) AS aplicado_por_nombre,

            CONCAT(
                ua.nombres,
                " ",
                ua.apellidos
            ) AS anulado_por_nombre

        FROM medicamento_aplicaciones ma

        INNER JOIN usuarios u
            ON u.id =
               ma.aplicado_por

        LEFT JOIN usuarios ua
            ON ua.id =
               ma.anulado_por

        LEFT JOIN unidades_medida um
            ON um.id =
               ma.unidad_id

        WHERE ma.tratamiento_medicamento_id
            = :medicamento

        ORDER BY
            ma.fecha_hora DESC,
            ma.id DESC
        '
        );

        $stmt->execute([
            'medicamento'
            => $treatmentMedicationId,
        ]);

        return $stmt->fetchAll();
    }
}
