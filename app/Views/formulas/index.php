<link rel="stylesheet" href="<?= asset('css/views/formulas.css') ?>">
<div
    id="formula-page-config"
    data-formula-data-template="<?= e(
                                    url('/formulas/versiones/__ID__/prueba')
                                ) ?>"
    hidden>
</div>
<div class="page-heading">
    <div>
        <span class="eyebrow">Motor clínico</span>
        <h1>Fórmulas médicas</h1>
        <p>Versionado, variables y publicación de cálculos.</p>
    </div>
    <?php if (can('formulas.crear')): ?>
        <button class="btn btn-primary" data-modal-open="formula-create">＋ Nueva fórmula</button>
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
<div class="cards-grid">
    <?php foreach ($formulas as $f): ?>
        <article class="soft-panel">
            <div class="inline-actions">
                <strong>
                    🧮 <?= e($f['nombre']) ?>
                </strong>
                <span class="status-badge status-neutral">
                    v
                    <?= e($f['ultima_version'] ?? '—') ?> · <?= e($f['ultimo_estado'] ?? '—') ?>
                </span>
            </div>
            <p class="text-muted">
                <?= e($f['descripcion'] ?? '') ?>
            </p>
            <p>
                <code><?= e($f['codigo']) ?></code> · Resultado <?= e($f['unidad'] ?? 'sin unidad') ?>
            </p>
            <div class="inline-actions">

                <?php if (
                    !empty($f['ultima_version_id'])
                    && can('formulas.calcular')
                ): ?>

                    <button
                        type="button"
                        class="btn btn-secondary btn-sm"
                        data-test-version="<?= (int) $f['ultima_version_id'] ?>"
                        data-formula-name="<?= e($f['nombre']) ?>"
                        data-modal-open="formula-test">

                        🧪 Probar fórmula

                    </button>

                <?php endif; ?>


                <?php if (
                    !empty($f['ultima_version_id'])
                    && $f['ultimo_estado'] === 'BORRADOR'
                    && can('formulas.editar')
                ): ?>

                    <button
                        type="button"
                        class="btn btn-secondary btn-sm"
                        data-edit-version="<?= (int) $f['ultima_version_id'] ?>"
                        data-modal-open="formula-edit">

                        ✏️ Editar borrador

                    </button>

                <?php endif; ?>


                <?php if (can('formulas.crear')): ?>

                    <button
                        type="button"
                        class="btn btn-secondary btn-sm"
                        data-version-formula="<?= (int) $f['id'] ?>"
                        data-formula-name="<?= e($f['nombre']) ?>"
                        data-modal-open="formula-version">

                        ＋ Nueva versión

                    </button>

                <?php endif; ?>

                <?php if (
                    !empty($f['ultima_version_id'])
                    && $f['ultimo_estado'] === 'BORRADOR'
                    && can('formulas.publicar')
                ): ?>

                    <button
                        type="button"
                        class="btn btn-primary btn-sm"
                        data-publish-version="<?= (int)$f['ultima_version_id'] ?>"
                        data-publish-formula="<?= e($f['nombre']) ?>"
                        data-publish-number="<?= (int)$f['ultima_version'] ?>"
                        data-modal-open="formula-publish">

                        <span>✓ Publicar versión</span>

                    </button>

                <?php endif; ?>

            </div>
        </article>
    <?php endforeach; ?>
