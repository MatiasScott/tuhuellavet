Actualizacion de requerimientos funcionales y clinicos - 2026-05-26

Objetivo
- Extender la arquitectura actual sin eliminar tablas ni simplificar relaciones.
- Mantener MVC, modularidad, escalabilidad y trazabilidad clinica.

Migracion aplicada
- storage/database/migrations/20260526_02_refinamiento_funcional_clinico.sql

Resumen de extensiones de BD
- Propietarios:
  - propietarios.portal_cliente_activo
- Animales:
  - animal_pesos (historial de peso)
- Consulta externa:
  - consultas.antecedentes
  - consultas.recomendaciones
  - consultas.tratamiento_clinico
  - consultas.tratamiento_casa
  - consulta_examen_general (1:1 con consultas)
- Diagnosticos:
  - catalogo_diagnosticos
  - consulta_diagnosticos
- Vacunas:
  - catalogo_vacunas
  - extension de animal_vacunas con catalogo_vacuna_id, dosis, laboratorio, lote, usuario_id
- Desparasitacion:
  - desparasitaciones
- Hospitalizacion:
  - tamanos_animales
  - hospitalizaciones
  - fluidoterapia
- Laboratorio:
  - examenes_laboratorio
- Cirugias:
  - cirugias
- Timeline clinico:
  - vista vw_historial_clinico_timeline
- Dashboard modular:
  - dashboard_widgets
  - dashboard_widget_roles

Lineamientos de implementacion MVC
- Controllers:
  - Orquestan request/response y autorizacion.
- Services:
  - Reglas de negocio (clinica, formulas, archivos, trazabilidad).
- Models:
  - Acceso a datos por modulo.
- Views:
  - Solo render, sin SQL ni logica de negocio.
- JS/CSS:
  - Por modulo/vista.

Siguiente capa de implementacion recomendada
1. Modulo Propietarios
- Controller: app/controllers/PropietarioController.php
- Service: app/services/PropietarioService.php
- Model: app/models/Propietario.php
- Integrar foto con app/services/FileStorageService.php

2. Modulo Animales
- Controller: app/controllers/AnimalController.php (extender)
- Service: app/services/AnimalService.php
- Model: app/models/Animal.php (extender)
- Registrar cambios de peso en animal_pesos

3. Consulta Externa
- Controller: app/controllers/ConsultaController.php
- Service: app/services/ConsultaService.php
- Persistir consultas + consulta_examen_general en transaccion

4. Diagnosticos
- Service: app/services/DiagnosticoService.php
- Soportar multiples diagnosticos por consulta y usuario_id

5. Vacunas y Desparasitacion
- Services separados por modulo
- Reutilizar consulta_examen_general donde aplique

6. Hospitalizacion y Fluidoterapia
- Service: app/services/HospitalizacionService.php
- Service: app/services/FluidoterapiaService.php

7. Laboratorio y Cirugias
- Integrar archivos PDF via tabla archivos y rutas fisicas
- Relacionar cirugias con formulas cuando exista formula_id

8. Timeline Clinico
- Query base desde vw_historial_clinico_timeline
- Filtros por animal, propietario y fecha

9. Dashboard Modular
- Resolver widgets por contexto y rol desde dashboard_widgets y dashboard_widget_roles

Notas de seguridad
- Mantener validaciones backend de archivos (MIME, extension, tamano, dimensiones).
- Guardar solo rutas/metadata en BD.
- Mantener auditoria en operaciones clinicas criticas.
