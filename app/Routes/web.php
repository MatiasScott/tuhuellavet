<?php
use App\Controllers\Admin\DashboardController;
use App\Controllers\Clinica\PacienteController;
use App\Controllers\Clinica\PropietarioController;

/** @var \App\Core\Router $router */
$router->get('/',function () {
    if (!is_authenticated()) { header('Location: '.url('/login')); exit; }
    header('Location: '.url(active_environment_id() ? '/dashboard' : '/seleccionar-entorno')); exit;
});
$router->get('/dashboard',[DashboardController::class,'index'],['auth','environment']);
$router->get('/pacientes',[PacienteController::class,'index'],['auth','environment','permission:pacientes.ver']);
$router->get('/propietarios',[PropietarioController::class,'index'],['auth','environment','permission:propietarios.ver']);
