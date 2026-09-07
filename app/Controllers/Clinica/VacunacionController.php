<?php

namespace App\Controllers\Clinica;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;

use App\Models\Catalog;
use App\Models\Patient;
use App\Models\PreventiveCare;

use App\Services\PreventiveCareService;

use Throwable;

class VacunacionController extends Controller
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

        $catalog = new Catalog();

        $this->view(
            'vacunas/index',
            [
                'title'
                    => 'Vacunación',

                'vaccinations'
                    => (new PreventiveCare())
                        ->vaccinationsByEnvironment(
                            active_environment_id(),
                            $search
                        ),

                'upcoming'
                    => (new PreventiveCare())
                        ->upcomingVaccinations(
                            active_environment_id()
                        ),

                'patients'
                    => (new Patient())
                        ->allByEnvironment(
                            active_environment_id()
                        ),

                'vaccines'
                    => $catalog
                        ->vaccines(),

                'units'
                    => $catalog
                        ->measurementUnits(),

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
                = (new PreventiveCareService())
                    ->createVaccination(
                        $request->all(),
                        active_environment_id(),
                        auth_id()
                    );

            Session::flash(
                'success',
                'Vacunación registrada correctamente.'
            );

            $patientId
                = (int)
                    $request->input(
                        'animal_id'
                    );

            $this->redirect(
                '/pacientes/'
                . $patientId
            );
        } catch (Throwable $e) {
            Session::flash(
                'error',
                $e->getMessage()
            );

            $this->redirect(
                '/vacunas'
            );
        }
    }
}