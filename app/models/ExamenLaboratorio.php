<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

final class ExamenLaboratorio extends Model
{
    public function animalesByEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, nombre FROM animales WHERE empresa_id = :empresa_id ORDER BY nombre ASC');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function consultasByEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT c.id, c.fecha_consulta, a.nombre AS animal FROM consultas c INNER JOIN animales a ON a.id = c.animal_id WHERE c.empresa_id = :empresa_id ORDER BY c.fecha_consulta DESC LIMIT 200');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listByEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT e.id, e.tipo_examen, e.observaciones, e.resultado, e.archivo_pdf, e.created_at, a.nombre AS animal, CONCAT(u.nombres, " ", COALESCE(u.apellidos, "")) AS usuario FROM examenes_laboratorio e INNER JOIN animales a ON a.id = e.animal_id LEFT JOIN usuarios u ON u.id = e.usuario_id WHERE e.empresa_id = :empresa_id ORDER BY e.id DESC LIMIT 200');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $payload): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO examenes_laboratorio (empresa_id, animal_id, consulta_id, tipo_examen, observaciones, resultado, archivo_pdf, usuario_id) VALUES (:empresa_id, :animal_id, :consulta_id, :tipo_examen, :observaciones, :resultado, :archivo_pdf, :usuario_id)');
        $stmt->execute($payload);

        return (int) $this->pdo->lastInsertId();
    }
}