</div>
<div class="modal" id="formula-create">
    <div class="modal-backdrop"></div>
    <div class="modal-dialog modal-xl">
        <div class="modal-header">
            <h2>Nueva fórmula</h2>
            <button class="modal-close" data-modal-close>×</button>
        </div>
        <form method="POST" action="<?= url('/formulas') ?>">
            <?= csrf_field() ?>
            <div class="modal-body">
                <div class="form-grid">
                    <label>
                        <span>
                            Código
                        </span>
                        <input name="codigo" required placeholder="DOSIS_MG">
                    </label>
                    <label>
                        <span>Nombre</span>
                        <input name="nombre" required>
                    </label>
                    <label>
                        <span>Categoría</span>
                        <select name="categoria_formula_id"><?php foreach ($categories as $x): ?><option value="<?= $x['id'] ?>"><?= e($x['nombre']) ?></option><?php endforeach; ?></select>
                    </label>
                    <label>
                        <span>Unidad resultado</span>
                        <select name="unidad_resultado_id">
                            <option value="">Sin unidad</option><?php foreach ($units as $x): ?><option value="<?= $x['id'] ?>"><?= e($x['simbolo'] . ' · ' . $x['nombre']) ?></option><?php endforeach; ?>
                        </select>
                    </label>
                    <label class="field-full">
                        <span>Descripción</span>
                        <textarea name="descripcion"></textarea>
                    </label>
                    <label class="field-full">
                        <span>Expresión</span>
                        <input name="expresion" required placeholder="PESO * DOSIS">
                    </label>
                </div>
                <h3>Variables</h3>
                <div id="formula-vars" class="stack">
                    <div class="form-grid soft-panel">
                        <label>
                            <span>
                                Código
                            </span>
                            <input name="variables[0][codigo]" placeholder="PESO">
                        </label>
                        <label>
                            <span>
                                Etiqueta
                            </span>
                            <input name="variables[0][etiqueta]" placeholder="Peso">
                        </label>
                        <label>
                            <span>Tipo</span>
                            <select name="variables[0][tipo_variable_id]"><?php foreach ($variableTypes as $x): ?><option value="<?= $x['id'] ?>"><?= e($x['nombre']) ?></option><?php endforeach; ?></select>
                        </label>
                        <label>
                            <span>Origen</span>
                            <select name="variables[0][origen_variable_id]"><?php foreach ($variableOrigins as $x): ?><option value="<?= $x['id'] ?>"><?= e($x['nombre']) ?></option><?php endforeach; ?></select>
                        </label>
                        <label>
                            <span>Unidad</span>
                            <select name="variables[0][unidad_medida_id]">
                                <option value="">—</option><?php foreach ($units as $x): ?><option value="<?= $x['id'] ?>"><?= e($x['simbolo']) ?></option><?php endforeach; ?>
                            </select>
                        </label>
                        <label>
                            <span>Obligatoria</span>
                            <select name="variables[0][obligatorio]">
                                <option value="1">Sí</option>
                                <option value="0">No</option>
                            </select>
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary">Crear fórmula</button></div>
        </form>
    </div>
</div>
<div class="modal" id="formula-version">

    <div class="modal-backdrop"></div>

    <div class="modal-dialog modal-xl">

        <div class="modal-header">

            <div>
                <span class="eyebrow">
                    Control de versiones
                </span>

                <h2>Nueva versión</h2>

                <p
                    class="text-muted"
                    id="formula-version-name"></p>
            </div>

            <button
                type="button"
                class="modal-close"
                data-modal-close>
                ×
            </button>

        </div>

        <form
            id="formula-version-form"
            method="POST"
            action=""
            data-action-template="<?= e(
                                        url('/formulas/__ID__/versiones')
                                    ) ?>">

            <?= csrf_field() ?>

            <div class="modal-body">

                <div class="alert alert-info">
                    La nueva versión se creará como borrador.
                    La versión anterior conservará su historial.
                </div>

                <div class="form-grid">

                    <label class="field-full">

                        <span>
                            Expresión matemática
                        </span>

                        <textarea
                            name="expresion"
                            id="formula-version-expression"
                            rows="4"
                            required
                            placeholder="PESO * DOSIS"></textarea>

                    </label>

                    <label class="field-full">

                        <span>
                            Notas de la versión
                        </span>

                        <textarea
                            name="notas_version"
                            rows="3"
                            placeholder="Describe los cambios realizados"></textarea>

                    </label>

                </div>

                <p class="text-muted">
                    Las variables de la versión anterior
                    deberán copiarse automáticamente.
                    Podrás editarlas posteriormente
                    en el borrador.
                </p>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-secondary"
                    data-modal-close>
                    Cancelar
                </button>

                <button
                    type="submit"
                    class="btn btn-primary">
                    Crear versión
                </button>

            </div>

        </form>

    </div>

