<?php

declare(strict_types=1);

namespace App\Controllers\Clinica;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\Catalog;
use App\Models\Patient;
use App\Models\Surgery;
use App\Services\SurgeryService;
use Throwable;

class CirugiaController extends Controller
{
    public function index(
        Request $request
    ): void {
        $search = trim(
            (string) $request->input('q', '')
        );

        $catalog = new Catalog();

        $this->view(
            'cirugias/index',
            [
                'title' => 'Cirugías',

                'surgeries' => (new Surgery())->list(
                    active_environment_id(),
                    $search
                ),

                'patients' => (new Patient())->allByEnvironment(
                    active_environment_id()
                ),

                'procedures' =>
                $catalog->surgeryProcedures(),

                'anesthesiaTypes' =>
                $catalog->anesthesiaTypes(),

                'search' => $search,

                'success' =>
                Session::pullFlash(
                    'success'
                ),

                'error' =>
                Session::pullFlash(
                    'error'
                ),
            ]
        );
    }

    public function show(
        Request $request,
        string $id
    ): void {
        $eventId = (int) $id;

        $model = new Surgery();

        $surgery = $model->find(
            $eventId,
            active_environment_id()
        );

        if (!$surgery) {
            Session::flash(
                'error',
                'Cirugía no encontrada.'
            );

            $this->redirect('/cirugias');
        }

        $catalog = new Catalog();

        $this->view(
            'cirugias/show',
            [
                'title' => 'Detalle de cirugía',

                'surgery' => $surgery,

                'anesthesia' =>
                $model->anesthesia(
                    $eventId
                ),

                'team' =>
                $model->team(
                    $eventId
                ),

                'evolutions' =>
                $model->evolutions(
                    $eventId
                ),

                'files' =>
                $model->files(
                    $eventId
                ),

                'users' =>
                $catalog->users(),

                'teamFunctions' =>
                $catalog
                    ->surgicalTeamFunctions(),

                'success' =>
                Session::pullFlash(
                    'success'
                ),

                'error' =>
                Session::pullFlash(
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
            (new SurgeryService())->create(
                $request->all(),
                $request->files(),
                active_environment_id(),
                auth_id()
            );

            Session::flash(
                'success',
                'Cirugía registrada.'
            );
        } catch (Throwable $e) {
            Session::flash(
                'error',
                $e->getMessage()
            );
        }

        $this->redirect('/cirugias');
    }

    public function team(
        Request $request,
        string $id
    ): void {
        $this->handle(
            $request,
            function () use (
                $request,
                $id
            ): void {
                (new SurgeryService())
                    ->addTeamMember(
                        (int) $id,
                        $request->all(),
                        active_environment_id(),
                        auth_id()
                    );
            },
            '/cirugias/' . (int) $id,
            'Integrante agregado al equipo quirúrgico.'
        );
    }

    public function removeTeam(
        Request $request,
        string $id
    ): void {
        $this->handle(
            $request,
            function () use (
                $request,
                $id
            ): void {
                (new SurgeryService())
                    ->removeTeamMember(
                        (int) $id,
                        (int) $request->input(
                            'usuario_id'
                        ),
                        (int) $request->input(
                            'funcion_id'
                        ),
                        active_environment_id(),
                        auth_id()
                    );
            },
            '/cirugias/' . (int) $id,
            'Integrante retirado del equipo quirúrgico.'
        );
    }

    public function evolution(
        Request $request,
        string $id
    ): void {
        $this->handle(
            $request,
            function () use (
                $request,
                $id
            ): void {
                (new SurgeryService())
                    ->addEvolution(
                        (int) $id,
                        $request->all(),
                        active_environment_id(),
                        auth_id()
                    );
            },
            '/cirugias/'
                . (int) $id
                . '#evoluciones',
            'Evolución registrada.'
        );
    }

    private function handle(
        Request $request,
        callable $callback,
        string $redirect,
        string $success
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
            $callback();

            Session::flash(
                'success',
                $success
            );
        } catch (Throwable $e) {
            Session::flash(
                'error',
                $e->getMessage()
            );
        }

        $this->redirect(
            $redirect
        );
    }
}
