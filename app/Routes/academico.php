<?php

use App\Controllers\Academico\AcademicController;

/** @var \App\Core\Router $router */

// ==========================================
// 1. VISTA GENERAL
// ==========================================
$router->get(
    '/academico',
    [AcademicController::class, 'index'],
    ['auth', 'environment', 'permission:academico.ver']
);

// ==========================================
// 2. ESTRUCTURA ACADÉMICA (Períodos, Cursos, Asignaturas)
// ==========================================
$router->post(
    '/academico/periodos',
    [AcademicController::class, 'period'],
    ['auth', 'environment', 'permission:academico.crear']
);
$router->post(
    '/academico/cursos',
    [AcademicController::class, 'course'],
    ['auth', 'environment', 'permission:academico.crear']
);
$router->post(
    '/academico/asignaturas',
    [AcademicController::class, 'subject'],
    ['auth', 'environment', 'permission:academico.crear']
);

// ==========================================
// 3. MATRÍCULA Y ASIGNACIONES
// ==========================================
$router->post(
    '/academico/cursos/{id}/asignar',
    [AcademicController::class, 'enroll'],
    ['auth', 'environment', 'permission:academico.editar']
);

// ==========================================
// 4. GESTIÓN DE CASOS
// ==========================================
$router->post(
    '/academico/casos',
    [AcademicController::class, 'case'],
    ['auth', 'environment', 'permission:academico.crear']
);
