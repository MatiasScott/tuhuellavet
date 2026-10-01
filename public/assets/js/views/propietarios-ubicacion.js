(() => {
  "use strict";

  function initLocationForm(form) {
    const country = form.querySelector("[data-location-country]");
    const province = form.querySelector("[data-location-province]");
    const canton = form.querySelector("[data-location-canton]");
    const parish = form.querySelector("[data-location-parish]");

    if (!country || !province || !canton || !parish) {
      return;
    }

    function resetSelect(select, placeholder) {
      select.innerHTML = "";

      const option = document.createElement("option");
      option.value = "";
      option.textContent = placeholder;

      select.appendChild(option);
    }

    function setLoading(select, message) {
      resetSelect(select, message);
      select.disabled = true;
    }

    function populateSelect(select, rows, placeholder) {
      resetSelect(select, placeholder);

      rows.forEach((row) => {
        const option = document.createElement("option");

        option.value = row.codigo;
        option.textContent = row.nombre;

        select.appendChild(option);
      });

      select.disabled = false;
    }

    async function request(url, parameter, value) {
      const endpoint = new URL(url, window.location.origin);

      endpoint.searchParams.set(parameter, value);

      const response = await fetch(endpoint, {
        method: "GET",
        credentials: "same-origin",
        headers: {
          Accept: "application/json",
        },
      });

      if (!response.ok) {
        throw new Error("No fue posible cargar la ubicación.");
      }

      const result = await response.json();

      if (!result.ok) {
        throw new Error(
          result.message || "No fue posible cargar la ubicación.",
        );
      }

      return Array.isArray(result.data) ? result.data : [];
    }

    async function loadProvinces() {
      resetSelect(province, "Seleccionar provincia");
      resetSelect(canton, "Seleccionar cantón");
      resetSelect(parish, "Seleccionar parroquia");

      canton.disabled = true;
      parish.disabled = true;

      if (!country.value) {
        province.disabled = true;
        return;
      }

      setLoading(province, "Cargando provincias...");

      try {
        const rows = await request(
          country.dataset.provincesUrl,
          "pais",
          country.value,
        );

        populateSelect(province, rows, "Seleccionar provincia");
      } catch (error) {
        resetSelect(province, "No fue posible cargar las provincias");

        province.disabled = true;

        console.error(error);
      }
    }

    async function loadCantons() {
      resetSelect(canton, "Seleccionar cantón");
      resetSelect(parish, "Seleccionar parroquia");

      parish.disabled = true;

      if (!province.value) {
        canton.disabled = true;
        return;
      }

      setLoading(canton, "Cargando cantones...");

      try {
        const rows = await request(
          province.dataset.cantonsUrl,
          "provincia",
          province.value,
        );

        populateSelect(canton, rows, "Seleccionar cantón");
      } catch (error) {
        resetSelect(canton, "No fue posible cargar los cantones");

        canton.disabled = true;

        console.error(error);
      }
    }

    async function loadParishes() {
      resetSelect(parish, "Seleccionar parroquia");

      if (!canton.value) {
        parish.disabled = true;
        return;
      }

      setLoading(parish, "Cargando parroquias...");

      try {
        const rows = await request(
          canton.dataset.parishesUrl,
          "canton",
          canton.value,
        );

        populateSelect(parish, rows, "Seleccionar parroquia");
      } catch (error) {
        resetSelect(parish, "No fue posible cargar las parroquias");

        parish.disabled = true;

        console.error(error);
      }
    }

    country.addEventListener("change", loadProvinces);
    province.addEventListener("change", loadCantons);
    canton.addEventListener("change", loadParishes);

    /*
     * En Ecuador viene seleccionado ECU por defecto.
     * Por eso cargamos sus provincias al abrir el formulario.
     */
    async function restoreSavedLocation() {
      const savedProvince = province.dataset.selected || "";
      const savedCanton = canton.dataset.selected || "";
      const savedParish = parish.dataset.selected || "";

      if (!country.value) {
        province.disabled = true;
        canton.disabled = true;
        parish.disabled = true;
        return;
      }

      await loadProvinces();

      if (!savedProvince) {
        return;
      }

      province.value = savedProvince;

      if (!province.value) {
        return;
      }

      await loadCantons();

      if (!savedCanton) {
        return;
      }

      canton.value = savedCanton;

      if (!canton.value) {
        return;
      }

      await loadParishes();

      if (savedParish) {
        parish.value = savedParish;
      }
    }

    restoreSavedLocation();
  }

  document.addEventListener("DOMContentLoaded", () => {
    document
      .querySelectorAll(".owner-validation-form")
      .forEach(initLocationForm);
  });
})();
