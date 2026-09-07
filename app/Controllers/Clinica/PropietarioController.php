<?php

namespace App\Controllers\Clinica;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;

use App\Models\Owner;
use App\Models\Catalog;

use App\Services\OwnerService;

use Throwable;

class PropietarioController extends Controller
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
            'propietarios/index',
            [
                'title'
                    => 'Propietarios',

                'owners'
                    => (new Owner())
                        ->listByEnvironment(
                            active_environment_id(),
                            $search
                        ),

                'identificationTypes'
                    => (new Catalog())
                        ->identificationTypes(),

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

            echo 'Sesión expirada.';

            return;
        }

        if (
            trim(
                (string)
                    $request->input(
                        'nombres'
                    )
            ) === ''
        ) {
            Session::flash(
                'error',
                'Los nombres son obligatorios.'
            );

            $this->redirect(
                '/propietarios'
            );
        }

        try {
            $result
                = (new OwnerService())
                    ->create(
                        $request->all(),
                        active_environment_id(),
                        auth_id()
                    );

            Session::flash(
                'success',
                'Propietario creado correctamente.'
            );

            if (
                !empty(
                    $result[
                        'temporary_password'
                    ]
                )
            ) {
                Session::flash(
                    'temporary_password',
                    $result[
                        'temporary_password'
                    ]
                );
            }
        } catch (Throwable $e) {
            Session::flash(
                'error',
                $e->getMessage()
            );
        }

        $this->redirect(
            '/propietarios'
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
            (new OwnerService())
                ->update(
                    (int) $id,
                    active_environment_id(),
                    $request->all(),
                    auth_id()
                );

            Session::flash(
                'success',
                'Propietario actualizado.'
            );
        } catch (Throwable $e) {
            Session::flash(
                'error',
                $e->getMessage()
            );
        }

        $this->redirect(
            '/propietarios'
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
            (new OwnerService())
                ->delete(
                    (int) $id,
                    active_environment_id(),
                    auth_id()
                );

            Session::flash(
                'success',
                'Propietario retirado del entorno.'
            );
        } catch (Throwable $e) {
            Session::flash(
                'error',
                $e->getMessage()
            );
        }

        $this->redirect(
            '/propietarios'
        );
    }
}