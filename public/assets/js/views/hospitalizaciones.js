document.addEventListener('DOMContentLoaded', () => {
    const data = window.HospitalizationData || {};

    document.querySelectorAll('[data-apply-med]').forEach((button) => {
        button.addEventListener('click', () => {
            const quantity = window.prompt('Cantidad aplicada');
            if (!quantity) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `${data.applicationUrlBase}/${button.dataset.applyMed}/aplicar`;

            const csrf = document.createElement('input');
            csrf.type = 'hidden';
            csrf.name = '_token';
            csrf.value = data.csrf || '';
            form.appendChild(csrf);

            const qty = document.createElement('input');
            qty.type = 'hidden';
            qty.name = 'cantidad_aplicada';
            qty.value = quantity;
            form.appendChild(qty);

            document.body.appendChild(form);
            form.submit();
        });
    });

    const select = document.getElementById('fluid-formula-version');
    const load = document.getElementById('fluid-formula-load');
    const panel = document.getElementById('fluid-formula-panel');
    const variablesBox = document.getElementById('fluid-formula-variables');
    const formulaName = document.getElementById('fluid-formula-name');
    const calculate = document.getElementById('fluid-formula-calculate');
    const execution = document.getElementById('fluid-formula-execution-id');
    const resultBox = document.getElementById('fluid-formula-result');
    const resultValue = document.getElementById('fluid-formula-result-value');
    let activeVersion = null;

    const escapeHtml = (value) => String(value ?? '')
        .replaceAll('&', '&amp;').replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;').replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    async function loadFormula() {
        if (!select?.value) {
            window.alert('Selecciona una fórmula.');
            return;
        }
        try {
            const response = await fetch(`${data.baseUrl}/formulas/version/${encodeURIComponent(select.value)}/variables`, {
                headers: {Accept: 'application/json'}
            });
            const payload = await response.json();
            if (!response.ok || !payload.ok) throw new Error(payload.message || 'No fue posible cargar la fórmula.');

            activeVersion = Number(select.value);
            formulaName.textContent = payload.formula.formula_nombre || payload.formula.nombre || 'Fórmula';
            variablesBox.innerHTML = '';

            (payload.variables || []).forEach((variable) => {
                const wrapper = document.createElement('label');
                if (variable.origen_codigo !== 'MANUAL') {
                    wrapper.innerHTML = `<span>${escapeHtml(variable.etiqueta)}</span><div class="formula-auto-variable">Automático: ${escapeHtml(variable.origen_nombre)}</div>`;
                } else {
                    wrapper.innerHTML = `<span>${escapeHtml(variable.etiqueta)}${Number(variable.obligatorio) === 1 ? ' *' : ''}</span><input type="number" step="any" data-formula-variable="${escapeHtml(variable.codigo)}" ${variable.valor_minimo !== null ? `min="${escapeHtml(variable.valor_minimo)}"` : ''} ${variable.valor_maximo !== null ? `max="${escapeHtml(variable.valor_maximo)}"` : ''} ${variable.valor_default !== null ? `value="${escapeHtml(variable.valor_default)}"` : ''} ${Number(variable.obligatorio) === 1 ? 'required' : ''}>${variable.unidad_simbolo ? `<small>${escapeHtml(variable.unidad_simbolo)}</small>` : ''}`;
                }
                variablesBox.appendChild(wrapper);
            });

            execution.value = '';
            resultBox.hidden = true;
            panel.hidden = false;
        } catch (error) {
            window.alert(error.message || 'No fue posible cargar la fórmula.');
        }
    }

    async function calculateFormula() {
        if (!activeVersion) {
            window.alert('Primero carga una fórmula.');
            return;
        }

        const formData = new FormData();
        formData.append('_token', data.csrf || '');
        formData.append('formula_version_id', String(activeVersion));
        formData.append('animal_id', String(data.patientId));
        formData.append('evento_clinico_id', String(data.eventId));
        formData.append('simulacion', data.isAcademic ? '1' : '0');
        formData.append('contexto', data.isAcademic ? 'ACADEMICO' : 'FLUIDOTERAPIA');
        document.querySelectorAll('[data-formula-variable]').forEach((input) => {
            formData.append(`variables[${input.dataset.formulaVariable}]`, input.value);
        });

        try {
            calculate.disabled = true;
            calculate.textContent = 'Calculando...';
            const response = await fetch(`${data.baseUrl}/formulas/calcular`, {
                method: 'POST', body: formData, headers: {Accept: 'application/json'}
            });
            const payload = await response.json();
            if (!response.ok || !payload.ok) throw new Error(payload.message || 'No fue posible calcular.');
            execution.value = payload.data.execution_id;
            resultValue.textContent = `${payload.data.result} ${payload.data.unit || ''}`.trim();
            resultBox.hidden = false;
        } catch (error) {
            window.alert(error.message || 'No fue posible calcular.');
        } finally {
            calculate.disabled = false;
            calculate.textContent = 'Calcular';
        }
    }

    load?.addEventListener('click', loadFormula);
    calculate?.addEventListener('click', calculateFormula);
});
