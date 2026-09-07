<?php

namespace App\Controllers\Clinica;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;

use App\Models\Patient;
use App\Models\Catalog;

use App\Models\ClinicalHistory;

use App\Services\PatientService;

use Throwable;

class PacienteController extends Controller
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
            'pacientes/index',
            [
                'title'
                => 'Pacientes',

                'patients'
                => (new Patient())
                    ->allByEnvironment(
                        active_environment_id(),
                        $search
                    ),

                'owners'
                => $catalog
                    ->ownersForEnvironment(
                        active_environment_id()
                    ),

                'species'
                => $catalog
                    ->species(),

                'breeds'
                => $catalog
                    ->breeds(),

                'sexes'
                => $catalog
                    ->sexes(),

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


    public function show(
        Request $request,
        string $id
    ): void {
        $patient
            = (new Patient())
            ->findInEnvironment(
                (int) $id,
                active_environment_id()
            );

        if (!$patient) {
            http_response_code(404);

            echo 'Paciente no encontrado.';

            return;
        }

        $this->view(
            'pacientes/show',
            [
                'title'
                => $patient['nombre']
                    ?: 'Paciente',

                'patient'
                => $patient,

                'weights'
                => (new Patient())
                    ->weights(
                        (int) $id
                    ),

                'history'
                => (new ClinicalHistory())
                    ->timeline(
                        (int) $id,
                        active_environment_id()
                    ),

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
                $request->input(
                    '_token'
                )
            )
        ) {
            http_response_code(419);

            return;
        }

        try {
            $patientId
                = (new PatientService())
                ->create(
                    $request->all(),
                    $request->files(),
                    active_environment_id(),
                    auth_id()
                );

            Session::flash(
                'success',
                'Paciente registrado correctamente.'
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
                '/pacientes'
            );
        }
    }


    public function update(
        Request $request,
        string $id
    ): void {
        if (
            !Session::validateCsrf(
                $request->input(
                    '_token'
                )
            )
        ) {
            http_response_code(419);

            return;
        }

        try {
            (new PatientService())
                ->update(
                    (int) $id,
                    $request->all(),
                    $request->files(),
                    active_environment_id(),
                    auth_id()
                );

            Session::flash(
                'success',
                'Paciente actualizado.'
            );
        } catch (Throwable $e) {
            Session::flash(
                'error',
                $e->getMessage()
            );
        }

        $this->redirect(
            '/pacientes/'
                . (int) $id
        );
    }


    public function addWeight(
        Request $request,
        string $id
    ): void {
        if (
            !Session::validateCsrf(
                $request->input(
                    '_token'
                )
            )
        ) {
            http_response_code(419);

            return;
        }

        try {
            (new PatientService())
                ->addWeight(
                    (int) $id,

                    (float)
                    $request->input(
                        'peso_kg'
                    ),

                    trim(
                        (string)
                        $request->input(
                            'observacion',
                            ''
                        )
                    )
                        ?: null,

                    active_environment_id(),

                    auth_id()
                );

            Session::flash(
                'success',
                'Peso actualizado correctamente.'
            );
        } catch (Throwable $e) {
            Session::flash(
                'error',
                $e->getMessage()
            );
        }

        $this->redirect(
            '/pacientes/'
                . (int) $id
        );
    }


    public function destroy(
        Request $request,
        string $id
    ): void {
        if (
            !Session::validateCsrf(
                $request->input(
                    '_token'
                )
            )
        ) {
            http_response_code(419);

            return;
        }

        try {
            (new PatientService())
                ->delete(
                    (int) $id,
                    active_environment_id(),
                    auth_id()
                );

            Session::flash(
                'success',
                'Paciente desactivado correctamente.'
            );
        } catch (Throwable $e) {
            Session::flash(
                'error',
                $e->getMessage()
            );
        }

        $this->redirect(
            '/pacientes'
        );
    }
}
