<link rel="stylesheet" href="<?= asset('css/views/propietarios.css') ?>">
<div class="page-heading">
    <div><span class="eyebrow">Clientes</span>
        <h1>Propietarios</h1>
        <p>Personas responsables de los pacientes.</p>
    </div>
    <?php if (can('propietarios.crear')): ?>
        <button class="btn btn-primary" data-modal-open="owner-create">＋ Nuevo propietario</button>
    <?php endif; ?>
</div>
<?php if ($success): ?>
    <div class="alert alert-success"><?= e($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>
<section class="card">
    <form class="toolbar">
        <div class="search-box">
            🔎<input name="q" value="<?= e($search) ?>" placeholder="Buscar propietario...">
        </div>
        <button class="btn btn-secondary">Buscar</button>
    </form>
    <div class="table-wrap">
        <table class="modern-table">
            <thead>
                <tr>
                    <th>Propietario</th>
                    <th>Contacto</th>
                    <th>Identificación</th>
                    <th>Animales</th>
                </tr>
            </thead>
            <tbody><?php foreach ($owners as $o): ?><tr>
                        <td>
                            <a
                                href="<?= url('/propietarios/' . (int) $o['id']) ?>"
                                class="owner-profile-link"
                                title="Ver información y editar propietario"
                                aria-label="Ver ficha de <?= e(trim($o['nombres'] . ' ' . ($o['apellidos'] ?? ''))) ?>">
                                <span class="owner-profile-avatar">
                                    <?= e(mb_strtoupper(mb_substr($o['nombres'], 0, 1))) ?>
                                </span>
                                <span class="owner-profile-info">
                                    <strong>
                                        <?= e(trim($o['nombres'] . ' ' . ($o['apellidos'] ?? ''))) ?>
                                    </strong>
                                    <small>Ver ficha del propietario</small>
                                </span>
                                <span class="owner-profile-arrow" aria-hidden="true">
                                    ↗
                                </span>
                            </a>
                        </td>
                        <td>
                            <?php
                            $email = trim((string) ($o['email'] ?? ''));
                            $celular = trim((string) ($o['celular'] ?? ''));
                            $telefono = trim((string) ($o['telefono'] ?? ''));

                            // Preparar número para WhatsApp (Ecuador).
                            $numero = preg_replace('/\D+/', '', $celular);

                            if (preg_match('/^09\d{8}$/', $numero)) {
                                $numero = '593' . substr($numero, 1);
                            } elseif (preg_match('/^9\d{8}$/', $numero)) {
                                $numero = '593' . $numero;
                            }
                            $whatsappValido = (bool) preg_match('/^5939\d{8}$/', $numero);
                            ?>
                            <div class="owner-contact">
                                <!-- CORREO -->
                                <div class="owner-contact-item">
                                    <span class="owner-contact-icon">✉</span>
                                    <?php if ($email !== ''): ?>
                                        <a href="mailto:<?= e($email) ?>"
                                            class="owner-contact-email">
                                            <?= e($email) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="owner-contact-empty">Sin correo</span>
                                    <?php endif; ?>
                                </div>
                                <!-- CELULAR / WHATSAPP -->
                                <?php if ($celular !== ''): ?>
                                    <div class="owner-contact-item">
                                        <span class="owner-contact-icon">📱</span>
                                        <?php if ($whatsappValido): ?>
                                            <a
                                                href="https://wa.me/<?= e($numero) ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="owner-contact-whatsapp"
                                                title="Abrir conversación en WhatsApp">
                                                <?= e($celular) ?>
                                                <span class="owner-whatsapp-label">
                                                    WhatsApp ↗
                                                </span>
                                            </a>
                                        <?php else: ?>
                                            <span><?= e($celular) ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <!-- TELÉFONO CONVENCIONAL -->
                                <?php if ($telefono !== ''): ?>
                                    <div class="owner-contact-item">
                                        <span class="owner-contact-icon">☎</span>
                                        <a href="tel:<?= e(preg_replace('/[^\d+]/', '', $telefono)) ?>">
                                            <?= e($telefono) ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?= e($o['identificacion'] ?? '—') ?></td>
                        <td><?= (int)$o['animales_count'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<div class="modal" id="owner-create">
    <div class="modal-backdrop"></div>
    <div class="modal-dialog modal-lg">
        <div class="modal-header">
            <h2>Nuevo propietario</h2>
            <button class="modal-close" data-modal-close>×</button>
        </div>
        <form method="POST" action="<?= url('/propietarios') ?>" class="owner-validation-form"
            data-validation-url="<?= url('/propietarios/validar') ?>">
            <?= csrf_field() ?>
            <div class="modal-body">
                <div class="form-grid">
                    <label>
                        <span>Nombres *</span>
                        <input name="nombres" required>
                    </label>
                    <label>
                        <span>Apellidos</span>
                        <input name="apellidos">
                    </label>
                    <label>
                        <span>Tipo de identificación</span>
                        <select name="tipo_identificacion_id"
                            data-owner-type>
                            <option value="">Seleccionar</option>
                            <?php foreach ($identificationTypes as $t): ?>
                                <option
                                    value="<?= (int) $t['id'] ?>"
                                    data-code="<?= e($t['codigo']) ?>">
                                    <?= e($t['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Identificación</span>
                        <input
                            name="identificacion"
                            maxlength="30"
                            autocomplete="off"
                            data-owner-field="identificacion">

                        <span class="owner-field-message"
                            data-message-for="identificacion"
                            aria-live="polite"></span>
                    </label>
                    <label>
                        <span>Correo electrónico</span>
                        <input
                            type="email"
                            name="email"
                            maxlength="180"
                            autocomplete="off"
                            data-owner-field="email">
                        <span class="owner-field-message"
                            data-message-for="email"
                            aria-live="polite"></span>
                    </label>
                    <label>
                        <span>Celular</span>
                        <input
                            type="tel"
                            name="celular"
                            maxlength="30"
                            autocomplete="off"
                            data-owner-field="celular">
                        <span class="owner-field-message"
                            data-message-for="celular"
                            aria-live="polite"></span>
                    </label>
                    <label class="field-full">
                        <span>Dirección</span>
                        <input name="direccion">
                    </label>
                    <div class="field-full owner-fiscal-section">

                        <div class="owner-fiscal-heading">
                            <div>
                                <strong>Datos de facturación</strong>
                                <small>
                                    Información que podrá utilizarse para emitir
                                    documentos fiscales.
                                </small>
                            </div>

                            <label class="owner-fiscal-toggle">
                                <input
                                    type="checkbox"
                                    name="crear_datos_fiscales"
                                    value="1"
                                    checked
                                    data-fiscal-enabled>
                                <span>Crear datos fiscales</span>
                            </label>
                        </div>


                        <div data-fiscal-container>

                            <label class="owner-fiscal-same">
                                <input
                                    type="checkbox"
                                    name="datos_fiscales_mismos"
                                    value="1"
                                    checked
                                    data-fiscal-same>

                                <span>
                                    <strong>
                                        Usar los mismos datos del propietario
                                    </strong>

                                    <small>
                                        Se utilizarán la identificación, nombre,
                                        dirección y contacto registrados arriba.
                                    </small>
                                </span>
                            </label>


                            <div
                                class="form-grid owner-fiscal-fields"
                                data-fiscal-fields
                                hidden>

                                <label>
                                    <span>Tipo de identificación *</span>

                                    <select
                                        name="fiscal_tipo_identificacion_id"
                                        data-fiscal-input>
                                        <option value="">
                                            Seleccionar
                                        </option>

                                        <?php foreach ($identificationTypes as $t): ?>
                                            <option value="<?= (int) $t['id'] ?>">
                                                <?= e($t['nombre']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>


                                <label>
                                    <span>Identificación *</span>

                                    <input
                                        name="fiscal_identificacion"
                                        maxlength="30"
                                        autocomplete="off"
                                        data-fiscal-input>
                                </label>


                                <label class="field-full">
                                    <span>Razón social *</span>

                                    <input
                                        name="fiscal_razon_social"
                                        maxlength="200"
                                        autocomplete="off"
                                        data-fiscal-input>
                                </label>


                                <label class="field-full">
                                    <span>Dirección</span>

                                    <input
                                        name="fiscal_direccion"
                                        maxlength="255"
                                        data-fiscal-input>
                                </label>


                                <label>
                                    <span>Correo de facturación</span>

                                    <input
                                        type="email"
                                        name="fiscal_email"
                                        maxlength="180"
                                        autocomplete="off"
                                        data-fiscal-input>
                                </label>


                                <label>
                                    <span>Teléfono</span>

                                    <input
                                        name="fiscal_telefono"
                                        maxlength="30"
                                        autocomplete="off"
                                        data-fiscal-input>
                                </label>

                            </div>

                        </div>

                    </div>
                    <div class="field-full owner-access-section">
                        <span class="owner-access-title">
                            Acceso al portal
                        </span>

                        <label class="owner-access-option">
                            <input
                                type="radio"
                                name="modo_acceso"
                                value="create"
                                checked
                                data-owner-access="create">

                            <span>
                                <strong>Crear o vincular cuenta de cliente</strong>
                                <small>
                                    Si el correo no existe, se creará una cuenta.
                                    Si ya existe, se vinculará sin eliminar sus otros roles.
                                </small>
                            </span>
                        </label>

                        <label class="owner-access-option">
                            <input
                                type="radio"
                                name="modo_acceso"
                                value="none"
                                data-owner-access="none">

                            <span>
                                <strong>Registrar sin acceso al portal</strong>
                                <small>
                                    Se creará solamente la ficha del propietario.
                                    Podrá habilitarse el acceso posteriormente.
                                </small>
                            </span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancelar</button>
                <button class="btn btn-primary">Crear</button>
            </div>
        </form>
    </div>
</div>
<script
    src="<?= asset('js/views/propietarios-validacion.js') ?>"
    defer></script>