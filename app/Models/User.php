<?php

namespace App\Models;

use App\Core\Model;

class User extends Model
{
    public function findByEmail(
        string $email
    ): ?array {
        $stmt = $this->db->prepare(
            '
            SELECT *
            FROM usuarios
            WHERE LOWER(email) = LOWER(:email)
              AND activo = 1
              AND deleted_at IS NULL
            LIMIT 1
            '
        );

        $stmt->execute([
            'email' => trim($email),
        ]);

        return $stmt->fetch()
            ?: null;
    }

    public function find(
        int $id
    ): ?array {
        $stmt = $this->db->prepare(
            '
            SELECT *
            FROM usuarios
            WHERE id = :id
              AND activo = 1
              AND deleted_at IS NULL
            LIMIT 1
            '
        );

        $stmt->execute([
            'id' => $id,
        ]);

        return $stmt->fetch()
            ?: null;
    }

    public function touchLastLogin(
        int $id
    ): void {
        $stmt = $this->db->prepare(
            '
            UPDATE usuarios
            SET ultimo_login_at = NOW()
            WHERE id = :id
            '
        );

        $stmt->execute([
            'id' => $id,
        ]);
    }
}