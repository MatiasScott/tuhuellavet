<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

class UserInvitationService
{
    public function send(int $userId): void
    {
        $db = Database::connection();

        $stmt = $db->prepare(
            'SELECT id, nombres, email
             FROM usuarios
             WHERE id = :id
               AND activo = 1
               AND deleted_at IS NULL
             LIMIT 1'
        );

        $stmt->execute([
            'id' => $userId
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new RuntimeException(
                'No se encontró un usuario activo para enviar la invitación.'
            );
        }

        $token = bin2hex(random_bytes(32));

        $tokenHash = hash('sha256', $token);

        $stmt = $db->prepare(
            'INSERT INTO password_reset_tokens
                (usuario_id, token_hash, expires_at)
             VALUES
                (:usuario_id, :token_hash,
                 DATE_ADD(NOW(), INTERVAL 60 MINUTE))'
        );

        $stmt->execute([
            'usuario_id' => $userId,
            'token_hash' => $tokenHash
        ]);

        $link = url(
            '/restablecer-password?token=' .
                urlencode($token)
        );

        $name = htmlspecialchars(
            (string) $user['nombres'],
            ENT_QUOTES,
            'UTF-8'
        );

        $safeLink = htmlspecialchars(
            $link,
            ENT_QUOTES,
            'UTF-8'
        );

        $html = <<<HTML
        <div style="font-family:Arial,sans-serif;
                    max-width:600px;margin:auto;
                    padding:30px;color:#243247">

            <h2 style="color:#008B9E">
                Bienvenido a Tu Huella Vet
            </h2>

            <p>Hola, {$name}.</p>

            <p>
                Se ha creado una cuenta para ti en
                Tu Huella Vet.
            </p>

            <p>
                Para activar tu acceso, establece
                una contraseña utilizando el
                siguiente botón:
            </p>

            <p style="margin:30px 0">
                <a href="{$safeLink}"
                   style="background:#008B9E;
                          color:white;
                          padding:14px 24px;
                          text-decoration:none;
                          border-radius:8px">
                    Establecer mi contraseña
                </a>
            </p>

            <p>
                Este enlace caduca en 60 minutos.
            </p>

            <p>
                Si no esperabas este mensaje,
                contacta con el administrador.
            </p>

        </div>
        HTML;

        $plainText =
            "Bienvenido a Tu Huella Vet.\n\n" .
            "Establece tu contraseña aquí:\n" .
            $link . "\n\n" .
            "El enlace caduca en 60 minutos.";

        (new MailService())->send(
            (string) $user['email'],
            'Activa tu cuenta - Tu Huella Vet',
            $html,
            $plainText
        );
    }
}
