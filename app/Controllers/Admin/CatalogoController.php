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
        $this->view('admin/catalogos', ['title' => 'Catálogos clínicos', 'species' => $c->species(), 'breeds' => $c->breeds(), 'vaccines' => $c->vaccines(), 'drugs' => $c->drugs(), 'labTypes' => $c->labExamTypes(), 'procedures' => $c->surgeryProcedures(), 'categories' => $db->query('SELECT id,codigo,nombre FROM categorias_animales WHERE activo=1 ORDER BY nombre')->fetchAll(), 'success' => Session::pullFlash('success'), 'error' => Session::pullFlash('error')]);
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
                    $db->prepare('INSERT INTO especies(categoria_id,codigo,nombre_comun,nombre_cientifico,activo) VALUES(:c,:co,:n,:nc,1)')->execute(['c' => (int)$r->input('categoria_id'), 'co' => strtoupper(trim((string)$r->input('codigo'))), 'n' => trim((string)$r->input('nombre')), 'nc' => trim((string)$r->input('nombre_cientifico', '')) ?: null]);
                    break;
                case 'raza':
                    $db->prepare('INSERT INTO razas(especie_id,nombre,descripcion,activo) VALUES(:e,:n,:d,1)')->execute(['e' => (int)$r->input('especie_id'), 'n' => trim((string)$r->input('nombre')), 'd' => trim((string)$r->input('descripcion', '')) ?: null]);
                    break;
                case 'vacuna':
                    $db->prepare('INSERT INTO vacunas(nombre,descripcion,activo) VALUES(:n,:d,1)')->execute(['n' => trim((string)$r->input('nombre')), 'd' => trim((string)$r->input('descripcion', '')) ?: null]);
                    break;
                case 'farmaco':
                    $db->prepare('INSERT INTO farmacos(nombre,descripcion,activo) VALUES(:n,:d,1)')->execute(['n' => trim((string)$r->input('nombre')), 'd' => trim((string)$r->input('descripcion', '')) ?: null]);
                    break;
                case 'laboratorio':
                    $db->prepare('INSERT INTO tipos_examen_laboratorio(nombre,descripcion,activo) VALUES(:n,:d,1)')->execute(['n' => trim((string)$r->input('nombre')), 'd' => trim((string)$r->input('descripcion', '')) ?: null]);
                    break;
                case 'cirugia':
                    $db->prepare('INSERT INTO procedimientos_quirurgicos(nombre,descripcion,activo) VALUES(:n,:d,1)')->execute(['n' => trim((string)$r->input('nombre')), 'd' => trim((string)$r->input('descripcion', '')) ?: null]);
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
}
