<?php

namespace App\Models;

use App\Core\Model;

class Laboratory extends Model
{
    public function list(int $e, string $q = ''): array
    {
        $sql = '
            SELECT el.*,tec.codigo AS evento_tipo,a.id AS animal_id,a.nombre AS paciente,tx.nombre AS tipo,CONCAT(u.nombres," ",u.apellidos) AS solicitado_por_nombre 
            FROM examenes_laboratorio el JOIN eventos_clinicos ec ON ec.id=el.evento_clinico_id 
            JOIN tipos_evento_clinico tec ON tec.id=ec.tipo_evento_id 
            JOIN animales a ON a.id=ec.animal_id 
            JOIN tipos_examen_laboratorio tx ON tx.id=el.tipo_examen_id 
            JOIN usuarios u ON u.id=el.solicitado_por 
            WHERE a.entorno_id=:e
            AND a.deleted_at IS NULL
            AND ec.anulado_at IS NULL
        ';
        $p = ['e' => $e];
        if ($q !== '') {
            $sql .= ' AND CONCAT_WS(" ",a.nombre,tx.nombre,el.resultado_resumen) LIKE :q';
            $p['q'] = '%' . $q . '%';
        }
        $s = $this->db->prepare(
            $sql . ' ORDER BY el.fecha_solicitud DESC'
        );

        $s->execute($p);

        $exams = $s->fetchAll();

        foreach ($exams as &$exam) {

            $exam['archivos'] = $this->files(
                (int) $exam['id']
            );
        }

        unset($exam);

        return $exams;
    }
    public function files(int $id): array
    {
        $s = $this->db->prepare('SELECT a.*,ela.descripcion FROM examen_laboratorio_archivos ela JOIN archivos a ON a.id=ela.archivo_id WHERE ela.examen_laboratorio_id=:id AND a.deleted_at IS NULL');
        $s->execute(['id' => $id]);
        return $s->fetchAll();
    }

    public function findFile(
        int $fileId,
        int $environmentId
    ): ?array {

        $stmt = $this->db->prepare(
            '
        SELECT
            ar.id,
            ar.nombre_original,
            ar.ruta_storage,
            ar.mime_type,
            ar.tamano_bytes

        FROM archivos ar

        INNER JOIN examen_laboratorio_archivos ela
            ON ela.archivo_id = ar.id

        INNER JOIN examenes_laboratorio el
            ON el.id = ela.examen_laboratorio_id

        INNER JOIN eventos_clinicos ec
            ON ec.id = el.evento_clinico_id

        INNER JOIN animales a
            ON a.id = ec.animal_id

        WHERE ar.id = :archivo
          AND a.entorno_id = :entorno
          AND a.deleted_at IS NULL
          AND ar.deleted_at IS NULL
          AND ec.anulado_at IS NULL

        LIMIT 1
        '
        );

        $stmt->execute([
            'archivo' => $fileId,
            'entorno' => $environmentId,
        ]);

        $file = $stmt->fetch();

        return $file ?: null;
    }
}
