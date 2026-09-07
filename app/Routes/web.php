<?php

use App\Controllers\Admin\DashboardController;
use App\Controllers\MediaController;
use App\Controllers\Clinica\PacienteController;
use App\Controllers\Clinica\PropietarioController;
use App\Controllers\Clinica\ConsultaController;
use App\Controllers\Clinica\TratamientoController;
use App\Controllers\Clinica\FormulaController;
use App\Controllers\Clinica\VacunacionController;
use App\Controllers\Clinica\DesparasitacionController;

/** @var \App\Core\Router $router */


/*
|--------------------------------------------------------------------------
| Página inicial
|--------------------------------------------------------------------------
*/

$router->get(
    '/',
    function () {

        if (!is_authenticated()) {
            header(
                'Location: '
                . url('/login')
            );

            exit;
        }

        if (!active_environment_id()) {
            header(
                'Location: '
                . url(
                    '/seleccionar-entorno'
                )
            );

            exit;
        }

        header(
            'Location: '
            . url('/dashboard')
        );

        exit;
    }
);


/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/

$router->get(
    '/dashboard',
    [
        DashboardController::class,
        'index',
    ],
    [
        'auth',
        'environment',
    ]
);


/*
|--------------------------------------------------------------------------
| Pacientes
|--------------------------------------------------------------------------
*/

$router->get(
    '/pacientes',
    [
        PacienteController::class,
        'index',
    ],
    [
        'auth',
        'environment',
        'permission:pacientes.ver',
    ]
);

$router->get(
    '/pacientes/{id}',
    [
        PacienteController::class,
        'show',
    ],
    [
        'auth',
        'environment',
        'permission:pacientes.ver',
    ]
);


$router->post(
    '/pacientes',
    [
        PacienteController::class,
        'store',
    ],
    [
        'auth',
        'environment',
        'permission:pacientes.crear',
    ]
);


$router->put(
    '/pacientes/{id}',
    [
        PacienteController::class,
        'update',
    ],
    [
        'auth',
        'environment',
        'permission:pacientes.editar',
    ]
);


$router->post(
    '/pacientes/{id}/peso',
    [
        PacienteController::class,
        'addWeight',
    ],
    [
        'auth',
        'environment',
        'permission:pacientes.editar',
    ]
);


$router->delete(
    '/pacientes/{id}',
    [
        PacienteController::class,
        'destroy',
    ],
    [
        'auth',
        'environment',
        'permission:pacientes.eliminar',
    ]
);

$router->get(
    '/media/pacientes/{filename}',
    [
        MediaController::class,
        'patientPhoto',
    ],
    [
        'auth',
    ]
);

/*
|--------------------------------------------------------------------------
| Propietarios
|--------------------------------------------------------------------------
*/

$router->get(
    '/propietarios',
    [
        PropietarioController::class,
        'index',
    ],
    [
        'auth',
        'environment',
        'permission:propietarios.ver',
    ]
);

$router->post(
    '/propietarios',
    [
        PropietarioController::class,
        'store',
    ],
    [
        'auth',
        'environment',
        'permission:propietarios.crear',
    ]
);

$router->put(
    '/propietarios/{id}',
    [
        PropietarioController::class,
        'update',
    ],
    [
        'auth',
        'environment',
        'permission:propietarios.editar',
    ]
);

$router->delete(
    '/propietarios/{id}',
    [
        PropietarioController::class,
        'destroy',
    ],
    [
        'auth',
        'environment',
        'permission:propietarios.eliminar',
    ]
);

/*
|--------------------------------------------------------------------------
| Consultas
|--------------------------------------------------------------------------
*/
$router->get(
    '/consultas',
    [
        ConsultaController::class,
        'index',
    ],
    [
        'auth',
        'environment',
        'permission:consultas.ver',
    ]
);


$router->post(
    '/consultas',
    [
        ConsultaController::class,
        'store',
    ],
    [
        'auth',
        'environment',
        'permission:consultas.crear',
    ]
);


$router->get(
    '/consultas/{id}',
    [
        ConsultaController::class,
        'show',
    ],
    [
        'auth',
        'environment',
        'permission:consultas.ver',
    ]
);

/*
|--------------------------------------------------------------------------
| Tratamientos
|--------------------------------------------------------------------------
*/
$router->post(
    '/consultas/{id}/tratamientos',
    [
        TratamientoController::class,
        'store',
    ],
    [
        'auth',
        'environment',
        'permission:tratamientos.crear',
    ]
);


$router->post(
    '/consultas/{id}/tratamientos/medicamentos/{medicationId}/aplicar',
    [
        TratamientoController::class,
        'applyMedication',
    ],
    [
        'auth',
        'environment',
        'permission:tratamientos.editar',
    ]
);


$router->get(
    '/formulas/version/{id}/variables',
    [
        FormulaController::class,
        'variables',
    ],
    [
        'auth',
        'environment',
        'permission:formulas.calcular',
    ]
);


$router->post(
    '/formulas/calcular',
    [
        FormulaController::class,
        'calculate',
    ],
    [
        'auth',
        'environment',
        'permission:formulas.calcular',
    ]
);

/*
|--------------------------------------------------------------------------
| Vacunación
|--------------------------------------------------------------------------
*/

$router->get(
    '/vacunas',
    [
        VacunacionController::class,
        'index',
    ],
    [
        'auth',
        'environment',
        'permission:vacunas.ver',
    ]
);


$router->post(
    '/vacunas',
    [
        VacunacionController::class,
        'store',
    ],
    [
        'auth',
        'environment',
        'permission:vacunas.crear',
    ]
);


/*
|--------------------------------------------------------------------------
| Desparasitación
|--------------------------------------------------------------------------
*/

$router->get(
    '/desparasitaciones',
    [
        DesparasitacionController::class,
        'index',
    ],
    [
        'auth',
        'environment',
        'permission:desparasitacion.ver',
    ]
);


$router->post(
    '/desparasitaciones',
    [
        DesparasitacionController::class,
        'store',
    ],
    [
        'auth',
        'environment',
        'permission:desparasitacion.crear',
    ]
);