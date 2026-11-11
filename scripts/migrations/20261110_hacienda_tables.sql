CREATE TABLE IF NOT EXISTS lotes_animales (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entorno_id BIGINT UNSIGNED NOT NULL,
    especie_id SMALLINT UNSIGNED NOT NULL,
    codigo VARCHAR(80) NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    cantidad INT UNSIGNED NOT NULL,
    metodo_identificacion VARCHAR(150) NOT NULL,
    origen VARCHAR(150) NOT NULL,
    observaciones TEXT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_lote_animal_entorno (id, entorno_id),
    UNIQUE KEY uq_lote_animal_codigo (entorno_id, codigo),
    CONSTRAINT fk_lote_animal_entorno FOREIGN KEY (entorno_id) REFERENCES entornos(id),
    CONSTRAINT fk_lote_animal_especie FOREIGN KEY (especie_id) REFERENCES especies(id),
    CONSTRAINT chk_lote_animal_cantidad CHECK (cantidad > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS produccion (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entorno_id BIGINT UNSIGNED NOT NULL,
    paciente_id BIGINT UNSIGNED NULL,
    lote_id BIGINT UNSIGNED NULL,
    tipo_producto VARCHAR(100) NOT NULL,
    cantidad DECIMAL(10,2) NOT NULL,
    unidad_id SMALLINT UNSIGNED NOT NULL,
    fecha_registro DATETIME NOT NULL,
    observaciones TEXT NULL,
    registrado_por BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    KEY idx_produccion_fecha (entorno_id, fecha_registro),
    CONSTRAINT fk_produccion_animal FOREIGN KEY (paciente_id, entorno_id) REFERENCES animales(id, entorno_id),
    CONSTRAINT fk_produccion_lote FOREIGN KEY (lote_id, entorno_id) REFERENCES lotes_animales(id, entorno_id),
    CONSTRAINT fk_produccion_entorno FOREIGN KEY (entorno_id) REFERENCES entornos(id),
    CONSTRAINT fk_produccion_unidad FOREIGN KEY (unidad_id) REFERENCES unidades_medida(id),
    CONSTRAINT fk_produccion_usuario FOREIGN KEY (registrado_por) REFERENCES usuarios(id),
    CONSTRAINT chk_produccion_destino CHECK ((paciente_id IS NULL) <> (lote_id IS NULL)),
    CONSTRAINT chk_produccion_cantidad CHECK (cantidad > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS genealogia (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entorno_id BIGINT UNSIGNED NOT NULL,
    paciente_id BIGINT UNSIGNED NOT NULL,
    padre_id BIGINT UNSIGNED NULL,
    madre_id BIGINT UNSIGNED NULL,
    generacion INT UNSIGNED NOT NULL DEFAULT 1,
    observaciones TEXT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_genealogia_paciente (paciente_id, entorno_id),
    CONSTRAINT fk_genealogia_animal FOREIGN KEY (paciente_id, entorno_id) REFERENCES animales(id, entorno_id),
    CONSTRAINT fk_genealogia_padre FOREIGN KEY (padre_id, entorno_id) REFERENCES animales(id, entorno_id),
    CONSTRAINT fk_genealogia_madre FOREIGN KEY (madre_id, entorno_id) REFERENCES animales(id, entorno_id),
    CONSTRAINT chk_genealogia_padres CHECK (
        (padre_id IS NULL OR padre_id <> paciente_id)
        AND (madre_id IS NULL OR madre_id <> paciente_id)
        AND (padre_id IS NULL OR madre_id IS NULL OR padre_id <> madre_id)
    ),
    CONSTRAINT chk_genealogia_generacion CHECK (generacion > 0)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS alimentacion (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entorno_id BIGINT UNSIGNED NOT NULL,
    paciente_id BIGINT UNSIGNED NULL,
    lote_id BIGINT UNSIGNED NULL,
    tipo_alimento VARCHAR(150) NOT NULL,
    cantidad_consumida DECIMAL(10,2) NOT NULL,
    unidad_id SMALLINT UNSIGNED NOT NULL,
    frecuencia VARCHAR(100) NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NULL,
    observaciones TEXT NULL,
    estado VARCHAR(20) NOT NULL DEFAULT 'PLANIFICADA',
    inventario_id BIGINT UNSIGNED NULL,
    producto_id BIGINT UNSIGNED NULL,
    lote_producto_id BIGINT UNSIGNED NULL,
    movimiento_id BIGINT UNSIGNED NULL,
    registrado_por BIGINT UNSIGNED NOT NULL,
    confirmado_por BIGINT UNSIGNED NULL,
    confirmado_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_alimentacion_movimiento (movimiento_id),
    KEY idx_alimentacion_fecha (entorno_id, fecha_inicio),
    CONSTRAINT fk_alimentacion_animal FOREIGN KEY (paciente_id, entorno_id) REFERENCES animales(id, entorno_id),
    CONSTRAINT fk_alimentacion_lote FOREIGN KEY (lote_id, entorno_id) REFERENCES lotes_animales(id, entorno_id),
    CONSTRAINT fk_alimentacion_entorno FOREIGN KEY (entorno_id) REFERENCES entornos(id),
    CONSTRAINT fk_alimentacion_unidad FOREIGN KEY (unidad_id) REFERENCES unidades_medida(id),
    CONSTRAINT fk_alimentacion_inventario FOREIGN KEY (inventario_id) REFERENCES inventarios(id),
    CONSTRAINT fk_alimentacion_producto FOREIGN KEY (producto_id) REFERENCES productos(id),
    CONSTRAINT fk_alimentacion_lote_producto FOREIGN KEY (lote_producto_id) REFERENCES lotes_producto(id),
    CONSTRAINT fk_alimentacion_movimiento FOREIGN KEY (movimiento_id) REFERENCES movimientos_inventario(id),
    CONSTRAINT fk_alimentacion_usuario FOREIGN KEY (registrado_por) REFERENCES usuarios(id),
    CONSTRAINT fk_alimentacion_confirmador FOREIGN KEY (confirmado_por) REFERENCES usuarios(id),
    CONSTRAINT chk_alimentacion_destino CHECK ((paciente_id IS NULL) <> (lote_id IS NULL)),
    CONSTRAINT chk_alimentacion_cantidad CHECK (cantidad_consumida > 0),
    CONSTRAINT chk_alimentacion_fechas CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio),
    CONSTRAINT chk_alimentacion_estado CHECK (
        (estado = 'PLANIFICADA' AND movimiento_id IS NULL AND confirmado_at IS NULL)
        OR (estado = 'CONSUMIDA' AND movimiento_id IS NOT NULL AND confirmado_at IS NOT NULL)
    )
) ENGINE=InnoDB;

INSERT INTO tipos_movimiento_inventario (codigo, nombre, factor)
SELECT 'CONSUMO_ALIMENTACION', 'Consumo de alimento en hacienda', -1
WHERE NOT EXISTS (SELECT 1 FROM tipos_movimiento_inventario WHERE codigo = 'CONSUMO_ALIMENTACION');

INSERT INTO unidades_medida (codigo,nombre,simbolo,categoria,activo)
SELECT 'UND','Unidad','und','CONTEO',1
WHERE NOT EXISTS (SELECT 1 FROM unidades_medida WHERE codigo='UND' OR (simbolo='und' AND categoria='CONTEO'));
