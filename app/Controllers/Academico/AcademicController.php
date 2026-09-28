<?php

namespace App\Controllers\Academico;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\Database;
use App\Models\Academic;
use App\Models\Catalog;
use RuntimeException;
use Throwable;

class AcademicController extends Controller
{
    public function index(Request $r): void
    {
        $user = auth_user();
        $roles = $user['roles'] ?? [];

        $userId = auth_id();
        $envId  = active_environment_id();

        $isSuperAdmin = !empty($user['is_super_admin']);
        $isTeacher    = in_array('DOCENTE', $roles, true);
        $isStudent    = in_array('ESTUDIANTE', $roles, true);

        $model = new Academic();

        /*
         * SUPER ADMINISTRADOR
         * Puede visualizar toda la información del entorno académico.
         */
        if ($isSuperAdmin) {
            $courses = $model->courses($envId);
            $cases   = $model->cases($envId);
        }

        /*
         * DOCENTE
         * Únicamente ve cursos donde está asignado y sus casos.
         */ elseif ($isTeacher) {
            $courses = $model->teacherCourses($envId, $userId);
            $cases   = $model->teacherCases($envId, $userId);
        }

        /*
         * ESTUDIANTE
         * Únicamente ve cursos donde está matriculado y casos disponibles.
         */ elseif ($isStudent) {
            $courses = $model->studentCourses($envId, $userId);
            $cases   = $model->studentCases($envId, $userId);
        }

        /*
         * Cualquier otro rol que consiguiera llegar hasta aquí
         * no recibe información académica.
         */ else {
            $courses = [];
            $cases   = [];
        }

        /*
         * Solamente SUPER ADMINISTRADOR y DOCENTE necesitan
         * catálogos administrativos.
         */
        $periods = $isSuperAdmin
            ? $model->periods()
            : [];

        $subjects = $isSuperAdmin
            ? $model->subjects()
            : [];

        /*
 * Super Administrador necesita usuarios para asignar docentes.
 * Docente necesita usuarios para asignar estudiantes.
 */
        $catalog = new Catalog();

        if ($isSuperAdmin) {
            /*
     * El Super Administrador únicamente necesita
     * usuarios con rol DOCENTE dentro del entorno activo.
     */
            $users = $catalog->academicUsersByRole(
                $envId,
                'DOCENTE'
            );
        } elseif ($isTeacher) {
            /*
     * El docente únicamente puede asignar usuarios
     * que ya sean ESTUDIANTES del entorno activo.
     */
            $users = $catalog->academicUsersByRole(
                $envId,
                'ESTUDIANTE'
            );
        } else {
            $users = [];
        }

        /*
         * Las entregas mostradas pertenecen exclusivamente
         * al usuario autenticado.
         */
        $assignments = $model->assignments($userId);

        $this->view(
            'academico/index',
            [
                'title'       => 'Aula clínica',
                'courses'     => $courses,
                'periods'     => $periods,
                'subjects'    => $subjects,
                'cases'       => $cases,
                'assignments' => $assignments,
                'users'       => $users,

                /*
                 * Conservamos isTeacher porque la vista actual
                 * ya utiliza esta variable para mostrar herramientas.
                 */
                'isSuperAdmin' => $isSuperAdmin,
                'isTeacher'    => $isTeacher,
                'isStudent'    => $isStudent,

                'success' => Session::pullFlash('success'),
                'error'   => Session::pullFlash('error'),
            ],
            'layouts/academico'
        );
    }

    public function period(Request $r): void
    {
        $this->act(
            $r,
            function () use ($r) {
                $this->requireAcademicManager();

                Database::connection()
                    ->prepare(
                        'INSERT INTO periodos_academicos
                            (codigo, nombre, fecha_inicio, fecha_fin, activo)
                         VALUES
                            (:codigo, :nombre, :inicio, :fin, 1)'
                    )
                    ->execute([
                        'codigo' => strtoupper(
                            trim((string) $r->input('codigo'))
                        ),
                        'nombre' => trim(
                            (string) $r->input('nombre')
                        ),
                        'inicio' => $r->input('fecha_inicio'),
                        'fin'    => $r->input('fecha_fin'),
                    ]);
            },
            'Periodo creado.'
        );
    }

