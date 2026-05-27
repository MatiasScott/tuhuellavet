<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdminCatalogoAnimal;
use RuntimeException;

final class AdminCatalogoAnimalService
{
    public function __construct(
        private readonly AdminCatalogoAnimal $model = new AdminCatalogoAnimal()
    ) {
    }

    public function categoriasData(): array
    {
        return [
            'categorias' => $this->model->categorias(),
            'canToggle' => $this->model->hasEstadoColumn('categorias_animales'),
        ];
    }

    public function especiesData(): array
    {
        return [
            'especies' => $this->model->especies(),
            'categorias' => $this->model->categorias(),
            'canToggle' => $this->model->hasEstadoColumn('especies'),
        ];
    }

    public function razasData(): array
    {
        return [
            'razas' => $this->model->razas(),
            'especies' => $this->model->especies(),
            'canToggle' => $this->model->hasEstadoColumn('razas'),
        ];
    }

    public function createCategoria(array $input): int
    {
        $nombre = trim((string) ($input['nombre'] ?? ''));
        if ($nombre === '') {
            throw new RuntimeException('El nombre de la categoria es obligatorio.');
        }

        return $this->model->createCategoria($nombre);
    }

    public function updateCategoria(int $id, array $input): void
    {
        $nombre = trim((string) ($input['nombre'] ?? ''));
        if ($id <= 0 || $nombre === '') {
            throw new RuntimeException('Datos invalidos para actualizar categoria.');
        }

        $ok = $this->model->updateCategoria($id, $nombre);
        if ($ok !== true) {
            throw new RuntimeException('No se pudo actualizar la categoria.');
        }
    }

    public function createEspecie(array $input): int
    {
        $categoriaId = (int) ($input['categoria_id'] ?? 0);
        $nombre = trim((string) ($input['nombre'] ?? ''));

        if ($categoriaId <= 0 || $nombre === '') {
            throw new RuntimeException('Categoria y nombre son obligatorios para la especie.');
        }

        return $this->model->createEspecie($categoriaId, $nombre);
    }

    public function updateEspecie(int $id, array $input): void
    {
        $categoriaId = (int) ($input['categoria_id'] ?? 0);
        $nombre = trim((string) ($input['nombre'] ?? ''));

        if ($id <= 0 || $categoriaId <= 0 || $nombre === '') {
            throw new RuntimeException('Datos invalidos para actualizar especie.');
        }

        $ok = $this->model->updateEspecie($id, $categoriaId, $nombre);
        if ($ok !== true) {
            throw new RuntimeException('No se pudo actualizar la especie.');
        }
    }

    public function createRaza(array $input): int
    {
        $especieId = (int) ($input['especie_id'] ?? 0);
        $nombre = trim((string) ($input['nombre'] ?? ''));

        if ($especieId <= 0 || $nombre === '') {
            throw new RuntimeException('Especie y nombre son obligatorios para la raza.');
        }

        return $this->model->createRaza($especieId, $nombre);
    }

    public function updateRaza(int $id, array $input): void
    {
        $especieId = (int) ($input['especie_id'] ?? 0);
        $nombre = trim((string) ($input['nombre'] ?? ''));

        if ($id <= 0 || $especieId <= 0 || $nombre === '') {
            throw new RuntimeException('Datos invalidos para actualizar raza.');
        }

        $ok = $this->model->updateRaza($id, $especieId, $nombre);
        if ($ok !== true) {
            throw new RuntimeException('No se pudo actualizar la raza.');
        }
    }

    public function toggleEstado(string $table, int $id, int $estado): void
    {
        if ($id <= 0) {
            throw new RuntimeException('Registro invalido para cambio de estado.');
        }

        $ok = $this->model->setEstado($table, $id, $estado);
        if ($ok !== true) {
            throw new RuntimeException('Este catalogo no tiene columna estado. Se reutiliza esquema existente sin duplicar tablas.');
        }
    }
}
