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

        $stmt = $pdo->prepare('SELECT u.id, u.nombres, u.apellidos, u.email, u.password, u.estado, r.nombre AS rol_nombre FROM usuarios u LEFT JOIN usuarios_empresas ue ON ue.usuario_id = u.id LEFT JOIN roles r ON r.id = ue.rol_id WHERE u.email = :email AND u.estado = 1 LIMIT 1');
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
        $roleName = (string) ($user['rol_nombre'] ?? 'invitado');

        $authPayload = [
            'id' => (int) $user['id'],
            'nombre' => $fullName !== '' ? $fullName : (string) ($user['nombres'] ?? 'Usuario'),
            'email' => (string) $user['email'],
            'rol_codigo' => $this->normalizeRoleName($roleName),
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
        $stmt = $pdo->prepare('SELECT e.id, e.nombre FROM empresas e INNER JOIN usuarios_empresas ue ON ue.empresa_id = e.id WHERE ue.usuario_id = :usuario_id AND e.estado = 1 ORDER BY e.nombre ASC');
        $stmt->execute([':usuario_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function userHasCompany(int $userId, int $companyId): bool
    {
        $pdo = Database::connection((array) config('database'));
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM usuarios_empresas WHERE usuario_id = :usuario_id AND empresa_id = :empresa_id');
        $stmt->execute([
            ':usuario_id' => $userId,
            ':empresa_id' => $companyId,
        ]);

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
