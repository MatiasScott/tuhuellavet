<?php

namespace App\Controllers\Gestion;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\Billing;
use App\Models\Catalog;
use App\Models\Owner;
use App\Models\Patient;
use App\Services\BillingService;
use App\Services\ClinicalBillingService;
use Throwable;

class FacturacionController extends Controller
{
    public function index(Request $r): void
    {
        $env = active_environment_id();
        $billing = new Billing();
        $ownerModel = new Owner();
        $catalog = new Catalog();

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
            'clinicalConsumptions' => (new ClinicalBillingService())->pending($env),
            'services' => $billing->saleServices(),
            'paymentMethods' => $billing->paymentMethods(),
            'payments' => $billing->paymentsByEnvironment($env),
            'identificationTypes' => $catalog->identificationTypes(),
            'countries' => $catalog->countries(),
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

    public function assignFiscal(Request $r, string $id): void
    {
        $this->go($r, function () use ($r, $id): void {
            $consumerFinal = BillingService::parseConsumerFinal($r->input('consumidor_final', '0'));
            $fiscal = $r->input('datos_fiscales_id', '0');
            if (!can('ventas.ver') || !can('facturacion.facturar') || !ctype_digit($id) || !is_scalar($fiscal) || !ctype_digit((string)$fiscal)) {
                throw new \RuntimeException('Asignación fiscal no permitida.');
            }
            (new BillingService())->assignFiscalData((int)$id, (int)$fiscal, active_environment_id(), auth_id(), $consumerFinal);
        }, 'Datos fiscales asignados correctamente.');
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
            'Factura encolada para iConta.'
        );
    }

    public function retryInvoice(Request $request, string $id): void
    {
        $this->go(
            $request,
            function () use ($id): void {
                (new BillingService())->retryInvoice(
                    (int) $id,
                    active_environment_id(),
                    auth_id()
                );
            },
            'Reintento de factura procesado correctamente.'
        );
    }

    public function syncFiscal(Request $r, string $id): void
    {
        $this->go($r, function () use ($id): void {
            if (!can('facturacion.facturar') || !can('ventas.ver') || !ctype_digit($id)) {
                throw new \RuntimeException('Operación fiscal no permitida.');
            }
            $d = \App\Services\FiscalDocumentAccess::document((int)$id, (int)active_environment_id());
            if (!$d || !\App\Services\FiscalDocumentAccess::canSync($d)) {
                throw new \RuntimeException('Documento fiscal no disponible para sincronización.');
            }
            (new \App\Services\IContaService())->syncInvoiceSriStatus((int)$d['iconta_documento_id'], false, (int)active_environment_id());
        }, 'Estado fiscal consultado correctamente.');
    }

    public function downloadFiscal(Request $r, string $id, string $kind): void
    {
        if (!can('ventas.ver') || !can('facturacion.facturar') || !ctype_digit($id)) {
            http_response_code(403);
            return;
        }
        $d = \App\Services\FiscalDocumentAccess::document((int)$id, (int)active_environment_id());
        $file = $d ? \App\Services\FiscalDocumentAccess::file($d, $kind) : null;
        if (!$file) {
            http_response_code(404);
            return;
        }
        header('Content-Type: ' . $file['mime']);
        header('Content-Disposition: attachment; filename="' . $file['name'] . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($file['path']);
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
