<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Animal;
use RuntimeException;

final class AnimalService
{
    public function __construct(
        private readonly Animal $model = new Animal(),
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

    public function catalogos(int $empresaId): array
    {
        return [
            'propietarios' => $this->model->propietariosByEmpresa($empresaId),
            'especies' => $this->model->especies(),
            'razas' => $this->model->razas(),
        ];
    }

    public function create(int $empresaId, int $usuarioId, array $input, ?array $foto): int
    {
        $nombre = trim((string) ($input['nombre'] ?? ''));
        if ($nombre === '') {
            throw new RuntimeException('El nombre del paciente es obligatorio.');
        }

        $especieId = (int) ($input['especie_id'] ?? 0);
        if ($especieId <= 0) {
            throw new RuntimeException('Debes seleccionar la especie del paciente.');
        }

        $pesoActual = $this->toNullableFloat($input['peso_actual'] ?? null);

        $id = $this->model->create([
            'empresa_id' => $empresaId,
            'propietario_id' => $this->toNullableInt($input['propietario_id'] ?? null),
            'especie_id' => $especieId,
            'raza_id' => $this->toNullableInt($input['raza_id'] ?? null),
            'codigo' => trim((string) ($input['codigo'] ?? '')),
            'nombre' => $nombre,
            'sexo' => in_array((string) ($input['sexo'] ?? ''), ['macho', 'hembra'], true) ? (string) $input['sexo'] : null,
            'fecha_nacimiento' => ($input['fecha_nacimiento'] ?? '') !== '' ? (string) $input['fecha_nacimiento'] : null,
            'peso_actual' => $pesoActual,
            'color' => trim((string) ($input['color'] ?? '')),
            'microchip' => trim((string) ($input['microchip'] ?? '')),
            'foto' => null,
            'observaciones' => trim((string) ($input['observaciones'] ?? '')),
            'estado' => isset($input['estado']) ? (int) $input['estado'] : 1,
        ]);

        if (is_array($foto) && (int) ($foto['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $stored = $this->files->uploadImage($foto, 'animales', $id);
            $this->model->update($id, $empresaId, [
                'propietario_id' => $this->toNullableInt($input['propietario_id'] ?? null),
                'especie_id' => $especieId,
                'raza_id' => $this->toNullableInt($input['raza_id'] ?? null),
                'codigo' => trim((string) ($input['codigo'] ?? '')),
                'nombre' => $nombre,
                'sexo' => in_array((string) ($input['sexo'] ?? ''), ['macho', 'hembra'], true) ? (string) $input['sexo'] : null,
                'fecha_nacimiento' => ($input['fecha_nacimiento'] ?? '') !== '' ? (string) $input['fecha_nacimiento'] : null,
                'peso_actual' => $pesoActual,
                'color' => trim((string) ($input['color'] ?? '')),
                'microchip' => trim((string) ($input['microchip'] ?? '')),
                'foto' => $stored['path'],
                'observaciones' => trim((string) ($input['observaciones'] ?? '')),
                'estado' => isset($input['estado']) ? (int) $input['estado'] : 1,
            ]);
        }

        if ($pesoActual !== null) {
            $this->model->insertPesoHistorico($id, null, $pesoActual, $usuarioId, 'Peso inicial del paciente');
        }

        return $id;
    }

    public function update(int $id, int $empresaId, int $usuarioId, array $input, ?array $foto): void
    {
        $actual = $this->find($id, $empresaId);
        if (!is_array($actual)) {
            throw new RuntimeException('Paciente no encontrado.');
        }

        $especieId = (int) ($input['especie_id'] ?? 0);
        if ($especieId <= 0) {
            throw new RuntimeException('Debes seleccionar la especie del paciente.');
        }

        $pesoActual = $this->toNullableFloat($input['peso_actual'] ?? null);
        $fotoPath = (string) ($actual['foto'] ?? '');

        if (is_array($foto) && (int) ($foto['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $stored = $this->files->replaceImage($foto, 'animales', $id, $fotoPath !== '' ? $fotoPath : null);
            $fotoPath = $stored['path'];
        }

        $this->model->update($id, $empresaId, [
            'propietario_id' => $this->toNullableInt($input['propietario_id'] ?? null),
            'especie_id' => $especieId,
            'raza_id' => $this->toNullableInt($input['raza_id'] ?? null),
            'codigo' => trim((string) ($input['codigo'] ?? '')),
            'nombre' => trim((string) ($input['nombre'] ?? '')),
            'sexo' => in_array((string) ($input['sexo'] ?? ''), ['macho', 'hembra'], true) ? (string) $input['sexo'] : null,
            'fecha_nacimiento' => ($input['fecha_nacimiento'] ?? '') !== '' ? (string) $input['fecha_nacimiento'] : null,
            'peso_actual' => $pesoActual,
            'color' => trim((string) ($input['color'] ?? '')),
            'microchip' => trim((string) ($input['microchip'] ?? '')),
            'foto' => $fotoPath !== '' ? $fotoPath : null,
            'observaciones' => trim((string) ($input['observaciones'] ?? '')),
            'estado' => isset($input['estado']) ? (int) $input['estado'] : 1,
        ]);

        if ($pesoActual !== null && (float) ($actual['peso_actual'] ?? -1) !== $pesoActual) {
            $this->model->insertPesoHistorico($id, null, $pesoActual, $usuarioId, 'Actualizacion de peso desde modulo animales');
        }
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
