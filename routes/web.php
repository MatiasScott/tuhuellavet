<?php

declare(strict_types=1);

use App\Controllers\AnimalController;
use App\Controllers\AdminAccessController;
use App\Controllers\AdminBusinessController;
use App\Controllers\AdminCatalogoAnimalController;
use App\Controllers\AuditoriaController;
use App\Controllers\AuthController;
use App\Controllers\ConsultaController;
use App\Controllers\CirugiaController;
use App\Controllers\DashboardController;
use App\Controllers\DesparasitacionController;
use App\Controllers\DiagnosticoController;
use App\Controllers\ExamenLaboratorioController;
use App\Controllers\FileController;
use App\Controllers\FormulaController;
use App\Controllers\HospitalizacionController;
use App\Controllers\PropietarioController;
use App\Controllers\TimelineController;
use App\Controllers\VacunaController;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\AdminMiddleware;
use App\Middlewares\CompanyContextMiddleware;
use App\Middlewares\RequirePasswordChangeMiddleware;

$router = app('router');

$router->get('/', static fn ($request, $response) => $response->redirect('/login'));

$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->post('/logout', [AuthController::class, 'logout'], [AuthMiddleware::class]);

$router->get('/pdf/ver', [FileController::class, 'viewPdf'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class]);
$router->get('/pdf/descargar', [FileController::class, 'downloadPdf'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class]);
$router->get('/imagen/ver', [FileController::class, 'viewImage'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class]);

$router->get('/password/change', [AuthController::class, 'showForcePasswordChange'], [AuthMiddleware::class]);
$router->post('/password/change', [AuthController::class, 'forcePasswordChange'], [AuthMiddleware::class]);

$router->get('/password/forgot', [AuthController::class, 'showForgotPassword']);
$router->post('/password/forgot', [AuthController::class, 'sendResetLink']);
$router->get('/password/reset', [AuthController::class, 'showResetPassword']);
$router->post('/password/reset', [AuthController::class, 'resetPassword']);

$router->get('/empresa/seleccionar', [AuthController::class, 'showCompanySelector'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class]);
$router->post('/empresa/seleccionar', [AuthController::class, 'selectCompany'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class]);
$router->post('/empresa/cambiar', [AuthController::class, 'selectCompany'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class]);

$router->get('/usuarios', [AdminAccessController::class, 'usuariosIndex'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class]);
$router->post('/usuarios/crear', [AdminAccessController::class, 'createUsuario'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class]);
$router->post('/usuarios/actualizar', [AdminAccessController::class, 'updateUsuario'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class]);
$router->post('/usuarios/estado', [AdminAccessController::class, 'toggleUsuarioEstado'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class]);
$router->post('/usuarios/reset-password', [AdminAccessController::class, 'resetUsuarioPassword'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class]);
$router->post('/usuarios/asignaciones', [AdminAccessController::class, 'syncUsuarioEmpresasRol'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class]);

$router->get('/roles', [AdminAccessController::class, 'rolesIndex'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class]);
$router->post('/roles/crear', [AdminAccessController::class, 'createRol'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class]);
$router->post('/roles/actualizar', [AdminAccessController::class, 'updateRol'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class]);
$router->post('/roles/duplicar', [AdminAccessController::class, 'duplicateRol'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class]);
$router->post('/roles/permisos', [AdminAccessController::class, 'syncRolPermisos'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class]);

$router->get('/permisos', [AdminAccessController::class, 'permisosIndex'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class]);
$router->post('/permisos/crear', [AdminAccessController::class, 'createPermiso'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class]);
$router->post('/permisos/actualizar', [AdminAccessController::class, 'updatePermiso'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class]);
$router->post('/permisos/sincronizar', [AdminAccessController::class, 'syncPermisos'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class]);

