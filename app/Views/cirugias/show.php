<link rel="stylesheet" href="<?= url('assets/css/views/cirugias.css') ?>">
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
                            <strong>
                                <?= e($member['integrante']) ?>
                            </strong>

                            <?php if (
                                $member['tipo_profesional'] === 'EXTERNO'
                            ): ?>

                                <div>
                                    <span class="badge">
                                        Profesional externo
                                    </span>
                                </div>

                                <?php if (
                                    !empty($member['institucion_externa'])
                                ): ?>
                                    <small>
                                        <?= e(
                                            $member['institucion_externa']
                                        ) ?>
                                    </small>
                                <?php endif; ?>

                            <?php else: ?>

                                <div>
                                    <span class="badge">
                                        Usuario del sistema
                                    </span>
                                </div>

                            <?php endif; ?>
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
                                    name="integrante_id"
                                    value="<?= (int) $member['id'] ?>">

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
                        <th>Acciones</th>
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
                            <td>
                                <button
                                    type="button"
                                    class="btn btn-secondary btn-sm"
                                    data-surgery-file-url="<?= e(
                                                                url('/cirugias/archivo?id=' . (int) $file['id'])
                                                            ) ?>"
                                    data-surgery-file-name="<?= e($file['nombre_original']) ?>">
                                    👁 Ver documento
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ==========================================
     VISOR DE DOCUMENTOS QUIRÚRGICOS
========================================== -->

        <div
            id="surgery-document-viewer"
            class="surgery-document-viewer"
            hidden>
            <div class="surgery-document-toolbar">

                <div>
                    <h3 id="surgery-document-title">
                        Documento quirúrgico
                    </h3>

                    <p class="text-muted">
                        Visualización del documento adjunto.
                    </p>
                </div>

                <div class="surgery-document-actions">

                    <a
                        id="surgery-document-new-tab"
                        class="btn btn-secondary"
                        href="#"
                        target="_blank"
                        rel="noopener noreferrer">
                        ↗ Abrir en otra pestaña
                    </a>

                    <button
                        type="button"
                        class="btn btn-secondary"
                        id="surgery-document-close">
                        × Cerrar
                    </button>

                </div>

            </div>

            <iframe
                id="surgery-document-frame"
                title="Documento del procedimiento quirúrgico"></iframe>

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
                        '/cirugias/' . $eventId . '/equipo'
                    ) ?>">
            <?= csrf_field() ?>

            <div class="modal-body form-grid">

                <!-- Tipo de profesional -->
                <label class="field-full">
                    <span>Tipo de profesional</span>

                    <select
                        name="tipo_profesional"
                        id="surgery-professional-type"
                        required>
                        <option value="INTERNO">
                            Usuario registrado en el sistema
                        </option>

                        <option value="EXTERNO">
                            Profesional externo / invitado
                        </option>
                    </select>
                </label>

                <!-- Profesional interno -->
                <label
                    class="field-full"
                    id="surgery-internal-fields">
                    <span>Profesional</span>

                    <select
                        name="usuario_id"
                        id="surgery-user-id"
                        required>
                        <option value="">
                            Seleccionar profesional
                        </option>

                        <?php foreach ($users as $user): ?>

                            <option
                                value="<?= (int) $user['id'] ?>">
                                <?= e(
                                    trim(
                                        ($user['nombres'] ?? '')
                                            . ' '
                                            . ($user['apellidos'] ?? '')
                                    )
                                ) ?>
                            </option>

                        <?php endforeach; ?>
                    </select>
                </label>

                <!-- Profesional externo -->
                <div
                    class="field-full"
                    id="surgery-external-fields"
                    hidden>

                    <div class="form-grid">

                        <label class="field-full">
                            <span>Nombre completo *</span>

                            <input
                                type="text"
                                name="nombre_externo"
                                id="surgery-external-name"
                                maxlength="200"
                                placeholder="Ej. Dr. Juan Pérez">
                        </label>

                        <label>
                            <span>Registro profesional</span>

                            <input
                                type="text"
                                name="registro_profesional"
                                maxlength="100">
                        </label>

                        <label>
                            <span>Institución de procedencia</span>

                            <input
                                type="text"
                                name="institucion_externa"
                                maxlength="200">
                        </label>

                    </div>
                </div>

                <!-- Función -->
                <label class="field-full">
                    <span>Función quirúrgica</span>

                    <select
                        name="funcion_id"
                        required>
                        <option value="">
                            Seleccionar función
                        </option>

                        <?php foreach (
                            $teamFunctions as $function
                        ): ?>

                            <option
                                value="<?= (int) $function['id'] ?>">
                                <?= e($function['nombre']) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>
                </label>

            </div>

            <div class="modal-footer">

                <button
                    class="btn btn-primary"
                    type="submit">
                    Agregar integrante
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
<script src="<?= url('assets/js/views/cirugias.js') ?>" defer></script>