    public function subject(Request $r): void
    {
        $this->act(
            $r,
            function () use ($r) {
                $this->requireAcademicManager();

                Database::connection()
                    ->prepare(
                        'INSERT INTO asignaturas
                            (codigo, nombre, descripcion, activo)
                         VALUES
                            (:codigo, :nombre, :descripcion, 1)'
                    )
                    ->execute([
                        'codigo' => strtoupper(
                            trim((string) $r->input('codigo'))
                        ),
                        'nombre' => trim(
                            (string) $r->input('nombre')
                        ),
                        'descripcion' => trim(
                            (string) $r->input('descripcion', '')
                        ) ?: null,
                    ]);
            },
            'Asignatura creada.'
        );
    }

    public function course(Request $r): void
    {
        $this->act(
            $r,
            function () use ($r) {
                $this->requireAcademicManager();

                Database::connection()
                    ->prepare(
                        'INSERT INTO cursos_academicos
                            (
                                entorno_id,
                                asignatura_id,
                                periodo_academico_id,
                                codigo_seccion,
                                activo
                            )
                         VALUES
                            (
                                :entorno,
                                :asignatura,
                                :periodo,
                                :seccion,
                                1
                            )'
                    )
                    ->execute([
                        'entorno'    => active_environment_id(),
                        'asignatura' => (int) $r->input('asignatura_id'),
                        'periodo'    => (int) $r->input('periodo_academico_id'),
                        'seccion'    => trim(
                            (string) $r->input(
                                'codigo_seccion',
                                'GENERAL'
                            )
                        ),
                    ]);
            },
            'Curso creado.'
        );
    }

    public function case(Request $r): void
    {
        $this->act(
            $r,
            function () use ($r) {
                $courseId = (int) $r->input('curso_id');

                $this->requireCourseManagement($courseId);

                Database::connection()
                    ->prepare(
                        'INSERT INTO casos_clinicos_academicos
                            (
                                curso_id,
                                titulo,
                                descripcion,
                                instrucciones,
                                creado_por,
                                fecha_disponible,
                                fecha_limite,
                                activo
                            )
                         VALUES
                            (
                                :curso,
                                :titulo,
                                :descripcion,
                                :instrucciones,
                                :usuario,
                                :disponible,
                                :limite,
                                1
                            )'
                    )
                    ->execute([
                        'curso' => $courseId,
                        'titulo' => trim(
                            (string) $r->input('titulo')
                        ),
                        'descripcion' => trim(
                            (string) $r->input('descripcion')
                        ),
                        'instrucciones' => trim(
                            (string) $r->input('instrucciones', '')
                        ) ?: null,
                        'usuario' => auth_id(),
                        'disponible' => $r->input('fecha_disponible') ?: null,
                        'limite'     => $r->input('fecha_limite') ?: null,
                    ]);
            },
            'Caso clínico creado.'
        );
    }

