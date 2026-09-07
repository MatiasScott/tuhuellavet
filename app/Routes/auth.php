<?php
use App\Controllers\Auth\LoginController;
use App\Controllers\Auth\EnvironmentController;

/** @var \App\Core\Router $router */
$router->get('/login',[LoginController::class,'show'],['guest']);
$router->post('/login',[LoginController::class,'login'],['guest']);
$router->get('/auth/google',[LoginController::class,'google'],['guest']);
$router->get('/auth/google/callback',[LoginController::class,'googleCallback'],['guest']);
$router->get('/seleccionar-entorno',[EnvironmentController::class,'index'],['auth']);
$router->post('/seleccionar-entorno',[EnvironmentController::class,'select'],['auth']);
$router->post('/logout',[LoginController::class,'logout'],['auth']);
