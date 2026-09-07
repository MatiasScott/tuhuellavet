document.addEventListener(
    'DOMContentLoaded',
    () => {

        const data =
            window.TreatmentData;

        if (!data) {
            return;
        }

        const container =
            document.getElementById(
                'treatment-medications'
            );

        const addButton =
            document.getElementById(
                'add-treatment-medication'
            );

        let medicationIndex = 0;


        const escapeHtml =
            value => {

                const div =
                    document
                        .createElement(
                            'div'
                        );

                div.textContent =
                    value ?? '';

                return div.innerHTML;
            };


        const options =
            (
                values,
                valueKey,
                labelCallback
            ) => {

                return values
                    .map(
                        item => `
                            <option
                                value="${item[valueKey]}"
                            >
                                ${
                                    escapeHtml(
                                        labelCallback(
                                            item
                                        )
                                    )
                                }
                            </option>
                        `
                    )
                    .join('');
            };


        const addMedication = () => {

            if (!container) {
                return;
            }

            const index =
                medicationIndex++;

            const card =
                document
                    .createElement(
                        'div'
                    );

            card.className =
                'treatment-medication-editor';

            card.dataset.index =
                index;

            card.innerHTML = `

                <div
                    class="
                        medication-editor-header
                    "
                >

                    <strong>
                        💊 Medicamento
                    </strong>

                    <button
                        type="button"
                        class="
                            btn
                            btn-small
                            btn-danger-soft
                        "
                        data-remove-medication
                    >
                        Quitar
                    </button>

                </div>


                <div class="form-grid">

                    <label>

                        <span>
                            Fármaco *
                        </span>

                        <select
                            name="
                                medicamentos[
                                    ${index}
                                ][farmaco_id]
                            "
                            data-drug
                            required
                        >

                            <option value="">
                                Seleccionar
                            </option>

                            ${
                                options(
                                    data.drugs,
                                    'id',
                                    item =>
                                        item.nombre
                                )
                            }

                        </select>

                    </label>


                    <label>

                        <span>
                            Presentación
                        </span>

                        <select
                            name="
                                medicamentos[
                                    ${index}
                                ][presentacion_id]
                            "
                            data-presentation
                        >

                            <option value="">
                                Seleccionar
                            </option>

                        </select>

                    </label>


                    <label>

                        <span>
                            Vía
                        </span>

                        <select
                            name="
                                medicamentos[
                                    ${index}
                                ][via_administracion_id]
                            "
                        >

                            <option value="">
                                Seleccionar
                            </option>

                            ${
                                options(
                                    data.routes,
                                    'id',
                                    item =>
                                        item.nombre
                                )
                            }

                        </select>

                    </label>


                    <label>

                        <span>
                            Dosis
                        </span>

                        <div
                            class="
                                dose-row
                            "
                        >

                            <input
                                type="number"
                                step="0.000001"
                                min="0"
                                name="
                                    medicamentos[
                                        ${index}
                                    ][dosis_cantidad]
                                "
                                data-dose-input
                            >

                            <select
                                name="
                                    medicamentos[
                                        ${index}
                                    ][dosis_unidad_id]
                                "
                                data-dose-unit
                            >

                                <option value="">
                                    Unidad
                                </option>

                                ${
                                    options(
                                        data.units,
                                        'id',
                                        item =>
                                            `${item.nombre}${
                                                item.simbolo
                                                    ? ` (${item.simbolo})`
                                                    : ''
                                            }`
                                    )
                                }

                            </select>

                        </div>

                    </label>


                    <label>

                        <span>
                            Frecuencia
                        </span>

                        <select
                            name="
                                medicamentos[
                                    ${index}
                                ][frecuencia_id]
                            "
                        >

                            <option value="">
                                Seleccionar
                            </option>

                            ${
                                options(
                                    data.frequencies,
                                    'id',
                                    item =>
                                        item.nombre
                                )
                            }

                        </select>

                    </label>


                    <label>

                        <span>
                            Frecuencia personalizada
                        </span>

                        <input
                            name="
                                medicamentos[
                                    ${index}
                                ][frecuencia_texto]
                            "
                            placeholder="
                                Ej. cada noche
                            "
                        >

                    </label>


                    <label>

                        <span>
                            Duración
                        </span>

                        <div
                            class="
                                dose-row
                            "
                        >

                            <input
                                type="number"
                                min="0"
                                step="0.01"
                                name="
                                    medicamentos[
                                        ${index}
                                    ][duracion_cantidad]
                                "
                            >

                            <select
                                name="
                                    medicamentos[
                                        ${index}
                                    ][duracion_unidad_id]
                                "
                            >

                                <option value="">
                                    Unidad
                                </option>

                                ${
                                    options(
                                        data.timeUnits,
                                        'id',
                                        item =>
                                            item.nombre
                                    )
                                }

                            </select>

                        </div>

                    </label>


                    <label>

                        <span>
                            Fórmula médica
                        </span>

                        <select
                            data-formula
                        >

                            <option value="">
                                Sin cálculo
                            </option>

                            ${
                                options(
                                    data.formulas,
                                    'formula_version_id',
                                    item =>
                                        `${item.nombre} · v${item.numero_version}`
                                )
                            }

                        </select>

                    </label>


                    <input
                        type="hidden"
                        name="
                            medicamentos[
                                ${index}
                            ][formula_ejecucion_id]
                        "
                        data-formula-execution
                    >


                    <div
                        class="
                            formula-calculator
                            field-full
                        "
                        data-formula-calculator
                        hidden
                    >

                        <div
                            class="
                                formula-calculator-title
                            "
                        >
                            🧮 Variables de fórmula
                        </div>

                        <div
                            data-formula-fields
                            class="
                                formula-variable-grid
                            "
                        ></div>

                        <button
                            type="button"
                            class="
                                btn
                                btn-secondary
                            "
                            data-calculate-formula
                        >
                            Calcular dosis
                        </button>

                        <div
                            class="
                                formula-calculation-result
                            "
                            data-formula-result
                        ></div>

                    </div>


                    <label
                        class="field-full"
                    >

                        <span>
                            Instrucciones
                        </span>

                        <textarea
                            name="
                                medicamentos[
                                    ${index}
                                ][instrucciones]
                            "
                            rows="2"
                        ></textarea>

                    </label>

                </div>
            `;

            container.appendChild(
                card
            );

            initializeMedicationCard(
                card
            );
        };


        const initializeMedicationCard =
            card => {

                const drug =
                    card.querySelector(
                        '[data-drug]'
                    );

                const presentation =
                    card.querySelector(
                        '[data-presentation]'
                    );

                const formula =
                    card.querySelector(
                        '[data-formula]'
                    );

                const calculator =
                    card.querySelector(
                        '[data-formula-calculator]'
                    );


                drug.addEventListener(
                    'change',
                    () => {

                        const drugId =
                            drug.value;

                        presentation
                            .innerHTML =
                            '<option value="">Seleccionar</option>';

                        data.presentations
                            .filter(
                                item =>
                                    String(
                                        item.farmaco_id
                                    )
                                    === drugId
                            )
                            .forEach(
                                item => {

                                    const option =
                                        document
                                            .createElement(
                                                'option'
                                            );

                                    option.value =
                                        item.id;

                                    option.textContent =
                                        item.nombre_comercial
                                        ||
                                        item.forma_farmaceutica;

                                    presentation
                                        .appendChild(
                                            option
                                        );
                                }
                            );
                    }
                );


                formula.addEventListener(
                    'change',
                    async () => {

                        calculator.hidden =
                            !formula.value;

                        if (
                            !formula.value
                        ) {
                            return;
                        }

                        await loadFormulaVariables(
                            card,
                            formula.value
                        );
                    }
                );


                card
                    .querySelector(
                        '[data-remove-medication]'
                    )
                    .addEventListener(
                        'click',
                        () => {
                            card.remove();
                        }
                    );


                card
                    .querySelector(
                        '[data-calculate-formula]'
                    )
                    .addEventListener(
                        'click',
                        () =>
                            calculateFormula(
                                card
                            )
                    );
            };


        const loadFormulaVariables =
            async (
                card,
                versionId
            ) => {

                const fields =
                    card.querySelector(
                        '[data-formula-fields]'
                    );

                fields.innerHTML =
                    'Cargando...';

                const response =
                    await fetch(
                        `${data.baseUrl}/formulas/version/${versionId}/variables`
                    );

                const payload =
                    await response.json();

                if (!payload.ok) {
                    fields.innerHTML =
                        escapeHtml(
                            payload.message
                        );

                    return;
                }

                fields.innerHTML = '';

                payload.variables
                    .forEach(
                        variable => {

                            const wrapper =
                                document
                                    .createElement(
                                        'label'
                                    );

                            const automatic =
                                variable
                                    .origen_codigo
                                !== 'MANUAL';

                            wrapper.innerHTML = `

                                <span>
                                    ${
                                        escapeHtml(
                                            variable
                                                .etiqueta
                                        )
                                    }

                                    ${
                                        variable
                                            .unidad_simbolo
                                            ? `(${escapeHtml(variable.unidad_simbolo)})`
                                            : ''
                                    }
                                </span>

                                ${
                                    automatic
                                        ? `
                                            <input
                                                value="${
                                                    escapeHtml(
                                                        variable
                                                            .origen_nombre
                                                    )
                                                }"
                                                disabled
                                            >
                                        `
                                        : `
                                            <input
                                                type="number"
                                                step="0.000001"

                                                data-formula-variable="${
                                                    escapeHtml(
                                                        variable.codigo
                                                    )
                                                }"

                                                ${
                                                    variable.obligatorio
                                                        ? 'required'
                                                        : ''
                                                }

                                                ${
                                                    variable.valor_default
                                                        !== null
                                                        ? `value="${variable.valor_default}"`
                                                        : ''
                                                }
                                            >
                                        `
                                }
                            `;

                            fields
                                .appendChild(
                                    wrapper
                                );
                        }
                    );
            };


        const calculateFormula =
            async card => {

                const formula =
                    card.querySelector(
                        '[data-formula]'
                    );

                const resultBox =
                    card.querySelector(
                        '[data-formula-result]'
                    );

                const executionInput =
                    card.querySelector(
                        '[data-formula-execution]'
                    );

                const doseInput =
                    card.querySelector(
                        '[data-dose-input]'
                    );

                const variables = {};

                card
                    .querySelectorAll(
                        '[data-formula-variable]'
                    )
                    .forEach(
                        input => {

                            variables[
                                input.dataset
                                    .formulaVariable
                            ] = input.value;
                        }
                    );


                resultBox.textContent =
                    'Calculando...';


                const body =
                    new URLSearchParams();

                body.append(
                    '_token',
                    data.csrf
                );

                body.append(
                    'formula_version_id',
                    formula.value
                );

                body.append(
                    'animal_id',
                    data.patientId
                );

                body.append(
                    'evento_clinico_id',
                    data.eventId
                );


                Object.entries(
                    variables
                )
                .forEach(
                    ([key, value]) => {

                        body.append(
                            `variables[${key}]`,
                            value
                        );

                    }
                );


                const response =
                    await fetch(
                        `${data.baseUrl}/formulas/calcular`,
                        {
                            method: 'POST',

                            headers: {
                                'Content-Type':
                                    'application/x-www-form-urlencoded;charset=UTF-8'
                            },

                            body:
                                body.toString()
                        }
                    );


                const payload =
                    await response.json();


                if (!payload.ok) {

                    resultBox
                        .textContent =
                        payload.message;

                    resultBox
                        .classList
                        .add('is-error');

                    return;
                }


                resultBox
                    .classList
                    .remove('is-error');


                resultBox
                    .innerHTML =
                    `
                        Resultado:
                        <strong>
                            ${
                                payload.data.result
                            }
                            ${
                                escapeHtml(
                                    payload.data.unit
                                    ?? ''
                                )
                            }
                        </strong>
                    `;


                executionInput.value =
                    payload
                        .data
                        .execution_id;


                /*
                 * El resultado también
                 * pasa automáticamente
                 * a la dosis.
                 */
                doseInput.value =
                    payload
                        .data
                        .result;
            };


        if (addButton) {

            addButton
                .addEventListener(
                    'click',
                    addMedication
                );

            /*
             * Arrancamos con una fila.
             */
            addMedication();
        }

        document
    .querySelectorAll(
        '[data-medication-apply]'
    )
    .forEach(
        button => {

            button
                .addEventListener(
                    'click',
                    () => {

                        const form =
                            document
                                .getElementById(
                                    'medication-apply-form'
                                );

                        const eventId =
                            button.dataset.event;

                        const medicationId =
                            button
                                .dataset
                                .medication;

                        form.action =
                            `${data.baseUrl}/consultas/${eventId}/tratamientos/medicamentos/${medicationId}/aplicar`;

                        document
                            .getElementById(
                                'applied-quantity'
                            )
                            .value =
                                button.dataset
                                    .dose
                                || '';

                        document
                            .getElementById(
                                'applied-unit'
                            )
                            .value =
                                button.dataset
                                    .unit
                                || '';

                        const modal =
                            document
                                .getElementById(
                                    'medication-apply'
                                );

                        modal
                            .classList
                            .add('is-open');

                        document.body
                            .classList
                            .add(
                                'modal-open'
                            );
                    }
                );
        }
    );

    }

);