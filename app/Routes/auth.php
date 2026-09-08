<?php
use App\Controllers\Auth\LoginController;use App\Controllers\Auth\EnvironmentController;use App\Controllers\Auth\PasswordController;
/** @var \App\Core\Router $router */
$router->get('/login',[LoginController::class,'show'],['guest']);$router->post('/login',[LoginController::class,'login'],['guest']);
$router->get('/auth/google',[LoginController::class,'google'],['guest']);$router->get('/auth/google/callback',[LoginController::class,'googleCallback'],['guest']);
$router->get('/seleccionar-entorno',[EnvironmentController::class,'index'],['auth']);$router->post('/seleccionar-entorno',[EnvironmentController::class,'select'],['auth']);
$router->get('/cambiar-password',[PasswordController::class,'edit'],['auth']);$router->post('/cambiar-password',[PasswordController::class,'update'],['auth']);
$router->post('/logout',[LoginController::class,'logout'],['auth']);

$router->get('/olvide-password',[PasswordController::class,'forgot'],['guest']);
$router->post('/olvide-password',[PasswordController::class,'sendReset'],['guest']);
$router->get('/restablecer-password',[PasswordController::class,'reset'],['guest']);
$router->post('/restablecer-password',[PasswordController::class,'performReset'],['guest']);
