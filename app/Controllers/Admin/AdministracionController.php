<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\AdminRepository;
use App\Models\Catalog;
use App\Services\AdminService;
use Throwable;

class AdministracionController extends Controller
{
    public function users(Request $r): void
    {
        $m = new AdminRepository();
        $this->view('admin/usuarios', [
            'title' => 'Usuarios',
            'users' => $m->users(active_environment_id()),
            'roles' => $m->roles(),
            'success' => Session::pullFlash('success'),
            'error' => Session::pullFlash('error')
        ]);
    }
    public function storeUser(Request $r): void
    {
        $this->go(
            $r,
            function () use ($r) {

                $result = (new AdminService())->createUser(
                    $r->all(),
                    active_environment_id(),
                    auth_id()
                );

                try {

                    (new \App\Services\UserInvitationService())
                        ->send((int) $result['id']);

                    Session::flash(
                        'success',
                        'Usuario creado correctamente. ' .
                            'Se envió un enlace de activación a su correo.'
                    );
                } catch (\Throwable $e) {

                    error_log(
                        '[TUHUELLAVET][MAIL] Usuario ID '
                            . (int) $result['id']
                            . ' | Error: '
                            . $e->getMessage()
                            . ' | Archivo: '
                            . $e->getFile()
                            . ' | Línea: '
                            . $e->getLine()
                    );

                    Session::flash(
                        'error',
                        'El usuario fue creado, pero no se pudo enviar '
                            . 'el correo de activación. Revisa el registro de errores.'
                    );
                }
            },
            '/admin/usuarios'
        );
    }
    public function roles(Request $r): void
    {
        (new \App\Services\PermissionService())->syncPermissions();
        $m = new AdminRepository();
        $roles = $m->roles();
        $selected = (int)$r->input('rol_id', ($roles[0]['id'] ?? 0));
        $this->view('admin/roles', [
            'title' => 'Roles y permisos',
            'roles' => $roles,
            'permissions' => $m->permissions(),
            'selectedRole' => $selected,
            'selectedPermissions' => $selected ? $m->rolePermissions($selected) : [],
            'success' => Session::pullFlash('success'),
            'error' => Session::pullFlash('error')
        ]);
    }
    public function storeRole(Request $r): void
    {
        $this->go($r, fn() => (new AdminService())->createRole($r->all(), active_environment_id(), auth_id()), '/admin/roles', 'Rol creado.');
    }
    public function savePermissions(Request $r, string $id): void
    {
        $this->go($r, fn() => (new AdminService())->saveRolePermissions((int)$id, (array)$r->input('permisos', []), active_environment_id(), auth_id()), '/admin/roles?rol_id=' . $id, 'Permisos actualizados.');
    }
    public function companies(Request $r): void
    {
        $db = \App\Core\Database::connection();
        $this->view('admin/empresas', [
            'title' => 'Empresas',
            'companies' => (new AdminRepository())->companies(),
            'environmentTypes' => $db->query('SELECT id,codigo,nombre FROM tipos_entorno WHERE activo=1 ORDER BY id')->fetchAll(),
            'success' => Session::pullFlash('success'),
            'error' => Session::pullFlash('error')
        ]);
    }
    public function storeEnvironment(Request $r): void
    {
        $this->go($r, fn() => (new AdminService())->createEnvironment($r->all(), auth_id()), '/admin/empresas', 'Entorno creado.');
    }
    public function storeCompany(Request $r): void
    {
        $this->go($r, fn() => (new AdminService())->createCompany($r->all(), auth_id()), '/admin/empresas', 'Empresa creada.');
    }
    public function audit(Request $r): void
    {
        $this->view('admin/auditoria', ['title' => 'Auditoría', 'rows' => (new AdminRepository())->audit(active_environment_id())]);
    }
    private function go(Request $r, callable $f, string $back, string $ok = ''): void
    {
        if (!Session::validateCsrf($r->input('_token'))) {
            http_response_code(419);
            return;
        }
        try {
            $f();
            if ($ok) Session::flash('success', $ok);
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect($back);
    }
    public function editUser(Request $r, string $id): void
    {
        header('Content-Type: application/json; charset=utf-8');

        $userId = (int) $id;
        $env = active_environment_id();

        if ($userId <= 0) {
            http_response_code(400);

            echo json_encode([
                'ok' => false,
                'message' => 'El usuario seleccionado no es válido.'
            ]);

            return;
        }

        $user = (new AdminRepository())->userInEnvironment(
            $userId,
            $env
        );

        if (!$user) {
            http_response_code(404);

            echo json_encode([
                'ok' => false,
                'message' => 'Usuario no encontrado en el entorno.'
            ]);

            return;
        }

        $isSuperAdmin = !empty(auth_user()['is_super_admin']);

        $hasGlobalRoles = (int) (
            $user['roles_globales'] ?? 0
        ) > 0;

        $isOwnAccount = $userId === auth_id();

        /*
     * Las cuentas globales solo pueden consultarse
     * aquí cuando el superadministrador está
     * editando su propia cuenta.
     */
        if ($hasGlobalRoles) {

            if (!$isSuperAdmin || !$isOwnAccount) {

                http_response_code(403);

                echo json_encode([
                    'ok' => false,
                    'message' =>
                    'Esta cuenta debe administrarse desde '
                        . 'la administración global.'
                ]);

                return;
            }

            echo json_encode([
                'ok' => true,
                'data' => $user,
                'edit_mode' => 'global_profile'
            ], JSON_UNESCAPED_UNICODE);

            return;
        }

        /*
     * Para usuarios normales mantenemos
     * las restricciones existentes.
     */
        if (
            !$isSuperAdmin &&
            (
                (int) $user['cantidad_roles'] !== 1 ||
                !$this->userHasOnlyClientRole($userId, $env)
            )
        ) {

            http_response_code(403);

            echo json_encode([
                'ok' => false,
                'message' =>
                'No tienes autorización para editar este usuario.'
            ]);

            return;
        }

        echo json_encode([
            'ok' => true,
            'data' => $user,
            'edit_mode' => 'environment_user'
        ], JSON_UNESCAPED_UNICODE);
    }
    private function userHasOnlyClientRole(
        int $userId,
        int $env
    ): bool {

        $db = \App\Core\Database::connection();

        $stmt = $db->prepare(
            "SELECT COUNT(*)
         FROM usuarios_entornos_roles uer
         INNER JOIN roles r
            ON r.id = uer.rol_id
         WHERE uer.usuario_id = :id
           AND uer.entorno_id = :env
           AND r.codigo = 'CLIENTE'"
        );

        $stmt->execute([
            'id' => $userId,
            'env' => $env
        ]);

        return (int) $stmt->fetchColumn() === 1;
    }
    public function updateUser(Request $r, string $id): void
    {
        $this->go(
            $r,
            function () use ($r, $id) {

                (new AdminService())->updateUser(
                    (int) $id,
                    $r->all(),
                    active_environment_id(),
                    auth_id()
                );
            },
            '/admin/usuarios',
            'Usuario actualizado correctamente.'
        );
    }
    public function resendUserInvitation(
        Request $r,
        string $id
    ): void {
        $this->go(
            $r,
            function () use ($id) {

                (new AdminService())->resendUserInvitation(
                    (int) $id,
                    active_environment_id(),
                    auth_id()
                );
            },
            '/admin/usuarios',
            'Invitación enviada correctamente.'
        );
    }
}
