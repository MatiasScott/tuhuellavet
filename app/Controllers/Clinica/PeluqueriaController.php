<?php

namespace App\Controllers\Clinica;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;

use App\Models\Catalog;
use App\Models\Grooming;
use App\Models\Patient;

use App\Services\GroomingService;

use Throwable;

class PeluqueriaController extends Controller
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

        $environmentId =
            active_environment_id();

        $catalog = new Catalog();

        $grooming = new Grooming();

        $this->view(
            'peluquerias/index',
            [
                'title'
                => 'Peluquería',

                'groomings'
                => $grooming
                    ->byEnvironment(
                        $environmentId,
                        $search
                    ),

                'patients'
                => (new Patient())
                    ->allByEnvironment(
                        $environmentId
                    ),

                'services'
                => $catalog
                    ->services(),

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
                $request->input(
                    '_token'
                )
            )
        ) {
            http_response_code(419);
            return;
        }

        try {
            (new GroomingService())
                ->create(
                    $request->all(),
                    active_environment_id(),
                    auth_id()
                );

            Session::flash(
                'success',
                'Servicio de peluquería registrado correctamente.'
            );
        } catch (Throwable $e) {
            Session::flash(
                'error',
                $e->getMessage()
            );
        }

        $this->redirect(
            '/peluquerias'
        );
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
            (new GroomingService())
                ->update(
                    (int)$id,
                    $request->all(),
                    active_environment_id(),
                    auth_id()
                );

            Session::flash(
                'success',
                'Servicio de peluquería actualizado correctamente.'
            );
        } catch (Throwable $e) {
            Session::flash(
                'error',
                $e->getMessage()
            );
        }

        $this->redirect(
            '/peluquerias'
        );
    }


    public function cancel(
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
            (new GroomingService())
                ->cancel(
                    (int)$id,
                    trim(
                        (string)
                        $request->input(
                            'motivo',
                            ''
                        )
                    ),
                    active_environment_id(),
                    auth_id()
                );

            Session::flash(
                'success',
                'Servicio de peluquería anulado correctamente.'
            );
        } catch (Throwable $e) {
            Session::flash(
                'error',
                $e->getMessage()
            );
        }

        $this->redirect(
            '/peluquerias'
        );
    }
}
