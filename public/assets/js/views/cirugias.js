document.addEventListener("DOMContentLoaded", function () {
  const viewer = document.getElementById("surgery-document-viewer");

  const frame = document.getElementById("surgery-document-frame");

  const title = document.getElementById("surgery-document-title");

  const newTab = document.getElementById("surgery-document-new-tab");

  const closeButton = document.getElementById("surgery-document-close");

  if (!viewer || !frame || !title || !newTab || !closeButton) {
    return;
  }

  /*
   * Abrir documento.
   */
  document
    .querySelectorAll("[data-surgery-file-url]")
    .forEach(function (button) {
      button.addEventListener("click", function () {
        const fileUrl = button.dataset.surgeryFileUrl;

        const fileName =
          button.dataset.surgeryFileName || "Documento quirúrgico";

        if (!fileUrl) {
          return;
        }

        title.textContent = fileName;

        frame.src = fileUrl;

        newTab.href = fileUrl;

        viewer.hidden = false;

        viewer.scrollIntoView({
          behavior: "smooth",
          block: "start",
        });
      });
    });

  /*
   * Cerrar documento.
   */
  closeButton.addEventListener("click", function () {
    frame.removeAttribute("src");

    newTab.removeAttribute("href");

    viewer.hidden = true;
  });
});

document.addEventListener("DOMContentLoaded", function () {
  const typeSelect = document.getElementById("surgery-professional-type");

  const internalFields = document.getElementById("surgery-internal-fields");

  const externalFields = document.getElementById("surgery-external-fields");

  const userSelect = document.getElementById("surgery-user-id");

  const externalName = document.getElementById("surgery-external-name");

  if (
    !typeSelect ||
    !internalFields ||
    !externalFields ||
    !userSelect ||
    !externalName
  ) {
    return;
  }

  function updateProfessionalFields() {
    const isExternal = typeSelect.value === "EXTERNO";

    internalFields.hidden = isExternal;

    externalFields.hidden = !isExternal;

    userSelect.required = !isExternal;

    externalName.required = isExternal;

    if (isExternal) {
      userSelect.value = "";
    } else {
      externalName.value = "";
    }
  }

  typeSelect.addEventListener("change", updateProfessionalFields);

  updateProfessionalFields();
});
