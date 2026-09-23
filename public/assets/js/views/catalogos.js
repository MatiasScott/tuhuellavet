document.addEventListener("DOMContentLoaded", () => {
  const modals = document.querySelectorAll("[data-catalog-modal]");

  const normalizeText = (value) => {
    return (value || "")
      .toString()
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase()
      .trim();
  };

  const closeModal = (modal) => {
    if (!modal) {
      return;
    }

    modal.classList.remove("is-open");
    modal.setAttribute("aria-hidden", "true");

    const search = modal.querySelector("[data-catalog-search]");

    if (search) {
      search.value = "";
    }

    modal.querySelectorAll("[data-catalog-row]").forEach((row) => {
      row.hidden = false;
    });

    const empty = modal.querySelector("[data-catalog-empty]");

    if (empty) {
      empty.hidden = true;
    }

    if (!document.querySelector(".catalog-modal.is-open")) {
      document.body.classList.remove("catalog-modal-open");
    }
  };

  const openModal = (modal) => {
    if (!modal) {
      return;
    }

    modal.classList.add("is-open");
    modal.setAttribute("aria-hidden", "false");
    document.body.classList.add("catalog-modal-open");

    const search = modal.querySelector("[data-catalog-search]");

    if (search) {
      window.setTimeout(() => {
        search.focus();
      }, 50);
    }
  };

  document.querySelectorAll("[data-catalog-modal-open]").forEach((button) => {
    button.addEventListener("click", () => {
      const name = button.dataset.catalogModalOpen;

      const modal = document.querySelector(`[data-catalog-modal="${name}"]`);

      openModal(modal);
    });
  });

  modals.forEach((modal) => {
    modal.querySelectorAll("[data-catalog-modal-close]").forEach((button) => {
      button.addEventListener("click", () => {
        closeModal(modal);
      });
    });

    const search = modal.querySelector("[data-catalog-search]");
    const rows = modal.querySelectorAll("[data-catalog-row]");
    const empty = modal.querySelector("[data-catalog-empty]");

    if (search) {
      search.addEventListener("input", () => {
        const query = normalizeText(search.value);

        let visible = 0;

        rows.forEach((row) => {
          const text = normalizeText(row.textContent);
          const matches = query === "" || text.includes(query);

          row.hidden = !matches;

          if (matches) {
            visible++;
          }
        });

        if (empty) {
          empty.hidden = visible !== 0;
        }
      });
    }
  });

  document.addEventListener("keydown", (event) => {
    if (event.key !== "Escape") {
      return;
    }

    const openedModal = document.querySelector(".catalog-modal.is-open");

    closeModal(openedModal);
  });
  const editModal = document.getElementById("catalog-edit-modal");
  const editForm = document.getElementById("catalog-edit-form");

  const editInputs = {
    categoriaEspecie: document.getElementById("catalog-edit-categoria-especie"),

    especie: document.getElementById("catalog-edit-especie"),

    codigo: document.getElementById("catalog-edit-codigo"),

    nombre: document.getElementById("catalog-edit-nombre"),

    nombreCientifico: document.getElementById("catalog-edit-nombre-cientifico"),

    descripcion: document.getElementById("catalog-edit-descripcion"),

    simbolo: document.getElementById("catalog-edit-simbolo"),

    categoriaUnidad: document.getElementById("catalog-edit-categoria-unidad"),

    precio: document.getElementById("catalog-edit-precio"),
  };

  const editFields = {
    categoriaEspecie: document.querySelector(
      '[data-edit-field="categoria-especie"]',
    ),

    especie: document.querySelector('[data-edit-field="especie"]'),

    codigo: document.querySelector('[data-edit-field="codigo"]'),

    nombre: document.querySelector('[data-edit-field="nombre"]'),

    nombreCientifico: document.querySelector(
      '[data-edit-field="nombre-cientifico"]',
    ),

    descripcion: document.querySelector('[data-edit-field="descripcion"]'),

    simbolo: document.querySelector('[data-edit-field="simbolo"]'),

    categoriaUnidad: document.querySelector(
      '[data-edit-field="categoria-unidad"]',
    ),

    precio: document.querySelector('[data-edit-field="precio"]'),
  };

  const fieldConfiguration = {
    especie: ["categoriaEspecie", "codigo", "nombre", "nombreCientifico"],

    raza: ["especie", "nombre", "descripcion"],

    vacuna: ["nombre", "descripcion"],

    farmaco: ["nombre", "descripcion"],

    laboratorio: ["nombre", "descripcion"],

    cirugia: ["nombre", "descripcion"],

    unidad: ["codigo", "nombre", "simbolo", "categoriaUnidad"],

    servicio: ["codigo", "nombre", "descripcion", "precio"],
  };

  const hideAllEditFields = () => {
    Object.values(editFields).forEach((field) => {
      if (field) {
        field.hidden = true;
      }
    });
  };

  const clearEditInputs = () => {
    Object.values(editInputs).forEach((input) => {
      if (input) {
        input.value = "";
      }
    });
  };

  const showFieldsForType = (type) => {
    hideAllEditFields();

    const fields = fieldConfiguration[type] || [];

    fields.forEach((name) => {
      if (editFields[name]) {
        editFields[name].hidden = false;
      }
    });
  };

  const closeEditModal = () => {
    if (!editModal) {
      return;
    }

    editModal.classList.remove("is-open");
    editModal.setAttribute("aria-hidden", "true");

    /*
     * Puede continuar abierto el modal principal del catálogo.
     */
    if (!document.querySelector(".catalog-modal.is-open")) {
      document.body.classList.remove("catalog-modal-open");
    }
  };

  document.querySelectorAll("[data-catalog-edit]").forEach((button) => {
    button.addEventListener("click", () => {
      if (!editModal || !editForm) {
        return;
      }

      const type = button.dataset.type;

      clearEditInputs();
      showFieldsForType(type);

      /*
       * Valores comunes.
       */
      if (editInputs.codigo) {
        editInputs.codigo.value = button.dataset.codigo || "";
      }

      if (editInputs.nombre) {
        editInputs.nombre.value = button.dataset.nombre || "";
      }

      if (editInputs.descripcion) {
        editInputs.descripcion.value = button.dataset.descripcion || "";
      }

      /*
       * Especie.
       */
      if (editInputs.categoriaEspecie) {
        editInputs.categoriaEspecie.value = button.dataset.categoriaId || "";
      }

      if (editInputs.nombreCientifico) {
        editInputs.nombreCientifico.value =
          button.dataset.nombreCientifico || "";
      }

      /*
       * Raza.
       */
      if (editInputs.especie) {
        editInputs.especie.value = button.dataset.especieId || "";
      }

      /*
       * Unidad.
       */
      if (editInputs.simbolo) {
        editInputs.simbolo.value = button.dataset.simbolo || "";
      }

      if (editInputs.categoriaUnidad) {
        editInputs.categoriaUnidad.value = button.dataset.categoria || "";
      }

      /*
       * Servicio.
       */
      if (editInputs.precio) {
        editInputs.precio.value = button.dataset.precio || "";
      }

      /*
       * La URL viene generada por PHP.
       */
      editForm.action = button.dataset.updateUrl || "";

      editModal.classList.add("is-open");
      editModal.setAttribute("aria-hidden", "false");

      document.body.classList.add("catalog-modal-open");

      window.setTimeout(() => {
        const firstVisible = editForm.querySelector(
          "[data-edit-field]:not([hidden]) input, " +
            "[data-edit-field]:not([hidden]) select, " +
            "[data-edit-field]:not([hidden]) textarea",
        );

        firstVisible?.focus();
      }, 50);
    });
  });

  document.querySelectorAll("[data-catalog-edit-close]").forEach((button) => {
    button.addEventListener("click", () => {
      closeEditModal();
    });
  });

  document.querySelectorAll("[data-confirm-status]").forEach((button) => {
    button.addEventListener("click", (event) => {
      const action = button.dataset.action || "";
      const name = button.dataset.name || "este registro";

      const confirmed = window.confirm(`¿Deseas ${action} "${name}"?`);

      if (!confirmed) {
        event.preventDefault();
      }
    });
  });
});
