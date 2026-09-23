<?php

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\Database;
use App\Models\Catalog;
use Throwable;

class CatalogoController extends Controller
{
    public function index(Request $r): void
    {
        $db = Database::connection();
        $c = new Catalog();

        $this->view('admin/catalogos', [
            'title' => 'Catálogos clínicos',

            // Para formularios/selectores
            'species' => $c->species(),

            // Para administración
            'adminSpecies' => $c->adminSpecies(),
            'breeds' => $c->adminBreeds(),
            'vaccines' => $c->adminVaccines(),
            'drugs' => $c->adminDrugs(),
            'labTypes' => $c->adminLabExamTypes(),
            'procedures' => $c->adminSurgeryProcedures(),
            'units' => $c->adminMeasurementUnits(),
            'services' => $c->adminServices(),

            'categories' => $db->query(
                'SELECT id,codigo,nombre
             FROM categorias_animales
             WHERE activo=1
             ORDER BY nombre'
            )->fetchAll(),

            'success' => Session::pullFlash('success'),
            'error' => Session::pullFlash('error'),
        ]);
    }

    public function store(Request $r, string $type): void
    {
        if (!Session::validateCsrf($r->input('_token'))) {
            http_response_code(419);
            return;
        }
        try {
            $db = Database::connection();
            switch ($type) {
                case 'especie':
                    $db->prepare(
                        'INSERT INTO especies
                            (categoria_id,codigo,nombre_comun,nombre_cientifico,activo) 
                        VALUES
                            (:c,:co,:n,:nc,1)'
                    )->execute([
                        'c' => (int)$r->input('categoria_id'),
                        'co' => strtoupper(trim((string)$r->input('codigo'))),
                        'n' => trim((string)$r->input('nombre')),
                        'nc' => trim((string)$r->input('nombre_cientifico', '')) ?: null
                    ]);
                    break;
                case 'raza':
                    $db->prepare(
                        'INSERT INTO razas
                            (especie_id, nombre, descripcion, activo)
                        VALUES
                            (:e, :n, :d, 1)'
                    )->execute([
                        'e' => (int)$r->input('especie_id'),
                        'n' => trim((string)$r->input('nombre')),
                        'd' => trim((string)$r->input('descripcion', '')) ?: null,
                    ]);
                    break;
                case 'vacuna':
                    $db->prepare(
                        'INSERT INTO vacunas(nombre,descripcion,activo) VALUES(:n,:d,1)'
                    )->execute([
                        'n' => trim((string)$r->input('nombre')),
                        'd' => trim((string)$r->input('descripcion', '')) ?: null
                    ]);
                    break;
                case 'farmaco':
                    $db->prepare(
                        'INSERT INTO farmacos(nombre,descripcion,activo) VALUES(:n,:d,1)'
                    )->execute([
                        'n' => trim((string)$r->input('nombre')),
                        'd' => trim((string)$r->input('descripcion', '')) ?: null
                    ]);
                    break;
                case 'laboratorio':
                    $db->prepare(
                        'INSERT INTO tipos_examen_laboratorio(nombre,descripcion,activo) VALUES(:n,:d,1)'
                    )->execute([
                        'n' => trim((string)$r->input('nombre')),
                        'd' => trim((string)$r->input('descripcion', '')) ?: null
                    ]);
                    break;
                case 'cirugia':
                    $db->prepare(
                        'INSERT INTO procedimientos_quirurgicos(nombre,descripcion,activo) VALUES(:n,:d,1)'
                    )->execute([
                        'n' => trim((string)$r->input('nombre')),
                        'd' => trim((string)$r->input('descripcion', '')) ?: null
                    ]);
                    break;
                case 'unidad':
                    $codigo = strtoupper(trim((string)$r->input('codigo')));
                    $nombre = trim((string)$r->input('nombre'));
                    $simbolo = trim((string)$r->input('simbolo'));
                    $categoria = strtoupper(trim((string)$r->input('categoria')));

                    if ($codigo === '' || $nombre === '' || $simbolo === '') {
                        throw new \RuntimeException(
                            'Código, nombre y símbolo son obligatorios.'
                        );
                    }

                    $db->prepare(
                        'INSERT INTO unidades_medida
                            (codigo, nombre, simbolo, categoria, activo)
                        VALUES
                            (:codigo, :nombre, :simbolo, :categoria, 1)'
                    )->execute([
                        'codigo'    => $codigo,
                        'nombre'    => $nombre,
                        'simbolo'   => $simbolo,
                        'categoria' => $categoria !== '' ? $categoria : null,
                    ]);
                    break;

                case 'servicio':
                    $codigo = strtoupper(trim((string)$r->input('codigo')));
                    $nombre = trim((string)$r->input('nombre'));
                    $descripcion = trim((string)$r->input('descripcion', ''));
                    $precioBase = trim((string)$r->input('precio_base', ''));

                    if ($codigo === '' || $nombre === '') {
                        throw new \RuntimeException(
                            'Código y nombre del servicio son obligatorios.'
                        );
                    }

                    if (
                        $precioBase !== '' &&
                        (!is_numeric($precioBase) || (float)$precioBase < 0)
                    ) {
                        throw new \RuntimeException(
                            'El precio base debe ser un valor válido mayor o igual a cero.'
                        );
                    }

                    $db->prepare(
                        'INSERT INTO servicios
                            (codigo, nombre, descripcion, precio_base, activo)
                        VALUES
                            (:codigo, :nombre, :descripcion, :precio_base, 1)'
                    )->execute([
                        'codigo'      => $codigo,
                        'nombre'      => $nombre,
                        'descripcion' => $descripcion !== '' ? $descripcion : null,
                        'precio_base' => $precioBase !== ''
                            ? number_format((float)$precioBase, 2, '.', '')
                            : null,
                    ]);
                    break;
                default:
                    throw new \RuntimeException('Catálogo no soportado.');
            }
            (new \App\Services\AuditService())->log(auth_id(), active_environment_id(), 'CATALOGOS', 'CREAR', null, null, null, ['tipo' => $type]);
            Session::flash('success', 'Catálogo actualizado.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/admin/catalogos');
    }

    public function update(Request $r, string $type, string $id): void
    {
        if (!Session::validateCsrf($r->input('_token'))) {
            http_response_code(419);
            return;
        }

        $id = (int)$id;

        if ($id <= 0) {
            Session::flash('error', 'Registro inválido.');
            $this->redirect('/admin/catalogos');
            return;
        }

        try {
            $db = Database::connection();

            switch ($type) {
                case 'especie':
                    $codigo = strtoupper(trim((string)$r->input('codigo')));
                    $nombre = trim((string)$r->input('nombre'));
                    $nombreCientifico = trim(
                        (string)$r->input('nombre_cientifico', '')
                    );
                    $categoriaId = (int)$r->input('categoria_id');

                    if ($codigo === '' || $nombre === '' || $categoriaId <= 0) {
                        throw new \RuntimeException(
                            'Categoría, código y nombre son obligatorios.'
                        );
                    }

                    $stmt = $db->prepare(
                        'UPDATE especies
                     SET
                        categoria_id = :categoria,
                        codigo = :codigo,
                        nombre_comun = :nombre,
                        nombre_cientifico = :cientifico
                     WHERE id = :id'
                    );

                    $stmt->execute([
                        'categoria' => $categoriaId,
                        'codigo' => $codigo,
                        'nombre' => $nombre,
                        'cientifico' => $nombreCientifico !== ''
                            ? $nombreCientifico
                            : null,
                        'id' => $id,
                    ]);
                    break;

                case 'raza':
                    $especieId = (int)$r->input('especie_id');
                    $nombre = trim((string)$r->input('nombre'));
                    $descripcion = trim(
                        (string)$r->input('descripcion', '')
                    );

                    if ($especieId <= 0 || $nombre === '') {
                        throw new \RuntimeException(
                            'Especie y nombre son obligatorios.'
                        );
                    }

                    $stmt = $db->prepare(
                        'UPDATE razas
                     SET
                        especie_id = :especie,
                        nombre = :nombre,
                        descripcion = :descripcion
                     WHERE id = :id'
                    );

                    $stmt->execute([
                        'especie' => $especieId,
                        'nombre' => $nombre,
                        'descripcion' => $descripcion !== ''
                            ? $descripcion
                            : null,
                        'id' => $id,
                    ]);
                    break;

                case 'vacuna':
                    $nombre = trim((string)$r->input('nombre'));
                    $descripcion = trim(
                        (string)$r->input('descripcion', '')
                    );

                    if ($nombre === '') {
                        throw new \RuntimeException(
                            'El nombre de la vacuna es obligatorio.'
                        );
                    }

                    $stmt = $db->prepare(
                        'UPDATE vacunas
                     SET
                        nombre = :nombre,
                        descripcion = :descripcion
                     WHERE id = :id'
                    );

                    $stmt->execute([
                        'nombre' => $nombre,
                        'descripcion' => $descripcion !== ''
                            ? $descripcion
                            : null,
                        'id' => $id,
                    ]);
                    break;

                case 'farmaco':
                    $nombre = trim((string)$r->input('nombre'));
                    $descripcion = trim(
                        (string)$r->input('descripcion', '')
                    );

                    if ($nombre === '') {
                        throw new \RuntimeException(
                            'El nombre del fármaco es obligatorio.'
                        );
                    }

                    $stmt = $db->prepare(
                        'UPDATE farmacos
                     SET
                        nombre = :nombre,
                        descripcion = :descripcion
                     WHERE id = :id'
                    );

                    $stmt->execute([
                        'nombre' => $nombre,
                        'descripcion' => $descripcion !== ''
                            ? $descripcion
                            : null,
                        'id' => $id,
                    ]);
                    break;

                case 'laboratorio':
                    $nombre = trim((string)$r->input('nombre'));
                    $descripcion = trim(
                        (string)$r->input('descripcion', '')
                    );

                    if ($nombre === '') {
                        throw new \RuntimeException(
                            'El nombre del examen es obligatorio.'
                        );
                    }

                    $stmt = $db->prepare(
                        'UPDATE tipos_examen_laboratorio
                     SET
                        nombre = :nombre,
                        descripcion = :descripcion
                     WHERE id = :id'
                    );

                    $stmt->execute([
                        'nombre' => $nombre,
                        'descripcion' => $descripcion !== ''
                            ? $descripcion
                            : null,
                        'id' => $id,
                    ]);
                    break;

                case 'cirugia':
                    $nombre = trim((string)$r->input('nombre'));
                    $descripcion = trim(
                        (string)$r->input('descripcion', '')
                    );

                    if ($nombre === '') {
                        throw new \RuntimeException(
                            'El nombre del procedimiento es obligatorio.'
                        );
                    }

                    $stmt = $db->prepare(
                        'UPDATE procedimientos_quirurgicos
                     SET
                        nombre = :nombre,
                        descripcion = :descripcion
                     WHERE id = :id'
                    );

                    $stmt->execute([
                        'nombre' => $nombre,
                        'descripcion' => $descripcion !== ''
                            ? $descripcion
                            : null,
                        'id' => $id,
                    ]);
                    break;

                case 'unidad':
                    $codigo = strtoupper(trim((string)$r->input('codigo')));
                    $nombre = trim((string)$r->input('nombre'));
                    $simbolo = trim((string)$r->input('simbolo'));
                    $categoria = strtoupper(
                        trim((string)$r->input('categoria', ''))
                    );

                    if ($codigo === '' || $nombre === '' || $simbolo === '') {
                        throw new \RuntimeException(
                            'Código, nombre y símbolo son obligatorios.'
                        );
                    }

                    $stmt = $db->prepare(
                        'UPDATE unidades_medida
                     SET
                        codigo = :codigo,
                        nombre = :nombre,
                        simbolo = :simbolo,
                        categoria = :categoria
                     WHERE id = :id'
                    );

                    $stmt->execute([
                        'codigo' => $codigo,
                        'nombre' => $nombre,
                        'simbolo' => $simbolo,
                        'categoria' => $categoria !== '' ? $categoria : null,
                        'id' => $id,
                    ]);
                    break;

                case 'servicio':
                    $codigo = strtoupper(trim((string)$r->input('codigo')));
                    $nombre = trim((string)$r->input('nombre'));
                    $descripcion = trim(
                        (string)$r->input('descripcion', '')
                    );
                    $precioBase = trim(
                        (string)$r->input('precio_base', '')
                    );

                    if ($codigo === '' || $nombre === '') {
                        throw new \RuntimeException(
                            'Código y nombre son obligatorios.'
                        );
                    }

                    if (
                        $precioBase !== '' &&
                        (!is_numeric($precioBase) || (float)$precioBase < 0)
                    ) {
                        throw new \RuntimeException(
                            'El precio base no es válido.'
                        );
                    }

                    $stmt = $db->prepare(
                        'UPDATE servicios
                     SET
                        codigo = :codigo,
                        nombre = :nombre,
                        descripcion = :descripcion,
                        precio_base = :precio
                     WHERE id = :id'
                    );

                    $stmt->execute([
                        'codigo' => $codigo,
                        'nombre' => $nombre,
                        'descripcion' => $descripcion !== ''
                            ? $descripcion
                            : null,
                        'precio' => $precioBase !== ''
                            ? number_format((float)$precioBase, 2, '.', '')
                            : null,
                        'id' => $id,
                    ]);
                    break;

                default:
                    throw new \RuntimeException(
                        'Catálogo no soportado.'
                    );
            }

            (new \App\Services\AuditService())->log(
                auth_id(),
                active_environment_id(),
                'CATALOGOS',
                'EDITAR',
                $id,
                null,
                null,
                ['tipo' => $type]
            );

            Session::flash('success', 'Registro actualizado correctamente.');
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect('/admin/catalogos');
    }

    public function toggleStatus(
        Request $r,
        string $type,
        string $id
    ): void {
        if (!Session::validateCsrf($r->input('_token'))) {
            http_response_code(419);
            return;
        }

        $id = (int)$id;

        if ($id <= 0) {
            Session::flash('error', 'Registro inválido.');
            $this->redirect('/admin/catalogos');
            return;
        }

        try {
            $db = Database::connection();

            $tables = [
                'especie' => 'especies',
                'raza' => 'razas',
                'vacuna' => 'vacunas',
                'farmaco' => 'farmacos',
                'laboratorio' => 'tipos_examen_laboratorio',
                'cirugia' => 'procedimientos_quirurgicos',
                'unidad' => 'unidades_medida',
                'servicio' => 'servicios',
            ];

            if (!isset($tables[$type])) {
                throw new \RuntimeException(
                    'Catálogo no soportado.'
                );
            }

            /*
         * El nombre de tabla NO proviene directamente del usuario.
         * Sale exclusivamente de la lista blanca anterior.
         */
            $table = $tables[$type];

            $stmt = $db->prepare(
                "SELECT id, activo
             FROM {$table}
             WHERE id = :id
             LIMIT 1"
            );

            $stmt->execute(['id' => $id]);

            $row = $stmt->fetch();

            if (!$row) {
                throw new \RuntimeException(
                    'El registro solicitado no existe.'
                );
            }

            $newStatus = (int)$row['activo'] === 1 ? 0 : 1;

            $stmt = $db->prepare(
                "UPDATE {$table}
             SET activo = :activo
             WHERE id = :id"
            );

            $stmt->execute([
                'activo' => $newStatus,
                'id' => $id,
            ]);

            (new \App\Services\AuditService())->log(
                auth_id(),
                active_environment_id(),
                'CATALOGOS',
                $newStatus === 1 ? 'ACTIVAR' : 'DESACTIVAR',
                $id,
                null,
                null,
                [
                    'tipo' => $type,
                    'activo' => $newStatus,
                ]
            );

            Session::flash(
                'success',
                $newStatus === 1
                    ? 'Registro activado correctamente.'
                    : 'Registro desactivado correctamente.'
            );
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }

        $this->redirect('/admin/catalogos');
    }
}
