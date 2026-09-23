document.addEventListener("DOMContentLoaded", () => {
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

  document.querySelectorAll("[data-modal-open]").forEach((button) => {
    button.addEventListener("click", () => {
      openModal(button.dataset.modalOpen);
    });
  });

  document.querySelectorAll("[data-modal-close]").forEach((button) => {
    button.addEventListener("click", () => {
      closeModal(button.closest(".modal"));
    });
  });

  /*
|--------------------------------------------------------------------------
| VACUNACIÓN - EDITAR
|--------------------------------------------------------------------------
*/

  document.querySelectorAll("[data-vaccination-edit]").forEach((button) => {
    button.addEventListener("click", () => {
      const form = document.getElementById("vac-edit-form");

      const patient = document.getElementById("vac-edit-patient");

      const date = document.getElementById("vac-edit-date");

      const vaccine = document.getElementById("vac-edit-vaccine");

      const dose = document.getElementById("vac-edit-dose");

      const unit = document.getElementById("vac-edit-unit");

      const lot = document.getElementById("vac-edit-lot");

      const commercialHouse = document.getElementById(
        "vac-edit-commercial-house",
      );

      const revaccination = document.getElementById("vac-edit-revaccination");

      const observations = document.getElementById("vac-edit-observations");

      if (!form || !vaccine) {
        return;
      }

      /*
       * URL generada por PHP.
       * No construimos rutas manualmente
       * desde JavaScript.
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

      vaccine.value = button.dataset.vaccineId || "";

      if (dose) {
        dose.value = button.dataset.dose || "";
      }

      if (unit) {
        unit.value = button.dataset.unitId || "";
      }

      if (lot) {
        lot.value = button.dataset.lot || "";
      }

      if (commercialHouse) {
        commercialHouse.value = button.dataset.commercialHouse || "";
      }

      if (revaccination) {
        revaccination.value = button.dataset.revaccination || "";
      }

      if (observations) {
        observations.value = button.dataset.observations || "";
      }

      openModal("vac-edit");
    });
  });

  /*
|--------------------------------------------------------------------------
| VACUNACIÓN - ANULAR
|--------------------------------------------------------------------------
*/

  document.querySelectorAll("[data-vaccination-cancel]").forEach((button) => {
    button.addEventListener("click", () => {
      const form = document.getElementById("vac-cancel-form");

      const description = document.getElementById("vac-cancel-description");

      const reason = document.getElementById("vac-cancel-reason");

      if (!form) {
        return;
      }

      form.action = button.dataset.cancelUrl || "";

      if (description) {
        const patient = button.dataset.patient || "";

        const vaccine = button.dataset.vaccine || "";

        description.textContent =
          patient && vaccine ? patient + " · " + vaccine : patient || vaccine;
      }

      /*
       * Evita conservar el motivo
       * escrito al abrir otra vacunación.
       */
      if (reason) {
        reason.value = "";
      }

      openModal("vac-cancel");

      if (reason) {
        setTimeout(() => reason.focus(), 100);
      }
    });
  });

  document.querySelectorAll(".modal-backdrop").forEach((backdrop) => {
    backdrop.addEventListener("click", () => {
      closeModal(backdrop.closest(".modal"));
    });
  });

  /*
   * Validamos que próxima fecha
   * no sea anterior a la actual.
   */
  document
    .querySelectorAll(
      'input[name="fecha_revacunacion"], input[name="proxima_desparasitacion"]',
    )
    .forEach((input) => {
      input.addEventListener("change", () => {
        if (!input.value) {
          return;
        }

        const selected = new Date(input.value + "T00:00:00");

        const today = new Date();

        today.setHours(0, 0, 0, 0);

        if (selected < today) {
          alert("La próxima fecha no puede estar en el pasado.");

          input.value = "";
        }
      });
    });

  /*
|--------------------------------------------------------------------------
| DESPARASITACIÓN - EDITAR
|--------------------------------------------------------------------------
*/

  document.querySelectorAll("[data-deworming-edit]").forEach((button) => {
    button.addEventListener("click", () => {
      const form = document.getElementById("dew-edit-form");

      const patient = document.getElementById("dew-edit-patient");

      const date = document.getElementById("dew-edit-date");

      const drug = document.getElementById("dew-edit-drug");

      const dose = document.getElementById("dew-edit-dose");

      const unit = document.getElementById("dew-edit-unit");

      const nextDate = document.getElementById("dew-edit-next-date");

      const observations = document.getElementById("dew-edit-observations");

      if (!form || !drug) {
        return;
      }

      /*
       * URL generada desde PHP.
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

      drug.value = button.dataset.drugId || "";

      if (dose) {
        dose.value = button.dataset.dose || "";
      }

      if (unit) {
        unit.value = button.dataset.unitId || "";
      }

      if (nextDate) {
        nextDate.value = button.dataset.nextDate || "";
      }

      if (observations) {
        observations.value = button.dataset.observations || "";
      }

      openModal("dew-edit");
    });
  });

  /*
|--------------------------------------------------------------------------
| DESPARASITACIÓN - ANULAR
|--------------------------------------------------------------------------
*/

  document.querySelectorAll("[data-deworming-cancel]").forEach((button) => {
    button.addEventListener("click", () => {
      const form = document.getElementById("dew-cancel-form");

      const description = document.getElementById("dew-cancel-description");

      const reason = document.getElementById("dew-cancel-reason");

      if (!form) {
        return;
      }

      form.action = button.dataset.cancelUrl || "";

      if (description) {
        const patient = button.dataset.patient || "";

        const drug = button.dataset.drug || "";

        description.textContent =
          patient && drug ? patient + " · " + drug : patient || drug;
      }

      /*
       * Limpiamos el motivo anterior.
       */
      if (reason) {
        reason.value = "";
      }

      openModal("dew-cancel");

      if (reason) {
        setTimeout(() => reason.focus(), 100);
      }
    });
  });
});
