# Checklist de pruebas finales

Las pruebas funcionales se ejecutarán después de terminar la construcción, según el orden acordado.

## 1. Instalación
- Importar `schema.sql` desde cero.
- Ejecutar `composer install`.
- Copiar `.env.example` a `.env`.
- Crear Super Administrador.
- Verificar rutas bajo `http://localhost/tuhuellavet/public`.

## 2. Autenticación y permisos
- Login local, cambio obligatorio, recuperación de contraseña y logout.
- Google OAuth con usuario previamente creado.
- Super Admin global y selección de los tres entornos.
- Administrador, Cliente, Invitado, Docente y Estudiante.
- Intentos de acceso directo a rutas sin permiso.

## 3. Clínica
- Propietarios, creación opcional de acceso de Cliente.
- Pacientes, fotos y peso histórico.
- Consulta externa, diagnósticos y tratamientos.
- En el detalle de consulta, abrir «Agregar tratamiento», verificar el medicamento inicial y agregar otro con «+ Medicamento». Comprobar que el formulario envía `tipo_tratamiento_id` y los medicamentos.
- Abrir «Registrar aplicación» de un medicamento clínico, cerrar con Cancelar y comprobar que la acción apunta al ID del medicamento y envía `cantidad_aplicada`.
- Vacunación y desparasitación con recordatorios.
- Hospitalización: ingreso, signos, peso, fórmulas, fluidoterapia, tratamientos, evoluciones y alta.
- Laboratorio con PDF/imagen.
- Cirugía y anestesia/documentos.
- Timeline completo del paciente.

## 4. Gestión
- Inventario, productos y movimientos.
- Citas.
- Fórmulas: crear, versionar, publicar, ejecutar.
- Notificaciones y reintentos.
- Ventas/facturación y bloqueo de facturación real en Académico.
- iConta: ante cualquier error previo al inicio de la generación de la factura (datos faltantes o inválidos, configuración o sincronización del cliente), corregir la causa y usar «Reintentar envío» en el documento existente. Para datos fiscales, editar el mismo registro asociado a la venta. El mensaje muestra el último intento; editar el propietario no reenvía la factura automáticamente. Si falta otro dato, el reintento debe mostrar el nuevo error y permitir corregirlo y volver a intentar.
- Verificar que el reintento solo aparezca con permiso de facturación, venta finalizada y entorno real, sin intentos de generación, ID remoto ni fecha de envío. Si ya se inició la generación, incluso ante un timeout sin ID remoto, se requiere conciliación con iConta para evitar duplicados. Comprobar que dos solicitudes simultáneas no generen dos facturas.
- Reportes CSV/XLSX/PDF.
- Auditoría.

## 5. Académico
- Periodos, asignaturas, cursos, docentes y estudiantes.
- Casos/actividades de simulación.
- Verificar que no se mezclen registros académicos con entornos reales.

## 6. Integraciones
- Google con credenciales reales.
- Contífico con sandbox/productivo del cliente.
- WhatsApp con proveedor definitivo.
- Correo saliente del servidor.

## 7. Producción
- `APP_DEBUG=false`.
- HTTPS/DocumentRoot a `/public`.
- Permisos de `storage`.
- Cron para notificaciones y Contífico.
- Backup y restauración de DB + uploads.
