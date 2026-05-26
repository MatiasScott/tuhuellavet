<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

final class Consulta extends Model
{
    public function byEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT c.id, c.fecha_consulta, c.motivo_consulta, c.estado, a.nombre AS animal, CONCAT(u.nombres, " ", COALESCE(u.apellidos, "")) AS veterinario FROM consultas c INNER JOIN animales a ON a.id = c.animal_id INNER JOIN usuarios u ON u.id = c.veterinario_id WHERE c.empresa_id = :empresa_id ORDER BY c.fecha_consulta DESC');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createWithExam(array $consulta, array $examen): int
    {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare('INSERT INTO consultas (empresa_id, animal_id, veterinario_id, fecha_consulta, motivo_consulta, anamnesis, antecedentes, diagnostico, tratamiento, recomendaciones, tratamiento_clinico, tratamiento_casa, observaciones, peso, temperatura, frecuencia_cardiaca, frecuencia_respiratoria, estado) VALUES (:empresa_id, :animal_id, :veterinario_id, :fecha_consulta, :motivo_consulta, :anamnesis, :antecedentes, :diagnostico, :tratamiento, :recomendaciones, :tratamiento_clinico, :tratamiento_casa, :observaciones, :peso, :temperatura, :frecuencia_cardiaca, :frecuencia_respiratoria, :estado)');

            $stmt->execute($consulta);
            $consultaId = (int) $this->pdo->lastInsertId();

            $stmtExam = $this->pdo->prepare('INSERT INTO consulta_examen_general (consulta_id, alimentacion, historial_reproductivo, frecuencia_cardiaca, frecuencia_respiratoria, temperatura, tiempo_llenado_capilar, ganglios_linfaticos, condicion_corporal, vomitos, diarrea, tos) VALUES (:consulta_id, :alimentacion, :historial_reproductivo, :frecuencia_cardiaca, :frecuencia_respiratoria, :temperatura, :tiempo_llenado_capilar, :ganglios_linfaticos, :condicion_corporal, :vomitos, :diarrea, :tos)');
            $examen['consulta_id'] = $consultaId;
            $stmtExam->execute($examen);

            $this->pdo->commit();

            return $consultaId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
