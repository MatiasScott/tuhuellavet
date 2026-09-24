document.addEventListener("DOMContentLoaded", () => {
  /*
  |--------------------------------------------------------------------------
  | MODALES
  |--------------------------------------------------------------------------
  */

  const openModal = (id) => {
    const modal = document.getElementById(id);

    if (!modal) {
      return;
    }

    modal.classList.add("is-open");

    document.body.classList.add("modal-open");
  };

  const closeModal = (modal) => {
    if (!modal) {
      return;
    }

    modal.classList.remove("is-open");

    document.body.classList.remove("modal-open");
  };

  /*
  |--------------------------------------------------------------------------
  | ABRIR MODALES DECLARATIVOS
  |--------------------------------------------------------------------------
  */

  document.querySelectorAll("[data-modal-open]").forEach((button) => {
    button.addEventListener("click", () => {
      const modalId = button.dataset.modalOpen;

      if (!modalId) {
        return;
      }

      openModal(modalId);
    });
  });

  /*
  |--------------------------------------------------------------------------
  | CERRAR MODALES
  |--------------------------------------------------------------------------
  */

  document.querySelectorAll("[data-modal-close]").forEach((button) => {
    button.addEventListener("click", () => {
      closeModal(button.closest(".modal"));
    });
  });

  /*
  |--------------------------------------------------------------------------
  | CERRAR AL HACER CLICK EN BACKDROP
  |--------------------------------------------------------------------------
  */

  document.querySelectorAll(".modal-backdrop").forEach((backdrop) => {
    backdrop.addEventListener("click", () => {
      closeModal(backdrop.closest(".modal"));
    });
  });

  /*
  |--------------------------------------------------------------------------
  | PELUQUERÍA - EDITAR
  |--------------------------------------------------------------------------
  */

  document.querySelectorAll("[data-grooming-edit]").forEach((button) => {
    button.addEventListener("click", () => {
      const form = document.getElementById("groom-edit-form");

      const patient = document.getElementById("groom-edit-patient");

      const date = document.getElementById("groom-edit-date");

      const service = document.getElementById("groom-edit-service");

      const nextDate = document.getElementById("groom-edit-next-date");

      const observations = document.getElementById("groom-edit-observations");

      if (!form || !service) {
        return;
      }

      /*
       * La URL viene generada desde PHP.
       * No construimos rutas manualmente.
       */
      form.action = button.dataset.updateUrl || "";

      if (patient) {
        patient.textContent = button.dataset.patient
          ? "Paciente: " + button.dataset.patient
          : "";
      }

      if (date) {
        date.value = button.dataset.eventDate || "";
      }

      service.value = button.dataset.serviceId || "";

      if (nextDate) {
        nextDate.value = button.dataset.nextDate || "";
      }

      if (observations) {
        observations.value = button.dataset.observations || "";
      }

      openModal("groom-edit");
    });
  });

  /*
  |--------------------------------------------------------------------------
  | PELUQUERÍA - ANULAR
  |--------------------------------------------------------------------------
  */

  document.querySelectorAll("[data-grooming-cancel]").forEach((button) => {
    button.addEventListener("click", () => {
      const form = document.getElementById("groom-cancel-form");

      const description = document.getElementById("groom-cancel-description");

      const reason = document.getElementById("groom-cancel-reason");

      if (!form) {
        return;
      }

      /*
       * URL generada desde PHP.
       */
      form.action = button.dataset.cancelUrl || "";

      if (description) {
        const patient = button.dataset.patient || "";

        const service = button.dataset.service || "";

        description.textContent =
          patient && service ? patient + " · " + service : patient || service;
      }

      /*
       * No conservamos el motivo
       * de una anulación anterior.
       */
      if (reason) {
        reason.value = "";
      }

      openModal("groom-cancel");

      if (reason) {
        setTimeout(() => reason.focus(), 100);
      }
    });
  });

  /*
  |--------------------------------------------------------------------------
  | VALIDACIÓN - PRÓXIMA PELUQUERÍA
  |--------------------------------------------------------------------------
  */

  document
    .querySelectorAll('input[name="proxima_peluqueria"]')
    .forEach((input) => {
      input.addEventListener("change", () => {
        if (!input.value) {
          return;
        }

        const selected = new Date(input.value + "T00:00:00");

        const today = new Date();

        today.setHours(0, 0, 0, 0);

        if (selected < today) {
          alert("La próxima fecha de peluquería no puede estar en el pasado.");

          input.value = "";
        }
      });
    });
});
