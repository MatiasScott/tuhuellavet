<?php

use App\Controllers\Admin\AdministracionController;
use App\Controllers\Admin\CatalogoController;

/** @var \App\Core\Router $router */

// ==========================================
// 1. GESTIÓN DE USUARIOS
// ==========================================
$router->get(
    '/admin/usuarios',
    [AdministracionController::class, 'users'],
    ['auth', 'environment', 'permission:usuarios.ver']
);
$router->post(
    '/admin/usuarios',
    [AdministracionController::class, 'storeUser'],
    ['auth', 'environment', 'permission:usuarios.crear']
);
$router->get(
    '/admin/usuarios/{id}/editar',
    [AdministracionController::class, 'editUser'],
    ['auth', 'environment', 'permission:usuarios.editar']
);
$router->post(
    '/admin/usuarios/{id}/editar',
    [AdministracionController::class, 'updateUser'],
    ['auth', 'environment', 'permission:usuarios.editar']
);
$router->post(
    '/admin/usuarios/{id}/reenviar-invitacion',
    [AdministracionController::class, 'resendUserInvitation'],
    ['auth', 'environment', 'permission:usuarios.editar']
);

// ==========================================
// 2. ROLES Y PERMISOS
// ==========================================
$router->get(
    '/admin/roles',
    [AdministracionController::class, 'roles'],
    ['auth', 'environment', 'permission:roles.ver']
);
$router->post(
    '/admin/roles',
    [AdministracionController::class, 'storeRole'],
    ['auth', 'environment', 'permission:roles.crear']
);
$router->post(
    '/admin/roles/{id}/permisos',
    [AdministracionController::class, 'savePermissions'],
    ['auth', 'environment', 'permission:permisos.editar']
);

// ==========================================
// 3. EMPRESAS Y ENTORNOS
// ==========================================
$router->get(
    '/admin/empresas',
    [AdministracionController::class, 'companies'],
    ['auth', 'environment', 'permission:empresas.ver']
);
$router->post(
    '/admin/empresas',
    [AdministracionController::class, 'storeCompany'],
    ['auth', 'environment', 'permission:empresas.crear']
);
$router->post(
    '/admin/entornos',
    [AdministracionController::class, 'storeEnvironment'],
    ['auth', 'environment', 'permission:empresas.crear']
);

// ==========================================
// 4. AUDITORÍA
// ==========================================
$router->get(
    '/admin/auditoria',
    [AdministracionController::class, 'audit'],
    ['auth', 'environment', 'permission:auditoria.ver']
);

// ==========================================
// 5. CATÁLOGOS
// ==========================================
$router->get(
    '/admin/catalogos',
    [CatalogoController::class, 'index'],
    ['auth', 'environment', 'permission:pacientes.editar']
);
$router->post(
    '/admin/catalogos/{type}',
    [CatalogoController::class, 'store'],
    ['auth', 'environment', 'permission:pacientes.editar']
);
$router->post(
    '/admin/catalogos/{type}/{id}/actualizar',
    [CatalogoController::class, 'update'],
    ['auth', 'environment', 'permission:pacientes.editar']
);
$router->post(
    '/admin/catalogos/{type}/{id}/estado',
    [CatalogoController::class, 'toggleStatus'],
    ['auth', 'environment', 'permission:pacientes.editar']
);
