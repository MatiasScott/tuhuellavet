.modal {
    position:
        fixed;

    inset: 0;

    z-index: 1000;

    display: none;

    align-items:
        center;

    justify-content:
        center;

    padding:
        1rem;
}

.modal.is-open {
    display: flex;
}

.modal-backdrop {
    position:
        absolute;

    inset: 0;

    background:
        rgba(
            15,
            23,
            42,
            .55
        );

    backdrop-filter:
        blur(3px);
}

.modal-dialog {
    position:
        relative;

    z-index: 1;

    width:
        min(
            560px,
            100%
        );

    max-height:
        calc(
            100vh - 2rem
        );

    overflow-y:
        auto;

    background:
        var(--surface);

    border-radius:
        var(--radius-lg);

    box-shadow:
        0 30px 80px
        rgba(
            15,
            23,
            42,
            .25
        );
}

.modal-lg {
    width:
        min(
            850px,
            100%
        );
}

.modal-header {
    display: flex;

    justify-content:
        space-between;

    align-items:
        flex-start;

    padding:
        1.4rem;

    border-bottom:
        1px solid
        var(--border);
}

.modal-header h2 {
    margin:
        .2rem 0 0;
}

.modal-close {
    width:
        38px;

    height:
        38px;

    display:
        grid;

    place-items:
        center;

    border: 0;

    border-radius:
        12px;

    background:
        var(--surface-soft);

    font-size:
        1.4rem;
}

.modal-body {
    padding:
        1.4rem;
}

.modal-footer {
    display: flex;

    justify-content:
        flex-end;

    gap:
        .7rem;

    padding:
        1rem 1.4rem;

    border-top:
        1px solid
        var(--border);
}

.modal-open {
    overflow: hidden;
}

.form-grid {
    display: grid;

    grid-template-columns:
        repeat(
            2,
            minmax(
                0,
                1fr
            )
        );

    gap:
        1rem;
}

.form-grid label {
    display: grid;

    gap:
        .45rem;
}

.form-grid label > span {
    font-size:
        .85rem;

    font-weight:
        700;
}

.form-grid input,
.form-grid select,
.form-grid textarea {
    width: 100%;

    border:
        1px solid
        var(--border);

    border-radius:
        12px;

    padding:
        .8rem .9rem;

    background:
        var(--surface);

    outline: none;
}

.form-grid input:focus,
.form-grid select:focus,
.form-grid textarea:focus {
    border-color:
        var(--primary);

    box-shadow:
        0 0 0 4px
        var(--primary-soft);
}

.field-full {
    grid-column:
        1 / -1;
}

.inline-form {
    display:
        inline;
}

.btn-danger-soft {
    background:
        #fff0f0;

    color:
        #b43434;
}

.status-neutral {
    background:
        #f1f5f9;

    color:
        #64748b;
}

@media (
    max-width: 650px
) {

    .form-grid {
        grid-template-columns:
            1fr;
    }

    .field-full {
        grid-column:
            auto;
    }

}