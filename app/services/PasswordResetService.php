<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DateInterval;
use DateTimeImmutable;
use PDO;

final class PasswordResetService
{
    public function createToken(string $email): ?string
    {
        $pdo = Database::connection((array) config('database'));

        $userStmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email AND activo = 1 LIMIT 1');
        $userStmt->execute([':email' => $email]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($user)) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $token);
        $expiresAt = (new DateTimeImmutable())->add(new DateInterval('PT30M'))->format('Y-m-d H:i:s');

        $pdo->prepare('DELETE FROM password_resets WHERE usuario_id = :usuario_id')->execute([
            ':usuario_id' => (int) $user['id'],
        ]);

        $stmt = $pdo->prepare('INSERT INTO password_resets (usuario_id, token_hash, expira_en, usado_en, creado_en) VALUES (:usuario_id, :token_hash, :expira_en, NULL, NOW())');
        $stmt->execute([
            ':usuario_id' => (int) $user['id'],
            ':token_hash' => $hashedToken,
            ':expira_en' => $expiresAt,
        ]);

        return $token;
    }

    public function consumeToken(string $token): ?int
    {
        $pdo = Database::connection((array) config('database'));
        $tokenHash = hash('sha256', $token);

        $stmt = $pdo->prepare('SELECT id, usuario_id, expira_en, usado_en FROM password_resets WHERE token_hash = :token_hash LIMIT 1');
        $stmt->execute([':token_hash' => $tokenHash]);
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($reset)) {
            return null;
        }

        if (!empty($reset['usado_en'])) {
            return null;
        }

        if (strtotime((string) $reset['expira_en']) < time()) {
            return null;
        }

        $pdo->prepare('UPDATE password_resets SET usado_en = NOW() WHERE id = :id')->execute([
            ':id' => (int) $reset['id'],
        ]);

        return (int) $reset['usuario_id'];
    }
}
