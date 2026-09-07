// JS específico de consultas.
document.addEventListener(
    'DOMContentLoaded',
    () => {

        const openModal = id => {

            const modal =
                document
                    .getElementById(id);

            if (!modal) {
                return;
            }

            modal
                .classList
                .add('is-open');

            document.body
                .classList
                .add('modal-open');
        };


        const closeModal = modal => {

            if (!modal) {
                return;
            }

            modal
                .classList
                .remove('is-open');

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

                        closeModal(
                            button.closest(
                                '.modal'
                            )
                        );

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

    }
);