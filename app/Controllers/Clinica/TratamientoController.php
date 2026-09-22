<?php

namespace App\Controllers\Clinica;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;

use App\Services\TreatmentService;

use Throwable;

class TratamientoController extends Controller
{
    public function store(
        Request $request,
        string $eventId
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
            (new TreatmentService())
                ->create(
                    (int) $eventId,
                    $request->all(),
                    active_environment_id(),
                    auth_id()
                );

            Session::flash(
                'success',
                'Tratamiento registrado correctamente.'
            );
        } catch (Throwable $e) {
            Session::flash(
                'error',
                $e->getMessage()
            );
        }

        $this->redirect(
            '/consultas/'
                . (int) $eventId
        );
    }


    public function applyMedication(
        Request $request,
        string $eventId,
        string $medicationId
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
            (new TreatmentService())
                ->addApplication(
                    (int) $medicationId,

                    (float)
                    $request->input(
                        'cantidad_aplicada'
                    ),

                    $request->input(
                        'unidad_id'
                    )
                        ? (int)
                        $request->input(
                            'unidad_id'
                        )
                        : null,

                    trim(
                        (string)
                        $request->input(
                            'observaciones',
                            ''
                        )
                    )
                        ?: null,

                    active_environment_id(),

                    auth_id()
                );

            Session::flash(
                'success',
                'aplicación registrada.'
            );
        } catch (Throwable $e) {
            Session::flash(
                'error',
                $e->getMessage()
            );
        }

        $this->redirect(
            '/consultas/'
                . (int) $eventId
        );
    }
}
