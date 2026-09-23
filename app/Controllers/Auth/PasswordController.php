<?php

namespace App\Controllers\Auth;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\Database;

class PasswordController extends Controller
{
    public function edit(Request $r): void
    {
        $this->view('auth/change-password', ['error' => Session::pullFlash('error')], 'layouts/auth');
    }
    public function update(Request $r): void
    {
        if (!Session::validateCsrf($r->input('_token'))) {
            http_response_code(419);
            return;
        }
        $p = (string)$r->input('password');
        $c = (string)$r->input('password_confirmation');
        if (strlen($p) < 8 || $p !== $c) {
            Session::flash('error', 'La contraseña debe tener al menos 8 caracteres y coincidir.');
            $this->redirect('/cambiar-password');
        }
        $s = Database::connection()->prepare('UPDATE usuarios SET password_hash=:h,requiere_cambio_password=0,password_changed_at=NOW() WHERE id=:id');
        $s->execute(['h' => password_hash($p, PASSWORD_DEFAULT), 'id' => auth_id()]);
        $u = auth_user();
        $u['requires_password_change'] = false;
        Session::put('auth_user', $u);
        (new \App\Services\AuditService())->log(auth_id(), active_environment_id(), 'AUTH', 'CAMBIO_PASSWORD', 'usuarios', auth_id());
        $this->redirect('/dashboard');
    }
    public function forgot(Request $r): void
    {
        $this->view('auth/forgot-password', ['success' => Session::pullFlash('success'), 'error' => Session::pullFlash('error')], 'layouts/auth');
    }
    public function sendReset(Request $r): void
    {
        if (!Session::validateCsrf($r->input('_token'))) {
            http_response_code(419);
            return;
        }

        $email = trim((string) $r->input('email'));

        $genericMessage =
            'Si existe una cuenta activa asociada al correo, '
            . 'recibirás un enlace de recuperación.';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('success', $genericMessage);
            $this->redirect('/olvide-password');
            return;
        }

        $db = Database::connection();

        $stmt = $db->prepare(
            'SELECT id
         FROM usuarios
         WHERE email = :email
           AND activo = 1
           AND deleted_at IS NULL
         LIMIT 1'
        );

        $stmt->execute([
            'email' => $email
        ]);

        $userId = (int) $stmt->fetchColumn();

        if ($userId > 0) {
            try {
                (new \App\Services\UserInvitationService())
                    ->send($userId);
            } catch (\Throwable $e) {
                error_log(
                    'Error al solicitar recuperación de contraseña '
                        . 'para usuario ID ' . $userId . ': '
                        . $e->getMessage()
                );
            }
        }

        Session::flash('success', $genericMessage);

        $this->redirect('/olvide-password');
    }
    public function reset(Request $r): void
    {
        $token = (string)$r->input('token');
        $this->view('auth/reset-password', ['token' => $token, 'error' => Session::pullFlash('error')], 'layouts/auth');
    }
    public function performReset(Request $r): void
    {
        if (!Session::validateCsrf($r->input('_token'))) {
            http_response_code(419);
            return;
        }

        $token = trim((string) $r->input('token'));
        $password = (string) $r->input('password');
        $confirmation = (string) $r->input('password_confirmation');

        if (
            strlen($token) !== 64 ||
            !ctype_xdigit($token)
        ) {
            Session::flash(
                'error',
                'El enlace de recuperación no es válido.'
            );

            $this->redirect('/olvide-password');
            return;
        }

        if (
            strlen($password) < 8 ||
            $password !== $confirmation
        ) {
            Session::flash(
                'error',
                'La contraseña debe tener al menos 8 caracteres '
                    . 'y coincidir con su confirmación.'
            );

            $this->redirect(
                '/restablecer-password?token=' . urlencode($token)
            );

            return;
        }

        try {

            Database::transaction(function (\PDO $db) use (
                $token,
                $password
            ) {

                $tokenHash = hash('sha256', $token);

                $stmt = $db->prepare(
                    'SELECT
                    prt.id,
                    prt.usuario_id
                 FROM password_reset_tokens prt
                 INNER JOIN usuarios u
                    ON u.id = prt.usuario_id
                 WHERE prt.token_hash = :token_hash
                   AND prt.usado_at IS NULL
                   AND prt.expires_at > NOW()
                   AND u.activo = 1
                   AND u.deleted_at IS NULL
                 LIMIT 1
                 FOR UPDATE'
                );

                $stmt->execute([
                    'token_hash' => $tokenHash
                ]);

                $reset = $stmt->fetch(\PDO::FETCH_ASSOC);

                if (!$reset) {
                    throw new \RuntimeException(
                        'El enlace es inválido, expiró o ya fue utilizado.'
                    );
                }

                $userId = (int) $reset['usuario_id'];

                $stmt = $db->prepare(
                    'UPDATE usuarios
                 SET
                    password_hash = :password_hash,
                    requiere_cambio_password = 0,
                    password_changed_at = NOW()
                 WHERE id = :user_id
                   AND activo = 1
                   AND deleted_at IS NULL'
                );

                $stmt->execute([
                    'password_hash' => password_hash(
                        $password,
                        PASSWORD_DEFAULT
                    ),
                    'user_id' => $userId
                ]);

                if ($stmt->rowCount() !== 1) {
                    throw new \RuntimeException(
                        'No se pudo actualizar la contraseña.'
                    );
                }

                $stmt = $db->prepare(
                    'UPDATE password_reset_tokens
                 SET usado_at = NOW()
                 WHERE usuario_id = :user_id
                   AND usado_at IS NULL'
                );

                $stmt->execute([
                    'user_id' => $userId
                ]);

                (new \App\Services\AuditService())->log(
                    $userId,
                    null,
                    'AUTH',
                    'RESTABLECER_PASSWORD',
                    'usuarios',
                    $userId
                );
            });

            Session::flash(
                'success',
                'Contraseña actualizada correctamente. '
                    . 'Ya puedes iniciar sesión.'
            );

            $this->redirect('/login');
            return;
        } catch (\RuntimeException $e) {

            Session::flash(
                'error',
                'El enlace es inválido, expiró o ya fue utilizado.'
            );

            $this->redirect('/olvide-password');
            return;
        } catch (\Throwable $e) {

            error_log(
                'Error al restablecer contraseña: ' .
                    $e->getMessage()
            );

            Session::flash(
                'error',
                'No se pudo restablecer la contraseña. '
                    . 'Inténtalo nuevamente.'
            );

            $this->redirect('/olvide-password');
            return;
        }
    }
}
