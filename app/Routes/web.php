<?php

use App\Controllers\Admin\DashboardController;
use App\Controllers\Clinica\CirugiaController;
use App\Controllers\Clinica\ConsultaController;
use App\Controllers\Clinica\DesparasitacionController;
use App\Controllers\Clinica\FormulaController;
use App\Controllers\Clinica\HospitalizacionController;
use App\Controllers\Clinica\LaboratorioController;
use App\Controllers\Clinica\PacienteController;
use App\Controllers\Clinica\PropietarioController;
use App\Controllers\Clinica\TratamientoController;
use App\Controllers\Clinica\VacunacionController;
use App\Controllers\Gestion\CitaController;
use App\Controllers\Gestion\FacturacionController;
use App\Controllers\Gestion\FormulaAdminController;
use App\Controllers\Gestion\InventarioController;
use App\Controllers\Gestion\NotificacionController;
use App\Controllers\Gestion\ReporteController;
use App\Controllers\MediaController;

/** @var \App\Core\Router $router */

/*
|--------------------------------------------------------------------------
| RUTAS BASE & DASHBOARD
|--------------------------------------------------------------------------
*/
$router->get('/', function () {
    if (!is_authenticated()) {
        header('Location: ' . url('/login'));
        exit;
    }
    if (!active_environment_id()) {
        header('Location: ' . url('/seleccionar-entorno'));
        exit;
    }
    header('Location: ' . url('/dashboard'));
    exit;
});

$router->get('/dashboard', [DashboardController::class, 'index'], ['auth', 'environment']);

/*
|--------------------------------------------------------------------------
| MÓDULO: MEDIA / ARCHIVOS
|--------------------------------------------------------------------------
*/
$router->get(
    '/media/pacientes/{id}',
    [MediaController::class, 'patientPhoto'],
    ['auth', 'environment']
);
$router->get(
    '/media/documentos/{id}',
    [MediaController::class, 'document'],
    ['auth']
);

/*
|--------------------------------------------------------------------------
| MÓDULO: PACIENTES
|--------------------------------------------------------------------------
*/
$router->get(
    '/pacientes',
    [PacienteController::class, 'index'],
    ['auth', 'environment', 'permission:pacientes.ver']
);
$router->post(
    '/pacientes',
    [PacienteController::class, 'store'],
    ['auth', 'environment', 'permission:pacientes.crear']
);
$router->get(
    '/pacientes/{id}',
    [PacienteController::class, 'show'],
    ['auth', 'environment', 'permission:pacientes.ver']
);
$router->put(
    '/pacientes/{id}',
    [PacienteController::class, 'update'],
    ['auth', 'environment', 'permission:pacientes.editar']
);
$router->delete(
    '/pacientes/{id}',
    [PacienteController::class, 'destroy'],
    ['auth', 'environment', 'permission:pacientes.eliminar']
);
$router->post(
    '/pacientes/{id}/peso',
    [PacienteController::class, 'addWeight'],
    ['auth', 'environment', 'permission:pacientes.editar']
);

/*
|--------------------------------------------------------------------------
| MÓDULO: PROPIETARIOS
|--------------------------------------------------------------------------
*/
$router->get(
    '/propietarios',
    [PropietarioController::class, 'index'],
    ['auth', 'environment', 'permission:propietarios.ver']
);
$router->post(
    '/propietarios',
    [PropietarioController::class, 'store'],
    ['auth', 'environment', 'permission:propietarios.crear']
);
$router->get(
    '/propietarios/validar',
    [PropietarioController::class, 'validateField'],
    ['auth', 'environment', 'permission:propietarios.ver']
);
$router->get(
    '/propietarios/{id}',
    [PropietarioController::class, 'show'],
    ['auth', 'environment', 'permission:propietarios.ver']
);
$router->put(
    '/propietarios/{id}',
    [PropietarioController::class, 'update'],
    ['auth', 'environment', 'permission:propietarios.editar']
);
$router->delete(
    '/propietarios/{id}',
    [PropietarioController::class, 'destroy'],
    ['auth', 'environment', 'permission:propietarios.eliminar']
);

/*
|--------------------------------------------------------------------------
| MÓDULO: CONSULTAS & TRATAMIENTOS
|--------------------------------------------------------------------------
*/
$router->get(
    '/consultas',
    [ConsultaController::class, 'index'],
    ['auth', 'environment', 'permission:consultas.ver']
);
$router->post(
    '/consultas',
    [ConsultaController::class, 'store'],
    ['auth', 'environment', 'permission:consultas.crear']
);
$router->get(
    '/consultas/{id}',
    [ConsultaController::class, 'show'],
    ['auth', 'environment', 'permission:consultas.ver']
);
$router->put(
    '/consultas/{id}',
    [ConsultaController::class, 'update'],
    ['auth', 'environment', 'permission:consultas.editar']
);
$router->put(
    '/consultas/{id}/examen',
    [ConsultaController::class, 'updateExam'],
    ['auth', 'environment', 'permission:consultas.editar']
);
$router->post(
    '/consultas/{id}/diagnosticos',
    [ConsultaController::class, 'addDiagnosis'],
    ['auth', 'environment', 'permission:consultas.editar']
);

