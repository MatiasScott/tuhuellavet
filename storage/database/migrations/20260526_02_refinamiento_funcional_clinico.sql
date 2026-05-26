-- Refinamiento funcional y clinico
-- No elimina tablas ni columnas existentes
-- Ejecutar sobre sistema_veterinario_ganadero

-- =====================================================
-- 1) PROPIETARIOS / CLIENTES
-- =====================================================
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'propietarios' AND column_name = 'portal_cliente_activo') = 0,
    'ALTER TABLE propietarios ADD COLUMN portal_cliente_activo TINYINT(1) NULL DEFAULT 0 AFTER usuario_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- 2) PACIENTES / ANIMALES
-- =====================================================
CREATE TABLE IF NOT EXISTS animal_pesos (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    animal_id BIGINT UNSIGNED NOT NULL,
    consulta_id BIGINT UNSIGNED NULL,
    peso DECIMAL(10,2) NOT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    usuario_id BIGINT UNSIGNED NULL,
    observacion TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_animal_pesos_animal_fecha (animal_id, fecha_registro),
    CONSTRAINT fk_animal_pesos_animal FOREIGN KEY (animal_id) REFERENCES animales(id) ON DELETE CASCADE,
    CONSTRAINT fk_animal_pesos_consulta FOREIGN KEY (consulta_id) REFERENCES consultas(id) ON DELETE SET NULL,
    CONSTRAINT fk_animal_pesos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3) CONSULTA EXTERNA
-- =====================================================
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'consultas' AND column_name = 'antecedentes') = 0,
    'ALTER TABLE consultas ADD COLUMN antecedentes TEXT NULL AFTER anamnesis',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'consultas' AND column_name = 'recomendaciones') = 0,
    'ALTER TABLE consultas ADD COLUMN recomendaciones TEXT NULL AFTER tratamiento',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'consultas' AND column_name = 'tratamiento_clinico') = 0,
    'ALTER TABLE consultas ADD COLUMN tratamiento_clinico TEXT NULL AFTER recomendaciones',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'consultas' AND column_name = 'tratamiento_casa') = 0,
    'ALTER TABLE consultas ADD COLUMN tratamiento_casa TEXT NULL AFTER tratamiento_clinico',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS consulta_examen_general (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    consulta_id BIGINT UNSIGNED NOT NULL,
    alimentacion TEXT NULL,
    historial_reproductivo TEXT NULL,
    frecuencia_cardiaca DECIMAL(10,2) NULL,
    frecuencia_respiratoria DECIMAL(10,2) NULL,
    temperatura DECIMAL(5,2) NULL,
    tiempo_llenado_capilar VARCHAR(50) NULL,
    ganglios_linfaticos VARCHAR(255) NULL,
    condicion_corporal VARCHAR(100) NULL,
    vomitos TINYINT(1) NULL,
    diarrea TINYINT(1) NULL,
    tos TINYINT(1) NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_examen_general_consulta (consulta_id),
    CONSTRAINT fk_examen_general_consulta FOREIGN KEY (consulta_id) REFERENCES consultas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 4) DIAGNOSTICOS
