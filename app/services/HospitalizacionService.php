<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Hospitalizacion;
use RuntimeException;

final class HospitalizacionService
{
    public function __construct(private readonly Hospitalizacion $model = new Hospitalizacion())
    {
    }

    public function data(int $empresaId): array
    {
        return [
            'animales' => $this->model->animalesByEmpresa($empresaId),
            'consultas' => $this->model->consultasByEmpresa($empresaId),
            'tamanos' => $this->model->tamanos(),
            'hospitalizaciones' => $this->model->listByEmpresa($empresaId),
            'fluidoterapia' => $this->model->fluidoterapiaByEmpresa($empresaId),
        ];
    }

    public function createTamano(array $input): int
    {
        $nombre = trim((string) ($input['nombre'] ?? ''));
        if ($nombre === '') {
            throw new RuntimeException('El nombre del tamano es obligatorio.');
        }

        return $this->model->createTamano([
            'nombre' => $nombre,
            'peso_min' => $this->toNullableFloat($input['peso_min'] ?? null),
            'peso_max' => $this->toNullableFloat($input['peso_max'] ?? null),
            'estado' => isset($input['estado']) ? 1 : 0,
        ]);
    }

    public function createHospitalizacion(int $empresaId, int $usuarioId, array $input): int
    {
        $animalId = (int) ($input['animal_id'] ?? 0);
        if ($animalId <= 0) {
            throw new RuntimeException('Debes seleccionar un paciente.');
        }

        $fechaIngreso = $this->normalizeDateTime((string) ($input['fecha_ingreso'] ?? ''));

        return $this->model->create([
            'empresa_id' => $empresaId,
            'animal_id' => $animalId,
            'consulta_id' => $this->toNullableInt($input['consulta_id'] ?? null),
            'fecha_ingreso' => $fechaIngreso,
            'fecha_salida' => null,
            'motivo' => trim((string) ($input['motivo'] ?? '')),
            'estado' => in_array((string) ($input['estado'] ?? 'activa'), ['activa', 'alta', 'traslado', 'cancelada'], true) ? (string) $input['estado'] : 'activa',
            'observaciones' => trim((string) ($input['observaciones'] ?? '')),
            'usuario_id' => $usuarioId,
        ]);
    }

    public function updateEstado(int $empresaId, array $input): void
    {
        $id = (int) ($input['hospitalizacion_id'] ?? 0);
        $estado = (string) ($input['estado'] ?? 'activa');

        if ($id <= 0) {
            throw new RuntimeException('Hospitalizacion invalida.');
        }

        if (!in_array($estado, ['activa', 'alta', 'traslado', 'cancelada'], true)) {
            throw new RuntimeException('Estado de hospitalizacion invalido.');
        }

        $actual = $this->model->findByEmpresa($id, $empresaId);
        if (!is_array($actual)) {
            throw new RuntimeException('No se encontro la hospitalizacion para esta empresa.');
        }

        $fechaSalida = null;
        if ($estado === 'alta' || $estado === 'traslado' || $estado === 'cancelada') {
            $fechaSalida = $this->normalizeDateTime((string) ($input['fecha_salida'] ?? ''));
        }

        $this->model->updateEstado($id, $empresaId, $estado, $fechaSalida);
    }

    public function createFluidoterapia(int $empresaId, array $input): int
    {
        $hospitalizacionId = (int) ($input['hospitalizacion_id'] ?? 0);
        if ($hospitalizacionId <= 0) {
            throw new RuntimeException('Debes seleccionar una hospitalizacion.');
        }

        $hospitalizacion = $this->model->findByEmpresa($hospitalizacionId, $empresaId);
        if (!is_array($hospitalizacion)) {
            throw new RuntimeException('La hospitalizacion no pertenece a la empresa activa.');
        }

        $mantenimiento = $this->toNullableFloat($input['mantenimiento'] ?? null);
        $rehidratacion = $this->toNullableFloat($input['rehidratacion'] ?? null);
        $formula = trim((string) ($input['formula'] ?? ''));

        $formulaResultado = null;
        if ($formula !== '' && class_exists('Symfony\\Component\\ExpressionLanguage\\ExpressionLanguage')) {
            $engine = new FormulaEngineService();
            $formulaResultado = (float) $engine->evaluate($formula, [
                'mantenimiento' => (float) ($mantenimiento ?? 0),
                'rehidratacion' => (float) ($rehidratacion ?? 0),
            ]);
        }

        return $this->model->createFluidoterapia([
            'hospitalizacion_id' => $hospitalizacionId,
            'tamano_animal_id' => $this->toNullableInt($input['tamano_animal_id'] ?? null),
            'mantenimiento' => $mantenimiento,
            'rehidratacion' => $rehidratacion,
            'formula' => $formula,
            'formulas_medicas' => $formulaResultado !== null ? (string) $formulaResultado : trim((string) ($input['formulas_medicas'] ?? '')),
            'signos_clinicos' => trim((string) ($input['signos_clinicos'] ?? '')),
            'observaciones' => trim((string) ($input['observaciones_fluidoterapia'] ?? '')),
        ]);
    }

    private function normalizeDateTime(string $value): string
    {
        $dateTime = trim($value);
        if ($dateTime === '') {
            return date('Y-m-d H:i:s');
        }

        $dateTime = str_replace('T', ' ', $dateTime);
        if (strlen($dateTime) === 16) {
            $dateTime .= ':00';
        }

        return $dateTime;
    }

    private function toNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || (int) $value <= 0) {
            return null;
        }

        return (int) $value;
    }

    private function toNullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
