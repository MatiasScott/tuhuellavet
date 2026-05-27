<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use App\Models\AdminAccess;
use RuntimeException;

final class AdminAccessService
{
    private const ACCIONES_ESTANDAR = ['ver', 'crear', 'editar', 'eliminar'];

    private const MODULOS_SISTEMA = [
        'usuarios' => 'Usuarios',
        'roles' => 'Roles',
        'permisos' => 'Permisos',
        'empresas' => 'Empresas',
        'pacientes' => 'Pacientes',
        'propietarios' => 'Propietarios',
        'consultas' => 'Consultas',
        'vacunas' => 'Vacunas',
        'hospitalizacion' => 'Hospitalizacion',
        'cirugias' => 'Cirugias',
        'inventario' => 'Inventario',
        'formulas' => 'Formulas',
        'auditoria' => 'Auditoria',
        'especies' => 'Especies',
        'razas' => 'Razas',
        'categorias' => 'Categorias',
        'reportes' => 'Reportes',
    ];

    public function __construct(
        private readonly AdminAccess $model = new AdminAccess(),
        private readonly AuditService $audit = new AuditService()
    ) {
    }

    public function usuariosData(): array
    {
        return [
            'usuarios' => $this->model->usuarios(),
            'roles' => $this->model->roles(),
            'empresas' => $this->model->empresas(),
        ];
    }

