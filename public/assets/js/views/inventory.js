document.addEventListener("DOMContentLoaded", function () {
  /*
   * ==========================================
   * PESTAÑAS DEL INVENTARIO
   * ==========================================
   */

  const tabsContainer = document.querySelector(".inventory-tabs");

  const tabButtons = document.querySelectorAll("[data-inventory-tab]");

  const tabPanels = document.querySelectorAll("[data-inventory-panel]");

  function activateTab(tabName) {
    tabButtons.forEach(function (button) {
      const isActive = button.dataset.inventoryTab === tabName;

      button.classList.toggle("is-active", isActive);

      button.setAttribute("aria-selected", isActive ? "true" : "false");
    });

    tabPanels.forEach(function (panel) {
      panel.hidden = panel.dataset.inventoryPanel !== tabName;
    });

    if (tabsContainer) {
      tabsContainer.dataset.activeTab = tabName;
    }

    const url = new URL(window.location.href);

    url.searchParams.set("tab", tabName);

    window.history.replaceState({}, "", url.toString());
  }

  tabButtons.forEach(function (button) {
    button.addEventListener("click", function () {
      activateTab(button.dataset.inventoryTab);
    });
  });

  /*
   * ==========================================
   * SELECTOR DINÁMICO DE LOTES
   * ==========================================
   */

  const productSelect = document.getElementById("movement-product");

  const lotSelect = document.getElementById("movement-lot");

  const lotField = document.getElementById("movement-lot-field");

  const lotHelp = document.getElementById("movement-lot-help");

  if (!productSelect || !lotSelect || !lotField) {
    return;
  }

  const lotOptions = Array.from(lotSelect.options).filter(function (option) {
    return option.value !== "";
  });

  function updateLots() {
    const productId = productSelect.value;

    const selectedProduct = productSelect.selectedOptions[0];

    const controlsLot = selectedProduct?.dataset.controlsLot === "1";

    const controlsExpiration =
      selectedProduct?.dataset.controlsExpiration === "1";

    const requiresLot = controlsLot || controlsExpiration;

    lotSelect.value = "";

    lotField.hidden = !requiresLot;

    lotSelect.required = requiresLot;

    lotSelect.disabled = !requiresLot;

    let available = 0;

    lotOptions.forEach(function (option) {
      const belongsToProduct = option.dataset.productId === productId;

      const hasExpiration = Boolean(option.dataset.expiration);

      const valid = belongsToProduct && (!controlsExpiration || hasExpiration);

      option.hidden = !valid;

      option.disabled = !valid;

      if (valid) {
        available++;
      }
    });

    if (lotHelp) {
      lotHelp.textContent =
        available === 0 && requiresLot
          ? "Este producto no tiene lotes válidos. Registra un lote antes de continuar."
          : "";
    }
  }

  productSelect.addEventListener("change", updateLots);

  updateLots();

  /*
   * ==========================================
   * FORMULARIO DE CREACIÓN DE LOTES
   * ==========================================
   */

  const lotProductSelect = document.getElementById("lot-product");

  const lotExpirationInput = document.getElementById("lot-expiration-date");

  const lotExpirationHelp = document.getElementById("lot-expiration-help");

  function updateLotCreationForm() {
    if (!lotProductSelect || !lotExpirationInput) {
      return;
    }

    const selectedProduct = lotProductSelect.selectedOptions[0];

    const controlsExpiration =
      selectedProduct?.dataset.controlsExpiration === "1";

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

  const productLotCheckbox = document.querySelector(
    'input[name="controla_lote"]',
  );

  const productExpirationCheckbox = document.querySelector(
    'input[name="controla_vencimiento"]',
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

    const controlsLot = productLotCheckbox.checked;

    const controlsExpiration = productExpirationCheckbox?.checked ?? false;

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

  productLotCheckbox?.addEventListener("change", updateProductLotFields);

  productExpirationCheckbox?.addEventListener("change", updateProductLotFields);

  updateProductLotFields();
});
