document.addEventListener(
    'DOMContentLoaded',
    () => {

        const openModal =
            id => {

                const modal =
                    document
                        .getElementById(
                            id
                        );

                if (!modal) {
                    return;
                }

                modal
                    .classList
                    .add(
                        'is-open'
                    );

                document.body
                    .classList
                    .add(
                        'modal-open'
                    );
            };


        const closeModal =
            modal => {

                if (!modal) {
                    return;
                }

                modal
                    .classList
                    .remove(
                        'is-open'
                    );

                document.body
                    .classList
                    .remove(
                        'modal-open'
                    );
            };


        document
            .querySelectorAll(
                '[data-modal-open]'
            )
            .forEach(
                button => {

                    button
                        .addEventListener(
                            'click',
                            () => {

                                openModal(
                                    button
                                        .dataset
                                        .modalOpen
                                );

                            }
                        );
                }
            );


        document
            .querySelectorAll(
                '[data-modal-close]'
            )
            .forEach(
                button => {

                    button
                        .addEventListener(
                            'click',
                            () => {

                                closeModal(
                                    button.closest(
                                        '.modal'
                                    )
                                );

                            }
                        );
                }
            );


        document
            .querySelectorAll(
                '.modal-backdrop'
            )
            .forEach(
                backdrop => {

                    backdrop
                        .addEventListener(
                            'click',
                            () => {

                                closeModal(
                                    backdrop.closest(
                                        '.modal'
                                    )
                                );

                            }
                        );
                }
            );


        /*
         * Validamos que próxima fecha
         * no sea anterior a la actual.
         */
        document
            .querySelectorAll(
                'input[name="fecha_revacunacion"], input[name="proxima_desparasitacion"]'
            )
            .forEach(
                input => {

                    input
                        .addEventListener(
                            'change',
                            () => {

                                if (
                                    !input.value
                                ) {
                                    return;
                                }

                                const selected =
                                    new Date(
                                        input.value
                                        + 'T00:00:00'
                                    );

                                const today =
                                    new Date();

                                today.setHours(
                                    0,
                                    0,
                                    0,
                                    0
                                );

                                if (
                                    selected
                                    < today
                                ) {
                                    alert(
                                        'La próxima fecha no puede estar en el pasado.'
                                    );

                                    input.value
                                        = '';
                                }

                            }
                        );
                }
            );

    }
);