-- =====================================================
CREATE TABLE IF NOT EXISTS catalogo_diagnosticos (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    codigo VARCHAR(60) NULL,
    nombre VARCHAR(200) NOT NULL,
    tipo ENUM('diferencial','preventivo','definitivo') NOT NULL,
    descripcion TEXT NULL,
    estado TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_catalogo_diagnosticos_nombre_tipo (nombre, tipo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS consulta_diagnosticos (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    consulta_id BIGINT UNSIGNED NOT NULL,
    diagnostico_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    observacion TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_consulta_diag_consulta (consulta_id),
    INDEX idx_consulta_diag_diag (diagnostico_id),
    CONSTRAINT fk_consulta_diag_consulta FOREIGN KEY (consulta_id) REFERENCES consultas(id) ON DELETE CASCADE,
    CONSTRAINT fk_consulta_diag_catalogo FOREIGN KEY (diagnostico_id) REFERENCES catalogo_diagnosticos(id),
    CONSTRAINT fk_consulta_diag_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 5) VACUNAS
-- =====================================================
CREATE TABLE IF NOT EXISTS catalogo_vacunas (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    empresa_id BIGINT UNSIGNED NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion TEXT NULL,
    estado TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_catalogo_vacunas_empresa (empresa_id),
    CONSTRAINT fk_catalogo_vacunas_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'animal_vacunas' AND column_name = 'catalogo_vacuna_id') = 0,
    'ALTER TABLE animal_vacunas ADD COLUMN catalogo_vacuna_id BIGINT UNSIGNED NULL AFTER vacuna_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'animal_vacunas' AND column_name = 'dosis') = 0,
    'ALTER TABLE animal_vacunas ADD COLUMN dosis VARCHAR(100) NULL AFTER catalogo_vacuna_id',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'animal_vacunas' AND column_name = 'laboratorio') = 0,
    'ALTER TABLE animal_vacunas ADD COLUMN laboratorio VARCHAR(150) NULL AFTER dosis',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'animal_vacunas' AND column_name = 'lote') = 0,
    'ALTER TABLE animal_vacunas ADD COLUMN lote VARCHAR(120) NULL AFTER laboratorio',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'animal_vacunas' AND column_name = 'usuario_id') = 0,
    'ALTER TABLE animal_vacunas ADD COLUMN usuario_id BIGINT UNSIGNED NULL AFTER observaciones',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'animal_vacunas' AND index_name = 'idx_animal_vacunas_catalogo') = 0,
    'CREATE INDEX idx_animal_vacunas_catalogo ON animal_vacunas(catalogo_vacuna_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'animal_vacunas' AND index_name = 'idx_animal_vacunas_usuario') = 0,
    'CREATE INDEX idx_animal_vacunas_usuario ON animal_vacunas(usuario_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = 'animal_vacunas' AND constraint_name = 'fk_animal_vacunas_catalogo') = 0,
    'ALTER TABLE animal_vacunas ADD CONSTRAINT fk_animal_vacunas_catalogo FOREIGN KEY (catalogo_vacuna_id) REFERENCES catalogo_vacunas(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = DATABASE() AND table_name = 'animal_vacunas' AND constraint_name = 'fk_animal_vacunas_usuario') = 0,
    'ALTER TABLE animal_vacunas ADD CONSTRAINT fk_animal_vacunas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- =====================================================
-- 6) DESPARASITACION
-- =====================================================
CREATE TABLE IF NOT EXISTS desparasitaciones (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    empresa_id BIGINT UNSIGNED NOT NULL,
    animal_id BIGINT UNSIGNED NOT NULL,
    farmaco VARCHAR(150) NOT NULL,
    dosis VARCHAR(100) NULL,
    fecha DATE NOT NULL,
    proxima_fecha DATE NULL,
    observacion TEXT NULL,
    peso_actual DECIMAL(10,2) NULL,
    usuario_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_desparasitaciones_animal_fecha (animal_id, fecha),
    CONSTRAINT fk_desparasitaciones_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id),
    CONSTRAINT fk_desparasitaciones_animal FOREIGN KEY (animal_id) REFERENCES animales(id) ON DELETE CASCADE,
    CONSTRAINT fk_desparasitaciones_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 7) HOSPITALIZACION
