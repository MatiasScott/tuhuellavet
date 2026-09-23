document.addEventListener("DOMContentLoaded", () => {
  const data = window.HospitalizationData || {};

  const applyMedicationModal = document.getElementById("h-apply-medication");

  const applyMedicationForm = document.getElementById("apply-medication-form");

  const applyMedicationName = document.getElementById("apply-medication-name");

  const applyMedicationDose = document.getElementById(
    "apply-medication-prescribed-dose",
  );

  const applyMedicationQuantity = document.getElementById(
    "apply-medication-quantity",
  );

  const applyMedicationUnit = document.getElementById("apply-medication-unit");

  document.querySelectorAll("[data-apply-med]").forEach((button) => {
    button.addEventListener("click", () => {
      const medicationId = button.dataset.applyMed;

      const medicationName = button.dataset.medName || "Medicamento";

      const dose = button.dataset.medDose || "";

      const unitId = button.dataset.medUnitId || "";

      const unit = button.dataset.medUnit || "";

      /*
       * Endpoint de aplicación.
       */
      applyMedicationForm.action = `${data.applicationUrlBase}/${medicationId}/aplicar`;

      /*
       * Información visual.
       */
      applyMedicationName.textContent = medicationName;

      applyMedicationDose.textContent = dose ? `${dose} ${unit}`.trim() : "—";

      /*
       * Por defecto proponemos la dosis prescrita
       * como cantidad aplicada.
       *
       * El usuario puede modificarla.
       */
      applyMedicationQuantity.value = dose;

      /*
       * Seleccionamos la misma unidad
       * prescrita.
       */
      if (unitId) {
        applyMedicationUnit.value = unitId;
      } else {
        applyMedicationUnit.value = "";
      }

      /*
       * Limpiar observaciones.
       */
      const observations = applyMedicationForm.querySelector(
        '[name="observaciones"]',
      );

      if (observations) {
        observations.value = "";
      }

      /*
       * Abrimos el modal utilizando el mismo
       * mecanismo de data-modal-open que usa
       * el sistema global del proyecto.
       */
      const modalTrigger = document.createElement("button");

      modalTrigger.type = "button";
      modalTrigger.dataset.modalOpen = "h-apply-medication";
      modalTrigger.hidden = true;

      document.body.appendChild(modalTrigger);

      modalTrigger.click();
      modalTrigger.remove();
    });
  });

  const cancelApplicationForm = document.getElementById(
    "cancel-application-form",
  );

  const cancelApplicationMedication = document.getElementById(
    "cancel-application-medication",
  );

  const cancelApplicationDose = document.getElementById(
    "cancel-application-dose",
  );

  const cancelApplicationReason = document.getElementById(
    "cancel-application-reason",
  );

  document.querySelectorAll("[data-cancel-application]").forEach((button) => {
    button.addEventListener("click", () => {
      if (
        !cancelApplicationForm ||
        !cancelApplicationMedication ||
        !cancelApplicationDose ||
        !cancelApplicationReason
      ) {
        console.error("No se encontró el modal de anulación de aplicaciones.");

        return;
      }

      const applicationId = button.dataset.cancelApplication;

      const medication = button.dataset.cancelMedication || "Medicamento";

      const quantity = button.dataset.cancelQuantity || "";

      const unit = button.dataset.cancelUnit || "";

      cancelApplicationForm.action = `${data.baseUrl}/hospitalizaciones/${data.eventId}/aplicaciones/${applicationId}/anular`;

      cancelApplicationMedication.textContent = medication;

      cancelApplicationDose.textContent = quantity
        ? `${quantity} ${unit}`.trim()
        : "";

      cancelApplicationReason.value = "";
    });
  });

  const select = document.getElementById("fluid-formula-version");
  const load = document.getElementById("fluid-formula-load");
  const panel = document.getElementById("fluid-formula-panel");
  const variablesBox = document.getElementById("fluid-formula-variables");
  const formulaName = document.getElementById("fluid-formula-name");
  const calculate = document.getElementById("fluid-formula-calculate");
  const execution = document.getElementById("fluid-formula-execution-id");
  const resultBox = document.getElementById("fluid-formula-result");
  const resultValue = document.getElementById("fluid-formula-result-value");
  let activeVersion = null;

  const escapeHtml = (value) =>
    String(value ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");

  async function loadFormula() {
    if (!select?.value) {
      window.alert("Selecciona una fórmula.");
      return;
    }
    try {
      const response = await fetch(
        `${data.baseUrl}/formulas/version/${encodeURIComponent(select.value)}/variables`,
        {
          headers: { Accept: "application/json" },
        },
      );
      const payload = await response.json();
      if (!response.ok || !payload.ok)
        throw new Error(payload.message || "No fue posible cargar la fórmula.");

      activeVersion = Number(select.value);
      formulaName.textContent =
        payload.formula.formula_nombre || payload.formula.nombre || "Fórmula";
      variablesBox.innerHTML = "";

      (payload.variables || []).forEach((variable) => {
        const wrapper = document.createElement("label");
        if (variable.origen_codigo !== "MANUAL") {
          wrapper.innerHTML = `<span>${escapeHtml(variable.etiqueta)}</span><div class="formula-auto-variable">Automático: ${escapeHtml(variable.origen_nombre)}</div>`;
        } else {
          wrapper.innerHTML = `<span>${escapeHtml(variable.etiqueta)}${Number(variable.obligatorio) === 1 ? " *" : ""}</span><input type="number" step="any" data-formula-variable="${escapeHtml(variable.codigo)}" ${variable.valor_minimo !== null ? `min="${escapeHtml(variable.valor_minimo)}"` : ""} ${variable.valor_maximo !== null ? `max="${escapeHtml(variable.valor_maximo)}"` : ""} ${variable.valor_default !== null ? `value="${escapeHtml(variable.valor_default)}"` : ""} ${Number(variable.obligatorio) === 1 ? "required" : ""}>${variable.unidad_simbolo ? `<small>${escapeHtml(variable.unidad_simbolo)}</small>` : ""}`;
        }
        variablesBox.appendChild(wrapper);
      });

      execution.value = "";
      resultBox.hidden = true;
      panel.hidden = false;
    } catch (error) {
      window.alert(error.message || "No fue posible cargar la fórmula.");
    }
  }

  async function calculateFormula() {
    if (!activeVersion) {
      window.alert("Primero carga una fórmula.");
      return;
    }

    const formData = new FormData();
    formData.append("_token", data.csrf || "");
    formData.append("formula_version_id", String(activeVersion));
    formData.append("animal_id", String(data.patientId));
    formData.append("evento_clinico_id", String(data.eventId));
    formData.append("simulacion", data.isAcademic ? "1" : "0");
    formData.append(
      "contexto",
      data.isAcademic ? "ACADEMICO" : "FLUIDOTERAPIA",
    );
    document.querySelectorAll("[data-formula-variable]").forEach((input) => {
      formData.append(
        `variables[${input.dataset.formulaVariable}]`,
        input.value,
      );
    });

    try {
      calculate.disabled = true;
      calculate.textContent = "Calculando...";
      const response = await fetch(`${data.baseUrl}/formulas/calcular`, {
        method: "POST",
        body: formData,
        headers: { Accept: "application/json" },
      });
      const payload = await response.json();
      if (!response.ok || !payload.ok)
        throw new Error(payload.message || "No fue posible calcular.");
      execution.value = payload.data.execution_id;
      resultValue.textContent =
        `${payload.data.result} ${payload.data.unit || ""}`.trim();
      resultBox.hidden = false;
    } catch (error) {
      window.alert(error.message || "No fue posible calcular.");
    } finally {
      calculate.disabled = false;
      calculate.textContent = "Calcular";
    }
  }

  load?.addEventListener("click", loadFormula);
  calculate?.addEventListener("click", calculateFormula);

  /*
|--------------------------------------------------------------------------
| HOSPITALIZACIÓN - MÚLTIPLES MEDICAMENTOS
|--------------------------------------------------------------------------
*/

  const medicationsContainer = document.getElementById(
    "hospital-medications-container",
  );

  const addMedicationButton = document.getElementById(
    "hospital-add-medication",
  );

  if (medicationsContainer && addMedicationButton) {
    /*
     * Reindexa todos los campos.
     *
     * Esto garantiza que PHP reciba:
     *
     * medicamentos[0][...]
     * medicamentos[1][...]
     * medicamentos[2][...]
     */
    const reindexMedications = () => {
      const rows = medicationsContainer.querySelectorAll(
        "[data-medication-row]",
      );

      rows.forEach((row, index) => {
        row.querySelectorAll("[data-medication-field]").forEach((field) => {
          const key = field.dataset.medicationField;

          field.name = `medicamentos[${index}][${key}]`;
        });

        const removeButton = row.querySelector("[data-remove-medication]");

        if (removeButton) {
          /*
           * Siempre dejamos al menos
           * una fila de medicamento.
           */
          removeButton.hidden = rows.length === 1;
        }
      });
    };

    /*
     * Agregar medicamento.
     */
    addMedicationButton.addEventListener("click", () => {
      const firstRow = medicationsContainer.querySelector(
        "[data-medication-row]",
      );

      if (!firstRow) {
        return;
      }

      const newRow = firstRow.cloneNode(true);

      /*
       * Limpiamos todos los valores
       * del clon.
       */
      newRow.querySelectorAll("input, select, textarea").forEach((field) => {
        if (field.tagName === "SELECT") {
          field.selectedIndex = 0;
        } else {
          field.value = "";
        }
      });

      medicationsContainer.appendChild(newRow);

      reindexMedications();
    });

    /*
     * Eliminar medicamento.
     *
     * Event delegation para que funcione
     * también con filas creadas dinámicamente.
     */
    medicationsContainer.addEventListener("click", (event) => {
      const button = event.target.closest("[data-remove-medication]");

      if (!button) {
        return;
      }

      const rows = medicationsContainer.querySelectorAll(
        "[data-medication-row]",
      );

      if (rows.length <= 1) {
        return;
      }

      const row = button.closest("[data-medication-row]");

      if (row) {
        row.remove();
      }

      reindexMedications();
    });

    reindexMedications();
  }
});
