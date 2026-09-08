# Arquitectura de Tu Huella Vet

Aplicación PHP 8.2+ sin framework, organizada con MVC propio y servicios de dominio.

## Capas

- `app/Core`: router, request, sesión, base de datos, vista y controlador base.
- `app/Config`: configuración de aplicación, base de datos e integraciones.
- `app/Routes`: rutas separadas por autenticación, administración, portal cliente y académico.
- `app/Controllers`: coordinación HTTP; no concentra lógica de negocio.
- `app/Models`: consultas de lectura y acceso de datos por módulo.
- `app/Services`: reglas de negocio, transacciones, auditoría e integraciones.
- `app/Middlewares`: autenticación, entorno activo, rol y permisos.
- `app/Views`: UI por módulo y layouts por contexto/rol.
- `public/assets/css`: CSS global/componentes y CSS específico por vista.
- `public/assets/js`: JS global y JS específico por vista.
- `storage`: cache, logs y archivos subidos; no se versionan los contenidos.

## Contextos

- `TU_HUELLA_VET`: entorno clínico productivo.
- `HACIENDA_AGUSBELLA`: entorno de hacienda/productivo.
- `ACADEMICO`: entorno de docentes/estudiantes y simulación, sin facturación real.

## Seguridad

Los botones se ocultan por permisos para UX, pero las rutas están protegidas por middleware. El Super Administrador usa rol global; los demás roles se asignan por entorno. Los permisos se generan desde `modulos` + `modulo_acciones` y pueden sincronizarse mediante `PermissionService`.

## Historial clínico

Los procedimientos se modelan sobre `eventos_clinicos`. Consulta, vacuna, desparasitación, hospitalización, laboratorio y cirugía se integran al timeline del paciente. Los pesos siempre se registran en `animales_pesos`, evitando sobrescribir el histórico.

## Fórmulas

Las fórmulas son versionadas y sus ejecuciones quedan registradas con variables, paciente, evento, resultado, contexto y bandera de simulación. Hospitalización reutiliza el motor para fluidoterapia.

## Integraciones

- Google Identity: preparado mediante variables `GOOGLE_*`.
- Contífico: cola/adaptador preparado; la emisión productiva requiere configurar y validar el endpoint/credenciales del tenant del cliente.
- WhatsApp: worker genérico configurable por `WHATSAPP_API_URL` y token.
- Correo: cola procesable; el envío actual utiliza `mail()` del servidor.