-- =====================================================
CREATE TABLE IF NOT EXISTS tamanos_animales (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    peso_min DECIMAL(10,2) NULL,
    peso_max DECIMAL(10,2) NULL,
    estado TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_tamanos_animales_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hospitalizaciones (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    empresa_id BIGINT UNSIGNED NOT NULL,
    animal_id BIGINT UNSIGNED NOT NULL,
    consulta_id BIGINT UNSIGNED NULL,
    fecha_ingreso DATETIME NOT NULL,
    fecha_salida DATETIME NULL,
    motivo TEXT NULL,
    estado ENUM('activa','alta','traslado','cancelada') DEFAULT 'activa',
    observaciones TEXT NULL,
    usuario_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_hospitalizaciones_animal_estado (animal_id, estado),
    CONSTRAINT fk_hospitalizaciones_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id),
    CONSTRAINT fk_hospitalizaciones_animal FOREIGN KEY (animal_id) REFERENCES animales(id) ON DELETE CASCADE,
    CONSTRAINT fk_hospitalizaciones_consulta FOREIGN KEY (consulta_id) REFERENCES consultas(id) ON DELETE SET NULL,
    CONSTRAINT fk_hospitalizaciones_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS fluidoterapia (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    hospitalizacion_id BIGINT UNSIGNED NOT NULL,
    tamano_animal_id BIGINT UNSIGNED NULL,
    mantenimiento DECIMAL(10,2) NULL,
    rehidratacion DECIMAL(10,2) NULL,
    formula VARCHAR(255) NULL,
    formulas_medicas TEXT NULL,
    signos_clinicos TEXT NULL,
    observaciones TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_fluidoterapia_hosp (hospitalizacion_id),
    CONSTRAINT fk_fluidoterapia_hospitalizacion FOREIGN KEY (hospitalizacion_id) REFERENCES hospitalizaciones(id) ON DELETE CASCADE,
    CONSTRAINT fk_fluidoterapia_tamano FOREIGN KEY (tamano_animal_id) REFERENCES tamanos_animales(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 8) EXAMENES DE LABORATORIO
-- =====================================================
CREATE TABLE IF NOT EXISTS examenes_laboratorio (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    empresa_id BIGINT UNSIGNED NOT NULL,
    animal_id BIGINT UNSIGNED NOT NULL,
    consulta_id BIGINT UNSIGNED NULL,
    tipo_examen VARCHAR(150) NOT NULL,
    observaciones TEXT NULL,
    resultado TEXT NULL,
    archivo_pdf VARCHAR(255) NULL,
    usuario_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_examenes_laboratorio_animal (animal_id),
    CONSTRAINT fk_examenes_laboratorio_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id),
    CONSTRAINT fk_examenes_laboratorio_animal FOREIGN KEY (animal_id) REFERENCES animales(id) ON DELETE CASCADE,
    CONSTRAINT fk_examenes_laboratorio_consulta FOREIGN KEY (consulta_id) REFERENCES consultas(id) ON DELETE SET NULL,
    CONSTRAINT fk_examenes_laboratorio_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 9) CIRUGIAS
-- =====================================================
CREATE TABLE IF NOT EXISTS cirugias (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    empresa_id BIGINT UNSIGNED NOT NULL,
    animal_id BIGINT UNSIGNED NOT NULL,
    consulta_id BIGINT UNSIGNED NULL,
    procedimiento_quirurgico VARCHAR(255) NOT NULL,
    medico_responsable VARCHAR(150) NULL,
    anestesia VARCHAR(150) NULL,
    formula_medica TEXT NULL,
    formula_id BIGINT UNSIGNED NULL,
    archivo_pdf VARCHAR(255) NULL,
    observaciones TEXT NULL,
    fecha DATETIME NOT NULL,
    usuario_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cirugias_animal_fecha (animal_id, fecha),
    CONSTRAINT fk_cirugias_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id),
    CONSTRAINT fk_cirugias_animal FOREIGN KEY (animal_id) REFERENCES animales(id) ON DELETE CASCADE,
    CONSTRAINT fk_cirugias_consulta FOREIGN KEY (consulta_id) REFERENCES consultas(id) ON DELETE SET NULL,
    CONSTRAINT fk_cirugias_formula FOREIGN KEY (formula_id) REFERENCES formulas(id) ON DELETE SET NULL,
    CONSTRAINT fk_cirugias_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 10) TIMELINE CLINICO (VISTA CONSOLIDADA)
