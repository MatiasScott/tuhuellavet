document.querySelectorAll("[data-clinical-stock]").forEach((root) => {
  if (root.dataset.initialized) return;

  root.dataset.initialized = "1";

  const form = root.closest("form");

  const enable = root.querySelector("[data-stock-enable]");
  const fields = root.querySelector("[data-stock-fields]");
  const origin = root.querySelector("[data-stock-origin]");
  const product = root.querySelector("[data-stock-product]");
  const lot = root.querySelector("[data-stock-lot]");
  const status = root.querySelector("[data-stock-status]");

  const balances = JSON.parse(
    root.querySelector("[data-stock-balances]").textContent,
  );

  const lots = JSON.parse(root.querySelector("[data-stock-lots]").textContent);

  const vaccine = form.querySelector('[name="vacuna_id"]');

  const drug =
    root.dataset.clinicalKind === "deworming"
      ? form.querySelector('[name="farmaco_id"]')
      : null;

  const options = Array.from(product.options)
    .slice(1)
    .map((option) => option.cloneNode(true));

  function refresh() {
    const previous = product.value;
    const previousLot = lot.value;

    fields.hidden = !enable.checked;

    fields.querySelectorAll("input, select").forEach((el) => {
      el.disabled = !enable.checked;
    });

    const rows = balances.filter(
      (row) => String(row.inventario_id) === origin.value,
    );

    product.replaceChildren(new Option("Seleccionar producto", ""));

    options.forEach((option) => {
      const vaccineMatches =
        !vaccine || option.dataset.vaccine === vaccine.value;

      const drugMatches =
        !drug || (drug.value !== "" && option.dataset.drug === drug.value);

      const available = rows.some(
        (row) =>
          String(row.producto_id) === option.value && Number(row.stock) > 0,
      );

      if (vaccineMatches && drugMatches && available) {
        product.append(option.cloneNode(true));
      }
    });

    product.value = previous;

    if (!product.value) {
      product.selectedIndex = 0;
    }

    const matching = rows.filter(
      (row) => String(row.producto_id) === product.value,
    );

    const controlled = product.selectedOptions[0]?.dataset.lot === "1";

    lot.replaceChildren(new Option("Seleccionar lote", ""));

    matching
      .filter(
        (row) => row.lote_id && Number(row.stock) > 0 && lots[row.lote_id],
      )
      .sort((a, b) =>
        (lots[a.lote_id].fecha_vencimiento || "9999").localeCompare(
          lots[b.lote_id].fecha_vencimiento || "9999",
        ),
      )
      .forEach((row) => {
        const item = lots[row.lote_id];

        lot.add(
          new Option(
            `${item.numero_lote} · ${
              item.fecha_vencimiento || "Sin caducidad"
            } · ${Number(row.stock)} disponibles`,
            row.lote_id,
          ),
        );
      });

    lot.value = previousLot;

    if (!lot.value) {
      lot.selectedIndex = 0;
    }

    lot.required = enable.checked && controlled;
    lot.disabled = !enable.checked || !controlled;

    if (product.value && !controlled) {
      lot.replaceChildren(new Option("No requiere lote", ""));
    }

    const stock = matching.reduce((sum, row) => sum + Number(row.stock), 0);

    status.textContent = product.value
      ? `Existencia registrada: ${stock}. La disponibilidad se valida al guardar.`
      : "Selecciona inventario y producto.";
  }

  [enable, origin, product, lot, vaccine, drug]
    .filter(Boolean)
    .forEach((element) => {
      element.addEventListener("change", refresh);
    });

  form.addEventListener("reset", () => {
    setTimeout(refresh, 0);
  });

  refresh();
});
