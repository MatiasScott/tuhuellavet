<?php

namespace App\Models;

use App\Core\Model;

class Academic extends Model
{
    /**
     * Todos los cursos activos de un entorno.
     *
     * Uso:
     * - Super Administrador.
     * - Operaciones internas que realmente necesiten el catálogo completo.
     */
    public function courses(int $env): array
    {
        $s = $this->db->prepare(
            'SELECT
                c.*,
                a.codigo AS asignatura_codigo,
                a.nombre AS asignatura,
                p.nombre AS periodo
             FROM cursos_academicos c
             INNER JOIN asignaturas a
                ON a.id = c.asignatura_id
             INNER JOIN periodos_academicos p
                ON p.id = c.periodo_academico_id
             WHERE c.entorno_id = :entorno
               AND c.activo = 1
             ORDER BY
                p.fecha_inicio DESC,
                a.nombre'
        );

        $s->execute([
            'entorno' => $env,
        ]);

        return $s->fetchAll();
    }

    /**
     * Cursos asignados a un docente.
     */
    public function teacherCourses(int $env, int $userId): array
    {
        $s = $this->db->prepare(
            'SELECT
                c.*,
                a.codigo AS asignatura_codigo,
                a.nombre AS asignatura,
                p.nombre AS periodo
             FROM cursos_academicos c
             INNER JOIN curso_docentes cd
                ON cd.curso_id = c.id
               AND cd.usuario_id = :usuario
             INNER JOIN asignaturas a
                ON a.id = c.asignatura_id
             INNER JOIN periodos_academicos p
                ON p.id = c.periodo_academico_id
             WHERE c.entorno_id = :entorno
               AND c.activo = 1
             ORDER BY
                p.fecha_inicio DESC,
                a.nombre'
        );

        $s->execute([
            'entorno' => $env,
            'usuario' => $userId,
        ]);

        return $s->fetchAll();
    }

    /**
     * Cursos activos en los que está matriculado un estudiante.
     */
    public function studentCourses(int $env, int $userId): array
    {
        $s = $this->db->prepare(
            'SELECT
                c.*,
                a.codigo AS asignatura_codigo,
                a.nombre AS asignatura,
                p.nombre AS periodo
             FROM cursos_academicos c
             INNER JOIN curso_estudiantes ce
                ON ce.curso_id = c.id
               AND ce.usuario_id = :usuario
               AND ce.activo = 1
             INNER JOIN asignaturas a
                ON a.id = c.asignatura_id
             INNER JOIN periodos_academicos p
                ON p.id = c.periodo_academico_id
             WHERE c.entorno_id = :entorno
               AND c.activo = 1
             ORDER BY
                p.fecha_inicio DESC,
                a.nombre'
        );

        $s->execute([
            'entorno' => $env,
            'usuario' => $userId,
        ]);

        return $s->fetchAll();
    }

    /**
     * Periodos académicos activos.
     */
    public function periods(): array
    {
        return $this->db
            ->query(
                'SELECT *
                 FROM periodos_academicos
                 WHERE activo = 1
                 ORDER BY fecha_inicio DESC'
            )
            ->fetchAll();
    }

    /**
     * Asignaturas activas.
     */
    public function subjects(): array
    {
        return $this->db
            ->query(
                'SELECT *
                 FROM asignaturas
                 WHERE activo = 1
                 ORDER BY nombre'
            )
            ->fetchAll();
    }

    /**
     * Todos los casos activos del entorno.
     *
     * Principalmente para Super Administrador.
     */
    public function cases(int $env): array
    {
        $s = $this->db->prepare(
            'SELECT
                cc.*,
                a.nombre AS asignatura,
                c.codigo_seccion,
                CONCAT(u.nombres, " ", u.apellidos) AS creado_por_nombre
             FROM casos_clinicos_academicos cc
             INNER JOIN cursos_academicos c
                ON c.id = cc.curso_id
             INNER JOIN asignaturas a
                ON a.id = c.asignatura_id
             INNER JOIN usuarios u
                ON u.id = cc.creado_por
             WHERE c.entorno_id = :entorno
               AND c.activo = 1
               AND cc.activo = 1
             ORDER BY cc.created_at DESC'
        );

        $s->execute([
            'entorno' => $env,
        ]);

        return $s->fetchAll();
    }

