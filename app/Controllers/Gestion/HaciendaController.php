<?php

declare(strict_types=1);

namespace App\Controllers\Gestion;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\Catalog;
use App\Models\Inventory;
use App\Models\Patient;
use App\Services\HaciendaService;
use Throwable;

class HaciendaController extends Controller
{
    public function index(Request $request, string $module): void
    {
        $service = new HaciendaService();
        $env = active_environment_id();
        $filters = $request->all();
        try {
            $rows = $service->rows($module, $env, $filters);
        } catch (\RuntimeException $e) {
            http_response_code(400);
            echo e($e->getMessage());
            return;
        }
        $inventory = new Inventory();
        $catalog = new Catalog();
        $this->view('hacienda/index', [
            'title' => 'Hacienda Agusbella',
            'module' => $module, 'rows' => $rows, 'filters' => $filters,
            'metrics' => $service->metrics($module, $rows),
            'patients' => (new Patient())->options($env),
            'animalLots' => $service->rows('lotes', $env),
            'species' => $catalog->species(),
            'units' => $catalog->measurementUnits(),
            'products' => $inventory->products(),
            'inventories' => $inventory->inventories($env),
            'productLots' => $inventory->lots($env),
            'success' => Session::pullFlash('success'), 'error' => Session::pullFlash('error'),
        ]);
    }

    public function store(Request $request, string $module): void
    {
        $this->csrf($request);
        try {
            (new HaciendaService())->create($module, $request->all(), active_environment_id(), auth_id());
            Session::flash('success', $module === 'alimentacion' ? 'Racion planificada. El stock se descuenta solo al confirmar el consumo.' : 'Registro guardado.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/hacienda/' . $module);
    }

    public function confirm(Request $request, string $id): void
    {
        $this->csrf($request);
        try {
            (new HaciendaService())->confirmFeeding((int) $id, active_environment_id(), auth_id());
            Session::flash('success', 'Consumo confirmado e inventario actualizado.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect('/hacienda/alimentacion');
    }

    public function updatePlan(Request $request, string $id): void
    {
        $this->csrf($request);
        try {
            (new HaciendaService())->updatePlan((int) $id, $request->all(), active_environment_id(), auth_id());
            Session::flash('success', 'Racion planificada actualizada; no se ha descontado stock.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/hacienda/alimentacion');
    }

    public function updateLot(Request $request, string $id): void
    {
        $this->csrf($request);
        try {
            (new HaciendaService())->updateLot((int) $id, $request->all(), active_environment_id(), auth_id());
            Session::flash('success', 'Lote actualizado.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/hacienda/lotes/' . (int) $id);
    }

    public function lot(Request $request, string $id): void
    {
        $service = new HaciendaService();
        try {
            $lot = $service->lot((int) $id, active_environment_id());
        } catch (\RuntimeException $e) {
            http_response_code(404);
            echo e($e->getMessage());
            return;
        }
        $this->view('hacienda/lot', [
            'title' => $lot['nombre'], 'lot' => $lot,
            'history' => can('consultas.ver') ? $service->lotHistory((int) $id, active_environment_id()) : [],
            'success' => Session::pullFlash('success'), 'error' => Session::pullFlash('error'),
        ]);
    }

    public function lotExam(Request $request, string $id): void
    {
        $this->csrf($request);
        try {
            (new HaciendaService())->lotExam((int) $id, $request->all(), active_environment_id(), auth_id());
            Session::flash('success', 'Atencion clinica registrada en el historial del lote.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/hacienda/lotes/' . (int) $id);
    }

    public function csv(Request $request, string $module): void
    {
        $service = new HaciendaService();
        try {
            $rows = $service->rows($module, active_environment_id(), $request->all());
        } catch (\RuntimeException $e) {
            http_response_code(400);
            echo e($e->getMessage());
            return;
        }
        $headers = ['ID', 'Paciente ID', 'Lote animal ID', 'Tipo registro', 'Animal/lote', 'Fecha', 'Producto/alimento', 'Cantidad', 'Unidad', 'Estado', 'Frecuencia', 'Fecha inicio plan', 'Fecha fin', 'Inventario', 'Producto inventario', 'Lote inventario', 'Movimiento', 'Confirmado', 'Observaciones'];
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="hacienda-' . $module . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, $headers);
        foreach ($rows as $row) {
            $values = [
                $row['id'], $row['paciente_id'] ?? '', $row['lote_id'] ?? '', $row['tipo_registro'], $row['destino'],
                $row['fecha_reporte'],
                $row['tipo_producto'] ?? $row['tipo_alimento'],
                $row['cantidad'] ?? $row['cantidad_consumida'], $row['unidad'],
                $row['estado'] ?? '', $row['frecuencia'] ?? '', $row['fecha_inicio'] ?? '', $row['fecha_fin'] ?? '',
                $row['inventario'] ?? '', $row['producto'] ?? '', $row['numero_lote'] ?? '',
                $row['movimiento_id'] ?? '', $row['confirmado_at'] ?? '', $row['observaciones'] ?? '',
            ];
            fputcsv($output, array_map(static function (mixed $value): string {
                $text = (string) $value;
                return preg_match('/^[=+\-@\t\r\n]/', $text) ? "'" . $text : $text;
            }, $values));
        }
        fclose($output);
        exit;
    }

    private function csrf(Request $request): void
    {
        if (!Session::validateCsrf($request->input('_token'))) {
            http_response_code(419);
            exit('Sesion expirada.');
        }
    }
}
