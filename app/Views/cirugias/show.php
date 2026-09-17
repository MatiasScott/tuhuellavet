<?php
$eventId = (int) $surgery['evento_clinico_id'];
?>

<div class="page-heading">
    <div>
        <span class="eyebrow">
            Procedimiento quirúrgico
        </span>

        <h1>
            🩺 <?= e($surgery['paciente']) ?>
        </h1>

        <p>
            <?= e($surgery['procedimiento']) ?>
            ·
            <?= e(
                date(
                    'd/m/Y H:i',
                    strtotime(
                        $surgery['fecha_inicio']
                    )
                )
            ) ?>
        </p>
    </div>

    <div class="inline-actions">
        <a
            class="btn btn-secondary"
            href="<?= url('/cirugias') ?>">
            ← Volver
        </a>
    </div>
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

<div class="metric-grid">
    <div class="metric-card">
        <span>Paciente</span>
        <strong>
            <?= e($surgery['paciente']) ?>
        </strong>
    </div>

    <div class="metric-card">
        <span>Especie</span>
        <strong>
            <?= e($surgery['especie']) ?>
        </strong>
    </div>

    <div class="metric-card">
        <span>Procedimiento</span>
        <strong>
            <?= e($surgery['procedimiento']) ?>
        </strong>
    </div>

    <div class="metric-card">
        <span>Médico responsable</span>
        <strong>
            <?= e($surgery['medico']) ?>
        </strong>
    </div>
</div>

<section class="card mb-1">
    <div class="card-header">
        <div>
            <h2>Resumen quirúrgico</h2>
        </div>
    </div>

    <div class="form-grid">
        <div>
            <strong>
                Diagnóstico preoperatorio
            </strong>

            <p>
                <?= nl2br(
                    e(
                        $surgery['diagnostico_preoperatorio']
                            ?? '—'
                    )
                ) ?>
            </p>
        </div>

        <div>
            <strong>
                Descripción del procedimiento
            </strong>

            <p>
                <?= nl2br(
                    e(
                        $surgery['descripcion_procedimiento']
                            ?? '—'
                    )
                ) ?>
            </p>
        </div>

        <div>
            <strong>Hallazgos</strong>

            <p>
                <?= nl2br(
                    e(
                        $surgery['hallazgos']
                            ?? '—'
                    )
                ) ?>
            </p>
        </div>

        <div>
            <strong>Complicaciones</strong>

            <p>
                <?= nl2br(
                    e(
                        $surgery['complicaciones']
                            ?? '—'
                    )
                ) ?>
            </p>
        </div>

        <div class="field-full">
            <strong>
                Indicaciones postoperatorias
            </strong>

            <p>
                <?= nl2br(
                    e(
                        $surgery['indicaciones_postoperatorias']
                            ?? '—'
                    )
                ) ?>
            </p>
        </div>
    </div>
</section>

<section class="card mb-1">
    <div class="card-header">
        <div>
            <h2>💉 Anestesia</h2>
        </div>
    </div>

    <?php if (!$anesthesia): ?>

        <p class="text-muted">
            No se registró información anestésica.
        </p>

    <?php else: ?>

        <div class="stack">
            <?php foreach ($anesthesia as $row): ?>
                <div class="soft-panel">
                    <strong>
                        <?= e(
                            $row['tipo_anestesia']
                                ?? 'Protocolo anestésico'
                        ) ?>
                    </strong>

                    <p>
                        <?= nl2br(
                            e(
                                $row['protocolo']
                                    ?? 'Sin protocolo'
                            )
                        ) ?>
                    </p>

                    <small>
                        Responsable:
                        <?= e($row['responsable']) ?>
                    </small>
                </div>
            <?php endforeach; ?>
        </div>

    <?php endif; ?>
</section>

