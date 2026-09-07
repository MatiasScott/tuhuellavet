<link
    rel="stylesheet"
    href="<?= asset(
                'css/views/pacientes.css'
            ) ?>">


<?php

function patient_age(
    ?string $date
): string {
    if (!$date) {
        return 'Edad no registrada';
    }

    $birth = new DateTime(
        $date
    );

    $today = new DateTime();

    $difference =
        $birth->diff(
            $today
        );

    if ($difference->y > 0) {
        return
            $difference->y
            . ' año(s) '
            . $difference->m
            . ' mes(es)';
    }

    if ($difference->m > 0) {
        return
            $difference->m
            . ' mes(es)';
    }

    return
        $difference->d
        . ' día(s)';
}

?>


<?php if (
    !empty($success)
): ?>

    <div class="alert alert-success">
        <?= e($success) ?>
    </div>

<?php endif; ?>


<?php if (
    !empty($error)
): ?>

    <div class="alert alert-danger">
        <?= e($error) ?>
    </div>

<?php endif; ?>


<div class="patient-profile-header">

    <div
        class="
            patient-profile-photo
        ">

        <?php if (
            !empty($patient['foto_principal_path'])
        ): ?>

            <img
                src="<?= e(
                            patient_photo_url(
                                $patient['foto_principal_path']
                            )
                        ) ?>"
                alt="<?= e(
                            $patient['nombre']
                                ?: 'Paciente'
                        ) ?>">

        <?php else: ?>

            <span>
                🐾
            </span>

        <?php endif; ?>

    </div>


    <div
        class="
            patient-profile-main
        ">

        <span class="eyebrow">

            <?= e(
                $patient['especie']
            ) ?>

        </span>

        <h1>

            <?= e(
                $patient['nombre']
                    ?: 'Paciente sin nombre'
            ) ?>

        </h1>

        <p>

            <?= e(
                $patient['raza']
                    ?: 'Sin raza registrada'
            ) ?>

            ·

            <?= e(
                $patient['sexo']
                    ?: 'Sexo no registrado'
            ) ?>

        </p>


        <div
            class="
                profile-badges
            ">

            <span
                class="
                    status-badge
                    status-info
                ">
                ⚖️
                <?= $patient['peso_actual'] !== null
                    ? e(
                        $patient['peso_actual']
                    )
                    . ' kg'
                    : 'Sin peso'
                ?>
            </span>

            <span
                class="
                    status-badge
                    status-neutral
                ">
                🎂
                <?= e(
                    patient_age(
                        $patient['fecha_nacimiento']
                    )
                ) ?>
            </span>

        </div>

    </div>


    <div class="page-actions">

        <?php if (
            can('consultas.crear')
        ): ?>

            <button
                class="btn btn-primary">
                🩺 Nueva consulta
            </button>

        <?php endif; ?>


        <?php if (
            can('pacientes.editar')
        ): ?>

            <button
                class="btn btn-secondary"
                data-modal-open="
                    weight-create
                ">
                ⚖️ Actualizar peso
            </button>

        <?php endif; ?>

    </div>

</div>


