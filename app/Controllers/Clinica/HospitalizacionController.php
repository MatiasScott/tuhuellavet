<?php

namespace App\Controllers\Clinica;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\Hospitalization;
use App\Models\Patient;
use App\Models\Catalog;
use App\Models\Formula;
use App\Models\Treatment;
use App\Services\HospitalizationService;
use App\Services\TreatmentService;
use Throwable;

class HospitalizacionController extends Controller
{
    public function index(Request $r): void
    {
        $q = trim((string)$r->input('q', ''));
        $m = new Hospitalization();
        $this->view('hospitalizaciones/index', ['title' => 'Hospitalización', 'hospitalizations' => $m->list(active_environment_id(), $q), 'patients' => (new Patient())->allByEnvironment(active_environment_id()), 'statuses' => $m->statuses(), 'search' => $q, 'success' => Session::pullFlash('success'), 'error' => Session::pullFlash('error')]);
    }

    public function show(Request $r, string $id): void
    {
        $m = new Hospitalization();
        $h = $m->find((int)$id, active_environment_id());
        if (!$h) {
            http_response_code(404);
            return;
        }
        $c = new Catalog();
        $this->view('hospitalizaciones/show', ['title' => 'Hospitalización · ' . $h['paciente'], 'hospitalization' => $h, 'signs' => $m->signs((int)$id), 'evolutions' => $m->evolutions((int)$id), 'fluidTherapies' => $m->fluids((int)$id), 'maintenanceCategories' => $m->maintenance((int)$h['especie_id']), 'treatments' => (new Treatment())->byEvent((int)$id, active_environment_id()), 'formulas' => (new Formula())->publishedForEnvironment(active_environment_id(), (int)$h['especie_id']), 'treatmentTypes' => $c->treatmentTypes(), 'drugs' => $c->drugs(), 'presentations' => $c->drugPresentations(), 'routes' => $c->administrationRoutes(), 'frequencies' => $c->administrationFrequencies(), 'units' => $c->measurementUnits(), 'timeUnits' => $c->timeUnits(), 'success' => Session::pullFlash('success'), 'error' => Session::pullFlash('error')]);
    }

    public function store(Request $r): void
    {
        $this->go($r, fn() => (new HospitalizationService())->create($r->all(), active_environment_id(), auth_id()), '/hospitalizaciones', 'Hospitalización registrada.', true);
    }

    public function signs(Request $r, string $id): void
    {
        $this->go($r, fn() => (new HospitalizationService())->addSigns((int)$id, $r->all(), active_environment_id(), auth_id()), '/hospitalizaciones/' . $id . '#signos', 'Control registrado.');
    }

    public function evolution(Request $r, string $id): void
    {
        $this->go($r, fn() => (new HospitalizationService())->addEvolution((int)$id, $r->all(), active_environment_id(), auth_id()), '/hospitalizaciones/' . $id . '#evoluciones', 'Evolución registrada.');
    }

    public function fluid(Request $r, string $id): void
    {
        $this->go($r, fn() => (new HospitalizationService())->addFluid((int)$id, $r->all(), active_environment_id(), auth_id()), '/hospitalizaciones/' . $id . '#fluidoterapia', 'Fluidoterapia registrada.');
    }

    public function treatment(Request $r, string $id): void
    {
        $this->go($r, fn() => (new TreatmentService())->create((int)$id, $r->all(), active_environment_id(), auth_id()), '/hospitalizaciones/' . $id . '#tratamientos', 'Tratamiento registrado.');
    }

    public function apply(
        Request $r,
        string $id,
        string $med
    ): void {
        $this->go(
            $r,
            function () use ($r, $med): void {
                (new TreatmentService())
                    ->addApplication(
                        (int) $med,
                        $r->input('cantidad_aplicada'),
                        $r->input('unidad_id'),
                        $r->input('observaciones'),
                        active_environment_id(),
                        auth_id()
                    );
            },
            '/hospitalizaciones/' . $id,
            'Aplicación registrada correctamente.'
        );
    }

    public function close(Request $r, string $id): void
    {
        $this->go($r, fn() => (new HospitalizationService())->close((int)$id, $r->all(), active_environment_id(), auth_id()), '/hospitalizaciones/' . $id, 'Hospitalización cerrada.');
    }

    private function go(Request $r, callable $fn, string $back, string $ok, bool $new = false): void
    {
        if (!Session::validateCsrf($r->input('_token'))) {
            http_response_code(419);
            return;
        }
        try {
            $res = $fn();
            Session::flash('success', $ok);
            if ($new && is_int($res)) $back = '/hospitalizaciones/' . $res;
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($back);
    }

    public function cancelApplication(
        Request $r,
        string $id,
        string $application
    ): void {
        $this->go(
            $r,
            function () use ($r, $application): void {
                (new TreatmentService())
                    ->cancelApplication(
                        (int)$application,
                        trim(
                            (string)$r->input('motivo')
                        ),
                        active_environment_id(),
                        auth_id()
                    );
            },
            '/hospitalizaciones/' . $id,
            'Aplicación anulada correctamente.'
        );
    }
}
