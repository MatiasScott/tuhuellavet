<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Desparasitacion;
use RuntimeException;

final class DesparasitacionService
{
    public function __construct(private readonly Desparasitacion $model = new Desparasitacion())
    {
    }

    public function data(int $empresaId): array
    {
        return [
            'animales' => $this->model->animalesByEmpresa($empresaId),
            'rows' => $this->model->listByEmpresa($empresaId),
        ];
    }

    public function create(int $empresaId, int $usuarioId, array $input): int
    {
        $animalId = (int) ($input['animal_id'] ?? 0);
        if ($animalId <= 0) {
            throw new RuntimeException('Debes seleccionar un paciente.');
        }

        $farmaco = trim((string) ($input['farmaco'] ?? ''));
        if ($farmaco === '') {
            throw new RuntimeException('El farmaco es obligatorio.');
        }

        $fecha = trim((string) ($input['fecha'] ?? ''));
        if ($fecha === '') {
            $fecha = date('Y-m-d');
        }

        return $this->model->create([
            'empresa_id' => $empresaId,
            'animal_id' => $animalId,
            'farmaco' => $farmaco,
            'dosis' => trim((string) ($input['dosis'] ?? '')),
            'fecha' => $fecha,
            'proxima_fecha' => ($input['proxima_fecha'] ?? '') !== '' ? (string) $input['proxima_fecha'] : null,
            'observacion' => trim((string) ($input['observacion'] ?? '')),
            'peso_actual' => $this->toNullableFloat($input['peso_actual'] ?? null),
            'usuario_id' => $usuarioId,
        ]);
    }

    private function toNullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
