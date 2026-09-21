document.addEventListener("DOMContentLoaded", function () {
  const baseUrl = document
    .querySelector("#formula-edit-form")
    ?.getAttribute("action");

  /*
   * Utilidades.
   */

  function getField(container, name) {
    return container.querySelector(`[data-field="${name}"]`);
  }

  function setMessage(element, message, type = "info") {
    if (!element) return;

    element.className = `alert alert-${type}`;
    element.textContent = message;
  }

  async function getVersion(versionId) {
    const url = document
      .getElementById("formula-page-config")
      ?.dataset.formulaDataTemplate?.replace(
        "__ID__",
        encodeURIComponent(versionId),
      );

    if (!url) {
      throw new Error("No se configuró la URL de consulta de versiones.");
    }

    const response = await fetch(url, {
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
      },
    });

    const data = await response.json();

    if (!response.ok || !data.ok) {
      throw new Error(data.message || "No se pudo cargar la versión.");
    }

    return data;
  }

  /*
   * Nueva versión.
   */

  const versionForm = document.getElementById("formula-version-form");

  const versionName = document.getElementById("formula-version-name");

  const versionExpression = document.getElementById(
    "formula-version-expression",
  );

  document.querySelectorAll("[data-version-formula]").forEach((button) => {
    button.addEventListener("click", function () {
      if (!versionForm) return;

      const id = button.dataset.versionFormula;

      versionForm.reset();

      versionForm.action = versionForm.dataset.actionTemplate.replace(
        "__ID__",
        encodeURIComponent(id),
      );

      versionName.textContent =
        "Fórmula: " + (button.dataset.formulaName || "");

      versionExpression.focus();
    });
  });

  /*
   * Editor de borradores.
   */

  const editForm = document.getElementById("formula-edit-form");

  const editName = document.getElementById("formula-edit-name");

  const editExpression = document.getElementById("formula-edit-expression");

  const editNotes = document.getElementById("formula-edit-notes");

  const editVariables = document.getElementById("formula-edit-variables");

  const editTemplate = document.getElementById("formula-variable-template");

  const editMessage = document.getElementById("formula-edit-message");

  const editSubmit = document.getElementById("formula-edit-submit");

  const addVariableButton = document.getElementById(
    "formula-edit-add-variable",
  );

  let variableIndex = 0;
  let editLoadToken = 0;

  function addVariable(data = {}) {
    if (!editTemplate || !editVariables) {
      return;
    }

    const fragment = editTemplate.content.cloneNode(true);

    const row = fragment.querySelector(".formula-variable-row");

    const index = variableIndex++;

    const fields = [
      "id",
      "codigo",
      "etiqueta",
      "descripcion",
      "tipo_variable_id",
      "origen_variable_id",
      "unidad_medida_id",
      "obligatorio",
      "valor_minimo",
      "valor_maximo",
      "valor_default",
    ];

    fields.forEach((field) => {
      const input = getField(row, field);

      if (!input) return;

      input.name = `variables[${index}][${field}]`;

      if (data[field] !== undefined && data[field] !== null) {
        input.value = String(data[field]);
      } else if (field === "id") {
        input.value = "0";
      }
    });

    const title = row.querySelector(".formula-variable-title");

    title.textContent = data.etiqueta || "Nueva variable";

    const removeButton = row.querySelector("[data-remove-variable]");

    removeButton.addEventListener("click", () => {
      row.remove();
    });

    editVariables.appendChild(fragment);
  }

  /*
   * Detectar identificadores de una expresión.
   *
   * Esta función es una ayuda visual.
   * La validación definitiva debe hacerse
   * en PHP con ExpressionLanguage.
   */

  function detectFormulaVariables(expression) {
    const normalized = expression.toUpperCase();

    const matches = normalized.match(/\b[A-Z][A-Z0-9_]*\b/g) || [];

    const reserved = new Set([
      "TRUE",
      "FALSE",
      "NULL",
      "AND",
      "OR",
      "NOT",
      "IN",
      "MATCHES",
    ]);

    return [...new Set(matches)].filter((code) => !reserved.has(code));
  }

  function synchronizeFormulaVariables() {
    if (!editExpression || !editVariables) {
      return;
    }

    const detected = detectFormulaVariables(editExpression.value);

    const existing = new Set();

    editVariables.querySelectorAll(".formula-variable-row").forEach((row) => {
      const code = getField(row, "codigo")?.value.trim().toUpperCase();

      if (code) {
        existing.add(code);
      }
    });

    detected.forEach((code) => {
      if (existing.has(code)) {
        return;
      }

      addVariable({
        id: 0,
        codigo: code,
        etiqueta: code,
        tipo_variable_id: 1,
        origen_variable_id: 1,
        obligatorio: 1,
      });

      existing.add(code);
    });
  }

  if (editExpression) {
    editExpression.addEventListener("input", synchronizeFormulaVariables);
  }

  if (addVariableButton) {
    addVariableButton.addEventListener("click", function () {
      addVariable();
    });
  }

  document.querySelectorAll("[data-edit-version]").forEach((button) => {
    button.addEventListener("click", async function () {
      const token = ++editLoadToken;
      const versionId = button.dataset.editVersion;

      editSubmit.disabled = true;

      editVariables.replaceChildren();

      variableIndex = 0;

      setMessage(editMessage, "Cargando borrador...");

      try {
        const data = await getVersion(versionId);

        if (token !== editLoadToken) return;

        if (data.version.estado_codigo !== "BORRADOR") {
          throw new Error("Esta versión no está en borrador.");
        }

        editForm.action = editForm.dataset.actionTemplate.replace(
          "__ID__",
          encodeURIComponent(versionId),
        );

        editName.textContent =
          data.version.formula_nombre + " · v" + data.version.numero_version;

        editExpression.value = data.version.expresion || "";

        editNotes.value = data.version.notas_version || "";

        data.variables.forEach(addVariable);

        synchronizeFormulaVariables();

        setMessage(editMessage, "Puedes editar la expresión y sus variables.");

        editSubmit.disabled = false;
      } catch (error) {
        if (token !== editLoadToken) return;

        setMessage(editMessage, error.message, "danger");
      }
    });
  });

  /*
   * Laboratorio de pruebas.
   */

  const testForm = document.getElementById("formula-test-form");

  const testName = document.getElementById("formula-test-name");

  const testVersion = document.getElementById("formula-test-version");

  const testExpression = document.getElementById("formula-test-expression");

  const testVariables = document.getElementById("formula-test-variables");

  const testMessage = document.getElementById("formula-test-message");

  const testResult = document.getElementById("formula-test-result");

  const testSubmit = document.getElementById("formula-test-submit");

  let testLoadToken = 0;

  document.querySelectorAll("[data-test-version]").forEach((button) => {
    button.addEventListener("click", async function () {
      const token = ++testLoadToken;
      const versionId = button.dataset.testVersion;

      testSubmit.disabled = true;

      testVariables.replaceChildren();

      testResult.hidden = true;

      testForm.reset();

      setMessage(testMessage, "Cargando variables...");

      try {
        const data = await getVersion(versionId);

        if (token !== testLoadToken) return;

        testVersion.value = versionId;

        testName.textContent =
          data.version.formula_nombre + " · v" + data.version.numero_version;

        testExpression.value = data.version.expresion;

        data.variables.forEach((variable) => {
          const label = document.createElement("label");

          const title = document.createElement("span");

          title.textContent =
            variable.etiqueta +
            " (" +
            variable.codigo +
            ")" +
            (variable.unidad_simbolo ? " · " + variable.unidad_simbolo : "");

          const input = document.createElement("input");

          input.type = "number";

          input.step =
            variable.tipo_codigo === "ENTERO" ||
            variable.tipo_codigo === "BOOLEANO"
              ? "1"
              : "any";

          if (variable.tipo_codigo === "BOOLEANO") {
            input.min = "0";
            input.max = "1";
          }

          input.name = `variables[${variable.codigo}]`;

          if (variable.valor_minimo !== null) {
            input.min = variable.valor_minimo;
          }

          if (variable.valor_maximo !== null) {
            input.max = variable.valor_maximo;
          }

          if (variable.valor_default !== null) {
            input.value = variable.valor_default;
          }

          input.required = Number(variable.obligatorio) === 1;

          label.append(title, input);

          testVariables.appendChild(label);
        });

        setMessage(
          testMessage,
          data.variables.length
            ? "Ingresa los valores y ejecuta la prueba."
            : "La versión no tiene variables definidas.",
          data.variables.length ? "info" : "danger",
        );

        testSubmit.disabled = data.variables.length === 0;
      } catch (error) {
        if (token !== testLoadToken) return;

        setMessage(testMessage, error.message, "danger");
      }
    });
  });

  /*
   * Ejecutar prueba mediante AJAX.
   */

  if (testForm) {
    testForm.addEventListener("submit", async function (event) {
      event.preventDefault();

      if (!testForm.reportValidity()) {
        return;
      }

      testSubmit.disabled = true;

      testResult.hidden = true;

      setMessage(testMessage, "Ejecutando cálculo...");

      try {
        const response = await fetch(testForm.dataset.testUrl, {
          method: "POST",
          credentials: "same-origin",
          headers: {
            Accept: "application/json",
          },
          body: new FormData(testForm),
        });

        const payload = await response.json();

        if (!response.ok || !payload.ok) {
          throw new Error(payload.message || "No se pudo ejecutar la prueba.");
        }

        const result = payload.data;

        testResult.replaceChildren();

        const heading = document.createElement("h3");

        heading.textContent = "Resultado obtenido";

        const value = document.createElement("strong");

        value.textContent = String(result.result) + " " + (result.unit || "");

        testResult.append(heading, value);

        if (result.expected !== null) {
          const comparison = document.createElement("p");

          comparison.textContent =
            "Resultado esperado: " +
            result.expected +
            " · Diferencia: " +
            result.difference +
            " · Coincide: " +
            (result.matches ? "Sí" : "No");

          testResult.appendChild(comparison);
        }

        testResult.hidden = false;

        setMessage(
          testMessage,
          "Prueba matemática ejecutada correctamente.",
          "success",
        );
      } catch (error) {
        setMessage(testMessage, error.message, "danger");
      } finally {
        testSubmit.disabled = false;
      }
    });
  }
  function enableUppercase(input) {
    if (!input) return;

    input.addEventListener("input", function () {
      const start = input.selectionStart;
      const end = input.selectionEnd;

      input.value = input.value.toUpperCase();

      if (start !== null && end !== null) {
        input.setSelectionRange(start, end);
      }
    });
  }

  enableUppercase(document.getElementById("formula-edit-expression"));

  enableUppercase(document.getElementById("formula-version-expression"));

  document
    .querySelectorAll('#formula-create [name="expresion"]')
    .forEach(enableUppercase);

  /*
   * =========================================
   * CONFIRMACIÓN DE PUBLICACIÓN
   * =========================================
   */

  const publishForm = document.getElementById("formula-publish-form");

  const publishName = document.getElementById("formula-publish-name");

  const publishVersion = document.getElementById("formula-publish-version");

  const publishSubmit = document.getElementById("formula-publish-submit");

  if (publishForm && publishName && publishVersion && publishSubmit) {
    document
      .querySelectorAll("[data-publish-version]")
      .forEach(function (button) {
        button.addEventListener("click", function () {
          const versionId = button.dataset.publishVersion;

          const formulaName = button.dataset.publishFormula || "";

          const versionNumber = button.dataset.publishNumber || "";

          if (!/^\d+$/.test(versionId || "")) {
            return;
          }

          publishForm.action = publishForm.dataset.actionTemplate.replace(
            "__ID__",
            encodeURIComponent(versionId),
          );

          publishName.textContent = formulaName;

          publishVersion.textContent = "Versión " + versionNumber;

          publishSubmit.disabled = false;

          publishSubmit.textContent = "Sí, publicar";
        });
      });

    publishForm.addEventListener("submit", function (event) {
      if (!publishForm.action || !publishForm.action.includes("/publicar")) {
        event.preventDefault();
        return;
      }

      /*
       * Evitar doble envío.
       */

      if (publishSubmit.disabled) {
        event.preventDefault();
        return;
      }

      publishSubmit.disabled = true;

      publishSubmit.textContent = "Publicando...";
    });
  }
});
