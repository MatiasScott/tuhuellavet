<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Diagnostico;
use RuntimeException;

final class DiagnosticoService
{
    public function __construct(private readonly Diagnostico $model = new Diagnostico())
    {
    }

    public function data(int $empresaId): array
    {
        return [
            'catalogo' => $this->model->catalogo(),
            'consultas' => $this->model->consultasByEmpresa($empresaId),
            'asignaciones' => $this->model->asignacionesByEmpresa($empresaId),
        ];
    }

    public function createCatalogo(array $input): int
    {
        $nombre = trim((string) ($input['nombre'] ?? ''));
        if ($nombre === '') {
            throw new RuntimeException('El nombre del diagnostico es obligatorio.');
        }

        $tipo = (string) ($input['tipo'] ?? 'diferencial');
        if (!in_array($tipo, ['diferencial', 'preventivo', 'definitivo'], true)) {
            throw new RuntimeException('Tipo de diagnostico invalido.');
        }

        return $this->model->createCatalogo([
            'codigo' => trim((string) ($input['codigo'] ?? '')),
            'nombre' => $nombre,
            'tipo' => $tipo,
            'descripcion' => trim((string) ($input['descripcion'] ?? '')),
            'estado' => isset($input['estado']) ? 1 : 0,
        ]);
    }

    public function asignar(int $usuarioId, array $input): int
    {
        $consultaId = (int) ($input['consulta_id'] ?? 0);
        $diagnosticoId = (int) ($input['diagnostico_id'] ?? 0);

        if ($consultaId <= 0) {
            throw new RuntimeException('Debes seleccionar una consulta.');
        }

        if ($diagnosticoId <= 0) {
            throw new RuntimeException('Debes seleccionar un diagnostico del catalogo.');
        }

        return $this->model->createAsignacion([
            'consulta_id' => $consultaId,
            'diagnostico_id' => $diagnosticoId,
            'usuario_id' => $usuarioId,
            'observacion' => trim((string) ($input['observacion'] ?? '')),
        ]);
    }
}