    public function enroll(Request $r, string $id): void
    {
        $this->act(
            $r,
            function () use ($r, $id) {
                $courseId = (int) $id;

                if ($courseId <= 0) {
                    throw new RuntimeException(
                        'Curso académico no válido.'
                    );
                }

                $user = auth_user();
                $isSuperAdmin = !empty($user['is_super_admin']);

                $type = strtoupper(
                    trim((string) $r->input('tipo'))
                );

                if (!in_array($type, ['DOCENTE', 'ESTUDIANTE'], true)) {
                    throw new RuntimeException(
                        'Tipo de asignación académica no válido.'
                    );
                }

                /*
             * SUPER ADMINISTRADOR:
             * puede asignar únicamente DOCENTES.
             */
                if ($isSuperAdmin) {
                    if ($type !== 'DOCENTE') {
                        throw new RuntimeException(
                            'El Super Administrador utiliza esta operación para asignar docentes.'
                        );
                    }

                    $this->requireCourseManagement($courseId);
                }

                /*
             * DOCENTE:
             * puede asignar únicamente ESTUDIANTES
             * y solamente dentro de sus propios cursos.
             */ else {
                    if ($type !== 'ESTUDIANTE') {
                        throw new RuntimeException(
                            'El docente solo puede asignar estudiantes.'
                        );
                    }

                    $this->requireCourseManagement($courseId);
                }

                $userId = (int) $r->input('usuario_id');

                if ($userId <= 0) {
                    throw new RuntimeException(
                        'Selecciona un usuario válido.'
                    );
                }

                if (
                    !$this->userHasEnvironmentRole(
                        $userId,
                        active_environment_id(),
                        $type
                    )
                ) {
                    throw new RuntimeException(
                        $type === 'DOCENTE'
                            ? 'El usuario seleccionado no es un docente activo de este entorno.'
                            : 'El usuario seleccionado no es un estudiante activo de este entorno.'
                    );
                }

                $db = Database::connection();

                if ($type === 'DOCENTE') {
                    $db->prepare(
                        'INSERT IGNORE INTO curso_docentes
                        (curso_id, usuario_id)
                     VALUES
                        (:curso, :usuario)'
                    )->execute([
                        'curso'   => $courseId,
                        'usuario' => $userId,
                    ]);

                    return;
                }

                $db->prepare(
                    'INSERT INTO curso_estudiantes
                    (curso_id, usuario_id, activo)
                 VALUES
                    (:curso, :usuario, 1)
                 ON DUPLICATE KEY UPDATE
                    activo = 1'
                )->execute([
                    'curso'   => $courseId,
                    'usuario' => $userId,
                ]);
            },
            'Usuario asignado al curso.'
        );
    }

    /**
     * Periodos, asignaturas y creación de cursos.
     *
     * Por ahora permitidos a:
     * - SUPER_ADMINISTRADOR
     * - DOCENTE
     *
     * El middleware permission:* continúa siendo una
     * segunda capa de autorización.
     */
    private function requireAcademicManager(): void
    {
        $user = auth_user();

        if (empty($user['is_super_admin'])) {
            throw new RuntimeException(
                'Esta operación está reservada al Super Administrador.'
            );
        }
    }

    /**
     * Valida operaciones relacionadas con un curso específico.
     *
     * SUPER_ADMINISTRADOR:
     * puede administrar cualquier curso del entorno.
     *
     * DOCENTE:
     * únicamente puede administrar cursos donde esté asignado.
     */
    private function requireCourseManagement(int $courseId): void
    {
        if ($courseId <= 0) {
            throw new RuntimeException(
                'Curso académico no válido.'
            );
        }

        $user  = auth_user();
        $roles = $user['roles'] ?? [];

        $isSuperAdmin = !empty($user['is_super_admin']);

        if ($isSuperAdmin) {
            /*
             * Incluso el SUPER ADMINISTRADOR debe trabajar
             * únicamente dentro del entorno activo.
             */
            $stmt = Database::connection()->prepare(
                'SELECT 1
                 FROM cursos_academicos
                 WHERE id = :curso
                   AND entorno_id = :entorno
                   AND activo = 1
                 LIMIT 1'
            );

            $stmt->execute([
                'curso'   => $courseId,
                'entorno' => active_environment_id(),
            ]);

            if (!$stmt->fetchColumn()) {
                throw new RuntimeException(
                    'El curso no pertenece al entorno activo.'
                );
            }

            return;
        }

        if (!in_array('DOCENTE', $roles, true)) {
            throw new RuntimeException(
                'No tienes autorización para administrar este curso.'
            );
        }

        $model = new Academic();

        if (
            !$model->teacherOwnsCourse(
                $courseId,
                auth_id(),
                active_environment_id()
            )
        ) {
            throw new RuntimeException(
                'No tienes autorización para administrar este curso.'
            );
        }
    }



    private function act(
        Request $r,
        callable $fn,
        string $ok
    ): void {
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
