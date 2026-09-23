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

            $result = (new OwnerService())->create(
                $r->all(),
                active_environment_id(),
                auth_id()
            );

            /*
         * Solamente una cuenta NUEVA necesita invitación.
         *
         * Una cuenta existente ya tiene sus propias credenciales
         * y simplemente fue vinculada como propietario.
         */
            if (
                !empty($result['user_created'])
                && !empty($result['user_id'])
            ) {

                try {

                    (new \App\Services\UserInvitationService())
                        ->send((int) $result['user_id']);

                    Session::flash(
                        'success',
                        'Propietario y cuenta de cliente creados correctamente. '
                            . 'Se envió una invitación al correo registrado.'
                    );
                } catch (Throwable $mailError) {

                    error_log(
                        '[TUHUELLAVET][OWNER_INVITATION] '
                            . $mailError->getMessage()
                    );

                    Session::flash(
                        'success',
                        'El propietario y su cuenta fueron creados correctamente, '
                            . 'pero no fue posible enviar la invitación. '
                            . 'Puedes reenviarla desde Administración de usuarios.'
                    );
                }
            } elseif (!empty($result['user_linked'])) {

                Session::flash(
                    'success',
                    'Propietario creado correctamente. '
                        . 'La cuenta existente fue vinculada y ahora también '
                        . 'tiene el rol Cliente en este entorno.'
                );
            } else {

                Session::flash(
                    'success',
                    'Propietario creado correctamente.'
                );
            }
        } catch (Throwable $e) {

            Session::flash(
                'error',
                $e->getMessage()
            );
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
     *
     * Para email también informa si existe una cuenta de usuario
     * que puede ser vinculada como propietario.
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

        // =========================================================
        // 1. VALIDAR CONTEXTO Y PERMISOS
        // =========================================================

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

        // =========================================================
        // 2. NORMALIZAR
        // =========================================================

        if ($field === 'email') {
            $value = strtolower($value);
        }

        if ($field === 'identificacion') {
            $value = preg_replace(
                '/[\s.\-]+/u',
                '',
                $value
            );
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
                'status' => 'available',
                'message' => '',
            ]);
        }

        $accessMode = trim(
            (string) $r->input(
                'modo_acceso',
                'create'
            )
        );

        if (!in_array($accessMode, ['create', 'none'], true)) {
            $accessMode = 'create';
        }

        // =========================================================
        // 3. IDENTIFICACIÓN Y CELULAR
        // =========================================================

        if ($field !== 'email') {

            $exists = (new Owner())->existsByField(
                $field,
                $value,
                $ownerId > 0 ? $ownerId : null
            );

            $messages = [
                'identificacion' =>
                'Esta identificación ya está registrada.',

                'celular' =>
                'Este celular ya está registrado.',
            ];

            $this->json([
                'ok' => true,
                'exists' => $exists,
                'status' => $exists
                    ? 'existing_owner'
                    : 'available',
                'message' => $exists
                    ? $messages[$field]
                    : 'Dato disponible.',
            ]);
        }

        // =========================================================
        // 4. EMAIL: PRIMERO COMPROBAR PROPIETARIOS
        // =========================================================

        $ownerExists = (new Owner())->existsByField(
            'email',
            $value,
            $ownerId > 0 ? $ownerId : null
        );

        /*
 * Sin acceso al portal:
 * el correo solamente funciona como dato de contacto.
 *
 * Ya comprobamos arriba que no pertenece a otro propietario.
 */
        if ($accessMode === 'none') {
            $this->json([
                'ok' => true,
                'exists' => false,
                'status' => 'available',
                'message' =>
                'Correo disponible como dato de contacto. '
                    . 'No se creará ni vinculará una cuenta.',
            ]);
        }

        if ($ownerExists) {
            $this->json([
                'ok' => true,
                'exists' => true,
                'status' => 'existing_owner',
                'message' =>
                'Este correo ya está registrado en otra ficha de propietario.',
            ]);
        }

        // =========================================================
        // 5. EMAIL: BUSCAR CUENTA DE USUARIO
        // =========================================================

        $db = \App\Core\Database::connection();

        $stmt = $db->prepare(
            'SELECT
            u.id,
            u.nombres,
            u.apellidos,
            u.email,
            u.activo,
            u.deleted_at,
            p.id AS propietario_id
         FROM usuarios u
         LEFT JOIN propietarios p
            ON p.usuario_id = u.id
         WHERE u.email = :email
         LIMIT 1'
        );

        $stmt->execute([
            'email' => $value,
        ]);

        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        // =========================================================
        // 6. NO EXISTE CUENTA
        // =========================================================

        if (!$user) {
            $this->json([
                'ok' => true,
                'exists' => false,
                'status' => 'available',
                'message' =>
                'Correo disponible. Se creará una nueva cuenta Cliente.',
            ]);
        }

        // =========================================================
        // 7. CUENTA ELIMINADA O INACTIVA
        // =========================================================

        if (!empty($user['deleted_at'])) {
            $this->json([
                'ok' => true,
                'exists' => true,
                'status' => 'unavailable_user',
                'message' =>
                'Existe una cuenta dada de baja con este correo. '
                    . 'Debe revisarse desde Administración.',
            ]);
        }

        if ((int) $user['activo'] !== 1) {
            $this->json([
                'ok' => true,
                'exists' => true,
                'status' => 'unavailable_user',
                'message' =>
                'Existe una cuenta inactiva con este correo. '
                    . 'Debe activarse antes de vincularla.',
            ]);
        }

        // =========================================================
        // 8. CUENTA YA VINCULADA A PROPIETARIO
        // =========================================================

        if (!empty($user['propietario_id'])) {
            $this->json([
                'ok' => true,
                'exists' => true,
                'status' => 'existing_owner',
                'message' =>
                'Esta cuenta ya está vinculada a una ficha de propietario.',
            ]);
        }

        // =========================================================
        // 9. CUENTA EXISTENTE DISPONIBLE PARA VINCULACIÓN
        // =========================================================

        $this->json([
            'ok' => true,
            'exists' => true,
            'status' => 'existing_user',
            'message' =>
            'Cuenta existente encontrada. '
                . 'Se vinculará como Cliente sin eliminar sus roles actuales.',
        ]);
    }
}