<section
    class="card mb-1"
    id="equipo">
    <div class="card-header">
        <div>
            <h2>👥 Equipo quirúrgico</h2>
        </div>

        <button
            class="btn btn-primary"
            data-modal-open="surgery-team">
            ＋ Integrante
        </button>
    </div>

    <div class="table-wrap">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Profesional</th>
                    <th>Función</th>
                    <th>Registrado</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>
                <?php if (!$team): ?>
                    <tr>
                        <td colspan="4">
                            No hay integrantes registrados.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($team as $member): ?>
                    <tr>
                        <td>
                            <?= e(
                                $member['integrante']
                            ) ?>
                        </td>

                        <td>
                            <?= e(
                                $member['funcion']
                            ) ?>
                        </td>

                        <td>
                            <?= e(
                                $member['created_at']
                            ) ?>
                        </td>

                        <td>
                            <form
                                method="POST"
                                action="<?= url(
                                            '/cirugias/'
                                                . $eventId
                                                . '/equipo/eliminar'
                                        ) ?>">
                                <?= csrf_field() ?>

                                <input
                                    type="hidden"
                                    name="usuario_id"
                                    value="<?= (int) $member['usuario_id'] ?>">

                                <input
                                    type="hidden"
                                    name="funcion_id"
                                    value="<?= (int) $member['funcion_id'] ?>">

                                <button
                                    class="btn btn-secondary btn-sm"
                                    type="submit">
                                    Retirar
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section
    class="card mb-1"
    id="evoluciones">
    <div class="card-header">
        <div>
            <h2>
                📋 Evoluciones postoperatorias
            </h2>
        </div>

        <button
            class="btn btn-primary"
            data-modal-open="surgery-evolution">
            ＋ Evolución
        </button>
    </div>

    <div class="stack">
        <?php if (!$evolutions): ?>
            <p class="text-muted">
                No existen evoluciones registradas.
            </p>
        <?php endif; ?>

        <?php foreach ($evolutions as $row): ?>
            <div class="soft-panel">
                <strong>
                    <?= e($row['fecha_hora']) ?>
                    ·
                    <?= e(
                        $row['registrado_por_nombre']
                    ) ?>
                </strong>

                <p>
                    <?= nl2br(
                        e($row['evolucion'])
                    ) ?>
                </p>

                <?php if (
                    !empty($row['observaciones'])
                ): ?>
                    <small>
                        <?= nl2br(
                            e(
                                $row['observaciones']
                            )
                        ) ?>
                    </small>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</section>

<section class="card mb-1">
    <div class="card-header">
        <div>
            <h2>📎 Documentos</h2>
        </div>
    </div>

    <?php if (!$files): ?>
        <p class="text-muted">
            No existen documentos adjuntos.
        </p>
    <?php else: ?>

        <div class="table-wrap">
            <table class="modern-table">
                <thead>
                    <tr>
                        <th>Archivo</th>
                        <th>Tipo</th>
                        <th>Tamaño</th>
                        <th>Descripción</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($files as $file): ?>
                        <tr>
                            <td>
                                <?= e(
                                    $file['nombre_original']
                                ) ?>
                            </td>

                            <td>
                                <?= e(
                                    $file['mime_type']
                                ) ?>
                            </td>

                            <td>
                                <?= e(
                                    number_format(
                                        ((int) $file['tamano_bytes']) / 1024,
                                        1
                                    )
                                ) ?>
                                KB
                            </td>

                            <td>
                                <?= e(
                                    $file['descripcion']
                                        ?? '—'
                                ) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <?php endif; ?>
</section>

<div
    class="modal"
    id="surgery-team">
    <div class="modal-backdrop"></div>

    <div class="modal-dialog">
        <div class="modal-header">
            <h2>
                Agregar integrante
            </h2>

            <button
                class="modal-close"
                data-modal-close>
                ×
            </button>
        </div>

        <form
            method="POST"
            action="<?= url(
                        '/cirugias/'
                            . $eventId
                            . '/equipo'
                    ) ?>">
            <?= csrf_field() ?>

            <div class="modal-body form-grid">
                <label>
                    <span>Profesional</span>

                    <select
                        name="usuario_id"
                        required>
                        <option value="">
                            Seleccionar
                        </option>

                        <?php foreach ($users as $user): ?>
                            <option
                                value="<?= (int) $user['id'] ?>">
                                <?= e(
                                    trim(
                                        (
                                            $user['nombres']
                                            ?? ''
                                        )
                                            . ' '
                                            . (
                                                $user['apellidos']
                                                ?? ''
                                            )
                                    )
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span>
                        Función
                    </span>

                    <select
                        name="funcion_id"
                        required>
                        <option value="">
                            Seleccionar
                        </option>

                        <?php foreach (
                            $teamFunctions
                            as $function
                        ): ?>
                            <option
                                value="<?= (int) $function['id'] ?>">
                                <?= e(
                                    $function['nombre']
                                ) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <div class="modal-footer">
                <button
                    class="btn btn-primary"
                    type="submit">
                    Agregar
                </button>
            </div>
        </form>
    </div>
</div>

<div
    class="modal"
    id="surgery-evolution">
    <div class="modal-backdrop"></div>

    <div class="modal-dialog">
        <div class="modal-header">
            <h2>
                Nueva evolución
            </h2>

            <button
                class="modal-close"
                data-modal-close>
                ×
            </button>
        </div>

        <form
            method="POST"
            action="<?= url(
                        '/cirugias/'
                            . $eventId
                            . '/evoluciones'
                    ) ?>">
            <?= csrf_field() ?>

            <div class="modal-body form-grid">
                <label>
                    <span>Fecha y hora</span>

                    <input
                        type="datetime-local"
                        name="fecha_hora">
                </label>

                <label class="field-full">
                    <span>Evolución</span>

                    <textarea
                        name="evolucion"
                        required></textarea>
                </label>

                <label class="field-full">
                    <span>
                        Observaciones
                    </span>

                    <textarea
                        name="observaciones"></textarea>
                </label>
            </div>

            <div class="modal-footer">
                <button
                    class="btn btn-primary"
                    type="submit">
                    Guardar
                </button>
            </div>
        </form>
    </div>
</div>