<?php
use App\Controllers\Cliente\PortalController;
/** @var \App\Core\Router $router */
$router->get('/cliente',[PortalController::class,'index'],['auth','environment']);
$router->get('/cliente/pacientes/{id}',[PortalController::class,'patient'],['auth','environment']);

$router->post('/cliente/pacientes/{id}/foto',[PortalController::class,'photo'],['auth','environment']);
