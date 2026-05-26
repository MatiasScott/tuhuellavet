<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Vacuna;
use RuntimeException;

final class VacunaService
{
    public function __construct(private readonly Vacuna $model = new Vacuna())
    {
    }

    public function data(int $empresaId): array
    {
        return [
            'catalogo' => $this->model->catalogoByEmpresa($empresaId),
            'animales' => $this->model->animalesByEmpresa($empresaId),
            'consultas' => $this->model->consultasByEmpresa($empresaId),
            'aplicaciones' => $this->model->aplicacionesByEmpresa($empresaId),
        ];
    }

    public function createCatalogo(int $empresaId, array $input): int
    {
        $nombre = trim((string) ($input['nombre'] ?? ''));
        if ($nombre === '') {
            throw new RuntimeException('El nombre de la vacuna es obligatorio.');
        }

        $descripcion = trim((string) ($input['descripcion'] ?? ''));

        $catalogoId = $this->model->createCatalogo([
            'empresa_id' => $empresaId,
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'estado' => isset($input['estado']) ? 1 : 0,
        ]);

        // Se guarda tambien en la tabla legacy vacunas para compatibilidad con FK vacuna_id.
        $legacyId = $this->model->createLegacyVacuna([
            'empresa_id' => $empresaId,
            'nombre' => $nombre,
            'descripcion' => $descripcion,
        ]);

        return $legacyId > 0 ? $catalogoId : 0;
    }

    public function aplicar(int $usuarioId, int $empresaId, array $input): int
    {
        $animalId = (int) ($input['animal_id'] ?? 0);
        $catalogoVacunaId = (int) ($input['catalogo_vacuna_id'] ?? 0);

        if ($animalId <= 0) {
            throw new RuntimeException('Debes seleccionar un paciente.');
        }

        if ($catalogoVacunaId <= 0) {
            throw new RuntimeException('Debes seleccionar una vacuna del catalogo.');
        }

        $fechaAplicacion = trim((string) ($input['fecha_aplicacion'] ?? ''));
        if ($fechaAplicacion === '') {
            $fechaAplicacion = date('Y-m-d');
        }

        $vacunaId = (int) ($input['vacuna_id'] ?? 0);
        if ($vacunaId <= 0) {
            // fallback de compatibilidad: usa el id de catalogo para poblar columna legacy si no se envia.
            $vacunaId = $catalogoVacunaId;
        }

        return $this->model->createAplicacion([
            'animal_id' => $animalId,
            'vacuna_id' => $vacunaId,
            'catalogo_vacuna_id' => $catalogoVacunaId,
            'dosis' => trim((string) ($input['dosis'] ?? '')),
            'laboratorio' => trim((string) ($input['laboratorio'] ?? '')),
            'lote' => trim((string) ($input['lote'] ?? '')),
            'consulta_id' => $this->toNullableInt($input['consulta_id'] ?? null),
            'fecha_aplicacion' => $fechaAplicacion,
            'proxima_aplicacion' => ($input['proxima_aplicacion'] ?? '') !== '' ? (string) $input['proxima_aplicacion'] : null,
            'observaciones' => trim((string) ($input['observaciones'] ?? '')),
            'usuario_id' => $usuarioId,
        ]);
    }

    private function toNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '' || (int) $value <= 0) {
            return null;
        }

        return (int) $value;
    }
}
