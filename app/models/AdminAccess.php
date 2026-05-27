<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use PDO;
use RuntimeException;

final class AdminAccess extends Model
{
    public function usuarios(): array
    {
        $stmt = $this->pdo->query('SELECT u.id, u.nombres, u.apellidos, u.email, u.telefono, u.estado, u.created_at, COUNT(ue.id) AS total_empresas FROM usuarios u LEFT JOIN usuarios_empresas ue ON ue.usuario_id = u.id GROUP BY u.id ORDER BY u.id DESC');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findUsuarioById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, nombres, apellidos, email, telefono, estado FROM usuarios WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function createUsuario(array $payload): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO usuarios (nombres, apellidos, email, password, telefono, estado) VALUES (:nombres, :apellidos, :email, :password, :telefono, :estado)');
        $stmt->execute($payload);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateUsuario(int $id, array $payload): bool
    {
        $stmt = $this->pdo->prepare('UPDATE usuarios SET nombres = :nombres, apellidos = :apellidos, email = :email, telefono = :telefono, estado = :estado, updated_at = NOW() WHERE id = :id');

        return $stmt->execute([
            ':id' => $id,
            ':nombres' => $payload['nombres'],
            ':apellidos' => $payload['apellidos'],
            ':email' => $payload['email'],
            ':telefono' => $payload['telefono'],
            ':estado' => $payload['estado'],
        ]);
    }

    public function resetPassword(int $id, string $passwordHash): bool
    {
        $stmt = $this->pdo->prepare('UPDATE usuarios SET password = :password, updated_at = NOW() WHERE id = :id');

        return $stmt->execute([
            ':id' => $id,
            ':password' => $passwordHash,
        ]);
    }

    public function empresas(): array
    {
        $stmt = $this->pdo->query('SELECT id, nombre, tipo, estado FROM empresas ORDER BY nombre ASC');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function roles(): array
    {
        $stmt = $this->pdo->query('SELECT r.id, r.nombre, r.descripcion, r.created_at, COUNT(rp.id) AS total_permisos FROM roles r LEFT JOIN rol_permisos rp ON rp.rol_id = r.id GROUP BY r.id ORDER BY r.nombre ASC');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findRolById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, nombre, descripcion FROM roles WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function createRol(array $payload): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO roles (nombre, descripcion) VALUES (:nombre, :descripcion)');
        $stmt->execute($payload);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateRol(int $id, array $payload): bool
    {
        $stmt = $this->pdo->prepare('UPDATE roles SET nombre = :nombre, descripcion = :descripcion WHERE id = :id');

        return $stmt->execute([
            ':id' => $id,
            ':nombre' => $payload['nombre'],
            ':descripcion' => $payload['descripcion'],
        ]);
    }

    public function duplicateRol(int $id, string $newName): int
    {
        $base = $this->findRolById($id);
        if (!is_array($base)) {
            throw new RuntimeException('Rol no encontrado.');
        }

        $this->pdo->beginTransaction();

        try {
            $newRoleId = $this->createRol([
                'nombre' => $newName,
                'descripcion' => (string) ($base['descripcion'] ?? ''),
            ]);

            $stmtPermisos = $this->pdo->prepare('SELECT permiso_id FROM rol_permisos WHERE rol_id = :rol_id');
            $stmtPermisos->execute([':rol_id' => $id]);
            $permisos = $stmtPermisos->fetchAll(PDO::FETCH_COLUMN);

            if (is_array($permisos)) {
                $insert = $this->pdo->prepare('INSERT INTO rol_permisos (rol_id, permiso_id) VALUES (:rol_id, :permiso_id)');
                foreach ($permisos as $permisoId) {
                    $insert->execute([
                        ':rol_id' => $newRoleId,
                        ':permiso_id' => (int) $permisoId,
                    ]);
                }
            }

            $this->pdo->commit();

            return $newRoleId;
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function permisos(): array
    {
        $stmt = $this->pdo->query('SELECT p.id, p.modulo_id, p.nombre, p.slug, m.nombre AS modulo_nombre FROM permisos p LEFT JOIN modulos m ON m.id = p.modulo_id ORDER BY m.nombre ASC, p.nombre ASC');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findPermisoById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, modulo_id, nombre, slug FROM permisos WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function createPermiso(array $payload): int
    {
        $stmt = $this->pdo->prepare('INSERT INTO permisos (modulo_id, nombre, slug) VALUES (:modulo_id, :nombre, :slug)');
        $stmt->execute($payload);

        return (int) $this->pdo->lastInsertId();
    }

    public function updatePermiso(int $id, array $payload): bool
    {
        $stmt = $this->pdo->prepare('UPDATE permisos SET modulo_id = :modulo_id, nombre = :nombre, slug = :slug WHERE id = :id');

        return $stmt->execute([
            ':id' => $id,
            ':modulo_id' => $payload['modulo_id'],
            ':nombre' => $payload['nombre'],
            ':slug' => $payload['slug'],
        ]);
    }

    public function modulos(): array
    {
        $stmt = $this->pdo->query('SELECT id, nombre, slug FROM modulos ORDER BY nombre ASC');

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function rolePermissionIds(int $rolId): array
    {
        $stmt = $this->pdo->prepare('SELECT permiso_id FROM rol_permisos WHERE rol_id = :rol_id');
        $stmt->execute([':rol_id' => $rolId]);

        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return is_array($rows) ? array_map(static fn ($id): int => (int) $id, $rows) : [];
    }

    public function syncRolePermisos(int $rolId, array $permisoIds): void
    {
        $this->pdo->beginTransaction();

        try {
            $delete = $this->pdo->prepare('DELETE FROM rol_permisos WHERE rol_id = :rol_id');
            $delete->execute([':rol_id' => $rolId]);

            if ($permisoIds !== []) {
                $insert = $this->pdo->prepare('INSERT INTO rol_permisos (rol_id, permiso_id) VALUES (:rol_id, :permiso_id)');
                foreach ($permisoIds as $permisoId) {
                    $insert->execute([
                        ':rol_id' => $rolId,
                        ':permiso_id' => (int) $permisoId,
                    ]);
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function userAsignaciones(int $usuarioId): array
    {
        $stmt = $this->pdo->prepare('SELECT ue.empresa_id, ue.rol_id, e.nombre AS empresa_nombre, r.nombre AS rol_nombre FROM usuarios_empresas ue INNER JOIN empresas e ON e.id = ue.empresa_id INNER JOIN roles r ON r.id = ue.rol_id WHERE ue.usuario_id = :usuario_id ORDER BY e.nombre ASC');
        $stmt->execute([':usuario_id' => $usuarioId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function syncUsuarioEmpresasRol(int $usuarioId, int $rolId, array $empresaIds): void
    {
        $this->pdo->beginTransaction();

        try {
            $delete = $this->pdo->prepare('DELETE FROM usuarios_empresas WHERE usuario_id = :usuario_id');
            $delete->execute([':usuario_id' => $usuarioId]);

            if ($empresaIds !== []) {
                $insert = $this->pdo->prepare('INSERT INTO usuarios_empresas (usuario_id, empresa_id, rol_id) VALUES (:usuario_id, :empresa_id, :rol_id)');
                foreach ($empresaIds as $empresaId) {
                    $insert->execute([
                        ':usuario_id' => $usuarioId,
                        ':empresa_id' => (int) $empresaId,
                        ':rol_id' => $rolId,
                    ]);
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
