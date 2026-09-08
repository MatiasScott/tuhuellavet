<?php
use App\Controllers\Admin\AdministracionController;use App\Controllers\Admin\CatalogoController;
/** @var \App\Core\Router $router */
$router->get('/admin/usuarios',[AdministracionController::class,'users'],['auth','environment','permission:usuarios.ver']);
$router->post('/admin/usuarios',[AdministracionController::class,'storeUser'],['auth','environment','permission:usuarios.crear']);
$router->get('/admin/roles',[AdministracionController::class,'roles'],['auth','environment','permission:roles.ver']);
$router->post('/admin/roles/{id}/permisos',[AdministracionController::class,'savePermissions'],['auth','environment','permission:permisos.editar']);
$router->get('/admin/empresas',[AdministracionController::class,'companies'],['auth','environment','permission:empresas.ver']);
$router->post('/admin/empresas',[AdministracionController::class,'storeCompany'],['auth','environment','permission:empresas.crear']);
$router->get('/admin/auditoria',[AdministracionController::class,'audit'],['auth','environment','permission:auditoria.ver']);

$router->get('/admin/catalogos',[CatalogoController::class,'index'],['auth','environment','permission:pacientes.editar']);
$router->post('/admin/catalogos/{type}',[CatalogoController::class,'store'],['auth','environment','permission:pacientes.editar']);

$router->post('/admin/roles',[AdministracionController::class,'storeRole'],['auth','environment','permission:roles.crear']);
$router->post('/admin/entornos',[AdministracionController::class,'storeEnvironment'],['auth','environment','permission:empresas.crear']);
