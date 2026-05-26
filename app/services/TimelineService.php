<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Timeline;

final class TimelineService
{
    public function __construct(private readonly Timeline $model = new Timeline())
    {
    }

    public function data(int $empresaId, array $filters): array
    {
        $animalId = $this->toNullableInt($filters['animal_id'] ?? null);
        $propietarioId = $this->toNullableInt($filters['propietario_id'] ?? null);
        $fechaInicio = $this->toNullableDate($filters['fecha_inicio'] ?? null);
        $fechaFin = $this->toNullableDate($filters['fecha_fin'] ?? null);

        return [
            'animales' => $this->model->animalesByEmpresa($empresaId),
            'propietarios' => $this->model->propietariosByEmpresa($empresaId),
            'rows' => $this->model->findByFilters($empresaId, $animalId, $propietarioId, $fechaInicio, $fechaFin),
            'filters' => [
                'animal_id' => $animalId,
                'propietario_id' => $propietarioId,
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
            ],
        ];
    }

    private function toNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || (int) $value <= 0) {
            return null;
        }

        return (int) $value;
    }

    private function toNullableDate(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return trim($value);
    }
}
