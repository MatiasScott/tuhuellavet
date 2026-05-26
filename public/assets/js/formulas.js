(function () {
    function baseUrl(path) {
        var base = (window.TVG_BASE_URL || '').replace(/\/$/, '');
        var normalized = String(path || '').replace(/^\//, '');
        return base + '/' + normalized;
    }

    var detectBtn = document.getElementById('detectarVariablesBtn');
    var expressionInput = document.getElementById('expresion_formula');
    var variablesContainer = document.getElementById('variablesDetectadas');

    function renderVariables(items) {
        if (!variablesContainer) {
            return;
        }

        variablesContainer.innerHTML = '';
        if (!items.length) {
            variablesContainer.innerHTML = '<div class="col-12"><div class="alert alert-warning mb-0">No se detectaron variables.</div></div>';
            return;
        }

        items.forEach(function (item) {
            var variableName = item.variable || '';
            var col = document.createElement('div');
            col.className = 'col-md-3';
            var input = document.createElement('div');
            input.className = 'form-control bg-light';
            input.textContent = variableName;
            col.appendChild(input);
            variablesContainer.appendChild(col);
        });
    }

    if (detectBtn && expressionInput) {
        detectBtn.addEventListener('click', function () {
            var detectUrl = detectBtn.getAttribute('data-detect-url');
            if (!detectUrl) {
                return;
            }

            var csrfInput = document.querySelector('input[name="_csrf_token"]');
            var params = new URLSearchParams();
            params.set('_csrf_token', csrfInput ? csrfInput.value : '');
            params.set('expresion_formula', expressionInput.value || '');

            fetch(detectUrl + '?' + params.toString(), {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then(function (response) {
                    return response.json();
                })
                .then(function (payload) {
                    if (!payload.ok) {
                        throw new Error(payload.message || 'No se pudo detectar variables.');
                    }
                    renderVariables(payload.variables || []);
                })
                .catch(function (error) {
                    if (variablesContainer) {
                        variablesContainer.innerHTML = '<div class="col-12"><div class="alert alert-danger mb-0">' + (error.message || 'Error al detectar variables.') + '</div></div>';
                    }
                });
        });
    }

    var testModalEl = document.getElementById('formulaTestModal');
    var testForm = document.getElementById('formulaTestForm');
    var testFields = document.getElementById('formulaTestFields');
    var testResult = document.getElementById('formulaTestResult');
    var testSubmit = document.getElementById('formulaTestSubmit');

    if (testModalEl && testForm && testFields && testResult && testSubmit && typeof bootstrap !== 'undefined') {
        var modal = new bootstrap.Modal(testModalEl);

        document.querySelectorAll('[data-test-formula]').forEach(function (button) {
            button.addEventListener('click', function () {
                var formulaId = button.getAttribute('data-formula-id') || '0';
                var formulaName = button.getAttribute('data-formula-nombre') || 'Formula';
                var csrfInput = document.querySelector('input[name="_csrf_token"]');
                var csrf = csrfInput ? csrfInput.value : '';

                testForm.querySelector('input[name="id"]').value = formulaId;
                testFields.innerHTML = '<div class="col-12 text-muted">Cargando variables...</div>';
                testResult.classList.add('d-none');
                testResult.textContent = '';
                var title = testModalEl.querySelector('.modal-title');
                if (title) {
                    title.textContent = 'Probar formula: ' + formulaName;
                }

                fetch(baseUrl('/formulas/test') + '?id=' + encodeURIComponent(formulaId) + '&_csrf_token=' + encodeURIComponent(csrf), {
                    method: 'GET',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                })
                    .then(function (response) { return response.json(); })
                    .then(function (payload) {
                        if (!payload.ok) {
                            throw new Error(payload.message || 'No fue posible cargar la formula.');
                        }

                        var vars = (payload.data && payload.data.variables) ? payload.data.variables : [];
                        testFields.innerHTML = '';
                        if (!vars.length) {
                            testFields.innerHTML = '<div class="col-12"><div class="alert alert-warning mb-0">La formula no tiene variables detectadas.</div></div>';
                            return;
                        }

                        vars.forEach(function (v) {
                            var variableName = v.variable || '';
                            var col = document.createElement('div');
                            col.className = 'col-md-6';
                            col.innerHTML = '<label class="form-label">' + variableName + '</label><input class="form-control" type="number" step="0.01" name="var_' + variableName + '">';
                            testFields.appendChild(col);
                        });
                    })
                    .catch(function (error) {
                        testFields.innerHTML = '<div class="col-12"><div class="alert alert-danger mb-0">' + (error.message || 'Error al cargar variables.') + '</div></div>';
                    });

                modal.show();
            });
        });

        testSubmit.addEventListener('click', function () {
            var formData = new FormData(testForm);
            var params = new URLSearchParams();
            formData.forEach(function (value, key) {
                params.append(key, String(value));
            });

            fetch(baseUrl('/formulas/test') + '?' + params.toString(), {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then(function (response) { return response.json(); })
                .then(function (payload) {
                    if (!payload.ok) {
                        throw new Error(payload.message || 'No fue posible calcular la formula.');
                    }

                    var result = payload.data ? payload.data.resultado : null;
                    testResult.textContent = 'Resultado calculado: ' + result;
                    testResult.classList.remove('d-none');
                })
                .catch(function (error) {
                    testResult.textContent = error.message || 'Error al calcular formula.';
                    testResult.classList.remove('d-none');
                });
        });
    }
})();
