document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("sale-form");
  if (!form) return;

  const readJson = (id) => {
    const node = document.getElementById(id);
    if (!node) return [];

    try {
      return JSON.parse(node.textContent || "[]");
    } catch (_) {
      return [];
    }
  };

  const products = readJson("sale-products-data");
  const services = readJson("sale-services-data");
  const clinicalConsumptions = readJson("sale-clinical-consumptions-data");

  const owner = document.getElementById("sale-owner");
  const patient = document.getElementById("sale-patient");
  const fiscal = document.getElementById("sale-fiscal-data");
  const lines = document.getElementById("sale-lines");
  const addButton = document.getElementById("sale-add-line");
  const submitButton = document.getElementById("sale-submit");

  let nextIndex = 0;

  const money = (value) => {
    return "$" + (Number(value) || 0).toFixed(2);
  };

  const number = (value, fallback = 0) => {
    const parsed = Number.parseFloat(value);

    return Number.isFinite(parsed) ? parsed : fallback;
  };

  const escapeHtml = (value) => {
    return String(value ?? "")
      .replaceAll("&", "&amp;")
      .replaceAll("<", "&lt;")
      .replaceAll(">", "&gt;")
      .replaceAll('"', "&quot;")
      .replaceAll("'", "&#039;");
  };

  /*
  |--------------------------------------------------------------------------
  | Propietario -> pacientes / datos fiscales
  |--------------------------------------------------------------------------
  */

  function filterCustomerFields() {
    const ownerId = owner?.value || "";

    /*
     * Pacientes
     */
    if (patient) {
      Array.from(patient.options).forEach((option) => {
        if (!option.value) {
          return;
        }

        const visible =
          ownerId !== "" && option.dataset.ownerEnvironmentId === ownerId;

        option.hidden = !visible;
        option.disabled = !visible;
      });

      if (patient.value && patient.selectedOptions[0]?.disabled) {
        patient.value = "";
      }
    }

    /*
     * Datos fiscales
     */
    if (fiscal) {
      let principal = "";

      Array.from(fiscal.options).forEach((option) => {
        if (!option.value) {
          return;
        }

        const visible =
          ownerId !== "" && option.dataset.ownerEnvironmentId === ownerId;

        option.hidden = !visible;
        option.disabled = !visible;

        if (visible && option.dataset.principal === "1" && !principal) {
          principal = option.value;
        }
      });

      if (fiscal.value && fiscal.selectedOptions[0]?.disabled) {
        fiscal.value = "";
      }

      /*
       * Seleccionar automáticamente
       * el dato fiscal principal.
       */
      if (!fiscal.value && principal) {
        fiscal.value = principal;
      }
    }
  }

  /*
  |--------------------------------------------------------------------------
  | Opciones de productos / servicios
  |--------------------------------------------------------------------------
  */

  function optionsFor(type) {
    if (type === "CONSUMO_CLINICO") {
      return clinicalConsumptions
        .filter(
          (consumption) =>
            String(consumption.animal_id) === patient.value &&
            String(consumption.propietario_entorno_id) === owner.value,
        )
        .map((consumption) => {
          const product = products.find(
            (p) => String(p.id) === String(consumption.producto_id),
          );

          const configured =
            product &&
            product.precio != null &&
            product.impuesto_tarifa_id != null &&
            product.impuesto_porcentaje != null;

          return `
          <option
            value="${Number(consumption.id)}"
            ${configured ? "" : "disabled"}
          >
            ${escapeHtml(consumption.nombre)}
            · ${Number(consumption.cantidad)} unidades
            · ${escapeHtml(consumption.inventario)}
            · ${escapeHtml(consumption.fecha_movimiento)}
            · #${Number(consumption.id)}
          </option>
        `;
        })
        .join("");
    }

    const source = type === "PRODUCTO" ? products : services;

    return source
      .map((item) => {
        const configured =
          item.precio !== null &&
          item.impuesto_tarifa_id !== null &&
          item.impuesto_porcentaje !== null;

        const stock = number(item.stock, 0);

        const disabled = !configured || (type === "PRODUCTO" && stock <= 0);

        let suffix = "";

        if (!configured) {
          suffix = " · configuración pendiente";
        } else if (type === "PRODUCTO") {
          suffix = ` · stock ${stock} ${item.unidad || ""}`;
        } else {
          suffix = ` · ${money(item.precio)}`;
        }

        return `
        <option
          value="${Number(item.id)}"
          ${disabled ? "disabled" : ""}
        >
          ${escapeHtml(item.codigo ? item.codigo + " · " : "")}
          ${escapeHtml(item.nombre)}
          ${escapeHtml(suffix)}
        </option>
      `;
      })
      .join("");
  }

  /*
  |--------------------------------------------------------------------------
  | Agregar línea
  |--------------------------------------------------------------------------
  */

  function addLine(type = "SERVICIO") {
    const index = nextIndex++;

    const row = document.createElement("div");

    row.className = "sale-line";
    row.dataset.index = String(index);

    row.innerHTML = `
      <div class="sale-line-top">

        <label>
          <span>Tipo</span>

          <select class="sale-line-type">
            <option
              value="SERVICIO"
              ${type === "SERVICIO" ? "selected" : ""}
            >
              Servicio
            </option>

            <option
              value="PRODUCTO"
              ${type === "PRODUCTO" ? "selected" : ""}
            >
              Producto
            </option>
            <option
              value="CONSUMO_CLINICO"
              ${type === "CONSUMO_CLINICO" ? "selected" : ""}
            >
              Consumo clínico
            </option>
          </select>
        </label>


        <label class="sale-item-field">
          <span>Ítem</span>

          <select
            class="sale-line-item"
            required
          >
            <option value="">
              Seleccionar…
            </option>
          </select>
        </label>


        <label>
          <span>Cantidad</span>

          <input
            class="sale-line-qty"
            name="items[${index}][cantidad]"
            type="number"
            min="0.0001"
            step="0.0001"
            value="1"
            required
          >
        </label>


        <label>
          <span>Descuento</span>

          <input
            class="sale-line-discount"
            name="items[${index}][descuento]"
            type="number"
            min="0"
            step="0.01"
            value="0"
          >
        </label>


        <button
          class="sale-line-remove"
          type="button"
          title="Eliminar línea"
        >
          ×
        </button>

      </div>


      <label class="sale-inventory-field" hidden>
        <span>Inventario de salida *</span>
        <select class="sale-line-inventory" name="items[${index}][inventario_id]" disabled></select>
      </label>
      <div class="sale-line-meta">

        <span class="sale-line-price">
          Precio: —
        </span>

        <span class="sale-line-tax-label">
          Impuesto: —
        </span>

        <span class="sale-line-stock"></span>

        <strong class="sale-line-total">
          $0.00
        </strong>

      </div>
    `;

    lines.appendChild(row);

    configureLine(row);
  }

  /*
  |--------------------------------------------------------------------------
  | Configurar eventos de una línea
  |--------------------------------------------------------------------------
  */

  function configureLine(row) {
    const type = row.querySelector(".sale-line-type");

    const item = row.querySelector(".sale-line-item");

    const qty = row.querySelector(".sale-line-qty");

    const discount = row.querySelector(".sale-line-discount");

    const remove = row.querySelector(".sale-line-remove");

    const inventory = row.querySelector(".sale-line-inventory");
    function rebuildInventory() {
      const product = type.value === "PRODUCTO";
      const clinical = type.value === "CONSUMO_CLINICO";

      inventory.closest("label").hidden = !product;
      inventory.disabled = !product;
      inventory.required = product;

      qty.readOnly = clinical;

      if (clinical) {
        qty.value = selectedData(row)?.cantidad_consumida || 0;
      }

      const options = product ? selectedData(row)?.inventarios || [] : [];

      inventory.innerHTML =
        '<option value="">Seleccionar inventario…</option>' +
        options
          .map(
            (i) => `
      <option
        value="${Number(i.id)}"
        ${Number(i.stock) <= 0 ? "disabled" : ""}
      >
        ${escapeHtml(i.nombre)}
        · ${Number(i.stock)} disponibles
      </option>
    `,
          )
          .join("");

      const available = options.filter((i) => Number(i.stock) > 0);

      if (available.length === 1) {
        inventory.value = String(available[0].id);
      }

      updateLine(row);
    }

    function rebuildItems() {
      item.innerHTML =
        '<option value="">Seleccionar…</option>' + optionsFor(type.value);

      const field = {
        PRODUCTO: "producto_id",
        SERVICIO: "servicio_id",
        CONSUMO_CLINICO: "consumo_clinico_id",
      }[type.value];

      item.name = `items[${row.dataset.index}][${field}]`;

      if (type.value !== "CONSUMO_CLINICO") {
        qty.value = "1";
      }

      rebuildInventory();
    }

    row.refreshClinicalItems = () => {
      if (type.value === "CONSUMO_CLINICO") {
        rebuildItems();
      }
    };

    type.addEventListener("change", rebuildItems);

    item.addEventListener("change", rebuildInventory);

    qty.addEventListener("input", () => updateLine(row));

    discount.addEventListener("input", () => updateLine(row));

    remove.addEventListener("click", () => {
      row.remove();

      /*
       * Siempre debe quedar al menos
       * una línea disponible.
       */
      if (!lines.children.length) {
        addLine();
      }

      updateSummary();
    });

    function refreshClinicalLines() {
      lines.querySelectorAll(".sale-line").forEach((row) => {
        row.refreshClinicalItems?.();
      });
    }

    owner?.addEventListener("change", () => {
      filterCustomerFields();
      refreshClinicalLines();
    });

    patient?.addEventListener("change", refreshClinicalLines);

    rebuildItems();
  }

  /*
  |--------------------------------------------------------------------------
  | Obtener información del ítem seleccionado
  |--------------------------------------------------------------------------
  */

  function selectedData(row) {
    const type = row.querySelector(".sale-line-type").value;

    const id = Number(row.querySelector(".sale-line-item").value || 0);

    if (type === "CONSUMO_CLINICO") {
      const consumption = clinicalConsumptions.find(
        (c) =>
          Number(c.id) === id &&
          String(c.animal_id) === patient.value &&
          String(c.propietario_entorno_id) === owner.value,
      );

      if (!consumption) return null;

      const product = products.find(
        (p) => Number(p.id) === Number(consumption.producto_id),
      );

      if (!product) return null;

      return {
        ...product,
        consumo_clinico_id: consumption.id,
        cantidad_consumida: Number(consumption.cantidad),
        inventario_consumo: consumption.inventario,
      };
    }

    const source = type === "PRODUCTO" ? products : services;

    return source.find((item) => Number(item.id) === id) || null;
  }

  /*
  |--------------------------------------------------------------------------
  | Cálculo visual
  |--------------------------------------------------------------------------
  |
  | IMPORTANTE:
  |
  | Este cálculo es solamente una vista previa.
  | BillingService vuelve a calcular absolutamente
  | todo en backend.
  |
  */

  function calculate(item, qty, discount) {
    const price = number(item?.precio, 0);

    const rate = number(item?.impuesto_porcentaje, 0);

    const includesTax = Number(item?.precio_incluye_impuesto) === 1;

    /*
     * Subtotal bruto
     */
    const subtotal = Math.round(qty * price * 100) / 100;

    /*
     * Descuento monetario
     */
    const safeDiscount = Math.max(0, Math.min(discount, subtotal));

    /*
     * Importe después del descuento
     */
    const taxable = Math.round((subtotal - safeDiscount) * 100) / 100;

    let base = taxable;
    let tax = 0;
    let total = taxable;

    /*
     * Precio con impuesto incluido
     */
    if (rate > 0 && includesTax) {
      base = Math.round((taxable / (1 + rate / 100)) * 100) / 100;

      tax = Math.round((taxable - base) * 100) / 100;
    } else {
      /*
       * Precio sin impuesto incluido
       */
      tax = Math.round(((base * rate) / 100) * 100) / 100;

      total = Math.round((base + tax) * 100) / 100;
    }

    return {
      subtotal,
      discount: safeDiscount,
      base,
      tax,
      total,
    };
  }

  /*
  |--------------------------------------------------------------------------
  | Actualizar una línea
  |--------------------------------------------------------------------------
  */

  function updateLine(row) {
    const item = selectedData(row);

    const qty = Math.max(
      0,
      number(row.querySelector(".sale-line-qty").value, 0),
    );

    const discountInput = row.querySelector(".sale-line-discount");

    const discount = Math.max(0, number(discountInput.value, 0));

    const type = row.querySelector(".sale-line-type").value;

    const result = calculate(item, qty, discount);

    /*
     * Validación descuento
     */
    if (discount > result.subtotal) {
      discountInput.setCustomValidity(
        "El descuento no puede superar el subtotal de la línea.",
      );
    } else {
      discountInput.setCustomValidity("");
    }

    /*
     * Validación visual de stock
     */
    const inventoryId = row.querySelector(".sale-line-inventory").value;
    const stock = number(
      item?.inventarios?.find((i) => String(i.id) === inventoryId)?.stock,
      0,
    );

    const qtyInput = row.querySelector(".sale-line-qty");

    if (type === "PRODUCTO" && item && inventoryId && qty > stock) {
      qtyInput.setCustomValidity(`Stock disponible: ${String(stock)}.`);
    } else {
      qtyInput.setCustomValidity("");
    }

    /*
     * Precio
     */
    row.querySelector(".sale-line-price").textContent = item
      ? `Precio: ${money(item.precio)}${
          Number(item.precio_incluye_impuesto) === 1
            ? " · impuesto incluido"
            : ""
        }`
      : "Precio: —";

    /*
     * Impuesto
     */
    row.querySelector(".sale-line-tax-label").textContent = item
      ? `Impuesto: ${
          item.impuesto_nombre || item.impuesto_tarifa_nombre || "—"
        } (${number(item.impuesto_porcentaje, 0).toFixed(2)}%)`
      : "Impuesto: —";

    /*
     * Stock
     */
    row.querySelector(".sale-line-stock").textContent =
      type === "CONSUMO_CLINICO" && item
        ? `Consumo registrado · ${item.inventario_consumo} · Sin nueva salida`
        : type === "PRODUCTO" && item
          ? `Stock: ${String(stock)} ${item.unidad || ""}`
          : "";

    /*
     * Total línea
     */
    row.querySelector(".sale-line-total").textContent = money(result.total);

    /*
     * Guardamos cálculo temporal
     * únicamente para resumen visual.
     */
    row._totals = result;

    updateSummary();
  }

  /*
  |--------------------------------------------------------------------------
  | Resumen general
  |--------------------------------------------------------------------------
  */

  function updateSummary() {
    const totals = {
      subtotal: 0,
      discount: 0,
      base: 0,
      tax: 0,
      total: 0,
    };

    lines.querySelectorAll(".sale-line").forEach((row) => {
      const rowTotals = row._totals || {};

      Object.keys(totals).forEach((key) => {
        totals[key] += number(rowTotals[key], 0);
      });
    });

    document.getElementById("sale-summary-subtotal").textContent = money(
      totals.subtotal,
    );

    document.getElementById("sale-summary-discount").textContent = money(
      totals.discount,
    );

    document.getElementById("sale-summary-base").textContent = money(
      totals.base,
    );

    document.getElementById("sale-summary-tax").textContent = money(totals.tax);

    document.getElementById("sale-summary-total").textContent = money(
      totals.total,
    );
  }

  /*
  |--------------------------------------------------------------------------
  | Eventos generales
  |--------------------------------------------------------------------------
  */

  owner?.addEventListener("change", filterCustomerFields);

  addButton?.addEventListener("click", () => addLine("SERVICIO"));

  /*
  |--------------------------------------------------------------------------
  | Envío
  |--------------------------------------------------------------------------
  */

  form.addEventListener("submit", function (event) {
    /*
     * Debe existir al menos una línea.
     */
    if (!lines.children.length) {
      event.preventDefault();

      addLine();

      return;
    }

    /*
     * Todas las líneas deben
     * tener un ítem seleccionado.
     */
    const invalidItem = Array.from(lines.querySelectorAll(".sale-line")).some(
      (row) => !selectedData(row),
    );

    if (invalidItem) {
      event.preventDefault();

      lines.querySelector(".sale-line-item:invalid")?.reportValidity();

      return;
    }

    const selectedConsumptions = Array.from(
      lines.querySelectorAll(".sale-line"),
    )
      .filter(
        (row) =>
          row.querySelector(".sale-line-type").value === "CONSUMO_CLINICO",
      )
      .map((row) => row.querySelector(".sale-line-item").value)
      .filter(Boolean);

    if (new Set(selectedConsumptions).size !== selectedConsumptions.length) {
      event.preventDefault();

      alert("No puedes facturar dos veces el mismo consumo clínico.");

      return;
    }

    /*
     * Validaciones HTML5:
     * cantidad, descuento, stock, etc.
     */
    if (!form.checkValidity()) {
      event.preventDefault();

      form.reportValidity();

      return;
    }

    /*
     * Evitar doble envío.
     */
    if (submitButton) {
      submitButton.disabled = true;

      submitButton.textContent = "Registrando…";
    }
  });

  /*
  |--------------------------------------------------------------------------
  | Inicialización
  |--------------------------------------------------------------------------
  */

  filterCustomerFields();

  addLine("SERVICIO");
});

