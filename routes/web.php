<?php

declare(strict_types=1);

use App\Controllers\AnimalController;
use App\Controllers\AuthController;
use App\Controllers\DashboardController;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\CompanyContextMiddleware;
use App\Middlewares\RequirePasswordChangeMiddleware;

$router = app('router');

$router->get('/', static fn ($request, $response) => $response->redirect('/login'));

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout'], [AuthMiddleware::class]);

$router->get('/password/change', [AuthController::class, 'showForcePasswordChange'], [AuthMiddleware::class]);
$router->post('/password/change', [AuthController::class, 'forcePasswordChange'], [AuthMiddleware::class]);

$router->get('/password/forgot', [AuthController::class, 'showForgotPassword']);
$router->post('/password/forgot', [AuthController::class, 'sendResetLink']);
$router->get('/password/reset', [AuthController::class, 'showResetPassword']);
$router->post('/password/reset', [AuthController::class, 'resetPassword']);

$router->get('/empresa/seleccionar', [AuthController::class, 'showCompanySelector'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class]);
$router->post('/empresa/seleccionar', [AuthController::class, 'selectCompany'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class]);

$router->get('/dashboard', [DashboardController::class, 'index'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->get('/animales', [AnimalController::class, 'index'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