// Tratamientos de consulta
$router->post(
    '/consultas/{id}/tratamientos',
    [TratamientoController::class, 'store'],
    ['auth', 'environment', 'permission:tratamientos.crear']
);
$router->post(
    '/consultas/{id}/tratamientos/medicamentos/{medicationId}/aplicar',
    [TratamientoController::class, 'applyMedication'],
    ['auth', 'environment', 'permission:tratamientos.editar']
);

/*
|--------------------------------------------------------------------------
| MÓDULO: PROCEDIMIENTOS CLÍNICOS
|--------------------------------------------------------------------------
*/
// Vacunación
$router->get(
    '/vacunas',
    [VacunacionController::class, 'index'],
    ['auth', 'environment', 'permission:vacunas.ver']
);
$router->post(
    '/vacunas',
    [VacunacionController::class, 'store'],
    ['auth', 'environment', 'permission:vacunas.crear']
);
$router->post(
    '/vacunas/{id}/actualizar',
    [VacunacionController::class, 'update'],
    ['auth', 'environment', 'permission:vacunas.editar']
);

$router->post(
    '/vacunas/{id}/anular',
    [VacunacionController::class, 'cancel'],
    ['auth', 'environment', 'permission:vacunas.eliminar']
);

// Desparasitación
$router->get(
    '/desparasitaciones',
    [DesparasitacionController::class, 'index'],
    ['auth', 'environment', 'permission:desparasitacion.ver']
);
$router->post(
    '/desparasitaciones',
    [DesparasitacionController::class, 'store'],
    ['auth', 'environment', 'permission:desparasitacion.crear']
);

// Hospitalización
$router->get(
    '/hospitalizaciones',
    [HospitalizacionController::class, 'index'],
    ['auth', 'environment', 'permission:hospitalizacion.ver']
);
$router->post(
    '/hospitalizaciones',
    [HospitalizacionController::class, 'store'],
    ['auth', 'environment', 'permission:hospitalizacion.crear']
);
$router->get(
    '/hospitalizaciones/{id}',
    [HospitalizacionController::class, 'show'],
    ['auth', 'environment', 'permission:hospitalizacion.ver']
);
$router->post(
    '/hospitalizaciones/{id}/signos',
    [HospitalizacionController::class, 'signs'],
    ['auth', 'environment', 'permission:hospitalizacion.editar']
);
$router->post(
    '/hospitalizaciones/{id}/evoluciones',
    [HospitalizacionController::class, 'evolution'],
    ['auth', 'environment', 'permission:hospitalizacion.editar']
);
$router->post(
    '/hospitalizaciones/{id}/fluidoterapia',
    [HospitalizacionController::class, 'fluid'],
    ['auth', 'environment', 'permission:hospitalizacion.editar']
);
$router->post(
    '/hospitalizaciones/{id}/tratamientos',
    [HospitalizacionController::class, 'treatment'],
    ['auth', 'environment', 'permission:tratamientos.crear']
);
$router->post(
    '/hospitalizaciones/{id}/medicamentos/{medicationId}/aplicar',
    [HospitalizacionController::class, 'apply'],
    ['auth', 'environment', 'permission:tratamientos.editar']
);
$router->post(
    '/hospitalizaciones/{id}/cerrar',
    [HospitalizacionController::class, 'close'],
    ['auth', 'environment', 'permission:hospitalizacion.editar']
);

// Cirugía
$router->get(
    '/cirugias',
    [CirugiaController::class, 'index'],
    ['auth', 'environment', 'permission:cirugias.ver']
);
$router->post(
    '/cirugias',
    [CirugiaController::class, 'store'],
    ['auth', 'environment', 'permission:cirugias.crear']
);
$router->get(
    '/cirugias/archivo',
    [CirugiaController::class, 'viewFile'],
    ['auth', 'environment', 'permission:cirugias.ver']
);
$router->get(
    '/cirugias/{id}',
    [CirugiaController::class, 'show'],
    ['auth', 'environment', 'permission:cirugias.ver']
);
$router->post(
    '/cirugias/{id}/equipo',
    [CirugiaController::class, 'team'],
    ['auth', 'environment', 'permission:cirugias.editar']
);
$router->post(
    '/cirugias/{id}/equipo/eliminar',
    [CirugiaController::class, 'removeTeam'],
    ['auth', 'environment', 'permission:cirugias.editar']
);
$router->post(
    '/cirugias/{id}/evoluciones',
    [CirugiaController::class, 'evolution'],
    ['auth', 'environment', 'permission:cirugias.editar']
);

