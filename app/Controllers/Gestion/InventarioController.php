<?php

namespace App\Controllers\Gestion;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\Inventory;
use App\Models\Catalog;
use App\Services\InventoryService;
use Throwable;

class InventarioController extends Controller
{
    public function index(Request $r): void
    {
        $environmentId = active_environment_id();

        (new InventoryService())->ensureDefault($environmentId);

        $inventory = new Inventory();
        $catalog = new Catalog();

        $farm = \App\Services\EnvironmentModules::isFarm(active_environment());
        $this->view($farm ? 'hacienda/inventory' : 'inventario/index', [
            'title' => 'Inventario',

            'inventories' => $inventory->inventories($environmentId),
            'stock' => $inventory->stock($environmentId),
            'movementBalances' => $farm ? [] : $inventory->movementBalances($environmentId),
            'movements' => $inventory->movements($environmentId),
            'products' => $inventory->products(),
            'lots' => $inventory->lots($environmentId),

            'movementTypes' => $farm ? array_values(array_filter($catalog->inventoryMovementTypes(), static fn(array $type): bool => in_array($type['codigo'], ['ENTRADA', 'SALIDA', 'AJUSTE_POSITIVO', 'AJUSTE_NEGATIVO'], true))) : $catalog->inventoryMovementTypes(),
            'productCategories' => $catalog->productCategories(),
            'units' => $catalog->measurementUnits(),
            'vaccines' => $farm ? [] : $catalog->vaccines(),
            'drugPresentations' => $farm ? [] : $catalog->drugPresentations(),
            'taxRates' => $farm ? [] : $catalog->taxRates(),

            'success' => Session::pullFlash('success'),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function product(Request $r): void
    {
        if (!Session::validateCsrf($r->input('_token'))) {
            http_response_code(419);
            return;
        }

        try {
            (new InventoryService())->createProduct(
                $r->all(),
                active_environment_id(),
                auth_id()
            );

            Session::flash('success', 'Producto creado.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect('/inventario?tab=productos');
    }

    public function updateProduct(
        Request $r,
        int $id
    ): void {
        if (!Session::validateCsrf($r->input('_token'))) {
            http_response_code(419);
            return;
        }

        try {
            (new InventoryService())->updateProduct(
                $id,
                $r->all(),
                active_environment_id(),
                auth_id()
            );

            Session::flash(
                'success',
                'Producto actualizado correctamente.'
            );
        } catch (Throwable $e) {
            Session::flash(
                'error',
                $e->getMessage()
            );
        }

        $this->redirect(
            '/inventario?tab=productos'
        );
    }

    /**
     * Registrar un lote de producto.
     */
    public function lot(Request $r): void
    {
        if (!Session::validateCsrf($r->input('_token'))) {
            http_response_code(419);
            return;
        }

        try {
            $lotId = (new InventoryService())->createLot(
                $r->all(),
                active_environment_id(),
                auth_id()
            );

            Session::flash(
                'success',
                'Lote registrado correctamente. ID: ' . $lotId
            );
        } catch (Throwable $e) {

            Session::flash(
                'error',
                $e->getMessage()
            );
        }

        $this->redirect('/inventario?tab=productos');
    }

    public function movement(Request $r): void
    {
        if (!Session::validateCsrf($r->input('_token'))) {
            http_response_code(419);
            return;
        }

        try {
            if (\App\Services\EnvironmentModules::isFarm(active_environment())) {
                $types = (new Catalog())->inventoryMovementTypes();
                $type = array_values(array_filter($types, static fn(array $row): bool => (int) $row['id'] === (int) $r->input('tipo_movimiento_id')));
                if (!$type || !in_array($type[0]['codigo'], ['ENTRADA', 'SALIDA', 'AJUSTE_POSITIVO', 'AJUSTE_NEGATIVO'], true) || $r->input('referencia_tipo') || $r->input('referencia_id')) {
                    throw new \RuntimeException('Utilice movimientos operativos; los consumos se confirman desde Alimentacion.');
                }
            }
            $data = $r->all();
            $service = new InventoryService();
            if ($r->input('tipo_movimiento_id') === 'transfer') {
                if (\App\Services\EnvironmentModules::isFarm(active_environment())) {
                    throw new \RuntimeException('Transferencia no habilitada en este entorno.');
                }
                $service->transfer([
                    'inventario_origen_id' => $data['inventario_id'] ?? null,
                    'inventario_destino_id' => $data['inventario_destino_id'] ?? null,
                    'producto_id' => $data['producto_id'] ?? null,
                    'lote_id' => $data['lote_id'] ?? null,
                    'cantidad' => $data['cantidad'] ?? null,
                    'observaciones' => $data['observaciones'] ?? '',
                ], active_environment_id(), auth_id());
                Session::flash('success', 'Transferencia registrada correctamente.');
            } else {
                $types = (new Catalog())->inventoryMovementTypes();
                $allowed = array_filter($types, static fn(array $type): bool =>
                (int) $type['id'] === (int) ($data['tipo_movimiento_id'] ?? 0)
                    && in_array($type['codigo'], \App\Services\EnvironmentModules::isFarm(active_environment())
                        ? ['ENTRADA', 'SALIDA', 'AJUSTE_POSITIVO', 'AJUSTE_NEGATIVO']
                        : ['ENTRADA', 'SALIDA', 'AJUSTE_POSITIVO', 'AJUSTE_NEGATIVO', 'CONSUMO_CLINICO'], true));
                if (!$allowed) {
                    throw new \RuntimeException('Tipo de movimiento manual no permitido.');
                }
                unset($data['referencia_tipo'], $data['referencia_id']);
                $service->movement($data, active_environment_id(), auth_id());
                Session::flash('success', 'Movimiento registrado.');
            }
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect('/inventario?tab=movimientos');
    }
}
