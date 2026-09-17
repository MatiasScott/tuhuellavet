<?php

namespace App\Controllers\Clinica;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;

use App\Models\Formula;

use App\Services\FormulaService;

use Throwable;

class FormulaController extends Controller
{
    public function variables(
        Request $request,
        string $versionId
    ): void {
        $formula
            = (new Formula())
            ->version(
                (int) $versionId,
                active_environment_id()
            );

        if (!$formula) {
            $this->json(
                [
                    'ok' => false,
                    'message'
                    => 'Fórmula no encontrada.',
                ],
                404
            );
        }

        $variables
            = (new Formula())
            ->variables(
                (int) $versionId
            );

        $this->json([
            'ok' => true,
            'formula' => $formula,
            'variables' => $variables,
        ]);
    }


    public function calculate(
        Request $request
    ): void {
        if (
            !Session::validateCsrf(
                $request->input('_token')
            )
        ) {
            $this->json(
                [
                    'ok' => false,
                    'message'
                    => 'Sesión expirada.',
                ],
                419
            );
        }

        try {
            $manualValues
                = $request->input(
                    'variables',
                    []
                );

            if (
                !is_array(
                    $manualValues
                )
            ) {
                $manualValues = [];
            }

            $result
                = (new FormulaService())
                ->calculate(
                    (int)
                    $request->input(
                        'formula_version_id'
                    ),

                    (int)
                    $request->input(
                        'animal_id'
                    ),

                    $request->input(
                        'evento_clinico_id'
                    )
                        ? (int)
                        $request->input(
                            'evento_clinico_id'
                        )
                        : null,

                    $manualValues,

                    active_environment_id(),

                    auth_id(),

                    !empty($request->input(
                            'simulacion'
                        )),

                    strtoupper(
                        trim(
                            (string) $request->input(
                                'contexto',
                                'TRATAMIENTO'
                            )
                        )
                    )
                );

            $this->json([
                'ok' => true,
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            $this->json(
                [
                    'ok' => false,
                    'message'
                    => $e->getMessage(),
                ],
                422
            );
        }
    }
}
