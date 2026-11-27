document.addEventListener("DOMContentLoaded", function () {
  /*
   * ==========================================
   * PESTAÑAS DE INVENTARIO
   * ==========================================
   */

  const tabs = document.querySelectorAll("[data-inventory-tab]");
  const panels = document.querySelectorAll("[data-inventory-panel]");
  const tabsContainer = document.querySelector(".inventory-tabs");

  function activateInventoryTab(tabName) {
    tabs.forEach(function (tab) {
      const active = tab.dataset.inventoryTab === tabName;

      tab.classList.toggle("is-active", active);
      tab.setAttribute("aria-selected", active ? "true" : "false");
    });

    panels.forEach(function (panel) {
      panel.hidden = panel.dataset.inventoryPanel !== tabName;
    });
  }

  tabs.forEach(function (tab) {
    tab.addEventListener("click", function () {
      activateInventoryTab(tab.dataset.inventoryTab);

      const url = new URL(window.location.href);
      url.searchParams.set("tab", tab.dataset.inventoryTab);

      window.history.replaceState({}, "", url);
    });
  });

  if (tabsContainer) {
    activateInventoryTab(tabsContainer.dataset.activeTab || "productos");
  }

  /*
   * ==========================================
   * MOVIMIENTOS - PRODUCTO / LOTE
   * ==========================================
   */

  const movementProduct = document.getElementById("movement-product");

  const movementLot = document.getElementById("movement-lot");

  const movementLotField = document.getElementById("movement-lot-field");

  const movementLotHelp = document.getElementById("movement-lot-help");

  const origin = document.getElementById("movement-origin");
  const destination = document.getElementById("movement-destination");
  const movementType = document.getElementById("movement-type");
  const movementQuantity = document.getElementById("movement-quantity");
  const balance = document.getElementById("movement-balance");
  const balances = JSON.parse(
    document.getElementById("movement-balances")?.textContent || "[]",
  );
  const fmt = (n) =>
    Number(n.toFixed(4)).toLocaleString("es-EC", { maximumFractionDigits: 4 });
  function available(inventory, product, lot = null) {
    return balances.reduce(
      (sum, row) =>
        sum +
        (String(row.inventario_id) === inventory &&
        String(row.producto_id) === product &&
        (lot === null || String(row.lote_id) === lot)
          ? Number(row.stock)
          : 0),
      0,
    );
  }
  function updateMovement(resetLot = false) {
    if (
      !origin ||
      !destination ||
      !movementType ||
      !movementProduct ||
      !movementLot
    )
      return;
    const transfer = movementType.value === "transfer";
    const outgoing =
      transfer || movementType.selectedOptions[0]?.dataset.factor === "-1";
    const product = movementProduct.value;
    const selected = movementProduct.selectedOptions[0];
    const controls =
      selected?.dataset.controlsLot === "1" ||
      selected?.dataset.controlsExpiration === "1";
    document.getElementById("movement-origin-label").textContent = transfer
      ? "Inventario de origen *"
      : "Inventario *";
    document.getElementById("movement-destination-field").hidden = !transfer;
    destination.disabled = !transfer;
    destination.required = transfer;
    for (const option of destination.options) {
      option.disabled = !!option.value && option.value === origin.value;
      option.hidden = option.disabled;
    }
    if (destination.value === origin.value) destination.value = "";
    if (resetLot) movementLot.value = "";
    for (const option of movementLot.options) {
      if (!option.value) continue;
      const valid =
        option.dataset.productId === product &&
        (!outgoing || available(origin.value, product, option.value) > 0);
      option.hidden = !valid;
      option.disabled = !valid;
    }
    if (movementLot.selectedOptions[0]?.disabled) movementLot.value = "";
    movementLotField.hidden = !controls;
    movementLot.disabled = !controls;
    movementLot.required = controls;
    if (movementLotHelp)
      movementLotHelp.textContent = controls
        ? "Seleccione un lote del producto. Las salidas muestran lotes con saldo en origen."
        : "";
    const lot = controls && movementLot.value ? movementLot.value : null;
    const source = available(origin.value, product, lot);
    const qty = Number(movementQuantity.value);
    const ready = !!product && (!controls || !!lot);
    movementQuantity.setCustomValidity(
      outgoing && ready && qty > source + 0.0000001
        ? "Stock insuficiente en origen."
        : "",
    );
    if (!product) {
      balance.textContent =
        "Seleccione un producto para consultar existencias.";
      return;
    }
    let text = "Stock en origen: " + fmt(source);
    if (transfer && destination.value) {
      const target = available(destination.value, product, lot);
      text += " · Stock en destino: " + fmt(target);
      if (ready && qty > 0 && qty <= source + 0.0000001)
        text +=
          " · Después: origen " +
          fmt(source - qty) +
          ", destino " +
          fmt(target + qty);
    }
    balance.textContent =
      text + " (saldo al cargar la página; se valida al guardar).";
  }
  if (
    origin &&
    movementType &&
    movementProduct &&
    movementLot &&
    movementQuantity &&
    destination
  ) {
    [origin, movementType, movementProduct].forEach((el) =>
      el.addEventListener("change", () => updateMovement(true)),
    );
    [destination, movementLot].forEach((el) =>
      el.addEventListener("change", () => updateMovement()),
    );
    movementQuantity.addEventListener("input", () => updateMovement());
    updateMovement(true);
  }

  /*
   * ==========================================
   * REGISTRO DE LOTES
   * ==========================================
   */

  const lotProductSelect = document.getElementById("lot-product");

  const lotExpirationInput = document.getElementById("lot-expiration-date");

  const lotExpirationHelp = document.getElementById("lot-expiration-help");

  function updateLotCreationForm() {
    if (!lotProductSelect || !lotExpirationInput) {
      return;
    }

    const selectedOption =
      lotProductSelect.options[lotProductSelect.selectedIndex];

    const controlsExpiration =
      selectedOption?.dataset.controlsExpiration === "1";

    lotExpirationInput.required = controlsExpiration;

    if (lotExpirationHelp) {
      lotExpirationHelp.textContent = controlsExpiration
        ? "La fecha de vencimiento es obligatoria para este producto."
        : "La fecha de vencimiento es opcional para este producto.";
    }
  }

  if (lotProductSelect && lotExpirationInput) {
    lotProductSelect.addEventListener("change", updateLotCreationForm);

    updateLotCreationForm();
  }

  /*
   * ==========================================
   * STOCK MÁXIMO - CREAR PRODUCTO
   * ==========================================
   */

  const unlimitedStock = document.getElementById("stock-maximo-sin-limite");

  const maximumStockField = document.getElementById("stock-maximo-field");

  const maximumStockInput = document.getElementById("stock-maximo");

  function updateMaximumStock() {
    if (!unlimitedStock || !maximumStockField || !maximumStockInput) {
      return;
    }

    const isUnlimited = unlimitedStock.checked;

    maximumStockField.hidden = isUnlimited;
    maximumStockInput.disabled = isUnlimited;
    maximumStockInput.required = !isUnlimited;

    if (isUnlimited) {
      maximumStockInput.value = "";
    }
  }

  if (unlimitedStock) {
    unlimitedStock.addEventListener("change", updateMaximumStock);

    updateMaximumStock();
  }

  /*
   * ==========================================
   * LOTE - CREAR PRODUCTO
   * ==========================================
   */

  const productLotCheckbox = document.getElementById(
    "product-create-controls-lot",
  );

  const productExpirationCheckbox = document.getElementById(
    "product-create-controls-expiration",
  );

  const productLotFields = document.getElementById("product-lot-fields");

  const productLotNumber = document.getElementById("product-lot-number");

  const productManufactureDate = document.getElementById(
    "product-manufacture-date",
  );

  const productExpirationDate = document.getElementById(
    "product-expiration-date",
  );

  function updateProductLotFields() {
    if (
      !productLotCheckbox ||
      !productLotFields ||
      !productLotNumber ||
      !productManufactureDate ||
      !productExpirationDate
    ) {
      return;
    }

    const controlsExpiration = productExpirationCheckbox?.checked ?? false;

    /*
     * Si controla vencimiento,
     * necesariamente debe controlar lote.
     */
    if (controlsExpiration && !productLotCheckbox.checked) {
      productLotCheckbox.checked = true;
    }

    const controlsLot = productLotCheckbox.checked;

    productLotFields.hidden = !controlsLot;

    productLotNumber.required = controlsLot;

    productExpirationDate.required = controlsLot && controlsExpiration;

    productLotNumber.disabled = !controlsLot;
    productManufactureDate.disabled = !controlsLot;
    productExpirationDate.disabled = !controlsLot;

    if (!controlsLot) {
      productLotNumber.value = "";
      productManufactureDate.value = "";
      productExpirationDate.value = "";
    }
  }

  productLotCheckbox?.addEventListener("change", function () {
    /*
     * No permitimos vencimiento sin lote.
     */
    if (!productLotCheckbox.checked && productExpirationCheckbox) {
      productExpirationCheckbox.checked = false;
    }

    updateProductLotFields();
  });

  productExpirationCheckbox?.addEventListener("change", updateProductLotFields);

  updateProductLotFields();

  /*
   * ==========================================
   * UTILIDADES PARA MODALES
   * ==========================================
   */

  function refreshBodyModalState() {
    const hasOpenModal =
      document.querySelector(".catalog-modal.is-open") !== null;

    document.body.classList.toggle("modal-open", hasOpenModal);
  }

  function openModal(modal) {
    if (!modal) {
      return;
    }

    modal.classList.add("is-open");
    modal.setAttribute("aria-hidden", "false");

    refreshBodyModalState();
  }

  function closeModal(modal) {
    if (!modal) {
      return;
    }

    modal.classList.remove("is-open");
    modal.setAttribute("aria-hidden", "true");

    refreshBodyModalState();
  }

  /*
   * ==========================================
   * MODAL CREAR PRODUCTO
   * ==========================================
   */

  const createModal = document.getElementById("product-create-modal");

  const createForm = document.getElementById("product-create-form");

  const createTax = document.getElementById("product-create-tax");

  const createPriceIncludesTax = document.getElementById(
    "product-create-price-tax",
  );

  function openCreateModal() {
    openModal(createModal);

    /*
     * Damos foco al código.
     */
    window.setTimeout(function () {
      createForm?.querySelector('input[name="codigo"]')?.focus();
    }, 50);
  }

  function closeCreateModal() {
    closeModal(createModal);
  }

  document
    .querySelectorAll("[data-product-create-open]")
    .forEach(function (button) {
      button.addEventListener("click", openCreateModal);
    });

  document
    .querySelectorAll("[data-product-create-close]")
    .forEach(function (button) {
      button.addEventListener("click", closeCreateModal);
    });

  /*
   * ==========================================
   * IMPUESTO - CREAR PRODUCTO
   * ==========================================
   */

  function updateCreateTaxState() {
    if (!createTax || !createPriceIncludesTax) {
      return;
    }

    const hasTax = createTax.value !== "";

    createPriceIncludesTax.disabled = !hasTax;

    if (!hasTax) {
      createPriceIncludesTax.checked = false;
    }
  }

  createTax?.addEventListener("change", updateCreateTaxState);

  updateCreateTaxState();

  /*
   * ==========================================
   * MODAL EXISTENCIAS
   * ==========================================
   */

  const stockModal = document.getElementById("stock-modal");

  const stockSearch = document.getElementById("inventory-stock-search");

  function openStockModal() {
    openModal(stockModal);

    window.setTimeout(function () {
      stockSearch?.focus();
    }, 50);
  }

  function closeStockModal() {
    closeModal(stockModal);
  }

  document.querySelectorAll("[data-stock-open]").forEach(function (button) {
    button.addEventListener("click", openStockModal);
  });

  document.querySelectorAll("[data-stock-close]").forEach(function (button) {
    button.addEventListener("click", closeStockModal);
  });

  /*
   * ==========================================
   * MODAL EDITAR PRODUCTO
   * ==========================================
   */

  const editModal = document.getElementById("product-edit-modal");

  const editForm = document.getElementById("product-edit-form");

  const editCode = document.getElementById("product-edit-code");

  const editName = document.getElementById("product-edit-name");

  const editDescription = document.getElementById("product-edit-description");

  const editCategory = document.getElementById("product-edit-category");

  const editUnit = document.getElementById("product-edit-unit");

  const editPrice = document.getElementById("product-edit-price");

  const editPurchaseCost = document.getElementById(
    "product-edit-purchase-cost",
  );

  const editTax = document.getElementById("product-edit-tax");

  const editPriceIncludesTax = document.getElementById(
    "product-edit-price-tax",
  );

  const editControlsLot = document.getElementById("product-edit-controls-lot");

  const editControlsExpiration = document.getElementById(
    "product-edit-controls-expiration",
  );

  const editMinimumStock = document.getElementById(
    "product-edit-minimum-stock",
  );

  const editUnlimitedStock = document.getElementById(
    "product-edit-unlimited-stock",
  );

  const editMaximumField = document.getElementById(
    "product-edit-maximum-field",
  );

  const editMaximumStock = document.getElementById(
    "product-edit-maximum-stock",
  );

  function openProductEditModal() {
    openModal(editModal);

    window.setTimeout(function () {
      editCode?.focus();
    }, 50);
  }

  function closeProductEditModal() {
    closeModal(editModal);
  }

  /*
   * ==========================================
   * STOCK MÁXIMO - EDITAR
   * ==========================================
   */

  function updateEditMaximumStock() {
    if (!editUnlimitedStock || !editMaximumField || !editMaximumStock) {
      return;
    }

    const isUnlimited = editUnlimitedStock.checked;

    editMaximumField.hidden = isUnlimited;
    editMaximumStock.disabled = isUnlimited;
    editMaximumStock.required = !isUnlimited;

    if (isUnlimited) {
      editMaximumStock.value = "";
    }
  }

  editUnlimitedStock?.addEventListener("change", updateEditMaximumStock);

  /*
   * ==========================================
   * CONTROL LOTE / VENCIMIENTO - EDITAR
   * ==========================================
   */

  function updateEditLotControls() {
    if (!editControlsLot || !editControlsExpiration) {
      return;
    }

    /*
     * Vencimiento requiere lote.
     */
    if (editControlsExpiration.checked && !editControlsLot.checked) {
      editControlsLot.checked = true;
    }
  }

  editControlsLot?.addEventListener("change", function () {
    /*
     * Si quitamos lote,
     * quitamos también vencimiento.
     */
    if (!editControlsLot.checked && editControlsExpiration) {
      editControlsExpiration.checked = false;
    }
  });

  editControlsExpiration?.addEventListener("change", updateEditLotControls);

  /*
   * ==========================================
   * IMPUESTO - EDITAR
   * ==========================================
   */

  function updateEditTaxState() {
    if (!editTax || !editPriceIncludesTax) {
      return;
    }

    const hasTax = editTax.value !== "";

    editPriceIncludesTax.disabled = !hasTax;

    if (!hasTax) {
      editPriceIncludesTax.checked = false;
    }
  }

  editTax?.addEventListener("change", updateEditTaxState);

  /*
   * ==========================================
   * CARGAR PRODUCTO EN MODAL DE EDICIÓN
   * ==========================================
   */

  document.querySelectorAll("[data-product-edit]").forEach(function (button) {
    button.addEventListener("click", function () {
      const vaccineSelect = document.getElementById("edit-product-vaccine");

      const drugSelect = document.getElementById("edit-product-drug");

      if (vaccineSelect) {
        vaccineSelect.value =
          button.dataset.vaccineId === "0"
            ? ""
            : button.dataset.vaccineId || "";
      }

      if (drugSelect) {
        drugSelect.value =
          button.dataset.drugPresentationId === "0"
            ? ""
            : button.dataset.drugPresentationId || "";
      }

      if (!editModal || !editForm) {
        return;
      }

      /*
       * URL del formulario
       */
      editForm.action = button.dataset.updateUrl || "";

      /*
       * Información básica
       */
      if (editCode) {
        editCode.value = button.dataset.codigo || "";
      }

      if (editName) {
        editName.value = button.dataset.nombre || "";
      }

      if (editDescription) {
        editDescription.value = button.dataset.descripcion || "";
      }

      if (editCategory) {
        editCategory.value = button.dataset.categoriaId || "";
      }

      if (editUnit) {
        editUnit.value = button.dataset.unidadId || "";
      }

      /*
       * Información comercial
       */
      if (editPrice) {
        editPrice.value = button.dataset.precio || "";
      }

      if (editPurchaseCost) {
        editPurchaseCost.value = button.dataset.costoCompra || "";
      }

      if (editTax) {
        editTax.value = button.dataset.impuestoTarifaId || "";
      }

      if (editPriceIncludesTax) {
        editPriceIncludesTax.checked =
          button.dataset.precioIncluyeImpuesto === "1";
      }

      /*
       * Control de lotes
       */
      if (editControlsLot) {
        editControlsLot.checked = button.dataset.controlaLote === "1";
      }

      if (editControlsExpiration) {
        editControlsExpiration.checked =
          button.dataset.controlaVencimiento === "1";
      }

      /*
       * Stock mínimo
       */
      if (editMinimumStock) {
        editMinimumStock.value = button.dataset.stockMinimo || "";
      }

      /*
       * Stock máximo
       */
      const maximumValue = button.dataset.stockMaximo || "";

      if (editUnlimitedStock) {
        editUnlimitedStock.checked = maximumValue === "";
      }

      if (editMaximumStock) {
        editMaximumStock.value = maximumValue;
      }

      updateEditMaximumStock();
      updateEditLotControls();
      updateEditTaxState();
      window.SearchableSelects?.sync(editForm);

      openProductEditModal();
    });
  });

  document
    .querySelectorAll("[data-product-edit-close]")
    .forEach(function (button) {
      button.addEventListener("click", closeProductEditModal);
    });

  /*
   * ==========================================
   * BUSCADORES
   * ==========================================
   */

  function normalizeInventorySearch(value) {
    return String(value || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase()
      .trim();
  }

  function bindTableSearch(
    inputId,
    clearId,
    rowSelector,
    countId,
    emptyId,
    singular,
    plural,
  ) {
    const input = document.getElementById(inputId);

    const clear = document.getElementById(clearId);

    const rows = Array.from(document.querySelectorAll(rowSelector));

    const count = document.getElementById(countId);

    const empty = document.getElementById(emptyId);

    if (!input) {
      return;
    }

    function filter() {
      const term = normalizeInventorySearch(input.value);

      let visible = 0;

      rows.forEach(function (row) {
        const searchable = normalizeInventorySearch(
          row.dataset.search || row.textContent,
        );

        const show = term === "" || searchable.includes(term);

        row.hidden = !show;

        if (show) {
          visible++;
        }
      });

      if (count) {
        count.textContent =
          visible === 1 ? `1 ${singular}` : `${visible} ${plural}`;
      }

      if (empty) {
        empty.hidden = visible !== 0;
      }

      if (clear) {
        clear.hidden = term === "";
      }
    }

    input.addEventListener("input", filter);

    clear?.addEventListener("click", function () {
      input.value = "";

      filter();

      input.focus();
    });

    filter();
  }

  /*
   * Buscador de productos registrados
   */
  bindTableSearch(
    "inventory-product-search",
    "inventory-product-search-clear",
    "[data-product-row]",
    "inventory-product-count",
    "inventory-product-empty",
    "producto",
    "productos",
  );

  /*
   * Buscador de existencias
   */
  bindTableSearch(
    "inventory-stock-search",
    "inventory-stock-search-clear",
    "[data-stock-row]",
    "inventory-stock-count",
    "inventory-stock-empty",
    "registro",
    "registros",
  );

  /*
   * ==========================================
   * CERRAR MODALES CON ESC
   * ==========================================
   */

  document.addEventListener("keydown", function (event) {
    if (event.key !== "Escape") {
      return;
    }

    /*
     * Cerramos solamente el modal abierto
     * con mayor prioridad.
     */
    if (editModal?.classList.contains("is-open")) {
      closeProductEditModal();
      return;
    }

    if (createModal?.classList.contains("is-open")) {
      closeCreateModal();
      return;
    }

    if (stockModal?.classList.contains("is-open")) {
      closeStockModal();
    }
  });

  /*
   * ==========================================
   * SEGURIDAD VISUAL AL CARGAR
   * ==========================================
   */

  updateCreateTaxState();
  updateEditTaxState();
  updateMaximumStock();
  updateProductLotFields();
});
