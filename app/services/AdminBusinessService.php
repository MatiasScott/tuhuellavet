<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdminBusiness;
use RuntimeException;

final class AdminBusinessService
{
    public function __construct(
        private readonly AdminBusiness $model = new AdminBusiness(),
        private readonly FileStorageService $files = new FileStorageService()
    ) {
    }

    public function empresasData(): array
    {
        return [
            'empresas' => $this->model->empresas(),
            'hasColorColumns' => $this->model->hasColorColumns(),
        ];
    }

    public function createEmpresa(array $input, ?array $logo): int
    {
        $nombre = trim((string) ($input['nombre'] ?? ''));
        $tipo = (string) ($input['tipo'] ?? 'veterinaria');

        if ($nombre === '') {
            throw new RuntimeException('El nombre de la empresa es obligatorio.');
        }

        if (!in_array($tipo, ['veterinaria', 'hacienda'], true)) {
            throw new RuntimeException('Tipo de empresa invalido.');
        }

        $id = $this->model->createEmpresa([
            'nombre' => $nombre,
            'tipo' => $tipo,
            'direccion' => trim((string) ($input['direccion'] ?? '')),
            'telefono' => trim((string) ($input['telefono'] ?? '')),
            'email' => trim((string) ($input['email'] ?? '')),
            'logo' => null,
            'estado' => isset($input['estado']) ? 1 : 0,
        ]);

        if (is_array($logo) && (int) ($logo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $stored = $this->files->uploadImage($logo, 'empresas', $id);
            $this->model->updateEmpresa($id, [
                'nombre' => $nombre,
                'tipo' => $tipo,
                'direccion' => trim((string) ($input['direccion'] ?? '')),
                'telefono' => trim((string) ($input['telefono'] ?? '')),
                'email' => trim((string) ($input['email'] ?? '')),
                'logo' => $stored['path'],
                'estado' => isset($input['estado']) ? 1 : 0,
            ]);
        }

        return $id;
    }

    public function updateEmpresa(int $id, array $input, ?array $logo): void
    {
        $current = $this->model->findEmpresaById($id);
        if (!is_array($current)) {
            throw new RuntimeException('Empresa no encontrada.');
        }

        $nombre = trim((string) ($input['nombre'] ?? ''));
        $tipo = (string) ($input['tipo'] ?? 'veterinaria');

        if ($nombre === '') {
            throw new RuntimeException('El nombre de la empresa es obligatorio.');
        }

        if (!in_array($tipo, ['veterinaria', 'hacienda'], true)) {
            throw new RuntimeException('Tipo de empresa invalido.');
        }

        $logoPath = (string) ($current['logo'] ?? '');
        if (is_array($logo) && (int) ($logo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $stored = $this->files->replaceImage($logo, 'empresas', $id, $logoPath !== '' ? $logoPath : null);
            $logoPath = $stored['path'];
        }

        $ok = $this->model->updateEmpresa($id, [
            'nombre' => $nombre,
            'tipo' => $tipo,
            'direccion' => trim((string) ($input['direccion'] ?? '')),
            'telefono' => trim((string) ($input['telefono'] ?? '')),
            'email' => trim((string) ($input['email'] ?? '')),
            'logo' => $logoPath !== '' ? $logoPath : null,
            'estado' => isset($input['estado']) ? 1 : 0,
        ]);

        if ($ok !== true) {
            throw new RuntimeException('No fue posible actualizar la empresa.');
        }
    }

    public function medicamentosData(int $empresaId): array
    {
        return [
            'medicamentos' => $this->model->medicamentosByEmpresa($empresaId),
        ];
    }

    public function createMedicamento(int $empresaId, array $input, ?array $foto): int
    {
        $nombre = trim((string) ($input['nombre'] ?? ''));
        if ($nombre === '') {
            throw new RuntimeException('El nombre del medicamento es obligatorio.');
        }

        $id = $this->model->createMedicamento([
            'empresa_id' => $empresaId,
            'nombre' => $nombre,
            'descripcion' => trim((string) ($input['descripcion'] ?? '')),
            'foto' => null,
            'concentracion' => trim((string) ($input['concentracion'] ?? '')),
            'unidad' => trim((string) ($input['unidad'] ?? '')),
            'stock_actual' => (float) ($input['stock_actual'] ?? 0),
            'estado' => isset($input['estado']) ? 1 : 0,
        ]);

        if (is_array($foto) && (int) ($foto['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $stored = $this->files->uploadImage($foto, 'medicamentos', $id);
            $this->model->updateMedicamento($id, $empresaId, [
                'nombre' => $nombre,
                'descripcion' => trim((string) ($input['descripcion'] ?? '')),
                'foto' => $stored['path'],
                'concentracion' => trim((string) ($input['concentracion'] ?? '')),
                'unidad' => trim((string) ($input['unidad'] ?? '')),
                'stock_actual' => (float) ($input['stock_actual'] ?? 0),
                'estado' => isset($input['estado']) ? 1 : 0,
            ]);
        }

        return $id;
    }

    public function updateMedicamento(int $id, int $empresaId, array $input, ?array $foto): void
    {
        $current = $this->model->findMedicamentoById($id, $empresaId);
        if (!is_array($current)) {
            throw new RuntimeException('Medicamento no encontrado.');
        }

        $nombre = trim((string) ($input['nombre'] ?? ''));
        if ($nombre === '') {
            throw new RuntimeException('El nombre del medicamento es obligatorio.');
        }

        $fotoPath = (string) ($current['foto'] ?? '');
        if (is_array($foto) && (int) ($foto['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $stored = $this->files->replaceImage($foto, 'medicamentos', $id, $fotoPath !== '' ? $fotoPath : null);
            $fotoPath = $stored['path'];
        }

        $ok = $this->model->updateMedicamento($id, $empresaId, [
            'nombre' => $nombre,
            'descripcion' => trim((string) ($input['descripcion'] ?? '')),
            'foto' => $fotoPath !== '' ? $fotoPath : null,
            'concentracion' => trim((string) ($input['concentracion'] ?? '')),
            'unidad' => trim((string) ($input['unidad'] ?? '')),
            'stock_actual' => (float) ($input['stock_actual'] ?? 0),
            'estado' => isset($input['estado']) ? 1 : 0,
        ]);

        if ($ok !== true) {
            throw new RuntimeException('No fue posible actualizar el medicamento.');
        }
    }

    public function laboratoriosData(int $empresaId): array
    {
        return [
            'laboratorios' => $this->model->laboratoriosResumen($empresaId),
        ];
    }

    public function renameLaboratorio(int $empresaId, string $oldName, string $newName): int
    {
        $old = trim($oldName);
        $new = trim($newName);

        if ($old === '' || $new === '') {
            throw new RuntimeException('Debes indicar laboratorio actual y nuevo nombre.');
        }

        return $this->model->renameLaboratorio($empresaId, $old, $new);
    }

    public function tiposExamenData(int $empresaId): array
    {
        return [
            'tipos' => $this->model->tiposExamenResumen($empresaId),
        ];
    }

    public function renameTipoExamen(int $empresaId, string $oldName, string $newName): int
    {
        $old = trim($oldName);
        $new = trim($newName);

        if ($old === '' || $new === '') {
            throw new RuntimeException('Debes indicar tipo actual y nuevo nombre.');
        }

        return $this->model->renameTipoExamen($empresaId, $old, $new);
    }
}
