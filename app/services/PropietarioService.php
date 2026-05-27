<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Propietario;
use RuntimeException;
use Throwable;

final class PropietarioService
{
    public function __construct(
        private readonly Propietario $model = new Propietario(),
        private readonly FileStorageService $files = new FileStorageService(),
        private readonly AdminAccessService $access = new AdminAccessService()
    ) {
    }

    public function listByEmpresa(int $empresaId): array
    {
        return $this->model->byEmpresa($empresaId);
    }

    public function find(int $id, int $empresaId): ?array
    {
        return $this->model->find($id, $empresaId);
    }

    public function create(int $empresaId, array $input, ?array $foto): array
    {
        $nombres = trim((string) ($input['nombres'] ?? ''));
        if ($nombres === '') {
            throw new RuntimeException('El nombre del propietario es obligatorio.');
        }

        $portalClienteActivo = isset($input['portal_cliente_activo']) ? 1 : 0;
        $email = trim((string) ($input['email'] ?? ''));
        $portalData = [
            'usuario_id' => null,
            'usuario_creado' => false,
            'clave_temporal' => null,
        ];

        $this->model->beginTransaction();

        try {
            if ($portalClienteActivo === 1) {
                $portalData = $this->syncClientePortal($empresaId, $input, null);
            }

            $fotoPath = null;
            $id = $this->model->create([
                'empresa_id' => $empresaId,
                'nombres' => $nombres,
                'apellidos' => trim((string) ($input['apellidos'] ?? '')),
                'identificacion' => trim((string) ($input['identificacion'] ?? '')),
                'telefono' => trim((string) ($input['telefono'] ?? '')),
                'celular' => trim((string) ($input['celular'] ?? '')),
                'email' => $email,
                'direccion' => trim((string) ($input['direccion'] ?? '')),
                'usuario_id' => $portalData['usuario_id'],
                'foto' => null,
                'portal_cliente_activo' => $portalClienteActivo,
                'estado' => isset($input['estado']) ? (int) $input['estado'] : 1,
            ]);

            if (is_array($foto) && (int) ($foto['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $stored = $this->files->uploadImage($foto, 'propietarios', $id);
                $fotoPath = $stored['path'];
                $updated = $this->model->update($id, $empresaId, [
                    'nombres' => $nombres,
                    'apellidos' => trim((string) ($input['apellidos'] ?? '')),
                    'identificacion' => trim((string) ($input['identificacion'] ?? '')),
                    'telefono' => trim((string) ($input['telefono'] ?? '')),
                    'celular' => trim((string) ($input['celular'] ?? '')),
                    'email' => $email,
                    'direccion' => trim((string) ($input['direccion'] ?? '')),
                    'usuario_id' => $portalData['usuario_id'],
                    'foto' => $fotoPath,
                    'portal_cliente_activo' => $portalClienteActivo,
                    'estado' => isset($input['estado']) ? (int) $input['estado'] : 1,
                ]);

                if ($updated !== true) {
                    throw new RuntimeException('No fue posible actualizar la foto del propietario.');
                }
            }

            $this->model->commit();

            return [
                'propietario_id' => $id,
                'usuario_id' => $portalData['usuario_id'],
                'usuario_creado' => (bool) $portalData['usuario_creado'],
                'clave_temporal' => $portalData['clave_temporal'],
                'portal_cliente_activo' => $portalClienteActivo,
            ];
        } catch (Throwable $exception) {
            $this->model->rollBack();
            throw $exception;
        }
    }

    public function update(int $id, int $empresaId, array $input, ?array $foto): void
    {
        $actual = $this->find($id, $empresaId);

        if (!is_array($actual)) {
            throw new RuntimeException('Propietario no encontrado.');
        }

        $nombres = trim((string) ($input['nombres'] ?? ''));
        if ($nombres === '') {
            throw new RuntimeException('El nombre del propietario es obligatorio.');
        }

        $portalClienteActivo = isset($input['portal_cliente_activo']) ? 1 : 0;
        $usuarioId = (int) ($actual['usuario_id'] ?? 0);

        $this->model->beginTransaction();

        try {
            if ($portalClienteActivo === 1) {
                $portalData = $this->syncClientePortal($empresaId, $input, $usuarioId > 0 ? $usuarioId : null);
                $usuarioId = (int) ($portalData['usuario_id'] ?? $usuarioId);
            }

        $fotoPath = (string) ($actual['foto'] ?? '');
        $fotoAnterior = $fotoPath;
        $seSubioFotoNueva = false;
        if (is_array($foto) && (int) ($foto['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $stored = $this->files->replaceImage($foto, 'propietarios', $id, $fotoPath !== '' ? $fotoPath : null);
            $fotoPath = $stored['path'];
            $seSubioFotoNueva = true;
        }

        $updated = $this->model->update($id, $empresaId, [
            'nombres' => $nombres,
            'apellidos' => trim((string) ($input['apellidos'] ?? '')),
            'identificacion' => trim((string) ($input['identificacion'] ?? '')),
            'telefono' => trim((string) ($input['telefono'] ?? '')),
            'celular' => trim((string) ($input['celular'] ?? '')),
            'email' => trim((string) ($input['email'] ?? '')),
            'direccion' => trim((string) ($input['direccion'] ?? '')),
            'usuario_id' => $usuarioId > 0 ? $usuarioId : null,
            'foto' => $fotoPath !== '' ? $fotoPath : null,
            'portal_cliente_activo' => $portalClienteActivo,
            'estado' => isset($input['estado']) ? (int) $input['estado'] : 1,
        ]);

        if ($updated !== true) {
            throw new RuntimeException('No fue posible actualizar el propietario. Intenta nuevamente.');
        }

        if ($seSubioFotoNueva) {
            $verificacion = $this->find($id, $empresaId);
            if (!is_array($verificacion) || (string) ($verificacion['foto'] ?? '') !== $fotoPath || $fotoPath === $fotoAnterior) {
                throw new RuntimeException('La foto no se pudo actualizar correctamente. Verifica permisos de escritura en storage/uploads/propietarios.');
            }
        }

            $this->model->commit();
        } catch (Throwable $exception) {
            $this->model->rollBack();
            throw $exception;
        }
    }

    private function syncClientePortal(int $empresaId, array $input, ?int $existingUsuarioId): array
    {
        $email = trim((string) ($input['email'] ?? ''));
        if ($email === '') {
            throw new RuntimeException('El correo es obligatorio para activar el portal cliente.');
        }

        $rolCliente = $this->access->findRolByCodigo('cliente');
        if (!is_array($rolCliente) || (int) ($rolCliente['id'] ?? 0) <= 0) {
            throw new RuntimeException('No se encontro el rol Cliente en el sistema.');
        }

        $usuarioId = 0;
        $usuarioCreado = false;
        $claveTemporal = null;

        $usuarioPorEmail = $this->access->findUsuarioByEmail($email);
        if ($existingUsuarioId !== null && $existingUsuarioId > 0) {
            $usuarioActual = $this->access->findUsuarioById($existingUsuarioId);
            if (!is_array($usuarioActual)) {
                throw new RuntimeException('El usuario asociado al propietario ya no existe.');
            }

            if (is_array($usuarioPorEmail) && (int) ($usuarioPorEmail['id'] ?? 0) !== $existingUsuarioId) {
                throw new RuntimeException('El correo ya esta registrado en otro usuario.');
            }

            $ok = $this->access->updateUsuario($existingUsuarioId, [
                'nombres' => trim((string) ($input['nombres'] ?? '')),
                'apellidos' => trim((string) ($input['apellidos'] ?? '')),
                'email' => $email,
                'telefono' => trim((string) ($input['celular'] ?? ($input['telefono'] ?? ''))),
                'estado' => 1,
            ]);

            if ($ok !== true) {
                throw new RuntimeException('No se pudo actualizar el usuario del portal cliente.');
            }

            $usuarioId = $existingUsuarioId;
        } elseif (is_array($usuarioPorEmail)) {
            $usuarioId = (int) ($usuarioPorEmail['id'] ?? 0);
            if ($usuarioId <= 0) {
                throw new RuntimeException('No se pudo resolver el usuario cliente existente.');
            }

            $ok = $this->access->updateUsuario($usuarioId, [
                'nombres' => trim((string) ($input['nombres'] ?? '')),
                'apellidos' => trim((string) ($input['apellidos'] ?? '')),
                'email' => $email,
                'telefono' => trim((string) ($input['celular'] ?? ($input['telefono'] ?? ''))),
                'estado' => 1,
            ]);

            if ($ok !== true) {
                throw new RuntimeException('No se pudo sincronizar el usuario cliente existente.');
            }
        } else {
            $claveTemporal = $this->generateTemporaryPassword();
            $usuarioId = $this->access->createUsuario([
                'nombres' => trim((string) ($input['nombres'] ?? '')),
                'apellidos' => trim((string) ($input['apellidos'] ?? '')),
                'email' => $email,
                'password' => password_hash($claveTemporal, PASSWORD_DEFAULT),
                'telefono' => trim((string) ($input['celular'] ?? ($input['telefono'] ?? ''))),
                'estado' => 1,
            ]);
            $usuarioCreado = true;
        }

        $this->access->assignUsuarioEmpresaRol($usuarioId, $empresaId, (int) ($rolCliente['id'] ?? 0));

        return [
            'usuario_id' => $usuarioId,
            'usuario_creado' => $usuarioCreado,
            'clave_temporal' => $claveTemporal,
        ];
    }

    private function generateTemporaryPassword(): string
    {
        return strtoupper(substr(bin2hex(random_bytes(6)), 0, 12));
    }
}
