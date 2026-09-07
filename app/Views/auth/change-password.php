<div class="auth-form-panel">

    <div class="auth-form-card">

        <div class="auth-logo">
            🔐
        </div>

        <h2>
            Crea tu nueva contraseña
        </h2>

        <p>
            Por seguridad debes cambiar
            la contraseña temporal antes
            de continuar.
        </p>

        <?php if (
            !empty($error)
        ): ?>

            <div
                class="alert alert-danger"
            >
                <?= e($error) ?>
            </div>

        <?php endif; ?>


        <form
            method="POST"
            action="<?= url(
                '/cambiar-password'
            ) ?>"
            class="form-stack"
        >

            <?= csrf_field() ?>


            <label>

                <span>
                    Nueva contraseña
                </span>

                <input
                    type="password"
                    name="password"
                    autocomplete="new-password"
                    required
                    minlength="8"
                >

            </label>


            <label>

                <span>
                    Confirmar contraseña
                </span>

                <input
                    type="password"
                    name="
                        password_confirmation
                    "
                    autocomplete="new-password"
                    required
                    minlength="8"
                >

            </label>


            <div
                class="password-requirements"
            >
                <span>
                    Debe incluir:
                </span>

                <small>
                    mínimo 8 caracteres,
                    una mayúscula,
                    una minúscula,
                    un número y un símbolo.
                </small>
            </div>


            <button
                class="
                    btn
                    btn-primary
                    btn-block
                "
                type="submit"
            >
                Guardar contraseña
            </button>

        </form>

    </div>

</div>