    /**
     * Casos pertenecientes a cursos asignados al docente.
     */
    public function teacherCases(int $env, int $userId): array
    {
        $s = $this->db->prepare(
            'SELECT
                cc.*,
                a.nombre AS asignatura,
                c.codigo_seccion,
                CONCAT(u.nombres, " ", u.apellidos) AS creado_por_nombre
             FROM casos_clinicos_academicos cc
             INNER JOIN cursos_academicos c
                ON c.id = cc.curso_id
             INNER JOIN curso_docentes cd
                ON cd.curso_id = c.id
               AND cd.usuario_id = :usuario
             INNER JOIN asignaturas a
                ON a.id = c.asignatura_id
             INNER JOIN usuarios u
                ON u.id = cc.creado_por
             WHERE c.entorno_id = :entorno
               AND c.activo = 1
               AND cc.activo = 1
             ORDER BY cc.created_at DESC'
        );

        $s->execute([
            'entorno' => $env,
            'usuario' => $userId,
        ]);

        return $s->fetchAll();
    }

    /**
     * Casos disponibles para un estudiante según sus cursos activos.
     */
    public function studentCases(int $env, int $userId): array
    {
        $s = $this->db->prepare(
            'SELECT
                cc.*,
                a.nombre AS asignatura,
                c.codigo_seccion,
                CONCAT(u.nombres, " ", u.apellidos) AS creado_por_nombre
             FROM casos_clinicos_academicos cc
             INNER JOIN cursos_academicos c
                ON c.id = cc.curso_id
             INNER JOIN curso_estudiantes ce
                ON ce.curso_id = c.id
               AND ce.usuario_id = :usuario
               AND ce.activo = 1
             INNER JOIN asignaturas a
                ON a.id = c.asignatura_id
             INNER JOIN usuarios u
                ON u.id = cc.creado_por
             WHERE c.entorno_id = :entorno
               AND c.activo = 1
               AND cc.activo = 1
               AND (
                    cc.fecha_disponible IS NULL
                    OR cc.fecha_disponible <= NOW()
               )
             ORDER BY cc.created_at DESC'
        );

        $s->execute([
            'entorno' => $env,
            'usuario' => $userId,
        ]);

        return $s->fetchAll();
    }

    /**
     * Entregas pertenecientes exclusivamente al estudiante indicado.
     */
    public function assignments(int $userId): array
    {
        $s = $this->db->prepare(
            'SELECT
                ea.*,
                aa.nombre AS actividad,
                cc.titulo AS caso
             FROM entregas_academicas ea
             INNER JOIN actividades_academicas aa
                ON aa.id = ea.actividad_id
             INNER JOIN casos_clinicos_academicos cc
                ON cc.id = aa.caso_clinico_id
             WHERE ea.estudiante_usuario_id = :usuario
             ORDER BY ea.id DESC'
        );

        $s->execute([
            'usuario' => $userId,
        ]);

        return $s->fetchAll();
    }

    /**
     * Comprueba que un docente esté asignado a un curso activo
     * perteneciente al entorno actual.
     */
    public function teacherOwnsCourse(
        int $courseId,
        int $userId,
        int $env
    ): bool {
        $s = $this->db->prepare(
            'SELECT 1
             FROM cursos_academicos c
             INNER JOIN curso_docentes cd
                ON cd.curso_id = c.id
               AND cd.usuario_id = :usuario
             WHERE c.id = :curso
               AND c.entorno_id = :entorno
               AND c.activo = 1
             LIMIT 1'
        );

        $s->execute([
            'curso'   => $courseId,
            'usuario' => $userId,
            'entorno' => $env,
        ]);

        return (bool) $s->fetchColumn();
    }

    /**
     * Comprueba que un estudiante esté matriculado activamente
     * en un curso del entorno actual.
     */
    public function studentBelongsToCourse(
        int $courseId,
        int $userId,
        int $env
    ): bool {
        $s = $this->db->prepare(
            'SELECT 1
             FROM cursos_academicos c
             INNER JOIN curso_estudiantes ce
                ON ce.curso_id = c.id
               AND ce.usuario_id = :usuario
               AND ce.activo = 1
             WHERE c.id = :curso
               AND c.entorno_id = :entorno
               AND c.activo = 1
             LIMIT 1'
        );

        $s->execute([
            'curso'   => $courseId,
            'usuario' => $userId,
            'entorno' => $env,
        ]);

        return (bool) $s->fetchColumn();
    }
}
