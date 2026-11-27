ALTER TABLE tratamientos
    ADD COLUMN anulado_at DATETIME NULL,
    ADD COLUMN anulado_por BIGINT UNSIGNED NULL,
    ADD COLUMN motivo_anulacion TEXT NULL,
    ADD CONSTRAINT fk_tratamiento_anulado_por
        FOREIGN KEY (anulado_por)
        REFERENCES usuarios(id);