-- =====================================================
CREATE OR REPLACE VIEW vw_historial_clinico_timeline AS
SELECT
    a.id AS animal_id,
    c.empresa_id,
    'consulta' AS modulo,
    c.id AS referencia_id,
    c.fecha_consulta AS fecha_evento,
    c.motivo_consulta AS titulo,
    c.observaciones AS detalle
FROM consultas c
INNER JOIN animales a ON a.id = c.animal_id
UNION ALL
SELECT
    av.animal_id,
    an.empresa_id,
    'vacuna' AS modulo,
    av.id AS referencia_id,
    CAST(av.fecha_aplicacion AS DATETIME) AS fecha_evento,
    COALESCE(cv.nombre, v.nombre, 'Vacuna') AS titulo,
    av.observaciones AS detalle
FROM animal_vacunas av
INNER JOIN animales an ON an.id = av.animal_id
LEFT JOIN catalogo_vacunas cv ON cv.id = av.catalogo_vacuna_id
LEFT JOIN vacunas v ON v.id = av.vacuna_id
UNION ALL
SELECT
    d.animal_id,
    d.empresa_id,
    'desparasitacion' AS modulo,
    d.id AS referencia_id,
    CAST(d.fecha AS DATETIME) AS fecha_evento,
    d.farmaco AS titulo,
    d.observacion AS detalle
FROM desparasitaciones d
UNION ALL
SELECT
    h.animal_id,
    h.empresa_id,
    'hospitalizacion' AS modulo,
    h.id AS referencia_id,
    h.fecha_ingreso AS fecha_evento,
    'Hospitalizacion' AS titulo,
    h.observaciones AS detalle
FROM hospitalizaciones h
UNION ALL
SELECT
    e.animal_id,
    e.empresa_id,
    'examen_laboratorio' AS modulo,
    e.id AS referencia_id,
    e.created_at AS fecha_evento,
    e.tipo_examen AS titulo,
    e.observaciones AS detalle
FROM examenes_laboratorio e
UNION ALL
SELECT
    ci.animal_id,
    ci.empresa_id,
    'cirugia' AS modulo,
    ci.id AS referencia_id,
    ci.fecha AS fecha_evento,
    ci.procedimiento_quirurgico AS titulo,
    ci.observaciones AS detalle
FROM cirugias ci;

-- =====================================================
-- 11) DASHBOARD MODULAR
-- =====================================================
CREATE TABLE IF NOT EXISTS dashboard_widgets (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    codigo VARCHAR(100) NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    contexto ENUM('veterinaria','hacienda','global') DEFAULT 'global',
    modulo_origen VARCHAR(100) NOT NULL,
    activo TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_dashboard_widgets_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dashboard_widget_roles (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    widget_id BIGINT UNSIGNED NOT NULL,
    rol_id BIGINT UNSIGNED NOT NULL,
    orden SMALLINT UNSIGNED DEFAULT 1,
    visible TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_dashboard_widget_roles (widget_id, rol_id),
    CONSTRAINT fk_dashboard_widget_roles_widget FOREIGN KEY (widget_id) REFERENCES dashboard_widgets(id) ON DELETE CASCADE,
    CONSTRAINT fk_dashboard_widget_roles_rol FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 12) INDICES DE APOYO
-- =====================================================
SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'consultas' AND index_name = 'idx_consultas_empresa_fecha') = 0,
    'CREATE INDEX idx_consultas_empresa_fecha ON consultas(empresa_id, fecha_consulta)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'consulta_diagnosticos' AND index_name = 'idx_consulta_diag_usuario') = 0,
    'CREATE INDEX idx_consulta_diag_usuario ON consulta_diagnosticos(usuario_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
