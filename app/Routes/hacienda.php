<?php

use App\Controllers\Gestion\HaciendaController;
use App\Core\Request;

/** @var \App\Core\Router $router */
foreach (['lotes' => 'lotes_animales', 'produccion' => 'produccion', 'genealogia' => 'genealogia', 'alimentacion' => 'alimentacion'] as $path => $permission) {
    $router->get('/hacienda/' . $path, static function (Request $r) use ($path): void {
        (new HaciendaController())->index($r, $path);
    }, ['auth', 'environment', 'permission:' . $permission . '.ver']);
    $router->post('/hacienda/' . $path, static function (Request $r) use ($path): void {
        (new HaciendaController())->store($r, $path);
    }, ['auth', 'environment', 'permission:' . $permission . ($path === 'genealogia' ? '.editar' : '.crear')]);
}
$router->get('/hacienda/lotes/{id}', [HaciendaController::class, 'lot'], ['auth', 'environment', 'permission:lotes_animales.ver']);
$router->post('/hacienda/lotes/{id}/examenes', [HaciendaController::class, 'lotExam'], ['auth', 'environment', 'permission:lotes_animales.ver', 'permission:consultas.crear']);
$router->post('/hacienda/alimentacion/{id}/confirmar', [HaciendaController::class, 'confirm'], ['auth', 'environment', 'permission:alimentacion.editar', 'permission:inventario.editar']);
$router->post('/hacienda/alimentacion/{id}/actualizar', [HaciendaController::class, 'updatePlan'], ['auth', 'environment', 'permission:alimentacion.editar']);
$router->post('/hacienda/lotes/{id}/actualizar', [HaciendaController::class, 'updateLot'], ['auth', 'environment', 'permission:lotes_animales.editar']);
foreach (['produccion', 'alimentacion'] as $path) {
    $router->get('/hacienda/' . $path . '/reporte.csv', static function (Request $r) use ($path): void {
        (new HaciendaController())->csv($r, $path);
    }, ['auth', 'environment', 'permission:reportes.exportar', 'permission:' . $path . '.ver']);
}
