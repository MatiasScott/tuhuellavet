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

class DesparasitacionController extends Controller
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
            'desparasitaciones/index',
            [
                'title'
                => 'Desparasitación',

                'dewormings'
                => (new PreventiveCare())
                    ->dewormingsByEnvironment(
                        active_environment_id(),
                        $search
                    ),

                'upcoming'
                => (new PreventiveCare())
                    ->upcomingDewormings(
                        active_environment_id()
                    ),

                'patients'
                => (new Patient())
                    ->allByEnvironment(
                        active_environment_id()
                    ),

                'drugs'
                => $catalog
                    ->drugs(),

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
            (new PreventiveCareService())
                ->createDeworming(
                    $request->all(),
                    active_environment_id(),
                    auth_id()
                );

            Session::flash(
                'success',
                'Desparasitación registrada correctamente.'
            );

            $this->redirect(
                '/pacientes/'
                    . (int)
                    $request->input(
                        'animal_id'
                    )
            );
        } catch (Throwable $e) {
            Session::flash(
                'error',
                $e->getMessage()
            );

            $this->redirect(
                '/desparasitaciones'
            );
        }
    }

    public function update(
        Request $request,
        string $id
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
            (new PreventiveCareService())
                ->updateDeworming(
                    (int)$id,
                    $request->all(),
                    active_environment_id(),
                    auth_id()
                );

            Session::flash(
                'success',
                'Desparasitación actualizada correctamente.'
            );
        } catch (Throwable $e) {
            Session::flash(
                'error',
                $e->getMessage()
            );
        }

        $this->redirect(
            '/desparasitaciones'
        );
    }


    public function cancel(
        Request $request,
        string $id
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
            (new PreventiveCareService())
                ->cancelDeworming(
                    (int)$id,
                    trim(
                        (string)$request->input(
                            'motivo_anulacion',
                            ''
                        )
                    ),
                    active_environment_id(),
                    auth_id()
                );

            Session::flash(
                'success',
                'Desparasitación anulada correctamente.'
            );
        } catch (Throwable $e) {
            Session::flash(
                'error',
                $e->getMessage()
            );
        }

        $this->redirect(
            '/desparasitaciones'
        );
    }
}
