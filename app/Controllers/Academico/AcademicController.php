<?php

namespace App\Controllers\Academico;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\Database;
use App\Models\Academic;
use App\Models\Catalog;
use Throwable;

class AcademicController extends Controller
{
    public function index(Request $r): void
    {
        $roles = auth_user()['roles'] ?? [];
        $teacher = in_array('DOCENTE', $roles, true) || !empty(auth_user()['is_super_admin']);
        $m = new Academic();
        $this->view('academico/index', ['title' => 'Aula clínica', 'courses' => $m->courses(active_environment_id()), 'periods' => $m->periods(), 'subjects' => $m->subjects(), 'cases' => $m->cases(active_environment_id()), 'assignments' => $m->assignments(auth_id()), 'users' => (new Catalog())->users(), 'isTeacher' => $teacher, 'success' => Session::pullFlash('success'), 'error' => Session::pullFlash('error')]);
    }
    public function period(Request $r): void
    {
        $this->act($r, function () use ($r) {
            Database::connection()->prepare('INSERT INTO periodos_academicos(codigo,nombre,fecha_inicio,fecha_fin,activo) VALUES(:c,:n,:i,:f,1)')->execute(['c' => strtoupper(trim((string)$r->input('codigo'))), 'n' => trim((string)$r->input('nombre')), 'i' => $r->input('fecha_inicio'), 'f' => $r->input('fecha_fin')]);
        }, 'Periodo creado.');
    }
    public function subject(Request $r): void
    {
        $this->act($r, function () use ($r) {
            Database::connection()->prepare('INSERT INTO asignaturas(codigo,nombre,descripcion,activo) VALUES(:c,:n,:d,1)')->execute(['c' => strtoupper(trim((string)$r->input('codigo'))), 'n' => trim((string)$r->input('nombre')), 'd' => trim((string)$r->input('descripcion', '')) ?: null]);
        }, 'Asignatura creada.');
    }
    public function course(Request $r): void
    {
        $this->act($r, function () use ($r) {
            Database::connection()->prepare('INSERT INTO cursos_academicos(entorno_id,asignatura_id,periodo_academico_id,codigo_seccion,activo) VALUES(:e,:a,:p,:s,1)')->execute(['e' => active_environment_id(), 'a' => (int)$r->input('asignatura_id'), 'p' => (int)$r->input('periodo_academico_id'), 's' => trim((string)$r->input('codigo_seccion', 'GENERAL'))]);
        }, 'Curso creado.');
    }
    public function case(Request $r): void
    {
        $this->act($r, function () use ($r) {
            $db = Database::connection();
            $db->prepare('INSERT INTO casos_clinicos_academicos(curso_id,titulo,descripcion,instrucciones,creado_por,fecha_disponible,fecha_limite,activo) VALUES(:c,:t,:d,:i,:u,:fd,:fl,1)')->execute(['c' => (int)$r->input('curso_id'), 't' => trim((string)$r->input('titulo')), 'd' => trim((string)$r->input('descripcion')), 'i' => trim((string)$r->input('instrucciones', '')) ?: null, 'u' => auth_id(), 'fd' => $r->input('fecha_disponible') ?: null, 'fl' => $r->input('fecha_limite') ?: null]);
        }, 'Caso clínico creado.');
    }
    public function enroll(Request $r, string $id): void
    {
        $this->act($r, function () use ($r, $id) {
            $type = (string)$r->input('tipo');
            $table = $type === 'DOCENTE' ? 'curso_docentes' : 'curso_estudiantes';
            $sql = $type === 'DOCENTE' ? 'INSERT IGNORE INTO curso_docentes(curso_id,usuario_id) VALUES(:c,:u)' : 'INSERT IGNORE INTO curso_estudiantes(curso_id,usuario_id,activo) VALUES(:c,:u,1)';
            Database::connection()->prepare($sql)->execute(['c' => (int)$id, 'u' => (int)$r->input('usuario_id')]);
        }, 'Usuario asignado al curso.');
    }
    private function act(Request $r, callable $fn, string $ok): void
    {
        if (!Session::validateCsrf($r->input('_token'))) {
            http_response_code(419);
            return;
        }
        try {
            $fn();
            Session::flash('success', $ok);
        } catch (Throwable $e) {
            Session::flash('error', $e->getMessage());
        }
        $this->redirect('/academico');
    }
}
