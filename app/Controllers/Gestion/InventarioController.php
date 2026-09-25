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

        $this->view('inventario/index', [
            'title' => 'Inventario',

            'inventories' => $inventory->inventories($environmentId),
            'stock' => $inventory->stock($environmentId),
            'movements' => $inventory->movements($environmentId),
            'products' => $inventory->products(),
            'lots' => $inventory->lots($environmentId),

            'movementTypes' => $catalog->inventoryMovementTypes(),
            'productCategories' => $catalog->productCategories(),
            'units' => $catalog->measurementUnits(),
            'taxRates' => $catalog->taxRates(),

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
            (new InventoryService())->movement(
                $r->all(),
                active_environment_id(),
                auth_id()
            );

            Session::flash('success', 'Movimiento registrado.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect('/inventario?tab=movimientos');
    }
}
