<div class="page-heading">
    <div>
        <span class="eyebrow">
            Simulación educativa
        </span>
        <h1>🎓 Aula clínica</h1>
        <p>Casos, prácticas y ejercicios separados de la operación real.</p>
    </div>
    <?php if ($isTeacher): ?>
        <button class="btn btn-primary" data-modal-open="case-create">
            ＋ Caso clínico
        </button>
    <?php endif; ?>
</div>
<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>
<div class="metric-grid">
    <div class="metric-card">
        <span>Cursos</span>
        <strong><?= count($courses) ?></strong>
    </div>
    <div class="metric-card">
        <span>Casos clínicos</span>
        <strong><?= count($cases) ?></strong>
    </div>
    <div class="metric-card">
        <span>Mis entregas</span>
        <strong><?= count($assignments) ?></strong>
    </div>
    <div class="metric-card">
        <span>Modo</span>
        <strong>Simulación</strong>
    </div>
</div>
<section class="card mb-1">
    <div class="card-header">
        <h2>Casos clínicos</h2>
    </div>
    <div class="cards-grid"><?php foreach ($cases as $c): ?>
            <article class="soft-panel">
                <strong>
                    <?= e($c['titulo']) ?>
                </strong>
                <p><?= e($c['asignatura']) ?> · <?= e($c['codigo_seccion']) ?></p>
                <p class="text-muted"><?= e($c['descripcion']) ?></p>
            </article><?php endforeach; ?>
    </div>
</section><?php if ($isTeacher): ?>
    <div class="modal" id="case-create">
        <div class="modal-backdrop"></div>
        <div class="modal-dialog modal-lg">
            <div class="modal-header">
                <h2>Nuevo caso clínico</h2><button class="modal-close" data-modal-close>×</button>
            </div>
            <form method="POST" action="<?= url('/academico/casos') ?>">
                <?= csrf_field() ?>
                <div class="modal-body form-grid">
                    <label>
                        <span>
                            Curso
                        </span>
                        <select name="curso_id">
                            <?php foreach ($courses as $c): ?>
                                <option value="<?= $c['id'] ?>">
                                    <?= e($c['asignatura'] . ' · ' . $c['codigo_seccion']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>
                            Título
                        </span>
                        <input name="titulo" required>
                    </label>
                    <label class="field-full">
                        <span>Descripción</span>
                        <textarea name="descripcion" required></textarea>
                    </label>
                    <label class="field-full">
                        <span>Instrucciones</span>
                        <textarea name="instrucciones"></textarea>
                    </label>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary">
                        Crear caso
                    </button>
                </div>
            </form>
        </div>
    </div><?php endif; ?>
<?php if ($isTeacher): ?>
    <section class="card mt-1">
        <div class="card-header">
            <div>
                <h2>Configuración académica</h2>
                <p>Periodos, asignaturas, cursos y asignaciones.</p>
            </div>
        </div>
        <div class="cards-grid">
            <form method="POST" action="<?= url('/academico/periodos') ?>" class="soft-panel form-stack">
                <?= csrf_field() ?>
                <strong>Periodo</strong>
                <input name="codigo" placeholder="2026-B" required>
                <input name="nombre" placeholder="Periodo 2026-B" required>
                <input type="date" name="fecha_inicio" required>
                <input type="date" name="fecha_fin" required>
                <button class="btn btn-secondary">Crear periodo</button>
            </form>
            <form method="POST" action="<?= url('/academico/asignaturas') ?>" class="soft-panel form-stack">
                <?= csrf_field() ?>
                <strong>Asignatura</strong>
                <input name="codigo" placeholder="ENFV101" required>
                <input name="nombre" placeholder="Enfermería veterinaria" required>
                <textarea name="descripcion" placeholder="Descripción"></textarea>
                <button class="btn btn-secondary">Crear asignatura</button>
            </form>
            <form method="POST" action="<?= url('/academico/cursos') ?>" class="soft-panel form-stack">
                <?= csrf_field() ?>
                <strong>Curso</strong>
                <select name="asignatura_id">
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= e($s['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="periodo_academico_id">
                    <?php foreach ($periods as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= e($p['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input name="codigo_seccion" value="GENERAL">
                <button class="btn btn-secondary">Crear curso</button>
            </form>
        </div>
    </section>
    <section class="card mt-1">
        <div class="card-header">
            <h2>Asignar usuarios</h2>
        </div>
        <div class="cards-grid"><?php foreach ($courses as $c): ?>
                <form method="POST" action="<?= url('/academico/cursos/' . $c['id'] . '/asignar') ?>" class="soft-panel form-stack">
                    <?= csrf_field() ?>
                    <strong><?= e($c['asignatura'] . ' · ' . $c['codigo_seccion']) ?></strong>
                    <select name="usuario_id">
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= e($u['nombres'] . ' ' . $u['apellidos'] . ' · ' . $u['email']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="tipo">
                        <option value="DOCENTE">Docente</option>
                        <option value="ESTUDIANTE">Estudiante</option>
                    </select>
                    <button class="btn btn-secondary">Asignar</button>
                </form>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>