$router->get('/especies', [AdminCatalogoAnimalController::class, 'especiesIndex'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class]);
$router->post('/especies/crear', [AdminCatalogoAnimalController::class, 'createEspecie'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class]);
$router->post('/especies/actualizar', [AdminCatalogoAnimalController::class, 'updateEspecie'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class]);
$router->post('/especies/estado', [AdminCatalogoAnimalController::class, 'toggleEspecie'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class]);

$router->get('/razas', [AdminCatalogoAnimalController::class, 'razasIndex'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class]);
$router->post('/razas/crear', [AdminCatalogoAnimalController::class, 'createRaza'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class]);
$router->post('/razas/actualizar', [AdminCatalogoAnimalController::class, 'updateRaza'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class]);
$router->post('/razas/estado', [AdminCatalogoAnimalController::class, 'toggleRaza'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class]);

$router->get('/categorias-animales', [AdminCatalogoAnimalController::class, 'categoriasIndex'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class]);
$router->post('/categorias-animales/crear', [AdminCatalogoAnimalController::class, 'createCategoria'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class]);
$router->post('/categorias-animales/actualizar', [AdminCatalogoAnimalController::class, 'updateCategoria'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class]);
$router->post('/categorias-animales/estado', [AdminCatalogoAnimalController::class, 'toggleCategoria'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class]);

$router->get('/empresas', [AdminBusinessController::class, 'empresasIndex'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class]);
$router->post('/empresas/crear', [AdminBusinessController::class, 'createEmpresa'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class]);
$router->post('/empresas/actualizar', [AdminBusinessController::class, 'updateEmpresa'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class]);

$router->get('/medicamentos', [AdminBusinessController::class, 'medicamentosIndex'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class, CompanyContextMiddleware::class]);
$router->post('/medicamentos/crear', [AdminBusinessController::class, 'createMedicamento'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class, CompanyContextMiddleware::class]);
$router->post('/medicamentos/actualizar', [AdminBusinessController::class, 'updateMedicamento'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class, CompanyContextMiddleware::class]);

$router->get('/laboratorios', [AdminBusinessController::class, 'laboratoriosIndex'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class, CompanyContextMiddleware::class]);
$router->post('/laboratorios/renombrar', [AdminBusinessController::class, 'renameLaboratorio'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class, CompanyContextMiddleware::class]);

$router->get('/tipos-examen', [AdminBusinessController::class, 'tiposExamenIndex'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class, CompanyContextMiddleware::class]);
$router->post('/tipos-examen/renombrar', [AdminBusinessController::class, 'renameTipoExamen'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, AdminMiddleware::class, CompanyContextMiddleware::class]);

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
$router->post('/diagnosticos/catalogo/actualizar', [DiagnosticoController::class, 'updateCatalogo'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->post('/diagnosticos/catalogo/eliminar', [DiagnosticoController::class, 'deleteCatalogo'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->post('/diagnosticos/asignar', [DiagnosticoController::class, 'asignar'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);

$router->get('/vacunas', [VacunaController::class, 'index'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->post('/vacunas/catalogo', [VacunaController::class, 'createCatalogo'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->post('/vacunas/catalogo/actualizar', [VacunaController::class, 'updateCatalogo'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->post('/vacunas/catalogo/eliminar', [VacunaController::class, 'deleteCatalogo'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
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

$router->get('/formulas', [FormulaController::class, 'index'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->get('/formulas/crear', [FormulaController::class, 'createForm'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->post('/formulas', [FormulaController::class, 'create'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->get('/formulas/editar', [FormulaController::class, 'edit'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->post('/formulas/actualizar', [FormulaController::class, 'update'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->post('/formulas/eliminar', [FormulaController::class, 'delete'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->post('/formulas/toggle', [FormulaController::class, 'toggle'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->get('/formulas/test', [FormulaController::class, 'test'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
$router->get('/formulas/variables/detectar', [FormulaController::class, 'detectVariables'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);

$router->get('/auditoria', [AuditoriaController::class, 'index'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);

$router->get('/timeline', [TimelineController::class, 'index'], [AuthMiddleware::class, RequirePasswordChangeMiddleware::class, CompanyContextMiddleware::class]);
