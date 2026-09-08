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