// Laboratorio
$router->get(
    '/laboratorio',
    [LaboratorioController::class, 'index'],
    ['auth', 'environment', 'permission:laboratorio.ver']
);
$router->post(
    '/laboratorio',
    [LaboratorioController::class, 'store'],
    ['auth', 'environment', 'permission:laboratorio.crear']
);
$router->get(
    '/laboratorio/archivo',
    [LaboratorioController::class, 'viewFile'],
    ['auth', 'environment', 'permission:laboratorio.ver']
);
$router->post(
    '/laboratorio/editar',
    [LaboratorioController::class, 'update'],
    ['auth', 'environment', 'permission:laboratorio.editar']
);
$router->post(
    '/laboratorio/eliminar',
    [LaboratorioController::class, 'delete'],
    ['auth', 'environment', 'permission:laboratorio.eliminar']
);

/*
|--------------------------------------------------------------------------
| MÓDULO: FÓRMULAS
|--------------------------------------------------------------------------
*/
// Cálculo (Operativo)
$router->get(
    '/formulas/version/{id}/variables',
    [FormulaController::class, 'variables'],
    ['auth', 'environment', 'permission:formulas.calcular']
);
$router->post(
    '/formulas/calcular',
    [FormulaController::class, 'calculate'],
    ['auth', 'environment', 'permission:formulas.calcular']
);

// Gestión (Administración)
$router->get(
    '/formulas',
    [FormulaAdminController::class, 'index'],
    ['auth', 'environment', 'permission:formulas.ver']
);
$router->post(
    '/formulas',
    [FormulaAdminController::class, 'store'],
    ['auth', 'environment', 'permission:formulas.crear']
);
$router->post(
    '/formulas/{id}/versiones',
    [FormulaAdminController::class, 'version'],
    ['auth', 'environment', 'permission:formulas.crear']
);
$router->post(
    '/formulas/versiones/{id}/publicar',
    [FormulaAdminController::class, 'publish'],
    ['auth', 'environment', 'permission:formulas.publicar']
);
$router->post(
    '/formulas/probar',
    [FormulaAdminController::class, 'test'],
    ['auth', 'environment', 'permission:formulas.calcular']
);
$router->get(
    '/formulas/versiones/{id}/prueba',
    [FormulaAdminController::class, 'testData'],
    ['auth', 'environment', 'permission:formulas.calcular']
);
$router->post(
    '/formulas/versiones/{id}/editar',
    [FormulaAdminController::class, 'updateDraft'],
    ['auth', 'environment', 'permission:formulas.editar']
);

/*
|--------------------------------------------------------------------------
| MÓDULO: GESTIÓN & OPERACIÓN
|--------------------------------------------------------------------------
*/
// Inventario
$router->get(
    '/inventario',
    [InventarioController::class, 'index'],
    ['auth', 'environment', 'permission:inventario.ver']
);
$router->post(
    '/inventario/productos',
    [InventarioController::class, 'product'],
    ['auth', 'environment', 'permission:inventario.crear']
);
$router->post(
    '/inventario/lotes',
    [InventarioController::class, 'lot'],
    ['auth', 'environment', 'permission:inventario.crear']
);
$router->post(
    '/inventario/movimientos',
    [InventarioController::class, 'movement'],
    ['auth', 'environment', 'permission:inventario.crear']
);

// Citas
$router->get(
    '/citas',
    [CitaController::class, 'index'],
    ['auth', 'environment', 'permission:citas.ver']
);
$router->post(
    '/citas',
    [CitaController::class, 'store'],
    ['auth', 'environment', 'permission:citas.crear']
);

// Facturación
$router->get(
    '/facturacion',
    [FacturacionController::class, 'index'],
    ['auth', 'environment', 'permission:ventas.ver']
);
$router->post(
    '/facturacion',
    [FacturacionController::class, 'store'],
    ['auth', 'environment', 'permission:ventas.crear']
);
$router->post(
    '/facturacion/{id}/emitir',
    [FacturacionController::class, 'invoice'],
    ['auth', 'environment', 'permission:facturacion.facturar']
);

// Reportes
$router->get(
    '/reportes',
    [ReporteController::class, 'index'],
    ['auth', 'environment', 'permission:reportes.ver']
);
$router->get(
    '/reportes/pacientes.csv',
    [ReporteController::class, 'csv'],
    ['auth', 'environment', 'permission:reportes.exportar']
);
$router->get(
    '/reportes/pacientes.xlsx',
    [ReporteController::class, 'xlsx'],
    ['auth', 'environment', 'permission:reportes.exportar']
);
$router->get(
    '/reportes/pacientes.pdf',
    [ReporteController::class, 'pdf'],
    ['auth', 'environment', 'permission:reportes.exportar']
);

// Notificaciones
$router->get(
    '/notificaciones',
    [NotificacionController::class, 'index'],
    ['auth', 'environment', 'permission:notificaciones.ver']
);
$router->post(
    '/notificaciones/procesar',
    [NotificacionController::class, 'process'],
    ['auth', 'environment', 'permission:notificaciones.editar']
);
