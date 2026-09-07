# Vet Academic ERP

Sistema veterinario, ganadero y académico en PHP MVC puro.

## Instalación

```bash
git clone <repositorio>
cd vet_academic_project
composer install
cp .env.example .env
```

Configura `.env` e importa `schema.sql`.

## Crear primer Super Administrador

```bash
php scripts/create_super_admin.php admin@dominio.com "PasswordSegura" "Nombre" "Apellido"
```

## Desarrollo local

```bash
php -S localhost:8000 -t public
```

En `.env`:

```env
APP_URL=http://localhost:8000
```

## Producción

El DocumentRoot debe apuntar a `/public`.

```bash
composer install --no-dev --optimize-autoloader
```

No uses rutas absolutas del servidor. Toda URL depende de `APP_URL`.

## Flujo implementado en esta versión

1. Login por correo/contraseña.
2. Login con Google cuando las variables `GOOGLE_*` estén configuradas.
3. Google no autocrea usuarios.
4. Carga de Super Administrador global.
5. Selección/cambio de entorno.
6. Carga de roles y permisos por entorno.
7. Sidebar filtrado por permisos.
8. Dashboard con datos reales.
9. Listado real de propietarios.
10. Listado real de pacientes.
11. CSRF en formularios de autenticación.
12. Rutas portables para subcarpetas o DocumentRoot directo a `/public`.
