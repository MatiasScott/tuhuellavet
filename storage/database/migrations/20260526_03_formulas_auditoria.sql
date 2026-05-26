-- Modulo de formulas medicas + ampliacion de auditoria
-- Ejecutar sobre sistema_veterinario_ganadero

CREATE TABLE IF NOT EXISTS formulas (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    empresa_id BIGINT UNSIGNED NOT NULL,
    nombre VARCHAR(180) NOT NULL,
    descripcion TEXT NULL,
    expresion_formula TEXT NULL,
    formula TEXT NULL,
    categoria VARCHAR(120) NULL,
    unidad_resultado VARCHAR(60) NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    created_by BIGINT UNSIGNED NULL,
    updated_by BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_formulas_empresa_estado (empresa_id, estado),
    CONSTRAINT fk_formulas_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id),
    CONSTRAINT fk_formulas_created_by FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_formulas_updated_by FOREIGN KEY (updated_by) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'formulas' AND column_name = 'expresion_formula') = 0,
    'ALTER TABLE formulas ADD COLUMN expresion_formula TEXT NULL AFTER descripcion',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'formulas' AND column_name = 'categoria') = 0,
    'ALTER TABLE formulas ADD COLUMN categoria VARCHAR(120) NULL AFTER formula',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'formulas' AND column_name = 'created_by') = 0,
    'ALTER TABLE formulas ADD COLUMN created_by BIGINT UNSIGNED NULL AFTER estado',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'formulas' AND column_name = 'updated_by') = 0,
    'ALTER TABLE formulas ADD COLUMN updated_by BIGINT UNSIGNED NULL AFTER created_by',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'formulas' AND index_name = 'idx_formulas_empresa_estado') = 0,
    'CREATE INDEX idx_formulas_empresa_estado ON formulas(empresa_id, estado)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS formulas_variables (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    formula_id BIGINT UNSIGNED NOT NULL,
    variable VARCHAR(120) NOT NULL,
    etiqueta VARCHAR(150) NULL,
    tipo_input VARCHAR(40) NOT NULL DEFAULT 'number',
    obligatorio TINYINT(1) NOT NULL DEFAULT 1,
    valor_default VARCHAR(120) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_formulas_variables (formula_id, variable),
    INDEX idx_formulas_variables_formula (formula_id),
    CONSTRAINT fk_formulas_variables_formula FOREIGN KEY (formula_id) REFERENCES formulas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'auditoria' AND column_name = 'empresa_id') = 0,
    'ALTER TABLE auditoria ADD COLUMN empresa_id BIGINT UNSIGNED NULL AFTER usuario_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'auditoria' AND column_name = 'tabla_afectada') = 0,
    'ALTER TABLE auditoria ADD COLUMN tabla_afectada VARCHAR(120) NULL AFTER accion',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'auditoria' AND column_name = 'registro_id') = 0,
    'ALTER TABLE auditoria ADD COLUMN registro_id BIGINT NULL AFTER tabla_afectada',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'auditoria' AND column_name = 'user_agent') = 0,
    'ALTER TABLE auditoria ADD COLUMN user_agent VARCHAR(255) NULL AFTER ip',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'auditoria' AND column_name = 'detalle') = 0,
    'ALTER TABLE auditoria ADD COLUMN detalle TEXT NULL AFTER datos_nuevos',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'auditoria' AND index_name = 'idx_auditoria_busqueda') = 0,
    'CREATE INDEX idx_auditoria_busqueda ON auditoria(empresa_id, modulo, accion, fecha_evento)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
