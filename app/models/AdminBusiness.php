<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;

final class AdminBusiness extends Model
{
    public function empresas(): array
    {
        $stmt = $this->pdo->query('SELECT id, nombre, tipo, direccion, telefono, email, logo, estado FROM empresas ORDER BY nombre ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findEmpresaById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, nombre, tipo, direccion, telefono, email, logo, estado FROM empresas WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function createEmpresa(array $payload): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO empresas (nombre, tipo, direccion, telefono, email, logo, estado) VALUES (:nombre, :tipo, :direccion, :telefono, :email, :logo, :estado)');
        $stmt->execute($payload);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateEmpresa(int $id, array $payload): bool
    {
        $stmt = $this->pdo->prepare('UPDATE empresas SET nombre = :nombre, tipo = :tipo, direccion = :direccion, telefono = :telefono, email = :email, logo = :logo, estado = :estado, updated_at = NOW() WHERE id = :id');

        return $stmt->execute([
            ':id' => $id,
            ':nombre' => $payload['nombre'],
            ':tipo' => $payload['tipo'],
            ':direccion' => $payload['direccion'],
            ':telefono' => $payload['telefono'],
            ':email' => $payload['email'],
            ':logo' => $payload['logo'],
            ':estado' => $payload['estado'],
        ]);
    }

    public function medicamentosByEmpresa(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, nombre, descripcion, foto, concentracion, unidad, stock_actual, estado FROM medicamentos WHERE empresa_id = :empresa_id ORDER BY id DESC');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findMedicamentoById(int $id, int $empresaId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, empresa_id, nombre, descripcion, foto, concentracion, unidad, stock_actual, estado FROM medicamentos WHERE id = :id AND empresa_id = :empresa_id LIMIT 1');
        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function createMedicamento(array $payload): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO medicamentos (empresa_id, nombre, descripcion, foto, concentracion, unidad, stock_actual, estado) VALUES (:empresa_id, :nombre, :descripcion, :foto, :concentracion, :unidad, :stock_actual, :estado)');
        $stmt->execute($payload);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateMedicamento(int $id, int $empresaId, array $payload): bool
    {
        $stmt = $this->pdo->prepare('UPDATE medicamentos SET nombre = :nombre, descripcion = :descripcion, foto = :foto, concentracion = :concentracion, unidad = :unidad, stock_actual = :stock_actual, estado = :estado, updated_at = NOW() WHERE id = :id AND empresa_id = :empresa_id');

        return $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
            ':nombre' => $payload['nombre'],
            ':descripcion' => $payload['descripcion'],
            ':foto' => $payload['foto'],
            ':concentracion' => $payload['concentracion'],
            ':unidad' => $payload['unidad'],
            ':stock_actual' => $payload['stock_actual'],
            ':estado' => $payload['estado'],
        ]);
    }

    public function laboratoriosResumen(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT laboratorio, COUNT(*) AS total_usos FROM animal_vacunas av INNER JOIN animales a ON a.id = av.animal_id WHERE a.empresa_id = :empresa_id AND laboratorio IS NOT NULL AND TRIM(laboratorio) <> "" GROUP BY laboratorio ORDER BY laboratorio ASC');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function renameLaboratorio(int $empresaId, string $oldName, string $newName): int
    {
        $stmt = $this->pdo->prepare('UPDATE animal_vacunas av INNER JOIN animales a ON a.id = av.animal_id SET av.laboratorio = :new_name WHERE a.empresa_id = :empresa_id AND av.laboratorio = :old_name');
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':old_name' => $oldName,
            ':new_name' => $newName,
        ]);

        return (int) $stmt->rowCount();
    }

    public function tiposExamenResumen(int $empresaId): array
    {
        $stmt = $this->pdo->prepare('SELECT tipo_examen, COUNT(*) AS total_usos FROM examenes_laboratorio WHERE empresa_id = :empresa_id AND tipo_examen IS NOT NULL AND TRIM(tipo_examen) <> "" GROUP BY tipo_examen ORDER BY tipo_examen ASC');
        $stmt->execute([':empresa_id' => $empresaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function renameTipoExamen(int $empresaId, string $oldName, string $newName): int
    {
        $stmt = $this->pdo->prepare('UPDATE examenes_laboratorio SET tipo_examen = :new_name WHERE empresa_id = :empresa_id AND tipo_examen = :old_name');
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':old_name' => $oldName,
            ':new_name' => $newName,
        ]);

        return (int) $stmt->rowCount();
    }

    public function hasColorColumns(): bool
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = "empresas" AND column_name IN ("color_primario", "color_secundario")');
        return ((int) $stmt->fetchColumn()) >= 2;
    }
}
