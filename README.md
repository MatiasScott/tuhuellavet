# Sistema Veterinario y Ganadero

Base tecnica inicial en PHP MVC para un sistema multiempresa.

## Stack

- PHP 8.1+
- MySQL
- JavaScript
- Bootstrap + CSS modular
- Composer
- Git

## Estructura

- app/controllers
- app/models
- app/views
- app/services
- app/helpers
- app/middlewares
- app/config
- app/core
- public/assets/css
- public/assets/js
- routes
- storage/logs
- storage/cache
- storage/uploads

## Instalacion

1. Instalar dependencias:
   composer install
2. Crear .env desde .env.example
3. Crear base de datos y ejecutar:
   storage/database/schema.sql
4. Configurar Apache apuntando a public/

## Seguridad base implementada

- Hash de contrasenas con password_hash/password_verify
- Sesiones seguras
- Middleware de autenticacion
- Cambio obligatorio de contrasena en primer ingreso
- CSRF token en formularios criticos
- Flujo de recuperacion de contrasena con token unico y expiracion

## Multiempresa

- Seleccion de empresa/sede tras login
- Contexto activo en sesion por empresa_id
- Estructura de tablas preparada para filtrar por empresa_id

## Modulos iniciales preparados

- Auth
- Dashboard
- Animales
- Inventario (modelo de movimientos)
- Auditoria
- Motor de formulas dinamicas sin eval

## Pendientes funcionales recomendados

- Integrar envio real de correo para password reset
- CRUD completo de animales y consultas
- Permisos granulares por modulo/accion
- Notificaciones por correo, WhatsApp e internas
- Exportes PDF/XLSX
- Pruebas automatizadas
