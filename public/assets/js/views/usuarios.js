document.addEventListener("DOMContentLoaded", () => {
  /*
   * EDICIÓN DE USUARIOS
   */

  const form = document.getElementById("user-edit-form");

  if (form) {
    const errorBox = document.getElementById("edit-user-error");
    const submit = document.getElementById("edit-user-submit");

    const fields = {
      nombres: document.getElementById("edit-user-nombres"),
      apellidos: document.getElementById("edit-user-apellidos"),
      email: document.getElementById("edit-user-email"),
      telefono: document.getElementById("edit-user-telefono"),
      rol: document.getElementById("edit-user-role"),
      activo: document.getElementById("edit-user-active"),
    };

    const roleContainer = document.getElementById("edit-user-role-container");

    const activeContainer = document.getElementById(
      "edit-user-active-container",
    );

    const actionTemplate = form.dataset.actionTemplate;

    let editController = null;
    let editRequestId = 0;

    function resetEditForm() {
      form.reset();

      form.removeAttribute("action");

      fields.nombres.value = "";
      fields.apellidos.value = "";
      fields.email.value = "";
      fields.telefono.value = "";
      fields.rol.value = "";
      fields.activo.value = "1";

      if (roleContainer) {
        roleContainer.hidden = false;
      }

      if (activeContainer) {
        activeContainer.hidden = false;
      }

      fields.rol.disabled = false;
      fields.activo.disabled = false;

      errorBox.hidden = true;
      errorBox.textContent = "";

      submit.disabled = true;
      submit.textContent = "Guardar cambios";
    }

    document.querySelectorAll("[data-edit-user]").forEach((button) => {
      button.addEventListener("click", async () => {
        /*
         * Cancelar la solicitud anterior.
         */
        if (editController) {
          editController.abort();
        }

        editController = new AbortController();

        const currentRequestId = ++editRequestId;

        const id = button.dataset.editUser;

        resetEditForm();

        submit.textContent = "Cargando...";

        try {
          if (!/^\d+$/.test(id) || Number(id) <= 0) {
            throw new Error("El identificador del usuario no es válido.");
          }

          if (!actionTemplate) {
            throw new Error("No está configurada la ruta de edición.");
          }

          const endpoint = actionTemplate.replace(
            "__ID__",
            encodeURIComponent(id),
          );

          const response = await fetch(endpoint, {
            method: "GET",
            headers: {
              Accept: "application/json",
            },
            credentials: "same-origin",
            signal: editController.signal,
          });

          /*
           * Ignorar respuestas de solicitudes anteriores.
           */
          if (currentRequestId !== editRequestId) {
            return;
          }

          let result;

          try {
            result = await response.json();
          } catch (_) {
            throw new Error("El servidor no devolvió una respuesta válida.");
          }

          if (currentRequestId !== editRequestId) {
            return;
          }

          if (!response.ok || !result.ok || !result.data) {
            throw new Error(
              result.message || "No se pudo cargar la información del usuario.",
            );
          }

          const user = result.data;

          const isGlobalProfile = result.edit_mode === "global_profile";

          /*
           * Configurar campos según el tipo de usuario.
           */
          if (roleContainer) {
            roleContainer.hidden = isGlobalProfile;
          }

          if (activeContainer) {
            activeContainer.hidden = isGlobalProfile;
          }

          fields.rol.disabled = isGlobalProfile;
          fields.activo.disabled = isGlobalProfile;

          /*
           * Cargar datos.
           */
          fields.nombres.value = user.nombres ?? "";
          fields.apellidos.value = user.apellidos ?? "";
          fields.email.value = user.email ?? "";
          fields.telefono.value = user.telefono ?? "";

          fields.rol.value = user.rol_id ?? "";

          fields.activo.value = String(Number(user.activo));

          /*
           * Habilitar guardado únicamente después
           * de recibir los datos correctos.
           */
          form.action = endpoint;

          submit.disabled = false;
          submit.textContent = "Guardar cambios";
        } catch (error) {
          /*
           * Una solicitud cancelada no debe modificar
           * el estado del formulario actual.
           */
          if (
            error.name === "AbortError" ||
            currentRequestId !== editRequestId
          ) {
            return;
          }

          resetEditForm();

          errorBox.textContent =
            error.message || "No se pudo cargar el usuario.";

          errorBox.hidden = false;
        }
      });
    });

    /*
     * Guardar cambios.
     */
    form.addEventListener("submit", (event) => {
      if (!form.hasAttribute("action") || submit.disabled) {
        event.preventDefault();
        return;
      }

      submit.disabled = true;
      submit.textContent = "Guardando...";
    });
  }

  /*
   * REENVÍO DE INVITACIONES
   */

  const resendForm = document.getElementById("user-resend-form");

  if (resendForm) {
    const resendSubmit = document.getElementById("resend-user-submit");

    const resendError = document.getElementById("resend-user-error");

    const resendName = document.getElementById("resend-user-name");

    const resendEmail = document.getElementById("resend-user-email");

    const resendTemplate = resendForm.dataset.actionTemplate;

    function resetResendForm() {
      resendForm.removeAttribute("action");

      resendSubmit.disabled = true;
      resendSubmit.textContent = "Enviar invitación";

      resendName.textContent = "";
      resendEmail.textContent = "";

      resendError.hidden = true;
      resendError.textContent = "";
    }

    document.querySelectorAll("[data-resend-invitation]").forEach((button) => {
      button.addEventListener("click", () => {
        resetResendForm();

        const id = button.dataset.resendInvitation;

        if (!/^\d+$/.test(id) || Number(id) <= 0) {
          resendError.textContent = "El usuario seleccionado no es válido.";

          resendError.hidden = false;

          return;
        }

        if (!resendTemplate) {
          resendError.textContent =
            "No está configurada la ruta de invitaciones.";

          resendError.hidden = false;

          return;
        }

        resendName.textContent = button.dataset.userName || "Usuario";

        resendEmail.textContent = button.dataset.userEmail || "";

        resendForm.action = resendTemplate.replace(
          "__ID__",
          encodeURIComponent(id),
        );

        resendSubmit.disabled = false;
      });
    });

    resendForm.addEventListener("submit", (event) => {
      if (!resendForm.hasAttribute("action") || resendSubmit.disabled) {
        event.preventDefault();
        return;
      }

      resendSubmit.disabled = true;
      resendSubmit.textContent = "Enviando...";
    });
  }
});
