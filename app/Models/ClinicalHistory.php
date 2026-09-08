<?php
namespace App\Models;
use App\Core\Model;
class ClinicalHistory extends Model
{
    public function timeline(int $patientId,int $environmentId): array
    {
        $stmt=$this->db->prepare('
            SELECT ec.id,ec.fecha_evento,ec.titulo,ec.observaciones,
                   tec.codigo AS tipo_codigo,tec.nombre AS tipo_nombre,
                   CONCAT(u.nombres," ",u.apellidos) AS responsable_nombre,
                   ce.motivo_consulta,ce.anamnesis,ce.antecedentes,ce.recomendaciones,
                   ecg.temperatura_c,ecg.frecuencia_cardiaca,ecg.frecuencia_respiratoria,
                   vac.id AS vacunacion_id,vac.dosis AS vacuna_dosis,vac.lote AS vacuna_lote,vac.fecha_revacunacion,
                   v.nombre AS vacuna_nombre,uv.simbolo AS vacuna_unidad,
                   desp.id AS desparasitacion_id,desp.dosis AS desparasitacion_dosis,desp.proxima_desparasitacion,
                   f.nombre AS desparasitacion_farmaco,ud.simbolo AS desparasitacion_unidad,
                   h.fecha_ingreso AS hospitalizacion_fecha_ingreso,h.fecha_salida AS hospitalizacion_fecha_salida,
                   h.motivo_ingreso AS hospitalizacion_motivo,h.impresion_clinica_ingreso AS hospitalizacion_impresion,
                   eh.codigo AS hospitalizacion_estado_codigo,eh.nombre AS hospitalizacion_estado_nombre,
                   el.id AS laboratorio_id,tx.nombre AS laboratorio_tipo,el.resultado_resumen AS laboratorio_resultado,
                   c.procedimiento_quirurgico_id,pq.nombre AS cirugia_procedimiento,c.fecha_inicio AS cirugia_fecha_inicio,
                   c.fecha_fin AS cirugia_fecha_fin
            FROM eventos_clinicos ec
            JOIN animales a ON a.id=ec.animal_id
            JOIN tipos_evento_clinico tec ON tec.id=ec.tipo_evento_id
            JOIN usuarios u ON u.id=ec.responsable_id
            LEFT JOIN consultas_externas ce ON ce.evento_clinico_id=ec.id
            LEFT JOIN examenes_clinicos_generales ecg ON ecg.evento_clinico_id=ec.id
            LEFT JOIN vacunaciones vac ON vac.evento_clinico_id=ec.id
            LEFT JOIN vacunas v ON v.id=vac.vacuna_id
            LEFT JOIN unidades_medida uv ON uv.id=vac.unidad_dosis_id
            LEFT JOIN desparasitaciones desp ON desp.evento_clinico_id=ec.id
            LEFT JOIN farmacos f ON f.id=desp.farmaco_id
            LEFT JOIN unidades_medida ud ON ud.id=desp.unidad_dosis_id
            LEFT JOIN hospitalizaciones h ON h.evento_clinico_id=ec.id
            LEFT JOIN estados_hospitalizacion eh ON eh.id=h.estado_hospitalizacion_id
            LEFT JOIN examenes_laboratorio el ON el.evento_clinico_id=ec.id
            LEFT JOIN tipos_examen_laboratorio tx ON tx.id=el.tipo_examen_id
            LEFT JOIN cirugias c ON c.evento_clinico_id=ec.id
            LEFT JOIN procedimientos_quirurgicos pq ON pq.id=c.procedimiento_quirurgico_id
            WHERE ec.animal_id=:animal AND a.entorno_id=:entorno
            ORDER BY ec.fecha_evento DESC,ec.id DESC');
        $stmt->execute(['animal'=>$patientId,'entorno'=>$environmentId]);return $stmt->fetchAll();
    }
    public function diagnoses(int $eventId): array
    {
        $s=$this->db->prepare('SELECT dc.id,dc.descripcion,td.codigo,td.nombre AS tipo,CONCAT(u.nombres," ",u.apellidos) AS ingresado_por_nombre,dc.created_at FROM diagnosticos_clinicos dc JOIN tipos_diagnostico td ON td.id=dc.tipo_diagnostico_id JOIN usuarios u ON u.id=dc.ingresado_por WHERE dc.evento_clinico_id=:evento ORDER BY td.id,dc.created_at');$s->execute(['evento'=>$eventId]);return$s->fetchAll();
    }
}
