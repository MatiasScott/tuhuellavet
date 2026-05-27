<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Session;
use PDO;
use Throwable;

final class AuthService
{
    public function attemptLogin(string $email, string $password): ?array
    {
        $pdo = Database::connection((array) config('database'));

        $stmt = $pdo->prepare('SELECT u.id, u.nombres, u.apellidos, u.email, u.password, u.estado FROM usuarios u WHERE u.email = :email AND u.estado = 1 LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($user)) {
            return null;
        }

        if (!password_verify($password, (string) $user['password'])) {
            return null;
        }

        Session::regenerate();

        $fullName = trim(((string) ($user['nombres'] ?? '')) . ' ' . ((string) ($user['apellidos'] ?? '')));

        $authPayload = [
            'id' => (int) $user['id'],
            'nombre' => $fullName !== '' ? $fullName : (string) ($user['nombres'] ?? 'Usuario'),
            'email' => (string) $user['email'],
            'rol_codigo' => 'invitado',
            'rol_id' => null,
            'permisos' => [],
            'require_password_change' => 0,
            'password_changed_at' => null,
        ];

        Session::put((string) config('auth.session_key'), $authPayload);

        return $authPayload;
    }

    public function logout(): void
    {
        Session::destroy();
    }

    public function updatePassword(int $userId, string $newPassword): bool
    {
        $pdo = Database::connection((array) config('database'));
        $stmt = $pdo->prepare('UPDATE usuarios SET password = :password_hash, updated_at = NOW() WHERE id = :id');

        return $stmt->execute([
            ':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            ':id' => $userId,
        ]);
    }

    public function ensureCsrf(?string $token): bool
    {
        return Csrf::verify($token, (int) config('auth.csrf_token_ttl', 3600));
    }

    public function userCompanies(int $userId): array
    {
        $pdo = Database::connection((array) config('database'));

        if ($this->isSuperAdminUser($userId)) {
            $stmt = $pdo->query('SELECT e.id, e.nombre FROM empresas e WHERE e.estado = 1 ORDER BY e.nombre ASC');
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmt = $pdo->prepare('SELECT e.id, e.nombre FROM empresas e INNER JOIN usuarios_empresas ue ON ue.empresa_id = e.id WHERE ue.usuario_id = :usuario_id AND e.estado = 1 ORDER BY e.nombre ASC');
        $stmt->execute([':usuario_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function userHasCompany(int $userId, int $companyId): bool
    {
        $pdo = Database::connection((array) config('database'));

        if ($this->isSuperAdminUser($userId) && $this->companyExists($companyId)) {
            return true;
        }

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM usuarios_empresas WHERE usuario_id = :usuario_id AND empresa_id = :empresa_id');
        $stmt->execute([
            ':usuario_id' => $userId,
            ':empresa_id' => $companyId,
        ]);

        return ((int) $stmt->fetchColumn()) > 0;
    }

    public function refreshSessionAccess(int $userId, int $companyId): void
    {
        $user = Session::get((string) config('auth.session_key'));
        if (!is_array($user)) {
            return;
        }

        $access = $this->resolveAccess($userId, $companyId);

        $user['rol_codigo'] = $access['rol_codigo'];
        $user['rol_id'] = $access['rol_id'];
        $user['permisos'] = $access['permisos'];

        Session::put((string) config('auth.session_key'), $user);
    }

    private function resolveAccess(int $userId, int $companyId): array
    {
        if ($this->isSuperAdminUser($userId)) {
            return [
                'rol_codigo' => 'super_administrador',
                'rol_id' => null,
                'permisos' => ['*'],
            ];
        }

        $pdo = Database::connection((array) config('database'));
        $stmt = $pdo->prepare('SELECT ue.rol_id, r.nombre AS rol_nombre FROM usuarios_empresas ue INNER JOIN roles r ON r.id = ue.rol_id WHERE ue.usuario_id = :usuario_id AND ue.empresa_id = :empresa_id LIMIT 1');
        $stmt->execute([
            ':usuario_id' => $userId,
            ':empresa_id' => $companyId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($row)) {
            $role = 'invitado';
            return [
                'rol_codigo' => $role,
                'rol_id' => null,
                'permisos' => $this->defaultPermissionsForRole($role),
            ];
        }

        $rolId = (int) ($row['rol_id'] ?? 0);
        $rolCodigo = $this->normalizeRoleName((string) ($row['rol_nombre'] ?? 'invitado'));

        $permissions = [];
        if ($rolId > 0) {
            $permStmt = $pdo->prepare('SELECT p.slug FROM rol_permisos rp INNER JOIN permisos p ON p.id = rp.permiso_id WHERE rp.rol_id = :rol_id');
            $permStmt->execute([':rol_id' => $rolId]);
            $permissions = $permStmt->fetchAll(PDO::FETCH_COLUMN);
            if (!is_array($permissions)) {
                $permissions = [];
            }
        }

        $permissions = array_values(array_unique(array_map(static fn ($v): string => (string) $v, $permissions)));
        if ($permissions === []) {
            $permissions = $this->defaultPermissionsForRole($rolCodigo);
        }

        return [
            'rol_codigo' => $rolCodigo,
            'rol_id' => $rolId > 0 ? $rolId : null,
            'permisos' => $permissions,
        ];
    }

    private function defaultPermissionsForRole(string $role): array
    {
        $defaults = (array) config('permissions.default_role_permissions.' . $role, []);
        return array_values(array_unique(array_map(static fn ($v): string => (string) $v, $defaults)));
    }

    private function isSuperAdminUser(int $userId): bool
    {
        $pdo = Database::connection((array) config('database'));
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM usuarios_empresas ue INNER JOIN roles r ON r.id = ue.rol_id WHERE ue.usuario_id = :usuario_id AND REPLACE(REPLACE(LOWER(r.nombre), " ", "_"), "-", "_") = "super_administrador"');
        $stmt->execute([':usuario_id' => $userId]);

        return ((int) $stmt->fetchColumn()) > 0;
    }

    private function companyExists(int $companyId): bool
    {
        if ($companyId <= 0) {
            return false;
        }

        $pdo = Database::connection((array) config('database'));
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM empresas WHERE id = :id');
        $stmt->execute([':id' => $companyId]);

        return ((int) $stmt->fetchColumn()) > 0;
    }

    private function normalizeRoleName(string $roleName): string
    {
        $normalized = strtolower(trim($roleName));
        $normalized = str_replace([' ', '-'], '_', $normalized);

        return match ($normalized) {
            'super_administrador' => 'super_administrador',
            'administrador' => 'administrador',
            'cliente', 'clientes' => 'cliente',
            default => 'invitado',
        };
    }
}
