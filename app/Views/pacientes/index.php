<link rel="stylesheet" href="<?= asset('css/views/pacientes.css') ?>">
<div class="page-heading">
    <div><span class="eyebrow">Clínica</span>
        <h1>Pacientes</h1>
        <p>Expediente y seguimiento de animales.</p>
    </div>
    <?php if (can('pacientes.crear')): ?>
        <button class="btn btn-primary" data-modal-open="patient-create">＋ Nuevo paciente</button>
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
            🔎<input name="q" value="<?= e($search) ?>" placeholder="Nombre, especie, raza o propietario">
        </div>
        <button class="btn btn-secondary">Buscar</button>
    </form>
    <div class="cards-grid"><?php foreach ($patients as $p): ?>
            <a class="soft-panel" href="<?= url('/pacientes/' . $p['id']) ?>">
                <strong>🐾 <?= e($p['nombre'] ?: 'Sin nombre') ?></strong>
                <p class="text-muted">
                    <?= e($p['especie']) ?><?= !empty($p['raza']) ? ' · ' . e($p['raza']) : '' ?>
                </p>
                <p>
                    <?= $p['peso_actual'] !== null ? e($p['peso_actual']) . ' kg' : 'Sin peso' ?>
                </p>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<div class="modal" id="patient-create">
    <div class="modal-backdrop"></div>
    <div class="modal-dialog modal-xl">
        <div class="modal-header">
            <h2>Nuevo paciente</h2>
            <button class="modal-close" data-modal-close>×</button>
        </div>
        <form method="POST" action="<?= url('/pacientes') ?>" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="modal-body">
                <div class="form-grid">
                    <label>
                        <span>Nombre</span>
                        <input name="nombre">
                    </label>
                    <label>
                        <span>Código</span>
                        <input name="codigo">
                    </label>
                    <label>
                        <span>Propietario</span>
                        <select name="propietario_entorno_id">
                            <option value="">Sin propietario</option>
                            <?php foreach ($owners as $o): ?>
                                <option value="<?= $o['id'] ?>"><?= e(trim($o['nombres'] . ' ' . ($o['apellidos'] ?? ''))) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Especie *</span>
                        <select name="especie_id" id="patient-create-species" required>
                            <option value="">Seleccionar</option>
                            <?php foreach ($species as $x): ?>
                                <option value="<?= $x['id'] ?>"><?= e($x['nombre_comun']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label><span>Raza</span><select
                            name="raza_id"
                            id="patient-create-breed"
                            disabled>
                            <option value="">Primero selecciona una especie</option>
                            <?php foreach ($breeds as $breed): ?>
                                <option
                                    value="<?= (int) $breed['id'] ?>"
                                    data-species-id="<?= (int) $breed['especie_id'] ?>">
                                    <?= e($breed['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Sexo</span>
                        <select name="sexo_id">
                            <option value="">Seleccionar</option>
                            <?php foreach ($sexes as $x): ?>
                                <option value="<?= $x['id'] ?>"><?= e($x['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>
                        <span>Fecha nacimiento</span>
                        <input type="date" name="fecha_nacimiento">
                    </label>
                    <label>
                        <span>Color</span>
                        <input name="color">
                    </label>
                    <label>
                        <span>Peso inicial kg</span>
                        <input type="number" step="0.001" name="peso_kg">
                    </label>
                    <label>
                        <span>Foto</span>
                        <input type="file" accept="image/*" name="foto">
                    </label>
                    <label class="field-full">
                        <span>Observaciones</span>
                        <textarea name="observaciones"></textarea>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancelar</button>
                <button class="btn btn-primary">Guardar paciente</button>
            </div>
        </form>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function() {

        const speciesSelect = document.getElementById(
            'patient-create-species'
        );

        const breedSelect = document.getElementById(
            'patient-create-breed'
        );

        if (!speciesSelect || !breedSelect) {
            return;
        }

        // Guardar todas las razas antes de filtrar.
        const allBreeds = Array.from(breedSelect.options)
            .filter(option => option.value !== '')
            .map(option => ({
                id: option.value,
                name: option.textContent.trim(),
                speciesId: option.dataset.speciesId
            }));

        function filterBreeds() {

            const speciesId = speciesSelect.value;

            // Limpiar las opciones anteriores.
            breedSelect.replaceChildren();

            const placeholder = document.createElement('option');
            placeholder.value = '';

            if (!speciesId) {
                placeholder.textContent = 'Primero selecciona una especie';
                breedSelect.appendChild(placeholder);
                breedSelect.disabled = true;
                return;
            }

            placeholder.textContent = 'Seleccionar raza';
            breedSelect.appendChild(placeholder);

            // Mostrar únicamente razas de la especie elegida.
            const matchingBreeds = allBreeds.filter(
                breed => breed.speciesId === speciesId
            );

            matchingBreeds.forEach(function(breed) {

                const option = document.createElement('option');

                option.value = breed.id;
                option.textContent = breed.name;

                breedSelect.appendChild(option);

            });

            breedSelect.disabled = matchingBreeds.length === 0;

            if (matchingBreeds.length === 0) {
                placeholder.textContent = 'No hay razas registradas';
            }

        }

        speciesSelect.addEventListener('change', filterBreeds);

        // Inicializar el filtro al cargar la página.
        filterBreeds();

    });
</script>