<div
    class="
        patient-detail-grid
    ">

    <section class="card">

        <div class="card-header">

            <div>

                <h2>
                    Información general
                </h2>

                <p>
                    Datos del paciente
                </p>

            </div>

        </div>


        <div class="data-list">

            <div>

                <span>
                    Propietario
                </span>

                <strong>

                    <?= e(
                        trim(
                            (
                                $patient['propietario_nombres']
                                ?? ''
                            )
                                . ' '
                                . (
                                    $patient['propietario_apellidos']
                                    ?? ''
                                )
                        )
                            ?: 'Sin propietario'
                    ) ?>

                </strong>

            </div>


            <div>

                <span>
                    Fecha nacimiento
                </span>

                <strong>

                    <?= $patient['fecha_nacimiento']
                        ? e(
                            date(
                                'd/m/Y',
                                strtotime(
                                    $patient['fecha_nacimiento']
                                )
                            )
                        )
                        : '—'
                    ?>

                </strong>

            </div>


            <div>

                <span>
                    Color
                </span>

                <strong>
                    <?= e(
                        $patient['color']
                            ?: '—'
                    ) ?>
                </strong>

            </div>


            <div>

                <span>
                    Código
                </span>

                <strong>
                    <?= e(
                        $patient['codigo']
                            ?: '—'
                    ) ?>
                </strong>

            </div>


            <div>

                <span>
                    Microchip
                </span>

                <strong>
                    <?= e(
                        $patient['microchip']
                            ?: '—'
                    ) ?>
                </strong>

            </div>


            <div>

                <span>
                    Arete
                </span>

                <strong>
                    <?= e(
                        $patient['arete']
                            ?: '—'
                    ) ?>
                </strong>

            </div>

        </div>

    </section>


    <section class="card">

        <div class="card-header">

            <div>

                <h2>
                    Evolución del peso
                </h2>

                <p>
                    Histórico completo
                </p>

            </div>

        </div>


        <?php if (
            empty($weights)
        ): ?>

            <div class="compact-empty">
                ⚖️ No existen registros
                de peso.
            </div>

        <?php else: ?>

            <div class="weight-history">

                <?php foreach (
                    $weights
                    as $weight
                ): ?>

                    <div
                        class="
                            weight-history-item
                        ">

                        <div>

                            <strong>

                                <?= e(
                                    $weight['peso_kg']
                                ) ?>
                                kg

                            </strong>

                            <span>

                                <?= e(
                                    date(
                                        'd/m/Y H:i',
                                        strtotime(
                                            $weight['fecha_registro']
                                        )
                                    )
                                ) ?>

                            </span>

                        </div>

                        <small>

                            <?= e(
                                $weight['observacion']
                                    ?: (
                                        $weight['origen']
                                        ?: 'Registro'
                                    )
                            ) ?>

                        </small>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

</div>


<section
    class="
        card
        clinical-history-section
    ">

    <div class="card-header">

        <div>

            <h2>
                Historial clínico
            </h2>

            <p>
                Línea de tiempo completa
                del paciente.
            </p>

        </div>


        <?php if (
            can('consultas.crear')
        ): ?>

            <a
                href="<?= url(
                            '/consultas'
                        ) ?>"
                class="btn btn-primary">
                ＋ Nueva consulta
            </a>

        <?php endif; ?>

    </div>


    <?php if (
        empty($history)
    ): ?>

        <div class="empty-state">

            <div class="empty-icon">
                🩺
            </div>

            <h2>
                Sin historial clínico
            </h2>

            <p>
                Este paciente todavía
                no tiene procedimientos
                registrados.
            </p>

        </div>

    <?php else: ?>

        <div
            class="
                clinical-timeline
            ">

            <?php foreach (
                $history
                as $event
            ): ?>

                <?php

                $icon = match ($event['tipo_codigo']) {
                    'CONSULTA_EXTERNA'
                    => '🩺',

                    'VACUNACION'
                    => '💉',

                    'DESPARASITACION'
                    => '💊',

                    'LABORATORIO'
                    => '🧪',

                    'HOSPITALIZACION'
                    => '🏥',

                    'CIRUGIA'
                    => '✂️',

                    default
                    => '📋',
                };

                ?>


                <article
                    class="
                        timeline-item
                    ">

                    <div
                        class="
                            timeline-marker
                        ">
                        <?= $icon ?>
                    </div>


                    <div
                        class="
                            timeline-content
                        ">

                        <div
                            class="
                                timeline-header
                            ">

                            <div>

                                <span
                                    class="
                                        timeline-type
                                    ">
                                    <?= e(
                                        $event['tipo_nombre']
                                    ) ?>
                                </span>

                                <h3>

                                    <?= e(
                                        $event['titulo']
                                            ?: $event['tipo_nombre']
                                    ) ?>

                                </h3>

                            </div>


                            <time>

                                <?= e(
                                    date(
                                        'd/m/Y H:i',
                                        strtotime(
                                            $event['fecha_evento']
                                        )
                                    )
                                ) ?>

                            </time>

                        </div>


                        <?php if (
                            !empty($event['motivo_consulta'])
                        ): ?>

                            <p>
                                <?= e(
                                    $event['motivo_consulta']
                                ) ?>
                            </p>

                        <?php endif; ?>

                        <?php if (
                            $event['tipo_codigo'] === 'VACUNACION'
                        ): ?>

                            <div
                                class="
            timeline-preventive-detail
        ">

                                <span>
                                    💉

                                    <strong>
                                        <?= e(
                                            $event['vacuna_nombre']
                                                ?? 'Vacuna'
                                        ) ?>
                                    </strong>
                                </span>


                                <?php if (
                                    $event['vacuna_dosis'] !== null
                                ): ?>

                                    <span>

                                        Dosis:

                                        <?= e(
                                            $event['vacuna_dosis']
                                        ) ?>

                                        <?= e(
                                            $event['vacuna_unidad']
                                                ?? ''
                                        ) ?>

                                    </span>

                                <?php endif; ?>


                                <?php if (
                                    !empty($event['vacuna_lote'])
                                ): ?>

                                    <span>
                                        Lote:
                                        <?= e(
                                            $event['vacuna_lote']
                                        ) ?>
                                    </span>

                                <?php endif; ?>


                                <?php if (
                                    !empty($event['fecha_revacunacion'])
                                ): ?>

                                    <span>
                                        Próxima:
                                        <?= e(
                                            date(
                                                'd/m/Y',
                                                strtotime(
                                                    $event['fecha_revacunacion']
                                                )
                                            )
                                        ) ?>
                                    </span>

                                <?php endif; ?>

                            </div>

                        <?php endif; ?>


                        <?php if (
                            $event['tipo_codigo'] === 'DESPARASITACION'
                        ): ?>

                            <div
                                class="
            timeline-preventive-detail
        ">

                                <span>

                                    💊

                                    <strong>
                                        <?= e(
                                            $event['desparasitacion_farmaco']
                                                ?? 'Desparasitación'
                                        ) ?>
                                    </strong>

                                </span>


                                <?php if (
                                    $event['desparasitacion_dosis'] !== null
                                ): ?>

                                    <span>

                                        Dosis:

                                        <?= e(
                                            $event['desparasitacion_dosis']
                                        ) ?>

                                        <?= e(
                                            $event['desparasitacion_unidad']
                                                ?? ''
                                        ) ?>

                                    </span>

                                <?php endif; ?>


                                <?php if (
                                    !empty($event['proxima_desparasitacion'])
                                ): ?>

                                    <span>

                                        Próxima:

                                        <?= e(
                                            date(
                                                'd/m/Y',
                                                strtotime(
                                                    $event['proxima_desparasitacion']
                                                )
                                            )
                                        ) ?>

                                    </span>

                                <?php endif; ?>

                            </div>

                        <?php endif; ?>


                        <div
                            class="
                                timeline-footer
                            ">

                            <span>
                                👤
                                <?= e(
                                    $event['responsable_nombre']
                                ) ?>
                            </span>


                            <?php if (
                                $event['tipo_codigo']
                                ===
                                'CONSULTA_EXTERNA'
                            ): ?>

                                <a
                                    href="<?= url(
                                                '/consultas/'
                                                    . $event['id']
                                            ) ?>"
                                    class="
                                        text-link
                                    ">
                                    Ver consulta
                                </a>

                            <?php endif; ?>

                        </div>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</section>


