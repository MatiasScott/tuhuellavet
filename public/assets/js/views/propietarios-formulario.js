(() => {
  "use strict";

  function initializeOwnerForm(form) {
    const typeSelect = form.querySelector("[data-owner-type]");
    const naturalContainer = form.querySelector(
      "[data-owner-natural-container]",
    );
    const naturalSelect = form.querySelector("[data-owner-natural]");

    if (!typeSelect || !naturalContainer || !naturalSelect) {
      return;
    }

    function documentType() {
      return typeSelect.selectedOptions?.[0]?.dataset.code || "";
    }

    function refreshPersonType() {
      const type = documentType();

      /*
       * iConta distingue:
       *
       * C = Cédula
       * N = RUC persona natural
       * R = RUC persona jurídica
       * P = Pasaporte
       * X = Consumidor final
       *
       * Solamente RUC necesita que el usuario
       * indique explícitamente Natural/Jurídica.
       */
      const requiresSelection = type === "RUC";

      naturalContainer.hidden = !requiresSelection;
      naturalSelect.disabled = !requiresSelection;
      naturalSelect.required = requiresSelection;

      if (!requiresSelection) {
        naturalSelect.value = "";
      }
    }

    typeSelect.addEventListener("change", refreshPersonType);

    refreshPersonType();
  }

  document.addEventListener("DOMContentLoaded", () => {
    document
      .querySelectorAll(".owner-validation-form")
      .forEach(initializeOwnerForm);
  });
})();
