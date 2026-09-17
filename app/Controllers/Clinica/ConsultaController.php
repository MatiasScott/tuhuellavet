<?php

namespace App\Controllers\Clinica;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;

use App\Models\Consultation;
use App\Models\Patient;
use App\Models\Treatment;
use App\Models\Catalog;
use App\Models\Formula;

use App\Services\ConsultationService;

use Throwable;

class ConsultaController extends Controller
{
    public function index(
        Request $request
    ): void {
        $search = trim(
            (string)
            $request->input(
                'q',
                ''
            )
        );

        $this->view(
            'consultas/index',
            [
                'title'
                => 'Consultas externas',

                'consultations'
                => (new Consultation())
                    ->listByEnvironment(
                        active_environment_id(),
                        $search
                    ),

                'patients'
                => (new Patient())
                    ->allByEnvironment(
                        active_environment_id()
                    ),

                'search'
                => $search,

                'success'
                => Session::pullFlash(
                    'success'
                ),

                'error'
                => Session::pullFlash(
                    'error'
                ),
            ]
        );
    }


    public function store(
        Request $request
    ): void {
        if (
            !Session::validateCsrf(
                $request->input('_token')
            )
        ) {
            http_response_code(419);

            return;
        }

        try {
            $eventId
                = (new ConsultationService())
                ->create(
                    $request->all(),
                    active_environment_id(),
                    auth_id()
                );

            Session::flash(
                'success',
                'Consulta registrada correctamente.'
            );

            $this->redirect(
                '/consultas/'
                    . $eventId
            );
        } catch (Throwable $e) {
            Session::flash(
                'error',
                $e->getMessage()
            );

            $this->redirect(
                '/consultas'
            );
        }
    }


    public function show(
        Request $request,
        string $id
    ): void {
        $consultation
            = (new Consultation())
            ->find(
                (int) $id,
                active_environment_id()
            );

        if (!$consultation) {
            http_response_code(404);

            echo 'Consulta no encontrada.';

            return;
        }

        $catalog
            = new Catalog();

        $formulas
            = (new Formula())
            ->publishedForEnvironment(
                active_environment_id()
            );

        $this->view(
            'consultas/show',
            [
                'title'
                => 'Consulta clínica',

                'consultation'
                => $consultation,

                'diagnoses'
                => (new \App\Models\ClinicalHistory())
                    ->diagnoses(
                        (int) $id
                    ),

                'treatments'
                => (new Treatment())
                    ->byEvent(
                        (int) $id,
                        active_environment_id()
                    ),

                'treatmentTypes'
                => $catalog
                    ->treatmentTypes(),

                'drugs'
                => $catalog
                    ->drugs(),

                'presentations'
                => $catalog
                    ->drugPresentations(),

                'routes'
                => $catalog
                    ->administrationRoutes(),

                'frequencies'
                => $catalog
                    ->administrationFrequencies(),

                'units'
                => $catalog
                    ->measurementUnits(),

                'timeUnits'
                => $catalog
                    ->timeUnits(),

                'formulas'
                => $formulas,

                'success'
                => \App\Core\Session
                    ::pullFlash(
                        'success'
                    ),

                'error'
                => \App\Core\Session
                    ::pullFlash(
                        'error'
                    ),
            ]
        );
    }
}