<?php if (
    can('pacientes.editar')
): ?>

    <div
        class="modal"
        id="weight-create">

        <div
            class="modal-backdrop"></div>

        <div class="modal-dialog">

            <div class="modal-header">

                <div>

                    <span class="eyebrow">
                        Seguimiento
                    </span>

                    <h2>
                        Actualizar peso
                    </h2>

                </div>

                <button
                    class="modal-close"
                    data-modal-close
                    type="button">
                    ×
                </button>

            </div>


            <form
                method="POST"
                action="<?= url(
                            '/pacientes/'
                                . $patient['id']
                                . '/peso'
                        ) ?>">

                <?= csrf_field() ?>


                <div class="modal-body">

                    <div class="form-grid">

                        <label>

                            <span>
                                Peso (kg) *
                            </span>

                            <input
                                type="number"
                                name="peso_kg"
                                min="0.001"
                                step="0.001"
                                required>

                        </label>


                        <label
                            class="field-full">

                            <span>
                                Observación
                            </span>

                            <textarea
                                name="observacion"
                                rows="3"></textarea>

                        </label>

                    </div>

                </div>


                <div class="modal-footer">

                    <button
                        class="
                        btn
                        btn-secondary
                    "
                        type="button"
                        data-modal-close>
                        Cancelar
                    </button>

                    <button
                        class="
                        btn
                        btn-primary
                    "
                        type="submit">
                        Registrar peso
                    </button>

                </div>

            </form>

        </div>

    </div>

<?php endif; ?>


<script
    src="<?= asset(
                'js/views/pacientes.js'
            ) ?>"></script>