</div>
<div class="modal" id="formula-edit">

    <div class="modal-backdrop"></div>

    <div class="modal-dialog modal-xl">

        <div class="modal-header">

            <div>
                <span class="eyebrow">
                    Administración de fórmulas
                </span>

                <h2>Editar borrador</h2>

                <p
                    id="formula-edit-name"
                    class="text-muted">
                </p>
            </div>

            <button
                type="button"
                class="modal-close"
                data-modal-close>
                ×
            </button>

        </div>

        <form
            id="formula-edit-form"
            method="POST"
            action=""
            data-action-template="<?= e(
                                        url('/formulas/versiones/__ID__/editar')
                                    ) ?>">

            <?= csrf_field() ?>

            <div class="modal-body">

                <div
                    id="formula-edit-message"
                    class="alert alert-info"
                    role="status"
                    aria-live="polite">

                    Cargando borrador...

                </div>

                <div class="form-grid">

                    <label class="field-full">

                        <span>Expresión matemática</span>

                        <textarea
                            name="expresion"
                            id="formula-edit-expression"
                            rows="3"
                            required></textarea>

                    </label>

                    <label class="field-full">

                        <span>Notas de la versión</span>

                        <textarea
                            name="notas_version"
                            id="formula-edit-notes"
                            rows="2"></textarea>

                    </label>

                </div>

                <div class="inline-actions">

                    <h3>Variables</h3>

                    <button
                        type="button"
                        id="formula-edit-add-variable"
                        class="btn btn-secondary btn-sm">

                        ＋ Agregar variable

                    </button>

                </div>

                <div
                    id="formula-edit-variables"
                    class="stack">
                </div>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-secondary"
                    data-modal-close>
                    Cancelar
                </button>

                <button
                    type="submit"
                    id="formula-edit-submit"
                    class="btn btn-primary"
                    disabled>

                    Guardar borrador

                </button>

            </div>

        </form>

    </div>

</div>


<!-- Plantilla reutilizable para variables -->

