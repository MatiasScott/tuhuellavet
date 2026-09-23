<?php

use App\Controllers\Cliente\PortalController;

/** @var \App\Core\Router $router */

// Middleware base para el portal del cliente
$clientMiddleware = ['auth', 'environment', 'role:CLIENTE'];

// ==========================================
// 1. DASHBOARD / INICIO
// ==========================================
$router->get(
    '/cliente',
    [PortalController::class, 'index'],
    $clientMiddleware
);

// ==========================================
// 2. GESTIÓN DE PACIENTES
// ==========================================
$router->get(
    '/cliente/pacientes/{id}',
    [PortalController::class, 'patient'],
    $clientMiddleware
);
$router->post(
    '/cliente/pacientes/{id}/foto',
    [PortalController::class, 'photo'],
    $clientMiddleware
);
