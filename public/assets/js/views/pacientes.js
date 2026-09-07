// JS específico de pacientes.
document.addEventListener(
    'DOMContentLoaded',
    () => {

        /*
         * Modales.
         */
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


        /*
         * Especie → raza.
         */
        const species =
            document
                .getElementById(
                    'create-species'
                );

        const breed =
            document
                .getElementById(
                    'create-breed'
                );

        if (
            species
            && breed
        ) {
            const options =
                Array.from(
                    breed.options
                );

            const filterBreeds = () => {

                const speciesId =
                    species.value;

                breed.value = '';

                options.forEach(
                    option => {

                        if (
                            !option.value
                        ) {
                            option.hidden
                                = false;

                            return;
                        }

                        option.hidden =
                            option.dataset
                                .species
                            !== speciesId;
                    }
                );

            };

            species.addEventListener(
                'change',
                filterBreeds
            );

            filterBreeds();
        }


        /*
         * Edad calculada.
         */
        const birthdate =
            document
                .getElementById(
                    'create-birthdate'
                );

        const agePreview =
            document
                .getElementById(
                    'create-age-preview'
                );

        if (
            birthdate
            && agePreview
        ) {
            birthdate.addEventListener(
                'change',
                () => {

                    if (
                        !birthdate.value
                    ) {
                        agePreview
                            .textContent
                            = '';

                        return;
                    }

                    const birth =
                        new Date(
                            `${birthdate.value}T00:00:00`
                        );

                    const today =
                        new Date();

                    if (
                        birth > today
                    ) {
                        agePreview
                            .textContent
                            =
                            'La fecha no puede estar en el futuro.';

                        return;
                    }

                    let years =
                        today.getFullYear()
                        -
                        birth.getFullYear();

                    let months =
                        today.getMonth()
                        -
                        birth.getMonth();

                    if (
                        today.getDate()
                        <
                        birth.getDate()
                    ) {
                        months--;
                    }

                    if (
                        months < 0
                    ) {
                        years--;
                        months += 12;
                    }

                    agePreview
                        .textContent
                        =
                        years > 0
                            ? `${years} año(s) y ${months} mes(es)`
                            : `${months} mes(es)`;
                }
            );
        }

    }
);