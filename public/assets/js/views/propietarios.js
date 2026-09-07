document.addEventListener(
    'DOMContentLoaded',
    () => {

        const openModal = id => {
            const modal =
                document.getElementById(id);

            if (!modal) {
                return;
            }

            modal.classList.add(
                'is-open'
            );

            document.body
                .classList
                .add('modal-open');
        };


        const closeModal = modal => {

            modal.classList.remove(
                'is-open'
            );

            document.body
                .classList
                .remove('modal-open');
        };


        document
            .querySelectorAll(
                '[data-modal-open]'
            )
            .forEach(button => {

                button.addEventListener(
                    'click',
                    () => {

                        openModal(
                            button.dataset
                                .modalOpen
                        );

                    }
                );

            });


        document
            .querySelectorAll(
                '[data-modal-close]'
            )
            .forEach(button => {

                button.addEventListener(
                    'click',
                    () => {

                        const modal =
                            button.closest(
                                '.modal'
                            );

                        if (modal) {
                            closeModal(
                                modal
                            );
                        }

                    }
                );

            });


        document
            .querySelectorAll(
                '.modal-backdrop'
            )
            .forEach(backdrop => {

                backdrop.addEventListener(
                    'click',
                    () => {

                        closeModal(
                            backdrop.closest(
                                '.modal'
                            )
                        );

                    }
                );

            });


        /*
         * Editar propietario.
         */
        document
            .querySelectorAll(
                '[data-owner-edit]'
            )
            .forEach(button => {

                button.addEventListener(
                    'click',
                    () => {

                        const id =
                            button.dataset.id;

                        const form =
                            document
                                .getElementById(
                                    'owner-edit-form'
                                );

                        form.action =
                            `${window.APP_URL ?? ''}/propietarios/${id}`;

                        document
                            .getElementById(
                                'edit-owner-type'
                            )
                            .value =
                                button.dataset.tipo
                                || '';

                        document
                            .getElementById(
                                'edit-owner-identification'
                            )
                            .value =
                                button.dataset
                                    .identificacion
                                || '';

                        document
                            .getElementById(
                                'edit-owner-names'
                            )
                            .value =
                                button.dataset
                                    .nombres
                                || '';

                        document
                            .getElementById(
                                'edit-owner-lastnames'
                            )
                            .value =
                                button.dataset
                                    .apellidos
                                || '';

                        document
                            .getElementById(
                                'edit-owner-email'
                            )
                            .value =
                                button.dataset
                                    .email
                                || '';

                        document
                            .getElementById(
                                'edit-owner-mobile'
                            )
                            .value =
                                button.dataset
                                    .celular
                                || '';

                        document
                            .getElementById(
                                'edit-owner-phone'
                            )
                            .value =
                                button.dataset
                                    .telefono
                                || '';

                        document
                            .getElementById(
                                'edit-owner-address'
                            )
                            .value =
                                button.dataset
                                    .direccion
                                || '';

                        openModal(
                            'owner-edit'
                        );

                    }
                );

            });


        /*
         * Confirmación de eliminación.
         */
        document
            .querySelectorAll(
                '[data-confirm-form]'
            )
            .forEach(form => {

                form.addEventListener(
                    'submit',
                    event => {

                        if (
                            !window.confirm(
                                form.dataset
                                    .confirmForm
                            )
                        ) {
                            event
                                .preventDefault();
                        }

                    }
                );

            });

    }
);