/**
 * Validación visual de propietarios.
 *
 * Compatible con formularios de creación y edición.
 * Consulta duplicados mediante AJAX.
 */

(() => {
  "use strict";

  // ==========================================
  // CÉDULA ECUATORIANA
  // ==========================================

  function validarCedula(cedula) {
    if (!/^\d{10}$/.test(cedula)) {
      return false;
    }

    const provincia = Number(cedula.substring(0, 2));

    if (provincia < 1 || (provincia > 24 && provincia !== 30)) {
      return false;
    }

    const tercerDigito = Number(cedula[2]);

    if (tercerDigito >= 6) {
      return false;
    }

    let suma = 0;

    for (let i = 0; i < 9; i++) {
      let numero = Number(cedula[i]);

      if (i % 2 === 0) {
        numero *= 2;

        if (numero > 9) {
          numero -= 9;
        }
      }

      suma += numero;
    }

    const verificador = (10 - (suma % 10)) % 10;

    return verificador === Number(cedula[9]);
  }

  // ==========================================
  // PASAPORTE: VALIDACIÓN DE FORMATO
  // ==========================================

  function validarPasaporte(pasaporte) {
    return /^[A-Z]{2}\d{6,7}$/.test(pasaporte);
  }

  // ==========================================
  // NORMALIZACIÓN
  // ==========================================

  function normalizar(campo, valor) {
    valor = valor.trim();

    if (campo === "identificacion") {
      return valor.replace(/[\s.-]+/g, "");
    }

    if (campo === "email") {
      return valor.toLowerCase();
    }

    if (campo === "celular") {
      let numero = valor.replace(/\D/g, "");

      if (numero.startsWith("593") && numero.length === 12) {
        numero = "0" + numero.substring(3);
      }

      return numero;
    }

    return valor;
  }

  // ==========================================
  // VALIDACIÓN LOCAL
  // ==========================================

  function validarFormato(campo, valor, tipo) {
    if (!valor) {
      return {
        valido: true,
        mensaje: "",
      };
    }

    if (campo === "identificacion") {
      if (tipo === "CEDULA") {
        return validarCedula(valor)
          ? {
              valido: true,
              mensaje: "",
            }
          : {
              valido: false,
              mensaje: "La cédula ecuatoriana no es válida.",
            };
      }

      if (tipo === "PASAPORTE") {
        return validarPasaporte(valor.toUpperCase())
          ? {
              valido: true,
              mensaje: "",
            }
          : {
              valido: false,
              mensaje:
                "El pasaporte debe contener dos letras y entre seis y siete números.",
            };
      }

      if (tipo === "RUC") {
        return /^\d{13}$/.test(valor)
          ? {
              valido: true,
              mensaje: "",
            }
          : {
              valido: false,
              mensaje: "El RUC debe contener 13 dígitos.",
            };
      }
    }

    if (campo === "email") {
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valor)
        ? {
            valido: true,
            mensaje: "",
          }
        : {
            valido: false,
            mensaje: "Ingresa un correo electrónico válido.",
          };
    }

    if (campo === "celular") {
      return /^09\d{8}$/.test(valor)
        ? {
            valido: true,
            mensaje: "",
          }
        : {
            valido: false,
            mensaje: "El celular debe tener 10 dígitos y comenzar con 09.",
          };
    }

    return {
      valido: true,
      mensaje: "",
    };
  }

  // ==========================================
  // INICIALIZACIÓN DE FORMULARIOS
  // ==========================================

  document.querySelectorAll(".owner-validation-form").forEach((form) => {
    const validationUrl = form.dataset.validationUrl;
    const ownerId = form.dataset.ownerId || "";

    const accessInputs = form.querySelectorAll("[data-owner-access]");
    const emailInput = form.querySelector('[name="email"]');

    function accessMode() {
      return (
        form.querySelector("[data-owner-access]:checked")?.value || "create"
      );
    }

    function updateAccessRequirements() {
      if (emailInput && accessInputs.length) {
        emailInput.required = accessMode() === "create";
        if (!emailInput.required) emailInput.setCustomValidity("");
      }
    }

    updateAccessRequirements();

    if (form.hasAttribute("data-required-markers")) {
      const updateRequiredMarker = (field) => {
        const title = field.closest("label")?.querySelector(":scope > span");
        if (!title) return;
        const text = title.textContent.trim().replace(/\s*\*$/, "");
        title.textContent = text + (field.required && !field.disabled ? " *" : "");
      };
      const fields = form.querySelectorAll(
        'input:not([type="hidden"]):not([type="radio"]):not([type="checkbox"]), select, textarea',
      );
      fields.forEach(updateRequiredMarker);
      const observer = new MutationObserver((records) => {
        records.forEach((record) => updateRequiredMarker(record.target));
      });
      fields.forEach((field) => observer.observe(field, {
        attributes: true,
        attributeFilter: ["required", "disabled"],
      }));
    }

    form.addEventListener("invalid", (event) => {
      const field = event.target;
      if (!field.validity.valueMissing) return;
      const label = field.closest("label")?.querySelector("span");
      const name = label?.textContent.trim().replace(/\s*\*$/, "") || "este campo";
      field.setCustomValidity(`Completa el campo obligatorio: ${name}.`);
    }, true);
    ["input", "change"].forEach((eventName) => {
      form.addEventListener(eventName, (event) => {
        if (event.target.validity?.customError) {
          event.target.setCustomValidity("");
        }
      });
    });

    const fields = ["identificacion", "email", "celular"];

    const timers = new Map();
    const controllers = new Map();
    const versions = new Map();

    const states = new Map();

    fields.forEach((field) => {
      states.set(field, "idle");
      versions.set(field, 0);
    });

    const submitButton = form.querySelector(
      'button[type="submit"], button:not([type])',
    );

    const typeSelect = form.querySelector("[data-owner-type]");

    function tipoDocumento() {
      const option = typeSelect?.selectedOptions?.[0];

      return option?.dataset.code || "";
    }

    function setMessage(field, status, message) {
      const input = form.querySelector(`[data-owner-field="${field}"]`);

      const span = form.querySelector(`[data-message-for="${field}"]`);

      if (!input || !span) {
        return;
      }

      states.set(field, status);

      span.textContent = message;

      span.classList.remove("is-error", "is-success", "is-checking");

      input.classList.remove("owner-input-error", "owner-input-success");

      input.removeAttribute("aria-invalid");

      if (status === "error") {
        span.classList.add("is-error");
        input.classList.add("owner-input-error");
        input.setAttribute("aria-invalid", "true");
      }

      if (status === "success") {
        span.classList.add("is-success");
        input.classList.add("owner-input-success");
      }

      if (status === "checking") {
        span.classList.add("is-checking");
      }

      updateSubmit();
    }

    function updateSubmit() {
      if (!submitButton) {
        return;
      }

      const blocked = [...states.values()].some(
        (state) => state === "error" || state === "checking",
      );

      submitButton.disabled = blocked;
    }

    async function validate(field) {
      const input = form.querySelector(`[data-owner-field="${field}"]`);

      if (!input) {
        return;
      }

      clearTimeout(timers.get(field));

      controllers.get(field)?.abort();

      const version = (versions.get(field) || 0) + 1;

      versions.set(field, version);

      const value = normalizar(field, input.value);

      const type = tipoDocumento();

      if (!value) {
        setMessage(field, "idle", "");
        return;
      }

      const format = validarFormato(field, value, type);

      if (!format.valido) {
        setMessage(field, "error", format.mensaje);

        return;
      }

      setMessage(field, "checking", "Verificando disponibilidad...");

      const controller = new AbortController();

      controllers.set(field, controller);

      try {
        const url = new URL(validationUrl, window.location.origin);

        url.searchParams.set("campo", field);
        url.searchParams.set("valor", value);

        if (field === "email") {
          url.searchParams.set("modo_acceso", accessMode());
        }

        if (ownerId) {
          url.searchParams.set("propietario_id", ownerId);
        }

        const response = await fetch(url, {
          method: "GET",
          credentials: "same-origin",
          signal: controller.signal,
          headers: {
            Accept: "application/json",
          },
        });

        if (!response.ok) {
          throw new Error("No se pudo verificar el dato.");
        }

        const data = await response.json();

        // Ignorar respuestas antiguas.
        if (versions.get(field) !== version) {
          return;
        }

        if (!data.ok) {
          throw new Error(data.message || "Error de validación.");
        }

        if (data.status === "existing_owner") {
          setMessage(field, "error", data.message);

          return;
        }

        if (data.status === "unavailable_user") {
          setMessage(field, "error", data.message);

          return;
        }

        if (data.status === "existing_user") {
          setMessage(field, "success", data.message);

          return;
        }

        setMessage(field, "success", data.message || "Dato disponible.");
      } catch (error) {
        if (error.name === "AbortError") {
          return;
        }

        if (versions.get(field) !== version) {
          return;
        }

        setMessage(field, "error", "No se pudo verificar. Intenta nuevamente.");
      }
    }

    function scheduleValidation(field) {
      clearTimeout(timers.get(field));

      controllers.get(field)?.abort();

      // Invalidar respuestas anteriores inmediatamente.
      versions.set(field, (versions.get(field) || 0) + 1);

      const input = form.querySelector(`[data-owner-field="${field}"]`);

      const value = normalizar(field, input?.value || "");

      if (!value) {
        setMessage(field, "idle", "");
        return;
      }

      const format = validarFormato(field, value, tipoDocumento());

      if (!format.valido) {
        setMessage(field, "error", format.mensaje);

        return;
      }

      setMessage(field, "checking", "Verificando disponibilidad...");

      const timer = setTimeout(() => {
        validate(field);
      }, 400);

      timers.set(field, timer);
    }

    fields.forEach((field) => {
      const input = form.querySelector(`[data-owner-field="${field}"]`);

      if (!input) {
        return;
      }

      input.addEventListener("input", () => {
        scheduleValidation(field);
      });

      input.addEventListener("blur", () => {
        scheduleValidation(field);
      });
    });

    typeSelect?.addEventListener("change", () => {
      scheduleValidation("identificacion");
    });

    accessInputs.forEach((input) => {
      input.addEventListener("change", () => {
        updateAccessRequirements();
        scheduleValidation("email");
      });
    });

    // Validar valores existentes en edición.
    fields.forEach((field) => {
      const input = form.querySelector(`[data-owner-field="${field}"]`);

      if (input?.value.trim()) {
        scheduleValidation(field);
      }
    });

    form.addEventListener("submit", (event) => {
      const blocked = [...states.values()].some(
        (state) => state === "error" || state === "checking",
      );

      if (blocked) {
        event.preventDefault();
      }
    });

    updateSubmit();
  });
})();
document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll(".owner-validation-form").forEach((form) => {
    const enabled = form.querySelector("[data-fiscal-enabled]");

    const container = form.querySelector("[data-fiscal-container]");

    const same = form.querySelector("[data-fiscal-same]");

    const fields = form.querySelector("[data-fiscal-fields]");

    if (!enabled || !container || !same || !fields) {
      return;
    }

    const fiscalInputs = fields.querySelectorAll("[data-fiscal-input]");
    const ownerType = form.querySelector('[name="tipo_identificacion_id"]');
    const ownerIdentification = form.querySelector('[name="identificacion"]');
    const fiscalType = form.querySelector('[name="fiscal_tipo_identificacion_id"]');
    const fiscalIdentification = form.querySelector('[name="fiscal_identificacion"]');
    const fiscalName = form.querySelector('[name="fiscal_razon_social"]');
    const notice = form.querySelector("[data-invoice-requirements]");
    const invoiceFields = [
      ["codigo_pais", "país"],
      ["codigo_provincia", "provincia"],
      ["codigo_canton", "cantón"],
      ["codigo_parroquia", "parroquia"],
    ];

    function updateInvoiceRequirements() {
      if (!notice || !enabled.checked) return;
      const missing = [];
      const value = (name) => form.elements.namedItem(name)?.value?.trim() || "";
      const type = same.checked ? ownerType : fiscalType;
      const typeCode = type.selectedOptions[0]?.dataset.code || "";
      if (!typeCode) missing.push("tipo de identificación fiscal");
      else if (!["CEDULA", "RUC", "PASAPORTE"].includes(typeCode)) {
        missing.push(typeCode === "CONSUMIDOR_FINAL"
          ? "consumidor final aún no está habilitado para facturar"
          : "tipo de identificación fiscal compatible");
      }
      if (!value(same.checked ? "identificacion" : "fiscal_identificacion")) {
        missing.push("identificación fiscal");
      }
      if (!value(same.checked ? "nombres" : "fiscal_razon_social")) {
        missing.push("razón social fiscal");
      }
      const email = form.elements.namedItem(same.checked ? "email" : "fiscal_email");
      if (!email.value.trim() || !email.validity.valid) missing.push("correo de facturación válido");
      invoiceFields.forEach(([name, label]) => {
        if (!value(name)) missing.push(label);
      });
      const ownerCode = ownerType.selectedOptions[0]?.dataset.code || "";
      const natural = value("es_persona_natural");
      if (!ownerCode || (ownerCode === "RUC" && !natural)) {
        missing.push("tipo de persona del propietario");
      } else if (["CEDULA", "PASAPORTE"].includes(typeCode) &&
          (ownerCode === "CONSUMIDOR_FINAL" || (ownerCode === "RUC" && natural === "0"))) {
        missing.push("tipo de persona natural para la identificación fiscal");
      }
      notice.textContent = missing.length
        ? `Para emitir facturas con esta integración, falta: ${missing.join(", ")}. Puede guardar la ficha y completar estos datos antes de facturar.`
        : "Datos del cliente completos para la validación local de facturación.";
    }

    const refresh = () => {
      const fiscalEnabled = enabled.checked;
      const useSame = same.checked;

      container.hidden = !fiscalEnabled;

      /*
       * Campos fiscales manuales.
       */
      fields.hidden = !fiscalEnabled || useSame;

      fiscalInputs.forEach((input) => {
        input.disabled = !fiscalEnabled || useSame;
      });

      ownerType.required = fiscalEnabled && useSame;
      ownerIdentification.required = fiscalEnabled && useSame;
      fiscalType.required = fiscalEnabled && !useSame;
      fiscalIdentification.required = fiscalEnabled && !useSame;
      fiscalName.required = fiscalEnabled && !useSame;
      [ownerType, ownerIdentification, fiscalType, fiscalIdentification, fiscalName]
        .filter((input) => !input.required)
        .forEach((input) => input.setCustomValidity(""));

      /*
       * El checkbox "usar mismos datos"
       * tampoco debe enviarse si toda la
       * sección fiscal está desactivada.
       */
      same.disabled = !fiscalEnabled;
      updateInvoiceRequirements();
    };

    enabled.addEventListener("change", refresh);

    same.addEventListener("change", refresh);
    form.addEventListener("input", updateInvoiceRequirements);
    form.addEventListener("change", updateInvoiceRequirements);
    const locationObserver = new MutationObserver(updateInvoiceRequirements);
    invoiceFields.forEach(([name]) => {
      const field = form.elements.namedItem(name);
      if (field) locationObserver.observe(field, { childList: true, subtree: true, attributes: true });
    });

    refresh();
  });
});
