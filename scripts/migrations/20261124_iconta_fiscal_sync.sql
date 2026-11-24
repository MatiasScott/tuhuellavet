ALTER TABLE iconta_documentos
    ADD COLUMN ultima_consulta_sri_at DATETIME NULL,
    ADD COLUMN proxima_consulta_sri_at DATETIME NULL,
    ADD COLUMN consulta_sri_exitosa_at DATETIME NULL,
    ADD COLUMN errores_consulta_sri SMALLINT UNSIGNED
        NOT NULL DEFAULT 0,
    ADD COLUMN ultimo_error_consulta_sri TEXT NULL,
    ADD INDEX idx_iconta_sync_sri (
        estado,
        proxima_consulta_sri_at,
        id
    );