<?php

namespace App\Controllers\Auth;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\Database;

use App\Services\AuditService;

class PasswordController extends Controller
{
    public function show(
        Request $request
    ): void {
        $this->view(
            'auth/change-password',
            [
                'error'
                    => Session::pullFlash(
                        'error'
                    ),
            ],
            'layouts/auth'
        );
    }

    public function update(
        Request $request
    ): void {
        if (
            !Session::validateCsrf(
                $request->input(
                    '_token'
                )
            )
        ) {
            http_response_code(419);

            echo 'Sesión expirada.';

            return;
        }

        $password = (string)
            $request->input(
                'password'
            );

        $confirmation = (string)
            $request->input(
                'password_confirmation'
            );

        if (
            strlen($password) < 8
        ) {
            Session::flash(
                'error',
                'La contraseña debe tener al menos 8 caracteres.'
            );

            $this->redirect(
                '/cambiar-password'
            );
        }

        if (
            $password
            !== $confirmation
        ) {
            Session::flash(
                'error',
                'Las contraseñas no coinciden.'
            );

            $this->redirect(
                '/cambiar-password'
            );
        }

        /*
         * Recomendación mínima:
         * mayúscula, minúscula,
         * número y símbolo.
         */
        if (
            !preg_match(
                '/[A-Z]/',
                $password
            )
            || !preg_match(
                '/[a-z]/',
                $password
            )
            || !preg_match(
                '/[0-9]/',
                $password
            )
            || !preg_match(
                '/[^A-Za-z0-9]/',
                $password
            )
        ) {
            Session::flash(
                'error',
                'La contraseña debe incluir mayúscula, minúscula, número y símbolo.'
            );

            $this->redirect(
                '/cambiar-password'
            );
        }

        $db = Database::connection();

        $stmt = $db->prepare(
            '
            UPDATE usuarios

            SET
                password_hash
                    = :password,

                requiere_cambio_password
                    = 0,

                password_changed_at
                    = NOW()

            WHERE id = :usuario
            '
        );

        $stmt->execute([
            'password'
                => password_hash(
                    $password,
                    PASSWORD_DEFAULT
                ),

            'usuario'
                => auth_id(),
        ]);

        /*
         * Actualizar sesión.
         */
        $user = auth_user();

        $user[
            'requires_password_change'
        ] = false;

        Session::put(
            'auth_user',
            $user
        );

        (new AuditService())
            ->log(
                auth_id(),
                active_environment_id(),
                'AUTH',
                'CAMBIAR_PASSWORD',
                'usuarios',
                auth_id()
            );

        Session::flash(
            'success',
            'Contraseña actualizada correctamente.'
        );

        /*
         * Si todavía no eligió entorno,
         * vuelve al selector.
         */
        if (
            !active_environment_id()
        ) {
            $this->redirect(
                '/seleccionar-entorno'
            );
        }

        $this->redirect(
            '/dashboard'
        );
    }
}