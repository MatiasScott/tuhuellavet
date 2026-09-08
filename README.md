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

## Notificaciones

Los procedimientos clínicos únicamente encolan notificaciones. El envío se procesa fuera del `POST` para que guardar una vacuna/desparasitación no dependa de servicios externos.

Ejecutar manualmente:

```bash
php scripts/process_notifications.php 50
```

En producción programa este comando con cron/Task Scheduler.

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
