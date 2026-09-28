<div class="page-heading">
    <div>
        <span class="eyebrow">Simulación educativa</span>
        <h1>🎓 Aula clínica</h1>
        <p>Casos, prácticas y ejercicios separados de la operación real.</p>
    </div>

    <?php if ($isTeacher): ?>
        <button
            class="btn btn-primary"
            data-modal-open="case-create">
            ＋ Caso clínico
        </button>
    <?php endif; ?>
</div>

<?php if ($success): ?>
    <div class="alert alert-success">
        <?= e($success) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <?= e($error) ?>
    </div>
<?php endif; ?>


<!-- ==========================================
     MÉTRICAS
========================================== -->
<div class="metric-grid">
    <div class="metric-card">
        <span>Cursos</span>
        <strong><?= count($courses) ?></strong>
    </div>

    <div class="metric-card">
        <span>Casos clínicos</span>
        <strong><?= count($cases) ?></strong>
    </div>

    <?php if ($isStudent): ?>
        <div class="metric-card">
            <span>Mis entregas</span>
            <strong><?= count($assignments) ?></strong>
        </div>
    <?php endif; ?>

    <div class="metric-card">
        <span>Modo</span>
        <strong>Simulación</strong>
    </div>
</div>


<!-- ==========================================
     CASOS CLÍNICOS
========================================== -->
<section class="card mb-1">
    <div class="card-header">
        <div>
            <h2>Casos clínicos</h2>

            <?php if ($isTeacher): ?>
                <p>Casos correspondientes a tus cursos asignados.</p>
            <?php elseif ($isStudent): ?>
                <p>Casos disponibles en tus cursos matriculados.</p>
            <?php else: ?>
                <p>Casos clínicos del entorno académico.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($cases)): ?>
        <div class="cards-grid">
            <?php foreach ($cases as $c): ?>
                <article class="soft-panel">
                    <strong>
                        <?= e($c['titulo']) ?>
                    </strong>

                    <p>
                        <?= e($c['asignatura']) ?>
                        ·
                        <?= e($c['codigo_seccion']) ?>
                    </p>

                    <p class="text-muted">
                        <?= e($c['descripcion']) ?>
                    </p>

                    <?php if (!empty($c['fecha_disponible'])): ?>
                        <small class="text-muted">
                            Disponible:
                            <?= e($c['fecha_disponible']) ?>
                        </small>
                    <?php endif; ?>

                    <?php if (!empty($c['fecha_limite'])): ?>
                        <small class="text-muted">
                            · Límite:
                            <?= e($c['fecha_limite']) ?>
                        </small>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <p>No existen casos clínicos disponibles.</p>
        </div>
    <?php endif; ?>
</section>


<!-- ==========================================
     DOCENTE: CREAR CASOS
