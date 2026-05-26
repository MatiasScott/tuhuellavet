<?php

declare(strict_types=1);

use App\Controllers\AnimalController;
use App\Controllers\AuthController;
use App\Controllers\ConsultaController;
use App\Controllers\CirugiaController;
use App\Controllers\DashboardController;
use App\Controllers\DesparasitacionController;
use App\Controllers\DiagnosticoController;
use App\Controllers\ExamenLaboratorioController;
use App\Controllers\HospitalizacionController;
use App\Controllers\PropietarioController;
use App\Controllers\TimelineController;
use App\Controllers\VacunaController;
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

$router->get('/propietarios', [PropietarioController::class, 'index'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->get('/propietarios/crear', [PropietarioController::class, 'createForm'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->post('/propietarios', [PropietarioController::class, 'create'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->get('/propietarios/editar', [PropietarioController::class, 'edit'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->post('/propietarios/actualizar', [PropietarioController::class, 'update'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);

$router->get('/animales', [AnimalController::class, 'index'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->get('/animales/crear', [AnimalController::class, 'createForm'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->post('/animales', [AnimalController::class, 'create'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->get('/animales/editar', [AnimalController::class, 'edit'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->post('/animales/actualizar', [AnimalController::class, 'update'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);

$router->get('/consultas', [ConsultaController::class, 'index'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->get('/consultas/crear', [ConsultaController::class, 'createForm'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->post('/consultas', [ConsultaController::class, 'create'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);

$router->get('/diagnosticos', [DiagnosticoController::class, 'index'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->post('/diagnosticos/catalogo', [DiagnosticoController::class, 'createCatalogo'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->post('/diagnosticos/asignar', [DiagnosticoController::class, 'asignar'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);

$router->get('/vacunas', [VacunaController::class, 'index'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->post('/vacunas/catalogo', [VacunaController::class, 'createCatalogo'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->post('/vacunas/aplicar', [VacunaController::class, 'aplicar'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);

$router->get('/desparasitaciones', [DesparasitacionController::class, 'index'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->post('/desparasitaciones', [DesparasitacionController::class, 'create'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);

$router->get('/hospitalizaciones', [HospitalizacionController::class, 'index'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->post('/hospitalizaciones', [HospitalizacionController::class, 'createHospitalizacion'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->post('/hospitalizaciones/tamanos', [HospitalizacionController::class, 'createTamano'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->post('/hospitalizaciones/estado', [HospitalizacionController::class, 'updateEstado'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->post('/hospitalizaciones/fluidoterapia', [HospitalizacionController::class, 'createFluidoterapia'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);

$router->get('/examenes', [ExamenLaboratorioController::class, 'index'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->post('/examenes', [ExamenLaboratorioController::class, 'create'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);

$router->get('/cirugias', [CirugiaController::class, 'index'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->post('/cirugias', [CirugiaController::class, 'create'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);

$router->get('/timeline', [TimelineController::class, 'index'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
