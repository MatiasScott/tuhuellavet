<?php

namespace App\Controllers\Gestion;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\FormulaAdmin;
use App\Models\Catalog;
use App\Services\FormulaAdminService;
use Throwable;

class FormulaAdminController extends Controller
{
    public function index(Request $r): void
    {
        $m = new FormulaAdmin();
        $c = new Catalog();
        $this->view('formulas/index', ['title' => 'Fórmulas médicas', 'formulas' => $m->all(active_environment_id()), 'categories' => $m->categories(), 'variableTypes' => $m->variableTypes(), 'variableOrigins' => $m->variableOrigins(), 'units' => $c->measurementUnits(), 'species' => $c->species(), 'success' => Session::pullFlash('success'), 'error' => Session::pullFlash('error')]);
    }
    public function store(Request $r): void
    {
        $this->go($r, fn() => (new FormulaAdminService())->create($r->all(), active_environment_id(), auth_id()), '/formulas', 'Fórmula creada.');
    }
    public function version(Request $r, string $id): void
    {
        $this->go($r, fn() => (new FormulaAdminService())->addVersion((int)$id, $r->all(), active_environment_id(), auth_id()), '/formulas', 'Nueva versión creada.');
    }
    public function publish(Request $r, string $id): void
    {
        $this->go($r, fn() => (new FormulaAdminService())->publish((int)$id, active_environment_id(), auth_id()), '/formulas', 'Versión publicada.');
    }
    private function go(Request $r, callable $f, string $b, string $ok): void
    {
        if (!Session::validateCsrf($r->input('_token'))) {
            http_response_code(419);
            return;
        }
        try {
            $f();
            Session::flash('success', $ok);
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($b);
    }
    public function test(Request $r): void
    {
        if (
            !Session::validateCsrf(
                $r->input('_token')
            )
        ) {
            $this->json([
                'ok' => false,
                'message' => 'Sesión expirada.'
            ], 419);

            return;
        }

        try {

            $values = $r->input(
                'variables',
                []
            );

            if (!is_array($values)) {
                throw new \RuntimeException(
                    'Las variables deben enviarse como un arreglo.'
                );
            }

            $expectedInput = $r->input(
                'resultado_esperado'
            );

            $expected = null;

            if (
                $expectedInput !== null
                && trim((string)$expectedInput) !== ''
            ) {

                if (!is_numeric($expectedInput)) {
                    throw new \RuntimeException(
                        'El resultado esperado debe ser numérico.'
                    );
                }

                $expected = (float)$expectedInput;

                if (!is_finite($expected)) {
                    throw new \RuntimeException(
                        'El resultado esperado no es válido.'
                    );
                }
            }

            $result = (
                new \App\Services\FormulaTestService()
            )->test(
                (int)$r->input(
                    'formula_version_id'
                ),

                $values,

                active_environment_id(),

                $expected
            );

            $this->json([
                'ok' => true,
                'data' => $result
            ]);
        } catch (Throwable $e) {

            $this->json([
                'ok' => false,
                'message' => $e->getMessage()
            ], 422);
        }
    }
    public function testData(
        Request $r,
        string $id
    ): void {

        try {

            $model = new FormulaAdmin();

            $version = $model->versionForEnvironment(
                (int)$id,
                active_environment_id()
            );

            if (!$version) {

                $this->json([
                    'ok' => false,
                    'message' => 'Versión no encontrada.'
                ], 404);

                return;
            }

            $variables = $model->versionVariables(
                (int)$id,
                active_environment_id()
            );

            $this->json([
                'ok' => true,

                'version' => $version,

                'variables' => $variables,
            ]);
        } catch (Throwable $e) {

            $this->json([
                'ok' => false,
                'message' => 'No se pudieron cargar los datos de la versión.'
            ], 500);

            return;
        }
    }
    public function updateDraft(
        Request $r,
        string $id
    ): void {

        $this->go(
            $r,

            fn() => (
                new FormulaAdminService()
            )->updateDraft(
                (int)$id,
                $r->all(),
                active_environment_id(),
                auth_id()
            ),

            '/formulas',

            'Borrador actualizado correctamente.'
        );
    }
}