// Asignación fiscal: un único modal reutilizable.
document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("sale-fiscal-assign-form");
  if (!form) return;
  const owner = document.getElementById("assign-owner");
  const fiscal = document.getElementById("assign-fiscal");
  const search = document.getElementById("assign-owner-search");
  const preview = document.getElementById("assign-fiscal-preview");
  const submit = document.getElementById("assign-fiscal-submit");
  const consumer = document.getElementById("assign-consumer");
  const registered = document.getElementById("assign-registered");
  const owners = Array.from(owner.options)
    .slice(1)
    .map((o) => o.cloneNode(true));
  const records = Array.from(fiscal.options)
    .slice(1)
    .map((o) => o.cloneNode(true));
  let lockedOwner = "";
  const normalize = (v) =>
    String(v || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase();
  function showPreview() {
    const o = fiscal.selectedOptions[0];
    const final = consumer.value === "1";
    registered.hidden = final;
    fiscal.disabled = final;
    fiscal.required = !final;
    owner.required = !final;
    owner.disabled = final || !!lockedOwner;
    search.disabled = final || !!lockedOwner;
    submit.disabled = !final && (!owner.value || !fiscal.value);
    if (final) {
      preview.textContent =
        "Razón social: CONSUMIDOR FINAL\nIdentificación: 9999999999999\nEmisión pendiente de habilitación";
      return;
    }
    preview.textContent = fiscal.value
      ? [
          "Razón social: " + o.dataset.name,
          "Identificación: " + o.dataset.identification,
          "Correo: " + (o.dataset.email || "—"),
          "Dirección: " + (o.dataset.address || "—"),
        ]
          .filter(Boolean)
          .join("\n")
      : "Selecciona los datos fiscales del cliente.";
  }
  function filterFiscal(selected = "") {
    fiscal.length = 1;
    records
      .filter((o) => o.dataset.owner === owner.value)
      .forEach((o) => fiscal.append(o.cloneNode(true)));
    fiscal.value = selected;
    if (!fiscal.value)
      fiscal.selectedIndex = fiscal.options.length === 2 ? 1 : 0;
    showPreview();
  }
  function filterOwners(selected = "") {
    owner.length = 1;
    const q = normalize(search.value);
    owners
      .filter(
        (o) =>
          (!lockedOwner || o.value === lockedOwner) &&
          normalize(o.textContent + " " + o.dataset.search).includes(q),
      )
      .forEach((o) => owner.append(o.cloneNode(true)));
    owner.value = selected;
    if (!owner.value) owner.selectedIndex = 0;
  }
  document.querySelectorAll("[data-fiscal-sale]").forEach((button) =>
    button.addEventListener("click", () => {
      const d = button.dataset;
      form.action = d.fiscalAction;
      consumer.value = d.fiscalConsumer === "1" ? "1" : "0";
      document.getElementById("assign-sale-number").textContent =
        d.fiscalNumber;
      document.getElementById("assign-sale-total").textContent = d.fiscalTotal;
      lockedOwner = Number(d.fiscalPatient) ? d.fiscalOwner : "";
      search.value = "";
      search.disabled = !!lockedOwner;
      filterOwners(d.fiscalOwner);
      owner.disabled = !!lockedOwner;
      filterFiscal(d.fiscalCurrent);
    }),
  );
  search.addEventListener("input", () => {
    filterOwners(owner.value);
    filterFiscal();
  });
  owner.addEventListener("change", () => filterFiscal());
  fiscal.addEventListener("change", showPreview);
  consumer.addEventListener("change", showPreview);
  form.addEventListener("submit", (e) => {
    if (submit.disabled || (consumer.value !== "1" && !fiscal.value))
      e.preventDefault();
  });
  document.addEventListener("click", (e) => {
    document.querySelectorAll(".sale-more[open]").forEach((d) => {
      if (!d.contains(e.target) || e.target.closest("[data-modal-open]"))
        d.open = false;
    });
  });
});

document.addEventListener("DOMContentLoaded", () => {
  const mode = document.getElementById("sale-consumer");
  const fiscal = document.getElementById("sale-fiscal-data");
  if (!mode || !fiscal) return;
  const update = () => {
    const final = mode.value === "1";
    fiscal.disabled = final;
    document.getElementById("sale-registered-fields").hidden = final;
    document.getElementById("sale-consumer-preview").hidden = !final;
  };
  mode.addEventListener("change", update);
  update();
});
