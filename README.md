# Tu Huella Vet

Sistema integral de gestión veterinaria, hacienda y formación académica desarrollado en **PHP MVC puro**. El mismo código soporta operación real de clínica/hacienda y un entorno académico de simulación separado.

## Requisitos

- PHP 8.2 o superior.
- MySQL 8 / MariaDB 10.5+.
- Composer.
- Extensiones PHP habituales: PDO MySQL, mbstring, curl, fileinfo, gd, zip, intl y openssl.
- Apache con `mod_rewrite` o servidor equivalente cuyo DocumentRoot apunte a `public/`.

## Instalación local con XAMPP

```bash
cd C:\xampp\htdocs
git clone <tu-repositorio> tuhuellavet
cd tuhuellavet
composer install
copy .env.example .env
```

Crea la base e importa `schema.sql`. El esquema incluye los catálogos base, especies principales y los tres entornos iniciales.
Después de importar el esquema, ejecuta `php scripts/migrate_hacienda.php`
para instalar las ampliaciones de Hacienda descritas abajo.

Configura `.env`:

```env
APP_URL=http://localhost/tuhuellavet/public
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=vet_academic_erp
DB_USERNAME=root
DB_PASSWORD=
```

Abre:

```text
http://localhost/tuhuellavet/public/
```

## Crear el primer Super Administrador

```bash
php scripts/create_super_admin.php admin@dominio.com "PasswordSegura" "Nombre" "Apellido"
```

El Super Administrador es global y puede elegir cualquiera de los entornos activos.

## Entornos incluidos

- **Tu Huella Vet** (`TU_HUELLA_VET`): clínica veterinaria, productivo y preparado para facturación real.
- **Hacienda Agusbella** (`HACIENDA_AGUSBELLA`): gestión animal/productiva.
- **Académico** (`ACADEMICO`): docentes, estudiantes y simulaciones; no productivo y sin facturación real.

## Roles

- **Super Administrador**: acceso global completo.
- **Administrador**: operación del entorno; no administra roles/permisos/auditoría ni puede crear Super Administradores. La creación de usuarios está restringida al rol Cliente.
- **Cliente**: portal separado, solo sus animales y su información clínica; puede actualizar fotos de sus animales.
- **Invitado**: lectura limitada.
- **Docente**: gestión académica y supervisión de simulaciones.
- **Estudiante**: prácticas académicas controladas.

## Hacienda Agusbella

Antes de actualizar, respalda la base configurada en `.env`. La migración
es aditiva y se puede volver a ejecutar; MySQL confirma los cambios DDL
individualmente, por lo que un error no revierte automáticamente toda la
migración. El comando informa errores y termina con código distinto de cero.

```powershell
php scripts\migrate_hacienda.php
# Base aislada de pruebas, nunca la base operativa:
php scripts\migrate_hacienda.php --qa
```

La adaptación reutiliza `animales`, `eventos_clinicos`,
`examenes_clinicos_generales`, unidades, permisos e inventarios existentes.
No crea otra tabla de pacientes ni confunde `lotes_animales` con
`lotes_producto`. Las relaciones de producción, genealogía y alimentación
comprueban también el entorno mediante llaves foráneas compuestas.

- En Pacientes, el selector distingue animal individual y lotes con ficha,
  especie y cantidad propias. Identificación y origen son obligatorios en
  Hacienda. Los registros antiguos no reciben un origen ficticio; deben
  completarse al editar.
- Las constantes se conservan **por atención**, no se sobrescriben en la
  ficha. `temperatura_c` representa la temperatura pulmonar/cloacal
  (`DECIMAL(5,2)`), conservando el nombre existente. El TLC numérico se
  mantiene en `tiempo_llenado_capilar_seg`; `llenado_capilar` guarda su
  descripción. Se agregan las otras constantes solicitadas al examen.
  Los lotes tienen atenciones e historial propios reutilizando los eventos
  y exámenes; los módulos clínicos individuales siguen usando `animal_id`.
  Los controles seriados de hospitalización también conservan estas
  constantes en `hospitalizacion_signos`.
- Producción registra cantidades por animal **o** lote y unidad.
  Genealogía permite actualizar progenitores, exige misma especie y sexo
  correspondiente y rechaza ciclos o linaje compartido conocido, incluso
  si una modificación de ancestros afecta a descendientes. No garantiza
  ausencia de consanguinidad cuando falta información genealógica.
