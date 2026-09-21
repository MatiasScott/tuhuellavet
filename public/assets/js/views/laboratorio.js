document.addEventListener("DOMContentLoaded", () => {
  const viewer = document.getElementById("lab-pdf-viewer");

  const frame = document.getElementById("lab-pdf-frame");

  if (!viewer || !frame) {
    return;
  }

  document.querySelectorAll("[data-lab-document-url]").forEach((button) => {
    button.addEventListener("click", () => {
      const url = button.dataset.labDocumentUrl;

      if (!url) {
        return;
      }

      frame.src = url;

      viewer.classList.add("is-open");

      viewer.setAttribute("aria-hidden", "false");
    });
  });

  const closeViewer = () => {
    viewer.classList.remove("is-open");

    viewer.setAttribute("aria-hidden", "true");

    frame.removeAttribute("src");
  };

  viewer
    .querySelectorAll("[data-modal-close], .modal-backdrop")
    .forEach((element) => {
      element.addEventListener("click", closeViewer);
    });
});
