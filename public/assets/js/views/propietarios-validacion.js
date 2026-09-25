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

    function accessMode() {
      return (
        form.querySelector("[data-owner-access]:checked")?.value || "create"
      );
    }

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

      /*
       * El checkbox "usar mismos datos"
       * tampoco debe enviarse si toda la
       * sección fiscal está desactivada.
       */
      same.disabled = !fiscalEnabled;
    };

    enabled.addEventListener("change", refresh);

    same.addEventListener("change", refresh);

    refresh();
  });
});
