-- Registrar el movimiento de devolución sin modificar una configuración existente.
INSERT INTO tipos_movimiento_inventario (codigo, nombre, factor)
VALUES ('DEVOLUCION_VENTA', 'Devolución por anulación de venta', 1)
ON DUPLICATE KEY UPDATE id = id;
