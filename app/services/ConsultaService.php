<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Consulta;
use RuntimeException;

final class ConsultaService
{
    public function __construct(private readonly Consulta $model = new Consulta())
    {
    }

    public function listByEmpresa(int $empresaId): array
    {
        return $this->model->byEmpresa($empresaId);
    }

    public function create(int $empresaId, int $usuarioId, array $input): int
    {
        $animalId = (int) ($input['animal_id'] ?? 0);
        if ($animalId <= 0) {
            throw new RuntimeException('Debes seleccionar un paciente.');
        }

        $fechaConsulta = trim((string) ($input['fecha_consulta'] ?? ''));
        if ($fechaConsulta === '') {
            $fechaConsulta = date('Y-m-d H:i:s');
        } else {
            $fechaConsulta = str_replace('T', ' ', $fechaConsulta);
            if (strlen($fechaConsulta) === 16) {
                $fechaConsulta .= ':00';
            }
        }

        $motivo = trim((string) ($input['motivo_consulta'] ?? ''));
        if ($motivo === '') {
            throw new RuntimeException('El motivo de consulta es obligatorio.');
        }

        $consulta = [
            'empresa_id' => $empresaId,
            'animal_id' => $animalId,
            'veterinario_id' => $usuarioId,
            'fecha_consulta' => $fechaConsulta,
            'motivo_consulta' => $motivo,
            'anamnesis' => trim((string) ($input['anamnesis'] ?? '')),
            'antecedentes' => trim((string) ($input['antecedentes'] ?? '')),
            'diagnostico' => trim((string) ($input['diagnostico'] ?? '')),
            'tratamiento' => trim((string) ($input['tratamiento'] ?? '')),
            'recomendaciones' => trim((string) ($input['recomendaciones'] ?? '')),
            'tratamiento_clinico' => trim((string) ($input['tratamiento_clinico'] ?? '')),
            'tratamiento_casa' => trim((string) ($input['tratamiento_casa'] ?? '')),
            'observaciones' => trim((string) ($input['observaciones'] ?? '')),
            'peso' => $this->toNullableFloat($input['peso'] ?? null),
            'temperatura' => $this->toNullableFloat($input['temperatura'] ?? null),
            'frecuencia_cardiaca' => $this->toNullableFloat($input['frecuencia_cardiaca'] ?? null),
            'frecuencia_respiratoria' => $this->toNullableFloat($input['frecuencia_respiratoria'] ?? null),
            'estado' => in_array((string) ($input['estado'] ?? 'abierta'), ['abierta', 'cerrada', 'anulada'], true) ? (string) $input['estado'] : 'abierta',
        ];

        $examen = [
            'alimentacion' => trim((string) ($input['alimentacion'] ?? '')),
            'historial_reproductivo' => trim((string) ($input['historial_reproductivo'] ?? '')),
            'frecuencia_cardiaca' => $this->toNullableFloat($input['eg_frecuencia_cardiaca'] ?? null),
            'frecuencia_respiratoria' => $this->toNullableFloat($input['eg_frecuencia_respiratoria'] ?? null),
            'temperatura' => $this->toNullableFloat($input['eg_temperatura'] ?? null),
            'tiempo_llenado_capilar' => trim((string) ($input['tiempo_llenado_capilar'] ?? '')),
            'ganglios_linfaticos' => trim((string) ($input['ganglios_linfaticos'] ?? '')),
            'condicion_corporal' => trim((string) ($input['condicion_corporal'] ?? '')),
            'vomitos' => isset($input['vomitos']) ? 1 : 0,
            'diarrea' => isset($input['diarrea']) ? 1 : 0,
            'tos' => isset($input['tos']) ? 1 : 0,
        ];

        return $this->model->createWithExam($consulta, $examen);
    }

    private function toNullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
