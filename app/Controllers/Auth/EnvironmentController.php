<?php
namespace App\Controllers\Auth;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\Environment;
use App\Services\AuthService;

class EnvironmentController extends Controller
{
    public function index(Request $request): void
    {
        $user = auth_user();
        $environments = !empty($user['is_super_admin'])
            ? (new Environment())->allActive()
            : (new Environment())->forUser((int)$user['id']);

        if (count($environments) === 1) {
            (new AuthService())->selectEnvironment((int)$environments[0]['id']);
            $this->redirect('/dashboard');
        }

        $this->view('auth/select-environment',[
            'environments'=>$environments,
            'error'=>Session::pullFlash('error'),
        ],'layouts/auth');
    }

    public function select(Request $request): void
    {
        if (!Session::validateCsrf($request->input('_token'))) {
            http_response_code(419); echo 'Sesión expirada.'; return;
        }

        if (!(new AuthService())->selectEnvironment((int)$request->input('entorno_id'))) {
            Session::flash('error','No tienes acceso al entorno seleccionado.');
            $this->redirect('/seleccionar-entorno');
        }

        $this->redirect('/dashboard');
    }
}
