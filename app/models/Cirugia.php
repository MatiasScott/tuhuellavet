<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

final class Cirugia extends Model
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

    public function formulasByEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, nombre, formula, unidad_resultado FROM formulas WHERE empresa_id = :empresa_id AND estado = 1 ORDER BY nombre ASC');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listByEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT c.id, c.procedimiento_quirurgico, c.medico_responsable, c.anestesia, c.formula_medica, c.archivo_pdf, c.fecha, a.nombre AS animal, f.nombre AS formula_nombre, CONCAT(u.nombres, " ", COALESCE(u.apellidos, "")) AS usuario FROM cirugias c INNER JOIN animales a ON a.id = c.animal_id LEFT JOIN formulas f ON f.id = c.formula_id LEFT JOIN usuarios u ON u.id = c.usuario_id WHERE c.empresa_id = :empresa_id ORDER BY c.id DESC LIMIT 200');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create(array $payload): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO cirugias (empresa_id, animal_id, consulta_id, procedimiento_quirurgico, medico_responsable, anestesia, formula_medica, formula_id, archivo_pdf, observaciones, fecha, usuario_id) VALUES (:empresa_id, :animal_id, :consulta_id, :procedimiento_quirurgico, :medico_responsable, :anestesia, :formula_medica, :formula_id, :archivo_pdf, :observaciones, :fecha, :usuario_id)');
        $stmt->execute($payload);

        return (int) $this->pdo->lastInsertId();
    }
}
