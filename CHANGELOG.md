# Historial de cambios - Axxis / Tu Huella Vet

## [1.57.0] - 2026-11-27

### Facturación

- Asignación y edición del titular fiscal antes de la emisión.
- Separación del propietario clínico y titular fiscal.
- Modalidad de venta a consumidor final.
- Selección de inventario por línea de venta.
- Facturación de consumos clínicos previamente registrados.
- Prevención de doble facturación y doble descuento de stock.
- Liberación de consumos clínicos al anular una venta.
- Mejoras en presentación de cantidades y valores decimales.

### Inventarios

- Inventarios de Farmacia, Bodega, Veterinaria y Hospitalización.
- Transferencias entre inventarios.
- Control de existencias por inventario y lote.
- Selección de lotes por vencimiento (FEFO).
- Reversión de movimientos al inventario y lote originales.
- Asociación de productos con vacunas y presentaciones farmacológicas.

### Gestión clínica

- Consumo de inventario en vacunaciones.
- Consumo de inventario en desparasitaciones.
- Consumo de insumos en consultas y cirugías.
- Consumo de insumos en hospitalizaciones.
- Consumo de medicamentos al registrar su aplicación.
- Selección de presentación farmacológica en tratamientos.
- Anulación lógica de tratamientos hospitalarios.
- Conservación del historial clínico y auditoría.
- Protección de consumos clínicos vinculados a ventas.

### Seguridad y permisos

- Corrección de asignaciones automáticas de permisos.
- Conservación de la administración de permisos desde la interfaz.
- Validaciones de pertenencia al entorno.
- Protección de operaciones de anulación.

### Integración fiscal iConta

- Conservación de los mecanismos de recuperación fiscal.
- Validaciones para la modalidad consumidor final.
- Emisión electrónica a consumidor final deshabilitada
  hasta confirmar la integración con el proveedor.

### Base de datos

- 20261126_thv_inventarios.sql
- 20261126_consumidor_final.sql
- 20261127_superadmin_permisos.sql
- 20261127_consumos_clinicos_facturacion.sql
- 20261127_anulacion_tratamientos.sql

### Control de calidad

- PHPUnit 11.5.56.
- 1179 pruebas.
- 6089 aserciones.
- 0 errores.
- 0 fallos.
- 1 prueba omitida por limitación de enlaces simbólicos en Windows.
- Validación de sintaxis PHP satisfactoria.
- Validación Git satisfactoria.
- Reversión hospitalaria completa pendiente de confirmación funcional.

## [1.56.2] - 2026-11-25

- Recuperación fiscal controlada de facturas rechazadas por iConta.
