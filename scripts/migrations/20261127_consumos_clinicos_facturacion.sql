CREATE TABLE IF NOT EXISTS venta_detalle_consumos_clinicos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    venta_detalle_id BIGINT UNSIGNED NOT NULL,
    movimiento_id BIGINT UNSIGNED NOT NULL,
    cantidad DECIMAL(14,4) NOT NULL,
    vinculado_por BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    liberado_at DATETIME DEFAULT NULL,
    liberado_por BIGINT UNSIGNED DEFAULT NULL,
    motivo_liberacion VARCHAR(500) DEFAULT NULL,

    movimiento_activo_id BIGINT UNSIGNED
        GENERATED ALWAYS AS (
            CASE
                WHEN liberado_at IS NULL
                THEN movimiento_id
                ELSE NULL
            END
        ) STORED,

    PRIMARY KEY (id),

    UNIQUE KEY uq_vdcc_movimiento_activo (
        movimiento_activo_id
    ),

    UNIQUE KEY uq_vdcc_detalle_movimiento (
        venta_detalle_id,
        movimiento_id
    ),

    KEY idx_vdcc_movimiento (movimiento_id),

    CONSTRAINT fk_vdcc_detalle
        FOREIGN KEY (venta_detalle_id)
        REFERENCES venta_detalles(id),

    CONSTRAINT fk_vdcc_movimiento
        FOREIGN KEY (movimiento_id)
        REFERENCES movimientos_inventario(id),

    CONSTRAINT fk_vdcc_vinculado_por
        FOREIGN KEY (vinculado_por)
        REFERENCES usuarios(id),

    CONSTRAINT fk_vdcc_liberado_por
        FOREIGN KEY (liberado_por)
        REFERENCES usuarios(id),

    CONSTRAINT chk_vdcc_cantidad
        CHECK (cantidad > 0)

) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;