========================================== -->
<?php if ($isTeacher): ?>
    <div class="modal" id="case-create">
        <div class="modal-backdrop"></div>

        <div class="modal-dialog modal-lg">
            <div class="modal-header">
                <h2>Nuevo caso clínico</h2>

                <button
                    type="button"
                    class="modal-close"
                    data-modal-close>
                    ×
                </button>
            </div>

            <form
                method="POST"
                action="<?= url('/academico/casos') ?>">
                <?= csrf_field() ?>

                <div class="modal-body form-grid">
                    <label>
                        <span>Curso</span>

                        <select name="curso_id" required>
                            <?php foreach ($courses as $c): ?>
                                <option value="<?= (int) $c['id'] ?>">
                                    <?= e(
                                        $c['asignatura']
                                            . ' · '
                                            . $c['codigo_seccion']
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <label>
                        <span>Título</span>
                        <input
                            name="titulo"
                            required>
                    </label>

                    <label class="field-full">
                        <span>Descripción</span>
                        <textarea
                            name="descripcion"
                            required></textarea>
                    </label>

                    <label class="field-full">
                        <span>Instrucciones</span>
                        <textarea
                            name="instrucciones"></textarea>
                    </label>

                    <label>
                        <span>Disponible desde</span>
                        <input
                            type="datetime-local"
                            name="fecha_disponible">
                    </label>

                    <label>
                        <span>Fecha límite</span>
                        <input
                            type="datetime-local"
                            name="fecha_limite">
                    </label>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-primary">
                        Crear caso
                    </button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>


<!-- ==========================================
     SUPER ADMINISTRADOR:
     CONFIGURACIÓN ACADÉMICA
========================================== -->
<?php if ($isSuperAdmin): ?>

    <section class="card mt-1">
        <div class="card-header">
            <div>
                <h2>Configuración académica</h2>
                <p>
                    Administración de períodos,
                    asignaturas y cursos.
                </p>
            </div>
        </div>

        <div class="cards-grid">

            <!-- PERÍODO -->
            <form
                method="POST"
                action="<?= url('/academico/periodos') ?>"
                class="soft-panel form-stack">
                <?= csrf_field() ?>

                <strong>Periodo académico</strong>

                <input
                    name="codigo"
                    placeholder="2026-B"
                    required>

                <input
                    name="nombre"
                    placeholder="Periodo 2026-B"
                    required>

                <input
                    type="date"
                    name="fecha_inicio"
                    required>

                <input
                    type="date"
                    name="fecha_fin"
                    required>

                <button class="btn btn-secondary">
                    Crear periodo
                </button>
            </form>


            <!-- ASIGNATURA -->
            <form
                method="POST"
                action="<?= url('/academico/asignaturas') ?>"
                class="soft-panel form-stack">
                <?= csrf_field() ?>

                <strong>Asignatura</strong>

                <input
                    name="codigo"
                    placeholder="ENFV101"
                    required>

                <input
                    name="nombre"
                    placeholder="Enfermería veterinaria"
                    required>

                <textarea
                    name="descripcion"
                    placeholder="Descripción"></textarea>

                <button class="btn btn-secondary">
                    Crear asignatura
                </button>
            </form>


            <!-- CURSO -->
            <form
                method="POST"
                action="<?= url('/academico/cursos') ?>"
                class="soft-panel form-stack">
                <?= csrf_field() ?>

                <strong>Curso</strong>

                <select
                    name="asignatura_id"
                    required>
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?= (int) $s['id'] ?>">
                            <?= e($s['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select
                    name="periodo_academico_id"
                    required>
                    <?php foreach ($periods as $p): ?>
                        <option value="<?= (int) $p['id'] ?>">
                            <?= e($p['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input
                    name="codigo_seccion"
                    value="GENERAL"
                    required>

                <button class="btn btn-secondary">
                    Crear curso
                </button>
            </form>

        </div>
    </section>


    <!-- ======================================
         SUPER ADMIN: ASIGNAR DOCENTES
    ======================================= -->
    <section class="card mt-1">
        <div class="card-header">
            <div>
                <h2>Asignar docentes</h2>
                <p>
                    Asigna docentes a los cursos académicos.
                </p>
            </div>
        </div>

        <?php if (!empty($courses)): ?>
            <div class="cards-grid">
                <?php foreach ($courses as $c): ?>

                    <form
                        method="POST"
                        action="<?= url(
                                    '/academico/cursos/'
                                        . $c['id']
                                        . '/asignar'
                                ) ?>"
                        class="soft-panel form-stack">
                        <?= csrf_field() ?>

                        <strong>
                            <?= e(
                                $c['asignatura']
                                    . ' · '
                                    . $c['codigo_seccion']
                            ) ?>
                        </strong>

                        <select
                            name="usuario_id"
                            required>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= (int) $u['id'] ?>">
                                    <?= e(
                                        $u['nombres']
                                            . ' '
                                            . $u['apellidos']
                                            . ' · '
                                            . $u['email']
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <input
                            type="hidden"
                            name="tipo"
                            value="DOCENTE">

                        <button class="btn btn-secondary">
                            Asignar docente
                        </button>
                    </form>

                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <p>
                    Primero debes crear un curso académico.
                </p>
            </div>
        <?php endif; ?>
    </section>

<?php endif; ?>


<!-- ==========================================
     DOCENTE: ASIGNAR ESTUDIANTES
========================================== -->
<?php if ($isTeacher): ?>

    <section class="card mt-1">
        <div class="card-header">
            <div>
                <h2>Asignar estudiantes</h2>
                <p>
                    Agrega estudiantes únicamente
                    a tus cursos asignados.
                </p>
            </div>
        </div>

        <?php if (!empty($courses)): ?>

            <div class="cards-grid">
                <?php foreach ($courses as $c): ?>

                    <form
                        method="POST"
                        action="<?= url(
                                    '/academico/cursos/'
                                        . $c['id']
                                        . '/asignar'
                                ) ?>"
                        class="soft-panel form-stack">
                        <?= csrf_field() ?>

                        <strong>
                            <?= e(
                                $c['asignatura']
                                    . ' · '
                                    . $c['codigo_seccion']
                            ) ?>
                        </strong>

                        <select
                            name="usuario_id"
                            required>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= (int) $u['id'] ?>">
                                    <?= e(
                                        $u['nombres']
                                            . ' '
                                            . $u['apellidos']
                                            . ' · '
                                            . $u['email']
                                    ) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <input
                            type="hidden"
                            name="tipo"
                            value="ESTUDIANTE">

                        <button class="btn btn-secondary">
                            Asignar estudiante
                        </button>
                    </form>

                <?php endforeach; ?>
            </div>

        <?php else: ?>

            <div class="empty-state">
                <p>
                    No tienes cursos académicos asignados.
                </p>
            </div>

        <?php endif; ?>
    </section>

<?php endif; ?>


<!-- ==========================================
     ESTUDIANTE: ENTREGAS
========================================== -->
<?php if ($isStudent): ?>

    <section class="card mt-1">
        <div class="card-header">
            <div>
                <h2>Mis entregas</h2>
                <p>
                    Actividades y ejercicios asociados
                    a tu cuenta.
                </p>
            </div>
        </div>

        <?php if (!empty($assignments)): ?>

            <div class="cards-grid">
                <?php foreach ($assignments as $assignment): ?>

                    <article class="soft-panel">
                        <strong>
                            <?= e($assignment['actividad']) ?>
                        </strong>

                        <p>
                            <?= e($assignment['caso']) ?>
                        </p>

                        <p class="text-muted">
                            Estado:
                            <?= e($assignment['estado']) ?>
                        </p>

                        <?php if (
                            $assignment['calificacion'] !== null
                        ): ?>
                            <p>
                                Calificación:
                                <strong>
                                    <?= e($assignment['calificacion']) ?>
                                </strong>
                            </p>
                        <?php endif; ?>
                    </article>

                <?php endforeach; ?>
            </div>

        <?php else: ?>

            <div class="empty-state">
                <p>
                    Todavía no tienes entregas académicas.
                </p>
            </div>

        <?php endif; ?>
    </section>

<?php endif; ?>