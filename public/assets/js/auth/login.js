(function () {
    const form = document.getElementById('loginForm');

    if (!form) {
        return;
    }

    form.addEventListener('submit', function (event) {
        const email = document.getElementById('email');
        const password = document.getElementById('password');

        if (!email || !password) {
            return;
        }

        if (email.value.trim() === '' || password.value.trim() === '') {
            event.preventDefault();
        }
    });
})();
