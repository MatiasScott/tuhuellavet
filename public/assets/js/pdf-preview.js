(function () {
    var modalEl = document.getElementById('pdfPreviewModal');
    if (!modalEl || typeof bootstrap === 'undefined') {
        return;
    }

    var modal = new bootstrap.Modal(modalEl);
    var iframe = modalEl.querySelector('iframe');
    var openButtons = document.querySelectorAll('[data-pdf-preview]');

    openButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            var url = button.getAttribute('data-pdf-preview');
            if (!url || !iframe) {
                return;
            }
            iframe.setAttribute('src', url);
            modal.show();
        });
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        if (iframe) {
            iframe.setAttribute('src', 'about:blank');
        }
    });
})();
