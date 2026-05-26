<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Propietario;
use RuntimeException;

final class PropietarioService
{
    public function __construct(
        private readonly Propietario $model = new Propietario(),
        private readonly FileStorageService $files = new FileStorageService()
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

    public function create(int $empresaId, array $input, ?array $foto): int
    {
        $nombres = trim((string) ($input['nombres'] ?? ''));
        if ($nombres === '') {
            throw new RuntimeException('El nombre del propietario es obligatorio.');
        }

        $fotoPath = null;

        $id = $this->model->create([
            'empresa_id' => $empresaId,
            'nombres' => $nombres,
            'apellidos' => trim((string) ($input['apellidos'] ?? '')),
            'identificacion' => trim((string) ($input['identificacion'] ?? '')),
            'telefono' => trim((string) ($input['telefono'] ?? '')),
            'celular' => trim((string) ($input['celular'] ?? '')),
            'email' => trim((string) ($input['email'] ?? '')),
            'direccion' => trim((string) ($input['direccion'] ?? '')),
            'usuario_id' => null,
            'foto' => null,
            'portal_cliente_activo' => isset($input['portal_cliente_activo']) ? 1 : 0,
            'estado' => isset($input['estado']) ? (int) $input['estado'] : 1,
        ]);

        if (is_array($foto) && (int) ($foto['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $stored = $this->files->uploadImage($foto, 'propietarios', $id);
            $fotoPath = $stored['path'];
            $this->model->update($id, $empresaId, [
                'nombres' => $nombres,
                'apellidos' => trim((string) ($input['apellidos'] ?? '')),
                'identificacion' => trim((string) ($input['identificacion'] ?? '')),
                'telefono' => trim((string) ($input['telefono'] ?? '')),
                'celular' => trim((string) ($input['celular'] ?? '')),
                'email' => trim((string) ($input['email'] ?? '')),
                'direccion' => trim((string) ($input['direccion'] ?? '')),
                'foto' => $fotoPath,
                'portal_cliente_activo' => isset($input['portal_cliente_activo']) ? 1 : 0,
                'estado' => isset($input['estado']) ? (int) $input['estado'] : 1,
            ]);
        }

        return $id;
    }

    public function update(int $id, int $empresaId, array $input, ?array $foto): void
    {
        $actual = $this->find($id, $empresaId);

        if (!is_array($actual)) {
            throw new RuntimeException('Propietario no encontrado.');
        }

        $fotoPath = (string) ($actual['foto'] ?? '');
        if (is_array($foto) && (int) ($foto['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $stored = $this->files->replaceImage($foto, 'propietarios', $id, $fotoPath !== '' ? $fotoPath : null);
            $fotoPath = $stored['path'];
        }

        $this->model->update($id, $empresaId, [
            'nombres' => trim((string) ($input['nombres'] ?? '')),
            'apellidos' => trim((string) ($input['apellidos'] ?? '')),
            'identificacion' => trim((string) ($input['identificacion'] ?? '')),
            'telefono' => trim((string) ($input['telefono'] ?? '')),
            'celular' => trim((string) ($input['celular'] ?? '')),
            'email' => trim((string) ($input['email'] ?? '')),
            'direccion' => trim((string) ($input['direccion'] ?? '')),
            'foto' => $fotoPath !== '' ? $fotoPath : null,
            'portal_cliente_activo' => isset($input['portal_cliente_activo']) ? 1 : 0,
            'estado' => isset($input['estado']) ? (int) $input['estado'] : 1,
        ]);
    }
}
