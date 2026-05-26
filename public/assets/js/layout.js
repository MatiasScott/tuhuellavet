(function () {
    var sidebar = document.getElementById('tvg-sidebar');
    if (sidebar) {
        var toggle = sidebar.querySelector('[data-sidebar-toggle]');
        if (toggle) {
            toggle.addEventListener('click', function () {
                sidebar.classList.toggle('is-collapsed');
            });
        }
    }

    var searchInputs = document.querySelectorAll('[data-table-search]');
    searchInputs.forEach(function (input) {
        input.addEventListener('input', function () {
            var selector = input.getAttribute('data-table-search');
            if (!selector) {
                return;
            }
            var table = document.querySelector(selector);
            if (!table) {
                return;
            }
            var term = input.value.toLowerCase().trim();
            var rows = table.querySelectorAll('tbody tr');
            rows.forEach(function (row) {
                var haystack = row.textContent ? row.textContent.toLowerCase() : '';
                row.style.display = haystack.indexOf(term) !== -1 ? '' : 'none';
            });
        });
    });
})();
