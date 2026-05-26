<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

final class Animal extends Model
{
    public function byEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT a.id, a.nombre, e.nombre AS especie, r.nombre AS raza FROM animales a INNER JOIN especies e ON e.id = a.especie_id LEFT JOIN razas r ON r.id = a.raza_id WHERE a.empresa_id = :empresa_id ORDER BY a.nombre ASC');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
