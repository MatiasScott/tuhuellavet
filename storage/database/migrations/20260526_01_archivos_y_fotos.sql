-- Ajustes de imagenes y archivos para esquema existente
-- Ejecutar sobre la BD sistema_veterinario_ganadero

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'propietarios' AND column_name = 'foto') = 0,
    'ALTER TABLE propietarios ADD COLUMN foto VARCHAR(255) NULL AFTER usuario_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'productos' AND column_name = 'foto') = 0,
    'ALTER TABLE productos ADD COLUMN foto VARCHAR(255) NULL AFTER descripcion',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'medicamentos' AND column_name = 'foto') = 0,
    'ALTER TABLE medicamentos ADD COLUMN foto VARCHAR(255) NULL AFTER descripcion',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS archivos (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    empresa_id BIGINT UNSIGNED NULL,
    entidad VARCHAR(50) NOT NULL,
    entidad_id BIGINT UNSIGNED NOT NULL,
    tipo_archivo VARCHAR(30) NOT NULL,
    nombre_original VARCHAR(255) NOT NULL,
    nombre_archivo VARCHAR(255) NOT NULL,
    ruta VARCHAR(255) NOT NULL,
    extension VARCHAR(20) NOT NULL,
    mime VARCHAR(100) NOT NULL,
    tamano_bytes BIGINT UNSIGNED NOT NULL,
    ancho INT UNSIGNED NULL,
    alto INT UNSIGNED NULL,
    metadata JSON NULL,
    subido_por BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_archivos_entidad (entidad, entidad_id),
    INDEX idx_archivos_empresa (empresa_id),
    CONSTRAINT fk_archivos_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE SET NULL,
    CONSTRAINT fk_archivos_usuario FOREIGN KEY (subido_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