- Alimentación separa planes y consumos. El plan puede vincularse después
  al producto, inventario y lote de producto. Confirmar descuenta la
  **cantidad total indicada**, una sola vez, sin multiplicarla por días,
  frecuencia o número de animales. No se automatizan consumos recurrentes.
  Se exige la unidad base del producto, sin conversiones implícitas.
  Stock insuficiente, lotes vencidos y consumos futuros se rechazan.
  Un consumo confirmado es inmutable y no se revierte manualmente desde
  inventario, para no romper su trazabilidad.
- El catálogo de productos de Hacienda es exclusivo (`productos.entorno_id`).
  Los códigos se distinguen por catálogo; el catálogo clínico preexistente
  conserva su ámbito compartido y sus datos. Los formularios de Hacienda
  no dependen de ventas ni requieren precios de venta o impuestos.
  Si ya existen productos compartidos asociados a inventarios de Hacienda,
  la migración se detiene para revisar su separación sin reasignarlos
  automáticamente. También completa columnas de productos ya utilizadas
  por el servicio de inventario si faltan en una instalación antigua.
- Peluquería se retira de la navegación y se bloquea por ruta en Hacienda;
  Fórmulas y Ventas también se bloquean, incluso para Super Administrador.
  Se conservan código, datos y funcionamiento en los demás entornos.
- Reportes enlaza producción y alimentación, con filtros de fechas,
  animal/lote, totales por producto/unidad/destino y exportación CSV.
  Alimentación distingue cantidades planificadas y consumidas y conserva
  el movimiento, inventario y lote de producto utilizados.

Los módulos nuevos se habilitan para Administrador y Super Administrador.
Otros perfiles requieren asignación explícita de permisos. Confirmar un
consumo requiere `alimentacion.editar` e `inventario.editar`; exportar,
`reportes.exportar` y acceso al módulo correspondiente.

Prueba focalizada, después de migrar QA:

```powershell
php vendor\phpunit\phpunit\phpunit tests\Functional\HaciendaWorkflowTest.php
```

## Módulos incluidos

- Autenticación local, cambio obligatorio de contraseña, recuperación de contraseña y Google OAuth preparado.
- Multiempresa y multientorno.
- Usuarios, roles, permisos dinámicos y auditoría.
- Catálogos clínicos y veterinarios.
- Propietarios/clientes y creación opcional de cuenta de acceso.
- Pacientes, fotos, especies/razas y evolución histórica de peso.
- Consulta externa, examen clínico, diagnósticos y tratamientos.
- Vacunación y desparasitación con recordatorios en cola.
- Hospitalización: ingreso, signos seriados, peso histórico, fluidoterapia, fórmulas, tratamientos, aplicaciones, evoluciones y alta/cierre.
- Laboratorio clínico y documentos PDF/imágenes.
- Cirugías, equipo/anestesia y documentos.
- Motor de fórmulas médicas versionado con variables automáticas/manuales e histórico de ejecuciones.
- Inventario, productos y movimientos.
- Citas.
- Notificaciones internas, correo y adaptador HTTP para WhatsApp.
- Ventas/facturación y cola para Contífico.
- Reportes CSV, XLSX y PDF.
- Portal académico para periodos, asignaturas, cursos, docentes/estudiantes y casos de simulación.
- Portal Cliente separado por rol.

## Fórmulas y hospitalización

Los pesos **no se sobrescriben** en vacunaciones, desparasitaciones u hospitalización: cada medición se registra en `animales_pesos`.

Las fórmulas se crean con versiones y variables. Cada ejecución conserva paciente, evento clínico, usuario, valores, resultado y contexto. En el entorno Académico las ejecuciones se marcan como simulación.

## Datos para facturar con iConta

Para registrar un paciente se exige la especie. Para registrar un propietario
se exigen los nombres y, si se habilita el acceso al portal, un correo válido.
La ficha puede guardarse sin completar los datos para facturación.

