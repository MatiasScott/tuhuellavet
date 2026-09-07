<div class="auth-shell">

    <section class="auth-visual">

        <div class="auth-badge">
            🐾 Gestión veterinaria moderna
        </div>

        <h1>
            Cuida, organiza y enseña
            desde una sola plataforma.
        </h1>

        <p>
            Clínica veterinaria,
            hacienda y entorno académico
            separados, con una experiencia
            simple y visual.
        </p>

        <div class="auth-pet-card">

            <div class="pet-avatar">
                🐶
            </div>

            <div>

                <strong>
                    Gestión clínica conectada
                </strong>

                <span>
                    Pacientes · vacunas ·
                    tratamientos · docencia
                </span>

            </div>

        </div>

    </section>

    <section class="auth-form-panel">

        <div class="auth-form-card">

            <div class="auth-logo">
                🐾
            </div>

            <h2>
                Bienvenido
            </h2>

            <p>
                Ingresa para continuar.
            </p>

            <?php if (!empty($error)): ?>

                <div class="alert alert-danger">
                    <?= e($error) ?>
                </div>

            <?php endif; ?>

            <?php if (!empty($success)): ?>

                <div class="alert alert-success">
                    <?= e($success) ?>
                </div>

            <?php endif; ?>

            <form
                action="<?= url('/login') ?>"
                method="POST"
                class="form-stack"
            >

                <?= csrf_field() ?>

                <label>

                    <span>
                        Correo electrónico
                    </span>

                    <input
                        type="email"
                        name="email"
                        autocomplete="email"
                        required
                    >

                </label>

                <label>

                    <span>
                        Contraseña
                    </span>

                    <input
                        type="password"
                        name="password"
                        autocomplete="current-password"
                        required
                    >

                </label>

                <button
                    class="btn btn-primary btn-block"
                    type="submit"
                >
                    Iniciar sesión
                </button>

            </form>

            <div class="divider">
                <span>o</span>
            </div>

            <?php if ($googleEnabled): ?>

                <a
                    class="btn btn-google btn-block"
                    href="<?= url('/auth/google') ?>"
                >
                    <strong>G</strong>

                    Continuar con Google
                </a>

            <?php else: ?>

                <button
                    class="btn btn-google btn-block"
                    type="button"
                    disabled
                >
                    <strong>G</strong>

                    Continuar con Google
                </button>

                <p class="form-footnote">
                    Configura las variables
                    GOOGLE_* para habilitarlo.
                </p>

            <?php endif; ?>

        </div>

    </section>

</div>