<template id="formula-variable-template">

    <div class="soft-panel formula-variable-row">

        <input
            type="hidden"
            data-field="id"
            value="0">

        <div class="inline-actions">

            <strong class="formula-variable-title">
                Nueva variable
            </strong>

            <button
                type="button"
                class="btn btn-secondary btn-sm"
                data-remove-variable>

                Eliminar

            </button>

        </div>

        <div class="form-grid">

            <label>

                <span>Código</span>

                <input
                    data-field="codigo"
                    required
                    maxlength="80"
                    placeholder="PESO">

            </label>

            <label>

                <span>Etiqueta</span>

                <input
                    data-field="etiqueta"
                    required
                    maxlength="150"
                    placeholder="Peso del paciente">

            </label>

            <label>

                <span>Tipo</span>

                <select
                    data-field="tipo_variable_id"
                    required>

                    <?php foreach ($variableTypes as $x): ?>

                        <option value="<?= (int) $x['id'] ?>">
                            <?= e($x['nombre']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </label>

            <label>

                <span>Origen</span>

                <select
                    data-field="origen_variable_id"
                    required>

                    <?php foreach ($variableOrigins as $x): ?>

                        <option value="<?= (int) $x['id'] ?>">
                            <?= e($x['nombre']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </label>

            <label>

                <span>Unidad</span>

                <select data-field="unidad_medida_id">

                    <option value="">
                        Sin unidad
                    </option>

                    <?php foreach ($units as $x): ?>

                        <option value="<?= (int) $x['id'] ?>">
                            <?= e(
                                $x['simbolo']
                                    . ' · '
                                    . $x['nombre']
                            ) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </label>

            <label>

                <span>Obligatoria</span>

                <select data-field="obligatorio">

                    <option value="1">Sí</option>
                    <option value="0">No</option>

                </select>

            </label>

            <label>

                <span>Valor mínimo</span>

                <input
                    data-field="valor_minimo"
                    type="number"
                    step="any">

            </label>

            <label>

                <span>Valor máximo</span>

                <input
                    data-field="valor_maximo"
                    type="number"
                    step="any">

            </label>

            <label>

                <span>Valor predeterminado</span>

                <input
                    data-field="valor_default"
                    type="number"
                    step="any">

            </label>

            <label class="field-full">

                <span>Descripción</span>

                <input
                    data-field="descripcion"
                    maxlength="255">

            </label>

        </div>

    </div>

</template>

<div class="modal" id="formula-test">

    <div class="modal-backdrop"></div>

    <div class="modal-dialog modal-xl">

        <div class="modal-header">

            <div>

                <span class="eyebrow">
                    Laboratorio de fórmulas
                </span>

                <h2>Probar cálculo</h2>

                <p
                    id="formula-test-name"
                    class="text-muted">
                </p>

            </div>

            <button
                type="button"
                class="modal-close"
                data-modal-close>
                ×
            </button>

        </div>

        <form
            id="formula-test-form"
            data-test-url="<?= e(
                                url('/formulas/probar')
                            ) ?>">

            <?= csrf_field() ?>

            <input
                type="hidden"
                name="formula_version_id"
                id="formula-test-version">

            <div class="modal-body">

                <div
                    id="formula-test-message"
                    class="alert alert-info"
                    role="status"
                    aria-live="polite">

                    Cargando variables...

                </div>

                <label class="field-full">

                    <span>Expresión</span>

                    <input
                        id="formula-test-expression"
                        readonly>

                </label>

                <h3>Valores de prueba</h3>

                <div
                    id="formula-test-variables"
                    class="form-grid">
                </div>

                <div class="form-grid">

                    <label>

                        <span>
                            Resultado esperado (opcional)
                        </span>

                        <input
                            name="resultado_esperado"
                            type="number"
                            step="any"
                            placeholder="Ej. 25">

                    </label>

                </div>

                <div
                    id="formula-test-result"
                    class="soft-panel"
                    role="status"
                    aria-live="polite"
                    hidden>
                </div>

            </div>

            <div class="modal-footer">

                <button
                    type="button"
                    class="btn btn-secondary"
                    data-modal-close>

                    Cerrar

                </button>

                <button
                    type="submit"
                    id="formula-test-submit"
                    class="btn btn-primary"
                    disabled>

                    Ejecutar prueba

                </button>

            </div>

        </form>

    </div>

</div>
<!-- MODAL: CONFIRMAR PUBLICACIÓN -->

<div class="modal" id="formula-publish">

    <div class="modal-backdrop"></div>

    <div class="modal-dialog formula-confirm-dialog">

        <div class="modal-header">

            <div class="formula-confirm-heading">

                <span class="formula-confirm-icon">
                    <svg
                        width="24"
                        height="24"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true">
                        <path d="M12 3v12" />
                        <path d="m7 10 5 5 5-5" />
                        <path d="M5 17v3h14v-3" />
                    </svg>
                </span>

                <div>
                    <span class="eyebrow">
                        Confirmación requerida
                    </span>

                    <h2 id="formula-publish-title">
                        Publicar fórmula
                    </h2>
                </div>

            </div>

            <button
                type="button"
                class="modal-close"
                data-modal-close
                aria-label="Cerrar">
                ×
            </button>

        </div>

        <form
            id="formula-publish-form"
            method="POST"
            action=""
            data-action-template="<?= e(
                                        url('/formulas/versiones/__ID__/publicar')
                                    ) ?>">

            <?= csrf_field() ?>

            <div class="modal-body">

                <p class="formula-confirm-description">
                    Estás a punto de publicar la siguiente
                    versión de la fórmula:
                </p>

                <div class="formula-confirm-summary">

                    <span class="formula-confirm-label">
                        Fórmula seleccionada
                    </span>

                    <strong id="formula-publish-name">
                        —
                    </strong>

                    <span
                        id="formula-publish-version"
                        class="formula-confirm-version">
                        —
                    </span>

                </div>

                <div class="formula-confirm-warning">

                    <span class="formula-warning-symbol">
                        !
                    </span>

                    <div>
                        <strong>
                            Esta acción requiere confirmación
                        </strong>

                        <p>
                            La versión publicada quedará
                            bloqueada para edición.
                            Si necesitas modificarla
                            posteriormente, deberás crear
                            una nueva versión.
                        </p>
                    </div>

                </div>

            </div>

            <div class="modal-footer formula-confirm-footer">

                <button
                    type="button"
                    class="btn btn-secondary"
                    data-modal-close>

                    Cancelar

                </button>

                <button
                    type="submit"
                    class="btn btn-primary"
                    id="formula-publish-submit">

                    Sí, publicar

                </button>

            </div>

        </form>

    </div>

</div>
<script src="<?= url('/assets/js/views/formulas.js') ?>" defer></script>