Antes de crear una factura, la integración comprueba que los datos fiscales
estén activos y contengan tipo de identificación compatible (cédula, RUC o
pasaporte), identificación, razón social y correo válido; además, el propietario
debe tener definida su condición de persona natural o jurídica y su país,
provincia, cantón y parroquia. En los formularios de propietario se muestra
una lista orientativa de datos pendientes cuando se crean datos fiscales.
La integración todavía no admite emitir facturas para consumidor final.

También se validan localmente la configuración del entorno, los detalles,
impuestos y pagos antes de encolar la factura. Los errores se presentan en
Ventas; hay que corregirlos antes de volver a emitir. La verificación local
no garantiza la aceptación por iConta ni por el SRI, y los reintentos de
documentos con evidencia de envío previo siguen requiriendo conciliación.
La documentación de iConta ofrece ejemplos para [guardar personas](https://apiconta.apidog.io/guardar-persona-27713537e0)
y [generar facturas](https://apiconta.apidog.io/generar-factura-27818612e0),
pero no indica claramente la obligatoriedad de cada campo en esos ejemplos.

## Notificaciones

Los procedimientos clínicos únicamente encolan notificaciones. El envío se procesa fuera del `POST` para que guardar una vacuna/desparasitación no dependa de servicios externos.

Ejecutar manualmente:

```bash
php scripts/process_notifications.php
```

En Linux, configura cron para ejecutar el procesador todos los días a las 18:00
en la misma zona horaria que `APP_TIMEZONE`. Por ejemplo, con cron que admita
`CRON_TZ`, ajusta la ruta y el ejecutable PHP al servidor e instala esta entrada
con `crontab -e`:

```cron
CRON_TZ=America/Guayaquil
0 18 * * * cd /ruta/absoluta/tuhuellavet && /usr/bin/php scripts/process_notifications.php >> storage/logs/notifications-cron.log 2>&1
```

Comprueba que el usuario de cron pueda ejecutar PHP y escribir el archivo de log,
que el servidor SMTP esté configurado y que cron efectivamente ejecute el comando.
Solo después, configura `NOTIFICATIONS_DAILY_CRON_ENABLED=true` en `.env`: se
ocultará el botón manual y se mostrará una estimación del próximo procesamiento.
La hora estimada no garantiza la entrega: el proceso atiende hasta 200 mensajes
pendientes por ejecución, los errores se registran en la cola y WhatsApp no se
procesa automáticamente. Supervisa el log y los estados de las notificaciones.

## Anulación de ventas e inventario

Las anulaciones con productos necesitan el tipo de movimiento
`DEVOLUCION_VENTA` con factor `1` para devolver al inventario las cantidades
y lotes de las salidas originales. En instalaciones existentes, aplicar una
vez [la migración de devolución de ventas](scripts/migrations/20261107_devolucion_venta.sql)
a la base de datos de la aplicación. La migración no cambia un registro existente;
si ya existe con otro factor, debe corregirse la configuración antes de anular.

## Contífico

El proyecto incluye la cola `contifico_documentos`, reglas para impedir facturación real en el entorno Académico y un adaptador `ContificoService`.

Configura:

```env
CONTIFICO_API_URL=
CONTIFICO_API_TOKEN=
```

Procesador:

```bash
php scripts/process_contifico.php 20
```

**Importante:** la emisión real debe terminar de validarse contra el endpoint y credenciales definitivas de la cuenta Contífico del cliente. El proyecto no inventa endpoints de proveedor ni marca una factura como emitida sin esa integración real.

## Google

Configura:

```env
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=http://localhost/tuhuellavet/public/auth/google/callback
```

Por seguridad, Google autentica usuarios existentes/vinculados; no debe convertir automáticamente cualquier cuenta Google en usuario autorizado del sistema.

## Archivos

Los documentos se guardan fuera de rutas públicas directas en `storage/uploads/` y se entregan mediante `MediaController`. No muestres `ruta_storage` al usuario.

## Producción

El DocumentRoot recomendado es `public/`.

```bash
composer install --no-dev --optimize-autoloader
```

Configuración mínima:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.com
```

No subas `.env`, `vendor/`, logs ni documentos clínicos a Git; `.gitignore` ya los excluye.

## Pruebas

La lista de pruebas funcionales e integraciones está en `docs/CHECKLIST_PRUEBAS.md`. La arquitectura está descrita en `docs/ARQUITECTURA.md`.
