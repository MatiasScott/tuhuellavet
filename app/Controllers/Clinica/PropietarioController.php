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
    public function index(Request $r): void
    {
        $q = trim((string)$r->input('q', ''));
        $this->view('propietarios/index', ['title' => 'Propietarios', 'owners' => (new Owner())->listByEnvironment(active_environment_id(), $q), 'identificationTypes' => (new Catalog())->identificationTypes(), 'search' => $q, 'success' => Session::pullFlash('success'), 'error' => Session::pullFlash('error')]);
    }
    public function store(Request $r): void
    {
        $this->csrf($r);
        try {
            $res = (new OwnerService())->create($r->all(), active_environment_id(), auth_id());
            $m = 'Propietario creado correctamente.';
            if ($res['temporary_password']) $m .= ' Contraseña temporal: ' . $res['temporary_password'];
            Session::flash('success', $m);
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/propietarios');
    }
    public function update(Request $r, string $id): void
    {
        $this->csrf($r);
        try {
            (new OwnerService())->update((int)$id, $r->all(), active_environment_id(), auth_id());
            Session::flash('success', 'Propietario actualizado.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/propietarios/' . (int) $id);
    }
    public function destroy(Request $r, string $id): void
    {
        $this->csrf($r);
        try {
            (new OwnerService())->delete((int)$id, active_environment_id(), auth_id());
            Session::flash('success', 'Propietario eliminado.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/propietarios');
    }
    private function csrf(Request $r): void
    {
        if (!Session::validateCsrf($r->input('_token'))) {
            http_response_code(419);
            exit('Sesión expirada.');
        }
    }
    /**
     * Mostrar expediente individual del propietario.
     */
    public function show(Request $r, string $id): void
    {
        $environmentId = active_environment_id();

        $ownerModel = new Owner();

        $owner = $ownerModel->find(
            (int) $id,
            $environmentId
        );

        if (!$owner) {
            http_response_code(404);
            echo 'Propietario no encontrado.';
            return;
        }

        $patients = $ownerModel->patients(
            (int) $owner['propietario_entorno_id'],
            $environmentId
        );

        $catalog = new Catalog();

        $this->view('propietarios/show', [
            'title' => 'Propietario: ' . $owner['nombres'],

            'owner' => $owner,

            'patients' => $patients,

            'identificationTypes' => $catalog->identificationTypes(),

            'success' => Session::pullFlash('success'),

            'error' => Session::pullFlash('error'),
        ]);
    }
    /**
     * Validación AJAX de identificación, email y celular.
     */
    public function validateField(Request $r): void
    {
        $field = trim((string) $r->input('campo', ''));
        $value = trim((string) $r->input('valor', ''));
        $ownerId = (int) $r->input('propietario_id', 0);

        $allowed = [
            'identificacion',
            'email',
            'celular',
        ];

        if (!in_array($field, $allowed, true)) {
            $this->json([
                'ok' => false,
                'message' => 'Campo no permitido.',
            ], 422);
        }

        // No aceptar un ID arbitrario para excluir registros ajenos.
        if ($ownerId > 0) {
            if (!can('propietarios.editar')) {
                $this->json([
                    'ok' => false,
                    'message' => 'No tienes permiso para editar propietarios.',
                ], 403);
            }

            $currentOwner = (new Owner())->find(
                $ownerId,
                active_environment_id()
            );

            if (!$currentOwner) {
                $this->json([
                    'ok' => false,
                    'message' => 'Propietario no encontrado.',
                ], 404);
            }
        } elseif (!can('propietarios.crear')) {
            $this->json([
                'ok' => false,
                'message' => 'No tienes permiso para crear propietarios.',
            ], 403);
        }

        if ($field === 'email') {
            $value = strtolower($value);
        }

        if ($field === 'identificacion') {
            $value = preg_replace('/[\s.\-]+/u', '', $value);
        }

        if ($field === 'celular') {
            $value = preg_replace('/\D+/', '', $value);

            if (
                str_starts_with($value, '593')
                && strlen($value) === 12
            ) {
                $value = '0' . substr($value, 3);
            }
        }

        if ($value === '') {
            $this->json([
                'ok' => true,
                'exists' => false,
                'message' => '',
            ]);
        }

        $exists = (new Owner())->existsByField(
            $field,
            $value,
            $ownerId > 0 ? $ownerId : null
        );

        $messages = [
            'identificacion' => 'Esta identificación ya está registrada.',
            'email' => 'Este correo electrónico ya está registrado.',
            'celular' => 'Este celular ya está registrado.',
        ];

        $this->json([
            'ok' => true,
            'exists' => $exists,
            'message' => $exists
                ? $messages[$field]
                : 'Disponible.',
        ]);
    }
}
