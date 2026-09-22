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
        $this->view('admin/usuarios', ['title' => 'Usuarios', 'users' => $m->users(), 'roles' => $m->roles(), 'success' => Session::pullFlash('success'), 'error' => Session::pullFlash('error')]);
    }
    public function storeUser(Request $r): void
    {
        $this->go($r, function () use ($r) {
            $x = (new AdminService())->createUser($r->all(), active_environment_id(), auth_id());
            Session::flash('success', 'Usuario creado. Contraseña temporal: ' . $x['password']);
        }, '/admin/usuarios');
    }
    public function roles(Request $r): void
    {
        (new \App\Services\PermissionService())->syncPermissions();
        $m = new AdminRepository();
        $roles = $m->roles();
        $selected = (int)$r->input('rol_id', ($roles[0]['id'] ?? 0));
        $this->view('admin/roles', ['title' => 'Roles y permisos', 'roles' => $roles, 'permissions' => $m->permissions(), 'selectedRole' => $selected, 'selectedPermissions' => $selected ? $m->rolePermissions($selected) : [], 'success' => Session::pullFlash('success'), 'error' => Session::pullFlash('error')]);
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
        $this->view('admin/empresas', ['title' => 'Empresas', 'companies' => (new AdminRepository())->companies(), 'environmentTypes' => $db->query('SELECT id,codigo,nombre FROM tipos_entorno WHERE activo=1 ORDER BY id')->fetchAll(), 'success' => Session::pullFlash('success'), 'error' => Session::pullFlash('error')]);
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
}
