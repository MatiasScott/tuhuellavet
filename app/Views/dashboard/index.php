<div class="page-heading">

    <div>

        <span class="eyebrow">
            <?= e(
                active_environment()[
                    'nombre'
                ] ?? 'Entorno'
            ) ?>
        </span>

        <h1>
            Buenos días,
            <?= e(
                explode(
                    ' ',
                    auth_user()['name']
                    ?? 'Usuario'
                )[0]
            ) ?>
            👋
        </h1>

        <p>
            Resumen de actividad
            del entorno seleccionado.
        </p>

    </div>

    <div class="page-actions">

        <?php if (
            can('citas.crear')
        ): ?>

            <a
                class="btn btn-secondary"
                href="#"
            >
                📅 Nueva cita
            </a>

        <?php endif; ?>


        <?php if (
            can('consultas.crear')
        ): ?>

            <a
                class="btn btn-primary"
                href="#"
            >
                ＋ Nueva consulta
            </a>

        <?php endif; ?>

    </div>

</div>


<div class="metrics-grid">

    <article class="metric-card">

        <div class="metric-icon">
            📅
        </div>

        <div>

            <span>
                Citas de hoy
            </span>

            <strong>
                <?= (int)
                    $metrics[
                        'appointments'
                    ] ?>
            </strong>

            <small>
                Agenda del día
            </small>

        </div>

    </article>


    <article class="metric-card">

        <div class="metric-icon">
            💉
        </div>

        <div>

            <span>
                Vacunas próximas
            </span>

            <strong>
                <?= (int)
                    $metrics[
                        'vaccines_due'
                    ] ?>
            </strong>

            <small>
                Próximos 7 días
            </small>

        </div>

    </article>


    <article class="metric-card">

        <div class="metric-icon">
            🏥
        </div>

        <div>

            <span>
                Hospitalizados
            </span>

            <strong>
                <?= (int)
                    $metrics[
                        'hospitalized'
                    ] ?>
            </strong>

            <small>
                Hospitalizaciones activas
            </small>

        </div>

    </article>


    <article class="metric-card">

        <div class="metric-icon">
            🐾
        </div>

        <div>

            <span>
                Pacientes
            </span>

            <strong>
                <?= (int)
                    $metrics[
                        'patients'
                    ] ?>
            </strong>

            <small>
                <?= (int)
                    $metrics[
                        'owners'
                    ] ?>
                propietarios
            </small>

        </div>

    </article>

</div>


<div class="dashboard-grid">

    <!-- Citas -->

    <section class="card">

        <div class="card-header">

            <div>

                <h2>
                    Próximas citas
                </h2>

                <p>
                    Agenda clínica de hoy
                </p>

            </div>

        </div>


        <?php if (
            empty($appointments)
        ): ?>

            <div class="compact-empty">
                📅 No hay citas
                programadas para hoy.
            </div>

        <?php else: ?>

            <div class="appointment-list">

                <?php foreach (
                    $appointments
                    as $appointment
                ): ?>

                    <div
                        class="appointment-item"
                    >

                        <div
                            class="pet-avatar"
                        >
                            <?= (
                                $appointment[
                                    'especie'
                                ] ?? ''
                            ) === 'Gato'
                                ? '🐱'
                                : '🐶'
                            ?>
                        </div>

                        <div
                            class="appointment-main"
                        >

                            <strong>
                                <?= e(
                                    $appointment[
                                        'animal'
                                    ]
                                ) ?>
                            </strong>

                            <span>

                                <?= e(
                                    trim(
                                        (
                                            $appointment[
                                                'raza'
                                            ] ?? ''
                                        )
                                        . ' · '
                                        . (
                                            $appointment[
                                                'motivo'
                                            ]
                                            ?? 'Cita'
                                        )
                                    )
                                ) ?>

                            </span>

                        </div>

                        <span
                            class="
                                status-badge
                                status-info
                            "
                        >
                            <?= e(
                                date(
                                    'H:i',
                                    strtotime(
                                        $appointment[
                                            'fecha_inicio'
                                        ]
                                    )
                                )
                            ) ?>
                        </span>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>


    <!-- Pacientes recientes -->

    <section class="card">

        <div class="card-header">

            <div>

                <h2>
                    Pacientes recientes
                </h2>

                <p>
                    Últimos registros
                </p>

            </div>

            <?php if (
                can('pacientes.ver')
            ): ?>

                <a
                    href="<?= url(
                        '/pacientes'
                    ) ?>"
                    class="text-link"
                >
                    Ver todos
                </a>

            <?php endif; ?>

        </div>


        <?php if (
            empty($recentPatients)
        ): ?>

            <div class="compact-empty">
                🐾 Aún no existen
                pacientes en este entorno.
            </div>

        <?php else: ?>

            <div class="appointment-list">

                <?php foreach (
                    $recentPatients
                    as $patient
                ): ?>

                    <div
                        class="appointment-item"
                    >

                        <div
                            class="pet-avatar"
                        >

                            <?= (
                                $patient[
                                    'especie'
                                ] ?? ''
                            ) === 'Gato'
                                ? '🐱'
                                : '🐾'
                            ?>

                        </div>

                        <div
                            class="appointment-main"
                        >

                            <strong>

                                <?= e(
                                    $patient[
                                        'nombre'
                                    ]
                                    ?: 'Sin nombre'
                                ) ?>

                            </strong>

                            <span>

                                <?= e(
                                    trim(
                                        (
                                            $patient[
                                                'especie'
                                            ] ?? ''
                                        )
                                        . ' · '
                                        . (
                                            $patient[
                                                'raza'
                                            ]
                                            ?? 'Sin raza'
                                        )
                                    )
                                ) ?>

                            </span>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </section>

</div>