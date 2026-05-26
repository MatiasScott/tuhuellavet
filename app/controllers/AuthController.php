<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Helpers\ValidationHelper;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\PasswordResetService;

final class AuthController extends Controller
{
    public function showLogin(Request $request, Response $response): never
    {
        $response->view('auth/login', [
            'csrfToken' => Csrf::token((int) config('auth.csrf_token_ttl', 3600)),
            'error' => Session::get('flash_error'),
        ]);
    }

    public function login(Request $request, Response $response): never
    {
        $authService = new AuthService();

        if (!$authService->ensureCsrf((string) $request->input('_csrf_token'))) {
            Session::put('flash_error', 'Token CSRF invalido.');
            $response->redirect('/login');
        }

        $payload = [
            'email' => (string) $request->input('email', ''),
            'password' => (string) $request->input('password', ''),
        ];

        $errors = ValidationHelper::required($payload, ['email', 'password']);
        if (!ValidationHelper::email($payload['email'])) {
            $errors['email'] = 'Correo invalido.';
        }

        if (!empty($errors)) {
            Session::put('flash_error', 'Credenciales invalidas.');
            $response->redirect('/login');
        }

        $user = $authService->attemptLogin($payload['email'], $payload['password']);

        if ($user === null) {
            Session::put('flash_error', 'Usuario o contrasena incorrectos.');
            $response->redirect('/login');
        }

        (new AuditService())->log([
            'usuario_id' => $user['id'],
            'ip' => $request->server('REMOTE_ADDR'),
            'modulo' => 'auth',
            'accion' => 'login',
            'datos_nuevos' => ['email' => $user['email']],
        ]);

        if ((int) $user['require_password_change'] === 1) {
            $response->redirect('/password/change');
        }

        $response->redirect('/empresa/seleccionar');
    }

    public function logout(Request $request, Response $response): never
    {
        if (!(new AuthService())->ensureCsrf((string) $request->input('_csrf_token'))) {
            $response->redirect('/dashboard');
        }

        $user = Session::get((string) config('auth.session_key'));

        if (is_array($user)) {
            (new AuditService())->log([
                'usuario_id' => $user['id'] ?? null,
                'ip' => $request->server('REMOTE_ADDR'),
                'modulo' => 'auth',
                'accion' => 'logout',
            ]);
        }

        (new AuthService())->logout();
        $response->redirect('/login');
    }

    public function showForcePasswordChange(Request $request, Response $response): never
    {
        $response->view('auth/force_password_change', [
            'csrfToken' => Csrf::token((int) config('auth.csrf_token_ttl', 3600)),
            'error' => Session::get('flash_error'),
        ]);
    }

    public function forcePasswordChange(Request $request, Response $response): never
    {
        $authService = new AuthService();
        $user = Session::get((string) config('auth.session_key'));

        if (!is_array($user)) {
            $response->redirect('/login');
        }

        if (!$authService->ensureCsrf((string) $request->input('_csrf_token'))) {
            Session::put('flash_error', 'Token CSRF invalido.');
            $response->redirect('/password/change');
        }

        $password = (string) $request->input('password', '');
        $passwordConfirm = (string) $request->input('password_confirm', '');

        if (strlen($password) < 8 || $password !== $passwordConfirm) {
            Session::put('flash_error', 'La contrasena debe tener al menos 8 caracteres y coincidir.');
            $response->redirect('/password/change');
        }

        $authService->updatePassword((int) $user['id'], $password);
        $user['require_password_change'] = 0;
        Session::put((string) config('auth.session_key'), $user);

        $response->redirect('/empresa/seleccionar');
    }

    public function showForgotPassword(Request $request, Response $response): never
    {
        $response->view('auth/forgot_password', [
            'csrfToken' => Csrf::token((int) config('auth.csrf_token_ttl', 3600)),
            'message' => Session::get('flash_message'),
        ]);
    }

    public function sendResetLink(Request $request, Response $response): never
    {
        if (!(new AuthService())->ensureCsrf((string) $request->input('_csrf_token'))) {
            $response->redirect('/password/forgot');
        }

        $email = (string) $request->input('email', '');
        $token = (new PasswordResetService())->createToken($email);

        // Placeholder: integrar envio real por correo.
        Session::put('flash_message', $token ? ('Token generado (debug): ' . $token) : 'Si el correo existe, se envio un enlace de recuperacion.');

        $response->redirect('/password/forgot');
    }

    public function showResetPassword(Request $request, Response $response): never
    {
        $response->view('auth/reset_password', [
            'csrfToken' => Csrf::token((int) config('auth.csrf_token_ttl', 3600)),
            'token' => (string) $request->input('token', ''),
            'error' => Session::get('flash_error'),
        ]);
    }

    public function resetPassword(Request $request, Response $response): never
    {
        if (!(new AuthService())->ensureCsrf((string) $request->input('_csrf_token'))) {
            $response->redirect('/password/reset');
        }

        $token = (string) $request->input('token', '');
        $password = (string) $request->input('password', '');
        $passwordConfirm = (string) $request->input('password_confirm', '');

        if (strlen($password) < 8 || $password !== $passwordConfirm) {
            Session::put('flash_error', 'La contrasena no cumple reglas minimas.');
            $response->redirect('/password/reset?token=' . urlencode($token));
        }

        $userId = (new PasswordResetService())->consumeToken($token);

        if ($userId === null) {
            Session::put('flash_error', 'Token invalido o expirado.');
            $response->redirect('/password/reset?token=' . urlencode($token));
        }

        (new AuthService())->updatePassword($userId, $password);
        $response->redirect('/login');
    }

    public function showCompanySelector(Request $request, Response $response): never
    {
        $authService = new AuthService();
        $user = Session::get((string) config('auth.session_key'));
        $companies = [];

        if (is_array($user)) {
            $companies = $authService->userCompanies((int) ($user['id'] ?? 0));
        }

        $response->view('auth/select_company', [
            'csrfToken' => Csrf::token((int) config('auth.csrf_token_ttl', 3600)),
            'companies' => $companies,
        ]);
    }

    public function selectCompany(Request $request, Response $response): never
    {
        if (!(new AuthService())->ensureCsrf((string) $request->input('_csrf_token'))) {
            $response->redirect('/empresa/seleccionar');
        }

        $companyId = (int) $request->input('empresa_id', 0);
        $redirectTo = (string) $request->input('redirect_to', '/dashboard');
        $user = Session::get((string) config('auth.session_key'));

        if (!is_array($user)) {
            $response->redirect('/login');
        }

        if (!(new AuthService())->userHasCompany((int) ($user['id'] ?? 0), $companyId)) {
            $response->redirect('/empresa/seleccionar');
        }

        $prevCompanyId = (int) Session::get((string) config('auth.company_session_key'), 0);
        Session::put((string) config('auth.company_session_key'), $companyId);

        (new AuditService())->log([
            'usuario_id' => $user['id'] ?? null,
            'empresa_id' => $companyId,
            'ip' => $request->server('REMOTE_ADDR'),
            'user_agent' => (string) $request->server('HTTP_USER_AGENT', ''),
            'modulo' => 'auth',
            'accion' => 'cambio_empresa',
            'tabla_afectada' => 'usuarios_empresas',
            'datos_anteriores' => ['empresa_id' => $prevCompanyId],
            'datos_nuevos' => ['empresa_id' => $companyId],
        ]);

        if ($redirectTo === '' || $redirectTo[0] !== '/') {
            $redirectTo = '/dashboard';
        }

        $response->redirect($redirectTo);
    }
}
