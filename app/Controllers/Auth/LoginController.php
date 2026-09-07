<?php

namespace App\Controllers\Auth;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;

use App\Services\AuthService;
use App\Services\GoogleAuthService;

class LoginController extends Controller
{
    public function show(
        Request $request
    ): void {
        $this->view(
            'auth/login',
            [
                'error'
                    => Session::pullFlash(
                        'error'
                    ),

                'success'
                    => Session::pullFlash(
                        'success'
                    ),

                'googleEnabled'
                    => (
                        new GoogleAuthService()
                    )->isConfigured(),
            ],
            'layouts/auth'
        );
    }

    public function login(
        Request $request
    ): void {
        if (
            !Session::validateCsrf(
                $request->input('_token')
            )
        ) {
            http_response_code(419);

            echo 'Sesión expirada. Recarga la página.';

            return;
        }

        $email = trim(
            (string)
                $request->input(
                    'email'
                )
        );

        $password = (string)
            $request->input(
                'password'
            );

        if (
            !(new AuthService())
                ->attempt(
                    $email,
                    $password
                )
        ) {
            Session::flash(
                'error',
                'Correo o contraseña incorrectos.'
            );

            $this->redirect(
                '/login'
            );
        }

        $this->redirect(
            '/seleccionar-entorno'
        );
    }

    public function google(
        Request $request
    ): void {
        $service
            = new GoogleAuthService();

        if (
            !$service
                ->isConfigured()
        ) {
            Session::flash(
                'error',
                'El acceso con Google todavía no está configurado.'
            );

            $this->redirect(
                '/login'
            );
        }

        header(
            'Location: '
            . $service
                ->authorizationUrl()
        );

        exit;
    }

    public function googleCallback(
        Request $request
    ): void {
        $code = (string)
            $request->input(
                'code'
            );

        $user = $code !== ''
            ? (
                new GoogleAuthService()
            )->authenticateCode(
                $code
            )
            : null;

        if (!$user) {
            Session::flash(
                'error',
                'Esta cuenta de Google no está autorizada en el sistema.'
            );

            $this->redirect(
                '/login'
            );
        }

        $auth
            = new AuthService();

        $auth
            ->establishSession(
                $user
            );

        (new \App\Models\User())
            ->touchLastLogin(
                (int) $user['id']
            );

        (new \App\Services\AuditService())
            ->log(
                (int) $user['id'],
                null,
                'AUTH',
                'LOGIN_GOOGLE'
            );

        $this->redirect(
            '/seleccionar-entorno'
        );
    }

    public function logout(
        Request $request
    ): void {
        if (
            !Session::validateCsrf(
                $request->input('_token')
            )
        ) {
            http_response_code(419);

            echo 'Sesión expirada.';

            return;
        }

        (new AuthService())
            ->logout();

        $this->redirect(
            '/login'
        );
    }
}