    public function createUsuario(array $input): int
    {
        $nombres = trim((string) ($input['nombres'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        $password = (string) ($input['password'] ?? '');

        if ($nombres === '' || $email === '') {
            throw new RuntimeException('Nombres y email son obligatorios.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Email invalido.');
        }

        if (strlen($password) < 8) {
            throw new RuntimeException('La contrasena debe tener al menos 8 caracteres.');
        }

        return $this->model->createUsuario([
            'nombres' => $nombres,
            'apellidos' => trim((string) ($input['apellidos'] ?? '')),
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'telefono' => trim((string) ($input['telefono'] ?? '')),
            'estado' => isset($input['estado']) ? 1 : 0,
        ]);
    }

    public function updateUsuario(int $id, array $input): void
    {
        $current = $this->model->findUsuarioById($id);
        if (!is_array($current)) {
            throw new RuntimeException('Usuario no encontrado.');
        }

        $nombres = trim((string) ($input['nombres'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));

        if ($nombres === '' || $email === '') {
            throw new RuntimeException('Nombres y email son obligatorios.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Email invalido.');
        }

        $ok = $this->model->updateUsuario($id, [
            'nombres' => $nombres,
            'apellidos' => trim((string) ($input['apellidos'] ?? '')),
            'email' => $email,
            'telefono' => trim((string) ($input['telefono'] ?? '')),
            'estado' => isset($input['estado']) ? 1 : 0,
        ]);

        if ($ok !== true) {
            throw new RuntimeException('No se pudo actualizar el usuario.');
        }
    }

    public function toggleUsuarioEstado(int $id, bool $activo): void
    {
        $current = $this->model->findUsuarioById($id);
        if (!is_array($current)) {
            throw new RuntimeException('Usuario no encontrado.');
        }

        $ok = $this->model->updateUsuario($id, [
            'nombres' => (string) ($current['nombres'] ?? ''),
            'apellidos' => (string) ($current['apellidos'] ?? ''),
            'email' => (string) ($current['email'] ?? ''),
            'telefono' => (string) ($current['telefono'] ?? ''),
            'estado' => $activo ? 1 : 0,
        ]);

        if ($ok !== true) {
            throw new RuntimeException('No se pudo cambiar el estado del usuario.');
        }
    }

    public function resetPassword(int $id, string $newPassword): void
    {
        if (strlen($newPassword) < 8) {
            throw new RuntimeException('La contrasena nueva debe tener al menos 8 caracteres.');
        }

        $ok = $this->model->resetPassword($id, password_hash($newPassword, PASSWORD_DEFAULT));
        if ($ok !== true) {
            throw new RuntimeException('No se pudo resetear la contrasena.');
        }
    }

    public function syncUsuarioEmpresasRol(int $usuarioId, array $input): void
    {
        $rolId = (int) ($input['rol_id'] ?? 0);
        $empresaIdsRaw = $input['empresa_ids'] ?? [];

        $empresaIds = [];
        if (is_array($empresaIdsRaw)) {
            foreach ($empresaIdsRaw as $empresaId) {
                $eid = (int) $empresaId;
                if ($eid > 0) {
                    $empresaIds[] = $eid;
                }
            }
        }

        if ($rolId <= 0) {
            throw new RuntimeException('Debes seleccionar un rol para las empresas asignadas.');
        }

        if ($empresaIds === []) {
            throw new RuntimeException('Debes seleccionar al menos una empresa.');
        }

        $currentUser = Session::get((string) config('auth.session_key'));
        $currentRole = is_array($currentUser) ? (string) ($currentUser['rol_codigo'] ?? 'invitado') : 'invitado';
        if ($currentRole === 'administrador') {
            $targetRoleName = (string) ($this->model->findRolNombreById($rolId) ?? '');
            $targetRoleNormalized = strtolower(trim(str_replace([' ', '-'], '_', $targetRoleName)));
            if (!in_array($targetRoleNormalized, ['cliente', 'clientes'], true)) {
                throw new RuntimeException('Como administrador solo puedes asignar usuarios con rol Cliente.');
            }
        }

        $this->model->syncUsuarioEmpresasRol($usuarioId, $rolId, array_values(array_unique($empresaIds)));
    }

    public function rolesData(): array
    {
        $roles = $this->model->roles();
        $permisos = $this->model->permisos();

        foreach ($roles as &$rol) {
            $rol['permiso_ids'] = $this->model->rolePermissionIds((int) ($rol['id'] ?? 0));
        }

        return [
            'roles' => $roles,
            'permisos' => $permisos,
            'modulosMatriz' => array_keys(self::MODULOS_SISTEMA),
            'accionesMatriz' => self::ACCIONES_ESTANDAR,
        ];
    }

    public function createRol(array $input): int
    {
        $nombre = trim((string) ($input['nombre'] ?? ''));
        if ($nombre === '') {
            throw new RuntimeException('El nombre del rol es obligatorio.');
        }

        return $this->model->createRol([
            'nombre' => $nombre,
            'descripcion' => trim((string) ($input['descripcion'] ?? '')),
        ]);
    }

    public function updateRol(int $id, array $input): void
    {
        $nombre = trim((string) ($input['nombre'] ?? ''));
        if ($nombre === '') {
            throw new RuntimeException('El nombre del rol es obligatorio.');
        }

        $ok = $this->model->updateRol($id, [
            'nombre' => $nombre,
            'descripcion' => trim((string) ($input['descripcion'] ?? '')),
        ]);

        if ($ok !== true) {
            throw new RuntimeException('No se pudo actualizar el rol.');
        }
    }

    public function duplicateRol(int $id, string $newName): int
    {
        $name = trim($newName);
        if ($name === '') {
            throw new RuntimeException('Debes indicar el nuevo nombre del rol duplicado.');
        }

        return $this->model->duplicateRol($id, $name);
    }

    public function syncRolPermisos(int $rolId, array $input): void
    {
        $idsRaw = $input['permiso_ids'] ?? [];
        $permisoIds = [];

        if (is_array($idsRaw)) {
            foreach ($idsRaw as $id) {
                $permisoId = (int) $id;
                if ($permisoId > 0) {
                    $permisoIds[] = $permisoId;
                }
            }
        }

        $this->model->syncRolePermisos($rolId, array_values(array_unique($permisoIds)));
    }

    public function permisosData(): array
    {
        return [
            'permisos' => $this->model->permisos(),
            'modulos' => $this->model->modulos(),
        ];
    }

    public function sincronizarPermisos(): array
    {
        $this->model->beginTransaction();

        try {
            $demoPermisosEliminados = $this->model->cleanupDemoPermisos();
            $demoModulosEliminados = $this->model->cleanupDemoModulosSinUso();

            $modulosCreados = 0;
            $permisosCreados = 0;
            $permisosActualizados = 0;

            foreach (self::MODULOS_SISTEMA as $slugModulo => $nombreModulo) {
                $modulo = $this->model->findModuloBySlug($slugModulo);
                if (!is_array($modulo)) {
                    $moduloId = $this->model->createModulo($nombreModulo, $slugModulo);
                    $modulosCreados++;
                } else {
                    $moduloId = (int) ($modulo['id'] ?? 0);
                }

                foreach (self::ACCIONES_ESTANDAR as $accion) {
                    $slugPermiso = $slugModulo . '.' . $accion;
                    $nombrePermiso = ucfirst($accion);
                    $permiso = $this->model->findPermisoBySlug($slugPermiso);

                    if (!is_array($permiso)) {
                        $this->model->createPermiso([
                            'modulo_id' => $moduloId,
                            'nombre' => $nombrePermiso,
                            'slug' => $slugPermiso,
                        ]);
                        $permisosCreados++;
                        continue;
                    }

                    $permisoId = (int) ($permiso['id'] ?? 0);
                    $permisoModuloId = (int) ($permiso['modulo_id'] ?? 0);
                    $permisoNombre = (string) ($permiso['nombre'] ?? '');

                    if ($permisoModuloId !== $moduloId || strtolower($permisoNombre) !== strtolower($nombrePermiso)) {
                        $this->model->updatePermisoById($permisoId, $moduloId, $nombrePermiso, $slugPermiso);
                        $permisosActualizados++;
                    }
                }
            }

            $this->model->commit();

            return [
                'demo_permisos_eliminados' => $demoPermisosEliminados,
                'demo_modulos_eliminados' => $demoModulosEliminados,
                'modulos_creados' => $modulosCreados,
                'permisos_creados' => $permisosCreados,
                'permisos_actualizados' => $permisosActualizados,
            ];
        } catch (\Throwable $exception) {
            $this->model->rollBack();
            throw $exception;
        }
    }

    public function createPermiso(array $input): int
    {
        $moduloId = (int) ($input['modulo_id'] ?? 0);
        $nombre = trim((string) ($input['nombre'] ?? ''));
        $slug = trim((string) ($input['slug'] ?? ''));

        if ($moduloId <= 0 || $nombre === '' || $slug === '') {
            throw new RuntimeException('Modulo, nombre y slug son obligatorios para el permiso.');
        }

        return $this->model->createPermiso([
            'modulo_id' => $moduloId,
            'nombre' => $nombre,
            'slug' => $slug,
        ]);
    }

    public function updatePermiso(int $id, array $input): void
    {
        $moduloId = (int) ($input['modulo_id'] ?? 0);
        $nombre = trim((string) ($input['nombre'] ?? ''));
        $slug = trim((string) ($input['slug'] ?? ''));

        if ($moduloId <= 0 || $nombre === '' || $slug === '') {
            throw new RuntimeException('Modulo, nombre y slug son obligatorios para el permiso.');
        }

        $ok = $this->model->updatePermiso($id, [
            'modulo_id' => $moduloId,
            'nombre' => $nombre,
            'slug' => $slug,
        ]);

        if ($ok !== true) {
            throw new RuntimeException('No se pudo actualizar el permiso.');
        }
    }
}
