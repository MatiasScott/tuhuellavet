<?php

namespace App\Controllers\Gestion;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\Billing;
use App\Models\Owner;
use App\Models\Patient;
use App\Services\BillingService;
use Throwable;

class FacturacionController extends Controller
{
    public function index(Request $r): void
    {
        $env = active_environment_id();
        $billing = new Billing();
        $ownerModel = new Owner();

        $this->view('facturacion/index', [
            'title' => 'Ventas',
            'sales' => $billing->sales($env),
            'documents' => $billing->documents($env),
            'owners' => $ownerModel->options(
                active_environment_id()
            ),

            'fiscalData' => $ownerModel->fiscalDataByEnvironment(
                active_environment_id()
            ),
            'patients' => (new Patient())->allByEnvironment($env),
            'products' => $billing->saleProducts($env),
            'services' => $billing->saleServices(),
            'paymentMethods' => $billing->paymentMethods(),
            'payments' => $billing->paymentsByEnvironment($env),
            'success' => Session::pullFlash('success'),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function store(Request $r): void
    {
        $this->go(
            $r,
            fn() => (new BillingService())->createSale(
                $r->all(),
                active_environment_id(),
                auth_id()
            ),
            'Venta registrada correctamente.'
        );
    }

    public function payment(
        Request $r,
        string $id
    ): void {
        $this->go(
            $r,
            fn() => (new BillingService())->registerPayment(
                (int) $id,
                $r->all(),
                active_environment_id(),
                auth_id()
            ),
            'Pago registrado correctamente.'
        );
    }


    public function cancelPayment(
        Request $r,
        string $id,
        string $paymentId
    ): void {
        $this->go(
            $r,
            fn() => (new BillingService())->cancelPayment(
                (int) $id,
                (int) $paymentId,
                (string) $r->input('motivo_anulacion'),
                active_environment_id(),
                auth_id()
            ),
            'Pago anulado correctamente.'
        );
    }

    public function cancel(Request $request, string $id): void
    {
        $this->go(
            $request,
            fn() => (new BillingService())->cancelSale(
                (int) $id,
                (string) $request->input('motivo_anulacion'),
                active_environment_id(),
                auth_id()
            ),
            'Venta anulada correctamente.'
        );
    }

    public function invoice(Request $r, string $id): void
    {
        $this->go(
            $r,
            fn() => (new BillingService())->queueInvoice(
                (int) $id,
                active_environment_id(),
                auth_id()
            ),
            'Factura encolada para Contífico.'
        );
    }

    private function go(Request $r, callable $f, string $ok): void
    {
        if (!Session::validateCsrf($r->input('_token'))) {
            http_response_code(419);
            return;
        }

        try {
            $f();
            Session::flash('success', $ok);
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect('/facturacion');
    }
}
