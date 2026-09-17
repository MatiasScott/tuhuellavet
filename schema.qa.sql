
-- ============================================================
-- SISTEMA INTEGRAL VETERINARIO, GANADERO Y ACADÉMICO
-- Esquema definitivo MySQL 8 / MariaDB 10.5+
-- Normalización objetivo: 1FN, 2FN, 3FN (y BCNF donde aplica)
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS tuhuellavet_qa
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE tuhuellavet_qa;

-- ============================================================
-- 1. NÚCLEO MULTIEMPRESA / MULTIENTORNO
-- ============================================================

CREATE TABLE empresas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(150) NOT NULL,
    nombre_comercial VARCHAR(150) NULL,
    razon_social VARCHAR(200) NULL,
    identificacion_fiscal VARCHAR(30) NULL,
    email VARCHAR(150) NULL,
    telefono VARCHAR(30) NULL,
    direccion VARCHAR(255) NULL,
    logo_path VARCHAR(500) NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_empresas_identificacion_fiscal (identificacion_fiscal)
) ENGINE=InnoDB;

CREATE TABLE tipos_entorno (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(50) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    descripcion VARCHAR(255) NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tipos_entorno_codigo (codigo),
    UNIQUE KEY uq_tipos_entorno_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE entornos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id BIGINT UNSIGNED NOT NULL,
    tipo_entorno_id SMALLINT UNSIGNED NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    codigo VARCHAR(60) NOT NULL,
    descripcion TEXT NULL,
    logo_path VARCHAR(500) NULL,
    color_primario VARCHAR(20) NULL,
    color_secundario VARCHAR(20) NULL,
    es_productivo BOOLEAN NOT NULL DEFAULT TRUE,
    permite_facturacion_real BOOLEAN NOT NULL DEFAULT FALSE,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_entornos_empresa
        FOREIGN KEY (empresa_id) REFERENCES empresas(id),
    CONSTRAINT fk_entornos_tipo
        FOREIGN KEY (tipo_entorno_id) REFERENCES tipos_entorno(id),
    UNIQUE KEY uq_entornos_empresa_codigo (empresa_id, codigo),
    KEY idx_entornos_empresa (empresa_id),
    KEY idx_entornos_tipo (tipo_entorno_id),
    KEY idx_entornos_activo (activo)
) ENGINE=InnoDB;

CREATE TABLE configuraciones_entorno (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entorno_id BIGINT UNSIGNED NOT NULL,
    clave VARCHAR(100) NOT NULL,
    valor TEXT NULL,
    descripcion VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_configuracion_entorno
        FOREIGN KEY (entorno_id) REFERENCES entornos(id) ON DELETE CASCADE,
    UNIQUE KEY uq_configuracion_entorno_clave (entorno_id, clave)
) ENGINE=InnoDB;

-- ============================================================
-- 2. USUARIOS, AUTENTICACIÓN, ROLES Y PERMISOS
-- ============================================================

CREATE TABLE usuarios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombres VARCHAR(120) NOT NULL,
    apellidos VARCHAR(120) NOT NULL,
    email VARCHAR(180) NOT NULL,
    telefono VARCHAR(30) NULL,
    foto_path VARCHAR(500) NULL,
    password_hash VARCHAR(255) NULL,
    requiere_cambio_password BOOLEAN NOT NULL DEFAULT FALSE,
    password_changed_at DATETIME NULL,
    email_verificado_at DATETIME NULL,
    ultimo_login_at DATETIME NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_email (email),
    KEY idx_usuarios_activo (activo),
    KEY idx_usuarios_nombre (apellidos, nombres)
) ENGINE=InnoDB;

CREATE TABLE proveedores_autenticacion (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(50) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (id),
    UNIQUE KEY uq_proveedores_auth_codigo (codigo)
) ENGINE=InnoDB;

CREATE TABLE usuario_identidades (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NOT NULL,
    proveedor_id SMALLINT UNSIGNED NOT NULL,
    subject_externo VARCHAR(255) NOT NULL,
    email_externo VARCHAR(180) NULL,
    email_verificado BOOLEAN NOT NULL DEFAULT FALSE,
    vinculado_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultimo_login_at DATETIME NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (id),
    CONSTRAINT fk_usuario_identidad_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_usuario_identidad_proveedor
        FOREIGN KEY (proveedor_id) REFERENCES proveedores_autenticacion(id),
    UNIQUE KEY uq_identidad_proveedor_subject (proveedor_id, subject_externo),
    UNIQUE KEY uq_identidad_usuario_proveedor (usuario_id, proveedor_id),
    KEY idx_identidad_usuario (usuario_id)
) ENGINE=InnoDB;

CREATE TABLE password_reset_tokens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    usado_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_password_reset_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    UNIQUE KEY uq_password_reset_token (token_hash),
    KEY idx_password_reset_usuario (usuario_id),
    KEY idx_password_reset_expira (expires_at)
) ENGINE=InnoDB;

CREATE TABLE roles (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(60) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    descripcion VARCHAR(255) NULL,
    es_global BOOLEAN NOT NULL DEFAULT FALSE,
    protegido BOOLEAN NOT NULL DEFAULT FALSE,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_codigo (codigo),
    UNIQUE KEY uq_roles_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE modulos (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(80) NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    descripcion VARCHAR(255) NULL,
    orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_modulos_codigo (codigo)
) ENGINE=InnoDB;

CREATE TABLE acciones_permiso (
    id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(40) NOT NULL,
    nombre VARCHAR(80) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_acciones_permiso_codigo (codigo)
) ENGINE=InnoDB;

CREATE TABLE modulo_acciones (
    modulo_id SMALLINT UNSIGNED NOT NULL,
    accion_id TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (modulo_id, accion_id),
    CONSTRAINT fk_modulo_acciones_modulo
        FOREIGN KEY (modulo_id) REFERENCES modulos(id) ON DELETE CASCADE,
    CONSTRAINT fk_modulo_acciones_accion
        FOREIGN KEY (accion_id) REFERENCES acciones_permiso(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE permisos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    modulo_id SMALLINT UNSIGNED NOT NULL,
    accion_id TINYINT UNSIGNED NOT NULL,
    codigo VARCHAR(150) NOT NULL,
    descripcion VARCHAR(255) NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_permisos_modulo
        FOREIGN KEY (modulo_id) REFERENCES modulos(id) ON DELETE CASCADE,
    CONSTRAINT fk_permisos_accion
        FOREIGN KEY (accion_id) REFERENCES acciones_permiso(id),
    UNIQUE KEY uq_permiso_modulo_accion (modulo_id, accion_id),
    UNIQUE KEY uq_permisos_codigo (codigo)
) ENGINE=InnoDB;

CREATE TABLE rol_permisos (
    rol_id SMALLINT UNSIGNED NOT NULL,
    permiso_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (rol_id, permiso_id),
    CONSTRAINT fk_rol_permisos_rol
        FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_rol_permisos_permiso
        FOREIGN KEY (permiso_id) REFERENCES permisos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE usuarios_entornos (
    usuario_id BIGINT UNSIGNED NOT NULL,
    entorno_id BIGINT UNSIGNED NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    es_predeterminado BOOLEAN NOT NULL DEFAULT FALSE,
    fecha_asignacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, entorno_id),
    CONSTRAINT fk_usuario_entorno_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_usuario_entorno_entorno
        FOREIGN KEY (entorno_id) REFERENCES entornos(id) ON DELETE CASCADE,
    KEY idx_usuario_entorno_entorno (entorno_id)
) ENGINE=InnoDB;

CREATE TABLE usuarios_entornos_roles (
    usuario_id BIGINT UNSIGNED NOT NULL,
    entorno_id BIGINT UNSIGNED NOT NULL,
    rol_id SMALLINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, entorno_id, rol_id),
    CONSTRAINT fk_uer_usuario_entorno
        FOREIGN KEY (usuario_id, entorno_id)
        REFERENCES usuarios_entornos(usuario_id, entorno_id) ON DELETE CASCADE,
    CONSTRAINT fk_uer_rol
        FOREIGN KEY (rol_id) REFERENCES roles(id),
    KEY idx_uer_rol (rol_id)
) ENGINE=InnoDB;

CREATE TABLE usuarios_roles_globales (
    usuario_id BIGINT UNSIGNED NOT NULL,
    rol_id SMALLINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, rol_id),
    CONSTRAINT fk_usuario_rol_global_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_usuario_rol_global_rol
        FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE sesiones_usuario (
    id CHAR(64) NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    creado_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultimo_uso_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expira_at DATETIME NOT NULL,
    revocada_at DATETIME NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_sesion_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    KEY idx_sesiones_usuario (usuario_id),
    KEY idx_sesiones_expira (expira_at)
) ENGINE=InnoDB;

CREATE TABLE intentos_login (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    email_ingresado VARCHAR(180) NULL,
    usuario_id BIGINT UNSIGNED NULL,
    proveedor VARCHAR(50) NOT NULL,
    exitoso BOOLEAN NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    motivo_fallo VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_intento_login_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    KEY idx_intentos_login_email (email_ingresado),
    KEY idx_intentos_login_ip (ip_address),
    KEY idx_intentos_login_fecha (created_at)
) ENGINE=InnoDB;

-- ============================================================
-- 3. IDENTIFICACIÓN Y PROPIETARIOS / CLIENTES
-- ============================================================

CREATE TABLE tipos_identificacion (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(30) NOT NULL,
    nombre VARCHAR(80) NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tipo_identificacion_codigo (codigo)
) ENGINE=InnoDB;

CREATE TABLE propietarios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NULL,
    tipo_identificacion_id SMALLINT UNSIGNED NULL,
    identificacion VARCHAR(30) NULL,
    nombres VARCHAR(120) NOT NULL,
    apellidos VARCHAR(120) NULL,
    email VARCHAR(180) NULL,
    telefono VARCHAR(30) NULL,
    celular VARCHAR(30) NULL,
    direccion VARCHAR(255) NULL,
    foto_path VARCHAR(500) NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_propietario_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_propietario_tipo_identificacion
        FOREIGN KEY (tipo_identificacion_id) REFERENCES tipos_identificacion(id) ON DELETE SET NULL,
    UNIQUE KEY uq_propietario_usuario (usuario_id),
    KEY idx_propietario_identificacion (identificacion),
    KEY idx_propietario_email (email),
    KEY idx_propietario_nombre (apellidos, nombres)
) ENGINE=InnoDB;

CREATE TABLE propietarios_entornos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    propietario_id BIGINT UNSIGNED NOT NULL,
    entorno_id BIGINT UNSIGNED NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_propietario_entorno_propietario
        FOREIGN KEY (propietario_id) REFERENCES propietarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_propietario_entorno_entorno
        FOREIGN KEY (entorno_id) REFERENCES entornos(id) ON DELETE CASCADE,
    UNIQUE KEY uq_propietario_entorno (propietario_id, entorno_id),
    UNIQUE KEY uq_propietario_entorno_id_entorno (id, entorno_id),
    KEY idx_propietario_entorno_entorno (entorno_id)
) ENGINE=InnoDB;

CREATE TABLE propietarios_datos_fiscales (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    propietario_id BIGINT UNSIGNED NOT NULL,
    tipo_identificacion_id SMALLINT UNSIGNED NOT NULL,
    identificacion VARCHAR(30) NOT NULL,
    razon_social VARCHAR(200) NOT NULL,
    direccion VARCHAR(255) NULL,
    email VARCHAR(180) NULL,
    telefono VARCHAR(30) NULL,
    es_principal BOOLEAN NOT NULL DEFAULT TRUE,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_prop_fiscal_propietario
        FOREIGN KEY (propietario_id) REFERENCES propietarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_prop_fiscal_tipo_identificacion
        FOREIGN KEY (tipo_identificacion_id) REFERENCES tipos_identificacion(id),
    UNIQUE KEY uq_prop_fiscal_identificacion (propietario_id, identificacion)
) ENGINE=InnoDB;

-- ============================================================
-- 4. CATÁLOGOS ANIMALES Y PACIENTES
-- ============================================================

CREATE TABLE categorias_animales (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(60) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    descripcion VARCHAR(255) NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categoria_animal_codigo (codigo),
    UNIQUE KEY uq_categoria_animal_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE especies (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    categoria_id SMALLINT UNSIGNED NOT NULL,
    codigo VARCHAR(60) NOT NULL,
    nombre_comun VARCHAR(100) NOT NULL,
    nombre_cientifico VARCHAR(150) NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_especie_categoria
        FOREIGN KEY (categoria_id) REFERENCES categorias_animales(id),
    UNIQUE KEY uq_especies_codigo (codigo),
    UNIQUE KEY uq_especies_nombre_categoria (categoria_id, nombre_comun)
) ENGINE=InnoDB;

CREATE TABLE razas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    especie_id SMALLINT UNSIGNED NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    descripcion VARCHAR(255) NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_raza_especie
        FOREIGN KEY (especie_id) REFERENCES especies(id),
    UNIQUE KEY uq_raza_especie_nombre (especie_id, nombre),
    UNIQUE KEY uq_raza_id_especie (id, especie_id),
    KEY idx_raza_especie (especie_id)
) ENGINE=InnoDB;

CREATE TABLE sexos_animales (
    id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(30) NOT NULL,
    nombre VARCHAR(50) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_sexo_animal_codigo (codigo)
) ENGINE=InnoDB;

CREATE TABLE animales (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entorno_id BIGINT UNSIGNED NOT NULL,
    propietario_entorno_id BIGINT UNSIGNED NULL,
    especie_id SMALLINT UNSIGNED NOT NULL,
    raza_id BIGINT UNSIGNED NULL,
    sexo_id TINYINT UNSIGNED NULL,
    codigo VARCHAR(80) NULL,
    nombre VARCHAR(150) NULL,
    fecha_nacimiento DATE NULL,
    fecha_nacimiento_aproximada BOOLEAN NOT NULL DEFAULT FALSE,
    color VARCHAR(120) NULL,
    microchip VARCHAR(100) NULL,
    arete VARCHAR(100) NULL,
    foto_principal_path VARCHAR(500) NULL,
    observaciones TEXT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_animal_entorno
        FOREIGN KEY (entorno_id) REFERENCES entornos(id),
    CONSTRAINT fk_animal_propietario_entorno
        FOREIGN KEY (propietario_entorno_id, entorno_id)
        REFERENCES propietarios_entornos(id, entorno_id),
    CONSTRAINT fk_animal_especie
        FOREIGN KEY (especie_id) REFERENCES especies(id),
    CONSTRAINT fk_animal_raza_especie
        FOREIGN KEY (raza_id, especie_id) REFERENCES razas(id, especie_id),
    CONSTRAINT fk_animal_sexo
        FOREIGN KEY (sexo_id) REFERENCES sexos_animales(id),
    UNIQUE KEY uq_animal_entorno_codigo (entorno_id, codigo),
    UNIQUE KEY uq_animal_id_entorno (id, entorno_id),
    KEY idx_animal_entorno (entorno_id),
    KEY idx_animal_propietario_entorno (propietario_entorno_id),
    KEY idx_animal_especie (especie_id),
    KEY idx_animal_raza (raza_id),
    KEY idx_animal_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE animales_pesos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    animal_id BIGINT UNSIGNED NOT NULL,
    peso_kg DECIMAL(10,3) NOT NULL,
    registrado_por BIGINT UNSIGNED NOT NULL,
    origen VARCHAR(50) NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    observacion VARCHAR(255) NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_animal_peso_animal
        FOREIGN KEY (animal_id) REFERENCES animales(id) ON DELETE CASCADE,
    CONSTRAINT fk_animal_peso_usuario
        FOREIGN KEY (registrado_por) REFERENCES usuarios(id),
    KEY idx_animal_peso_animal_fecha (animal_id, fecha_registro)
) ENGINE=InnoDB;

-- ============================================================
-- 5. UNIDADES Y CATÁLOGOS CLÍNICOS GENERALES
-- ============================================================

CREATE TABLE unidades_medida (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(30) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    simbolo VARCHAR(30) NOT NULL,
    categoria VARCHAR(60) NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (id),
    UNIQUE KEY uq_unidad_codigo (codigo),
    UNIQUE KEY uq_unidad_simbolo_categoria (simbolo, categoria)
) ENGINE=InnoDB;

CREATE TABLE unidades_tiempo (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(30) NOT NULL,
    nombre VARCHAR(80) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_unidad_tiempo_codigo (codigo)
) ENGINE=InnoDB;

CREATE TABLE tipos_evento_clinico (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(60) NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    descripcion VARCHAR(255) NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tipo_evento_codigo (codigo)
) ENGINE=InnoDB;

CREATE TABLE eventos_clinicos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    animal_id BIGINT UNSIGNED NOT NULL,
    tipo_evento_id SMALLINT UNSIGNED NOT NULL,
    responsable_id BIGINT UNSIGNED NOT NULL,
    fecha_evento DATETIME NOT NULL,
    titulo VARCHAR(200) NULL,
    observaciones TEXT NULL,
    anulado_at DATETIME NULL,
    anulado_por BIGINT UNSIGNED NULL,
    motivo_anulacion TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_evento_animal
        FOREIGN KEY (animal_id) REFERENCES animales(id),
    CONSTRAINT fk_evento_tipo
        FOREIGN KEY (tipo_evento_id) REFERENCES tipos_evento_clinico(id),
    CONSTRAINT fk_evento_responsable
        FOREIGN KEY (responsable_id) REFERENCES usuarios(id),
    KEY idx_evento_animal_fecha (animal_id, fecha_evento),
    KEY idx_evento_tipo (tipo_evento_id),
    KEY idx_evento_responsable (responsable_id)
) ENGINE=InnoDB;

-- ============================================================
-- 6. CONSULTA EXTERNA Y DIAGNÓSTICOS
-- ============================================================

CREATE TABLE consultas_externas (
    evento_clinico_id BIGINT UNSIGNED NOT NULL,
    motivo_consulta TEXT NULL,
    anamnesis TEXT NULL,
    antecedentes TEXT NULL,
    recomendaciones TEXT NULL,
    PRIMARY KEY (evento_clinico_id),
    CONSTRAINT fk_consulta_evento
        FOREIGN KEY (evento_clinico_id) REFERENCES eventos_clinicos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE examenes_clinicos_generales (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    evento_clinico_id BIGINT UNSIGNED NOT NULL,
    alimentacion TEXT NULL,
    historial_reproductivo TEXT NULL,
    frecuencia_cardiaca DECIMAL(8,2) NULL,
    frecuencia_respiratoria DECIMAL(8,2) NULL,
    temperatura_c DECIMAL(5,2) NULL,
    tiempo_llenado_capilar_seg DECIMAL(5,2) NULL,
    ganglios_linfaticos TEXT NULL,
    condicion_corporal VARCHAR(100) NULL,
    vomitos BOOLEAN NULL,
    diarrea BOOLEAN NULL,
    tos BOOLEAN NULL,
    observaciones TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_examen_general_evento
        FOREIGN KEY (evento_clinico_id) REFERENCES eventos_clinicos(id) ON DELETE CASCADE,
    UNIQUE KEY uq_examen_general_evento (evento_clinico_id)
) ENGINE=InnoDB;

CREATE TABLE tipos_diagnostico (
    id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(40) NOT NULL,
    nombre VARCHAR(80) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tipo_diagnostico_codigo (codigo)
) ENGINE=InnoDB;

CREATE TABLE diagnosticos_clinicos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    evento_clinico_id BIGINT UNSIGNED NOT NULL,
    tipo_diagnostico_id TINYINT UNSIGNED NOT NULL,
    descripcion TEXT NOT NULL,
    ingresado_por BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_diagnostico_evento
        FOREIGN KEY (evento_clinico_id) REFERENCES eventos_clinicos(id) ON DELETE CASCADE,
    CONSTRAINT fk_diagnostico_tipo
        FOREIGN KEY (tipo_diagnostico_id) REFERENCES tipos_diagnostico(id),
    CONSTRAINT fk_diagnostico_usuario
        FOREIGN KEY (ingresado_por) REFERENCES usuarios(id),
    KEY idx_diagnostico_evento (evento_clinico_id),
    KEY idx_diagnostico_tipo (tipo_diagnostico_id)
) ENGINE=InnoDB;

-- ============================================================
-- 7. FARMACOLOGÍA BASE, LABORATORIOS, VACUNACIÓN Y DESPARASITACIÓN
-- ============================================================

CREATE TABLE laboratorios_farmaceuticos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(150) NOT NULL,
    pais VARCHAR(100) NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_laboratorio_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE vacunas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(150) NOT NULL,
    descripcion VARCHAR(255) NULL,
    laboratorio_id BIGINT UNSIGNED NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_vacuna_laboratorio
        FOREIGN KEY (laboratorio_id) REFERENCES laboratorios_farmaceuticos(id) ON DELETE SET NULL,
    KEY idx_vacuna_laboratorio (laboratorio_id)
) ENGINE=InnoDB;

CREATE TABLE vacunaciones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    evento_clinico_id BIGINT UNSIGNED NOT NULL,
    vacuna_id BIGINT UNSIGNED NOT NULL,
    dosis DECIMAL(12,4) NULL,
    unidad_dosis_id SMALLINT UNSIGNED NULL,
    lote VARCHAR(100) NULL,
    fecha_revacunacion DATE NULL,
    observaciones TEXT NULL,
    aplicada_por BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_vacunacion_evento
        FOREIGN KEY (evento_clinico_id) REFERENCES eventos_clinicos(id) ON DELETE CASCADE,
    CONSTRAINT fk_vacunacion_vacuna
        FOREIGN KEY (vacuna_id) REFERENCES vacunas(id),
    CONSTRAINT fk_vacunacion_unidad
        FOREIGN KEY (unidad_dosis_id) REFERENCES unidades_medida(id) ON DELETE SET NULL,
    CONSTRAINT fk_vacunacion_usuario
        FOREIGN KEY (aplicada_por) REFERENCES usuarios(id),
    KEY idx_vacunacion_evento (evento_clinico_id),
    KEY idx_vacunacion_vacuna (vacuna_id),
    KEY idx_vacunacion_revacunacion (fecha_revacunacion)
) ENGINE=InnoDB;

CREATE TABLE farmacos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(150) NOT NULL,
    laboratorio_id BIGINT UNSIGNED NULL,
    descripcion TEXT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_farmaco_laboratorio
        FOREIGN KEY (laboratorio_id) REFERENCES laboratorios_farmaceuticos(id) ON DELETE SET NULL,
    KEY idx_farmaco_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE desparasitaciones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    evento_clinico_id BIGINT UNSIGNED NOT NULL,
    farmaco_id BIGINT UNSIGNED NOT NULL,
    dosis DECIMAL(12,4) NULL,
    unidad_dosis_id SMALLINT UNSIGNED NULL,
    proxima_desparasitacion DATE NULL,
    observaciones TEXT NULL,
    aplicada_por BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_desparasitacion_evento
        FOREIGN KEY (evento_clinico_id) REFERENCES eventos_clinicos(id) ON DELETE CASCADE,
    CONSTRAINT fk_desparasitacion_farmaco
        FOREIGN KEY (farmaco_id) REFERENCES farmacos(id),
    CONSTRAINT fk_desparasitacion_unidad
        FOREIGN KEY (unidad_dosis_id) REFERENCES unidades_medida(id) ON DELETE SET NULL,
    CONSTRAINT fk_desparasitacion_usuario
        FOREIGN KEY (aplicada_por) REFERENCES usuarios(id),
    KEY idx_desparasitacion_evento (evento_clinico_id),
    KEY idx_desparasitacion_proxima (proxima_desparasitacion)
) ENGINE=InnoDB;

-- ============================================================
-- 8. PRESENTACIONES FARMACÉUTICAS
-- ============================================================

CREATE TABLE formas_farmaceuticas (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(50) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (id),
    UNIQUE KEY uq_forma_farmaceutica_codigo (codigo),
    UNIQUE KEY uq_forma_farmaceutica_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE vias_administracion (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(50) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (id),
    UNIQUE KEY uq_via_administracion_codigo (codigo)
) ENGINE=InnoDB;

CREATE TABLE principios_activos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(180) NOT NULL,
    descripcion VARCHAR(255) NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (id),
    UNIQUE KEY uq_principio_activo_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE farmaco_presentaciones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    farmaco_id BIGINT UNSIGNED NOT NULL,
    forma_farmaceutica_id SMALLINT UNSIGNED NOT NULL,
    nombre_comercial VARCHAR(180) NULL,
    laboratorio_id BIGINT UNSIGNED NULL,
    contenido_cantidad DECIMAL(14,4) NULL,
    contenido_unidad_id SMALLINT UNSIGNED NULL,
    descripcion VARCHAR(255) NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_presentacion_farmaco
        FOREIGN KEY (farmaco_id) REFERENCES farmacos(id),
    CONSTRAINT fk_presentacion_forma
        FOREIGN KEY (forma_farmaceutica_id) REFERENCES formas_farmaceuticas(id),
    CONSTRAINT fk_presentacion_laboratorio
        FOREIGN KEY (laboratorio_id) REFERENCES laboratorios_farmaceuticos(id) ON DELETE SET NULL,
    CONSTRAINT fk_presentacion_unidad
        FOREIGN KEY (contenido_unidad_id) REFERENCES unidades_medida(id) ON DELETE SET NULL,
    KEY idx_presentacion_farmaco (farmaco_id),
    KEY idx_presentacion_laboratorio (laboratorio_id)
) ENGINE=InnoDB;

CREATE TABLE farmaco_presentacion_componentes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    presentacion_id BIGINT UNSIGNED NOT NULL,
    principio_activo_id BIGINT UNSIGNED NOT NULL,
    cantidad DECIMAL(18,6) NOT NULL,
    unidad_cantidad_id SMALLINT UNSIGNED NOT NULL,
    por_cantidad DECIMAL(18,6) NULL,
    por_unidad_id SMALLINT UNSIGNED NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_componente_presentacion
        FOREIGN KEY (presentacion_id) REFERENCES farmaco_presentaciones(id) ON DELETE CASCADE,
    CONSTRAINT fk_componente_principio_activo
        FOREIGN KEY (principio_activo_id) REFERENCES principios_activos(id),
    CONSTRAINT fk_componente_unidad_cantidad
        FOREIGN KEY (unidad_cantidad_id) REFERENCES unidades_medida(id),
    CONSTRAINT fk_componente_unidad_por
        FOREIGN KEY (por_unidad_id) REFERENCES unidades_medida(id) ON DELETE SET NULL,
    UNIQUE KEY uq_presentacion_principio (presentacion_id, principio_activo_id),
    KEY idx_componente_presentacion (presentacion_id)
) ENGINE=InnoDB;

CREATE TABLE farmaco_presentacion_vias (
    presentacion_id BIGINT UNSIGNED NOT NULL,
    via_administracion_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (presentacion_id, via_administracion_id),
    CONSTRAINT fk_presentacion_via_presentacion
        FOREIGN KEY (presentacion_id) REFERENCES farmaco_presentaciones(id) ON DELETE CASCADE,
    CONSTRAINT fk_presentacion_via_via
        FOREIGN KEY (via_administracion_id) REFERENCES vias_administracion(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 9. MOTOR DE FÓRMULAS
-- ============================================================

CREATE TABLE categorias_formula (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(60) NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    descripcion VARCHAR(255) NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categoria_formula_codigo (codigo)
) ENGINE=InnoDB;

CREATE TABLE formulas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    categoria_formula_id SMALLINT UNSIGNED NOT NULL,
    codigo VARCHAR(80) NOT NULL,
    nombre VARCHAR(180) NOT NULL,
    descripcion TEXT NULL,
    unidad_resultado_id SMALLINT UNSIGNED NULL,
    creada_por BIGINT UNSIGNED NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_formula_categoria
        FOREIGN KEY (categoria_formula_id) REFERENCES categorias_formula(id),
    CONSTRAINT fk_formula_unidad_resultado
        FOREIGN KEY (unidad_resultado_id) REFERENCES unidades_medida(id) ON DELETE SET NULL,
    CONSTRAINT fk_formula_creador
        FOREIGN KEY (creada_por) REFERENCES usuarios(id),
    UNIQUE KEY uq_formula_codigo (codigo),
    KEY idx_formula_categoria (categoria_formula_id)
) ENGINE=InnoDB;

CREATE TABLE formula_entornos (
    formula_id BIGINT UNSIGNED NOT NULL,
    entorno_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (formula_id, entorno_id),
    CONSTRAINT fk_formula_entorno_formula
        FOREIGN KEY (formula_id) REFERENCES formulas(id) ON DELETE CASCADE,
    CONSTRAINT fk_formula_entorno_entorno
        FOREIGN KEY (entorno_id) REFERENCES entornos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE estados_formula_version (
    id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(30) NOT NULL,
    nombre VARCHAR(80) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_estado_formula_version_codigo (codigo)
) ENGINE=InnoDB;

CREATE TABLE formula_versiones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    formula_id BIGINT UNSIGNED NOT NULL,
    numero_version INT UNSIGNED NOT NULL,
    expresion TEXT NOT NULL,
    notas_version TEXT NULL,
    estado_id TINYINT UNSIGNED NOT NULL,
    creada_por BIGINT UNSIGNED NOT NULL,
    publicada_por BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    publicada_at DATETIME NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_formula_version_formula
        FOREIGN KEY (formula_id) REFERENCES formulas(id) ON DELETE CASCADE,
    CONSTRAINT fk_formula_version_estado
        FOREIGN KEY (estado_id) REFERENCES estados_formula_version(id),
    CONSTRAINT fk_formula_version_creador
        FOREIGN KEY (creada_por) REFERENCES usuarios(id),
    CONSTRAINT fk_formula_version_publicador
        FOREIGN KEY (publicada_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    UNIQUE KEY uq_formula_version (formula_id, numero_version),
    KEY idx_formula_version_estado (estado_id)
) ENGINE=InnoDB;

CREATE TABLE tipos_variable_formula (
    id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(40) NOT NULL,
    nombre VARCHAR(80) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tipo_variable_formula_codigo (codigo)
) ENGINE=InnoDB;

CREATE TABLE origenes_variable_formula (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(60) NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    descripcion VARCHAR(255) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_origen_variable_codigo (codigo)
) ENGINE=InnoDB;

CREATE TABLE formula_variables (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    formula_version_id BIGINT UNSIGNED NOT NULL,
    tipo_variable_id TINYINT UNSIGNED NOT NULL,
    origen_variable_id SMALLINT UNSIGNED NOT NULL,
    unidad_medida_id SMALLINT UNSIGNED NULL,
    codigo VARCHAR(80) NOT NULL,
    etiqueta VARCHAR(150) NOT NULL,
    descripcion VARCHAR(255) NULL,
    obligatorio BOOLEAN NOT NULL DEFAULT TRUE,
    valor_minimo DECIMAL(18,6) NULL,
    valor_maximo DECIMAL(18,6) NULL,
    valor_default DECIMAL(18,6) NULL,
    orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    CONSTRAINT fk_formula_variable_version
        FOREIGN KEY (formula_version_id) REFERENCES formula_versiones(id) ON DELETE CASCADE,
    CONSTRAINT fk_formula_variable_tipo
        FOREIGN KEY (tipo_variable_id) REFERENCES tipos_variable_formula(id),
    CONSTRAINT fk_formula_variable_origen
        FOREIGN KEY (origen_variable_id) REFERENCES origenes_variable_formula(id),
    CONSTRAINT fk_formula_variable_unidad
        FOREIGN KEY (unidad_medida_id) REFERENCES unidades_medida(id) ON DELETE SET NULL,
    UNIQUE KEY uq_formula_variable_codigo (formula_version_id, codigo),
    UNIQUE KEY uq_formula_variable_id_version (id, formula_version_id),
    KEY idx_formula_variable_version (formula_version_id)
) ENGINE=InnoDB;

CREATE TABLE formula_especies (
    formula_id BIGINT UNSIGNED NOT NULL,
    especie_id SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (formula_id, especie_id),
    CONSTRAINT fk_formula_especie_formula
        FOREIGN KEY (formula_id) REFERENCES formulas(id) ON DELETE CASCADE,
    CONSTRAINT fk_formula_especie_especie
        FOREIGN KEY (especie_id) REFERENCES especies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE farmaco_formulas (
    farmaco_id BIGINT UNSIGNED NOT NULL,
    formula_id BIGINT UNSIGNED NOT NULL,
    es_predeterminada BOOLEAN NOT NULL DEFAULT FALSE,
    PRIMARY KEY (farmaco_id, formula_id),
    CONSTRAINT fk_farmaco_formula_farmaco
        FOREIGN KEY (farmaco_id) REFERENCES farmacos(id) ON DELETE CASCADE,
    CONSTRAINT fk_farmaco_formula_formula
        FOREIGN KEY (formula_id) REFERENCES formulas(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE formula_ejecuciones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    formula_version_id BIGINT UNSIGNED NOT NULL,
    animal_id BIGINT UNSIGNED NULL,
    evento_clinico_id BIGINT UNSIGNED NULL,
    ejecutado_por BIGINT UNSIGNED NOT NULL,
    contexto VARCHAR(50) NOT NULL,
    resultado DECIMAL(18,6) NOT NULL,
    unidad_resultado_id SMALLINT UNSIGNED NULL,
    es_simulacion BOOLEAN NOT NULL DEFAULT FALSE,
    fecha_ejecucion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    observaciones TEXT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_formula_ejecucion_version
        FOREIGN KEY (formula_version_id) REFERENCES formula_versiones(id),
    CONSTRAINT fk_formula_ejecucion_animal
        FOREIGN KEY (animal_id) REFERENCES animales(id) ON DELETE SET NULL,
    CONSTRAINT fk_formula_ejecucion_evento
        FOREIGN KEY (evento_clinico_id) REFERENCES eventos_clinicos(id) ON DELETE SET NULL,
    CONSTRAINT fk_formula_ejecucion_usuario
        FOREIGN KEY (ejecutado_por) REFERENCES usuarios(id),
    CONSTRAINT fk_formula_ejecucion_unidad
        FOREIGN KEY (unidad_resultado_id) REFERENCES unidades_medida(id) ON DELETE SET NULL,
    UNIQUE KEY uq_formula_ejecucion_id_version (id, formula_version_id),
    KEY idx_formula_ejecucion_version (formula_version_id),
    KEY idx_formula_ejecucion_animal (animal_id),
    KEY idx_formula_ejecucion_evento (evento_clinico_id),
    KEY idx_formula_ejecucion_fecha (fecha_ejecucion)
) ENGINE=InnoDB;

CREATE TABLE formula_ejecucion_valores (
    formula_ejecucion_id BIGINT UNSIGNED NOT NULL,
    formula_variable_id BIGINT UNSIGNED NOT NULL,
    formula_version_id BIGINT UNSIGNED NOT NULL,
    valor DECIMAL(18,6) NOT NULL,
    fue_automatico BOOLEAN NOT NULL DEFAULT FALSE,
    PRIMARY KEY (formula_ejecucion_id, formula_variable_id),
    CONSTRAINT fk_formula_valor_ejecucion_version
        FOREIGN KEY (formula_ejecucion_id, formula_version_id)
        REFERENCES formula_ejecuciones(id, formula_version_id) ON DELETE CASCADE,
    CONSTRAINT fk_formula_valor_variable_version
        FOREIGN KEY (formula_variable_id, formula_version_id)
        REFERENCES formula_variables(id, formula_version_id)
) ENGINE=InnoDB;

-- ============================================================
-- 10. HOSPITALIZACIÓN Y FLUIDOTERAPIA
-- ============================================================

CREATE TABLE estados_hospitalizacion (
    id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(40) NOT NULL,
    nombre VARCHAR(80) NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (id),
    UNIQUE KEY uq_estado_hospitalizacion_codigo (codigo)
) ENGINE=InnoDB;

CREATE TABLE categorias_mantenimiento_fluido (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    especie_id SMALLINT UNSIGNED NULL,
    codigo VARCHAR(60) NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    descripcion VARCHAR(255) NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (id),
    CONSTRAINT fk_categoria_mantenimiento_especie
        FOREIGN KEY (especie_id) REFERENCES especies(id) ON DELETE SET NULL,
    UNIQUE KEY uq_categoria_mantenimiento_codigo (codigo)
) ENGINE=InnoDB;

CREATE TABLE hospitalizaciones (
    evento_clinico_id BIGINT UNSIGNED NOT NULL,
    estado_hospitalizacion_id TINYINT UNSIGNED NOT NULL,
    fecha_ingreso DATETIME NOT NULL,
    fecha_salida DATETIME NULL,
    motivo_ingreso TEXT NULL,
    impresion_clinica_ingreso TEXT NULL,
    indicaciones_generales TEXT NULL,
    observaciones_alta TEXT NULL,
    responsable_alta_id BIGINT UNSIGNED NULL,
    PRIMARY KEY (evento_clinico_id),
    CONSTRAINT fk_hospitalizacion_evento
        FOREIGN KEY (evento_clinico_id) REFERENCES eventos_clinicos(id) ON DELETE CASCADE,
    CONSTRAINT fk_hospitalizacion_estado
        FOREIGN KEY (estado_hospitalizacion_id) REFERENCES estados_hospitalizacion(id),
    CONSTRAINT fk_hospitalizacion_responsable_alta
        FOREIGN KEY (responsable_alta_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    KEY idx_hospitalizacion_estado (estado_hospitalizacion_id),
    KEY idx_hospitalizacion_fecha_ingreso (fecha_ingreso)
) ENGINE=InnoDB;

CREATE TABLE hospitalizacion_evoluciones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    hospitalizacion_evento_id BIGINT UNSIGNED NOT NULL,
    registrado_por BIGINT UNSIGNED NOT NULL,
    fecha_hora DATETIME NOT NULL,
    evolucion TEXT NOT NULL,
    observaciones TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_hosp_evolucion_hospitalizacion
        FOREIGN KEY (hospitalizacion_evento_id)
        REFERENCES hospitalizaciones(evento_clinico_id) ON DELETE CASCADE,
    CONSTRAINT fk_hosp_evolucion_usuario
        FOREIGN KEY (registrado_por) REFERENCES usuarios(id),
    KEY idx_hosp_evolucion_fecha (hospitalizacion_evento_id, fecha_hora)
) ENGINE=InnoDB;

CREATE TABLE hospitalizacion_signos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    hospitalizacion_evento_id BIGINT UNSIGNED NOT NULL,
    registrado_por BIGINT UNSIGNED NOT NULL,
    fecha_hora DATETIME NOT NULL,
    temperatura_c DECIMAL(5,2) NULL,
    frecuencia_cardiaca DECIMAL(8,2) NULL,
    frecuencia_respiratoria DECIMAL(8,2) NULL,
    tiempo_llenado_capilar_seg DECIMAL(5,2) NULL,
    condicion_corporal VARCHAR(100) NULL,
    nivel_dolor VARCHAR(100) NULL,
    apetito VARCHAR(100) NULL,
    hidratacion VARCHAR(100) NULL,
    vomitos BOOLEAN NULL,
    diarrea BOOLEAN NULL,
    tos BOOLEAN NULL,
    observaciones TEXT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_hosp_signos_hospitalizacion
        FOREIGN KEY (hospitalizacion_evento_id)
        REFERENCES hospitalizaciones(evento_clinico_id) ON DELETE CASCADE,
    CONSTRAINT fk_hosp_signos_usuario
        FOREIGN KEY (registrado_por) REFERENCES usuarios(id),
    KEY idx_hosp_signos_fecha (hospitalizacion_evento_id, fecha_hora)
) ENGINE=InnoDB;

CREATE TABLE hospitalizacion_fluidoterapias (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    hospitalizacion_evento_id BIGINT UNSIGNED NOT NULL,
    categoria_mantenimiento_id SMALLINT UNSIGNED NULL,
    registrado_por BIGINT UNSIGNED NOT NULL,
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME NULL,
    mantenimiento_ml DECIMAL(12,3) NULL,
    rehidratacion_ml DECIMAL(12,3) NULL,
    porcentaje_deshidratacion DECIMAL(5,2) NULL,
    volumen_total_ml DECIMAL(12,3) NULL,
    velocidad_ml_hora DECIMAL(12,3) NULL,
    formula_id BIGINT UNSIGNED NULL,
    formula_ejecucion_id BIGINT UNSIGNED NULL,
    observaciones TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_fluidoterapia_hospitalizacion
        FOREIGN KEY (hospitalizacion_evento_id)
        REFERENCES hospitalizaciones(evento_clinico_id) ON DELETE CASCADE,
    CONSTRAINT fk_fluidoterapia_categoria
        FOREIGN KEY (categoria_mantenimiento_id)
        REFERENCES categorias_mantenimiento_fluido(id) ON DELETE SET NULL,
    CONSTRAINT fk_fluidoterapia_usuario
        FOREIGN KEY (registrado_por) REFERENCES usuarios(id),
    CONSTRAINT fk_fluidoterapia_formula
        FOREIGN KEY (formula_id) REFERENCES formulas(id) ON DELETE SET NULL,
    CONSTRAINT fk_fluidoterapia_formula_ejecucion
        FOREIGN KEY (formula_ejecucion_id) REFERENCES formula_ejecuciones(id) ON DELETE SET NULL,
    KEY idx_fluidoterapia_hospitalizacion (hospitalizacion_evento_id)
) ENGINE=InnoDB;

-- ============================================================
-- 11. ARCHIVOS Y LABORATORIO CLÍNICO
-- ============================================================

CREATE TABLE archivos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre_original VARCHAR(255) NOT NULL,
    nombre_almacenado VARCHAR(255) NOT NULL,
    ruta_storage VARCHAR(500) NOT NULL,
    extension VARCHAR(20) NULL,
    mime_type VARCHAR(150) NOT NULL,
    tamano_bytes BIGINT UNSIGNED NOT NULL,
    hash_sha256 CHAR(64) NULL,
    subido_por BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_archivo_usuario
        FOREIGN KEY (subido_por) REFERENCES usuarios(id),
    UNIQUE KEY uq_archivo_nombre_almacenado (nombre_almacenado),
    KEY idx_archivo_hash (hash_sha256)
) ENGINE=InnoDB;

CREATE TABLE tipos_examen_laboratorio (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(150) NOT NULL,
    descripcion VARCHAR(255) NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tipo_examen_laboratorio_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE examenes_laboratorio (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    evento_clinico_id BIGINT UNSIGNED NOT NULL,
    tipo_examen_id BIGINT UNSIGNED NOT NULL,
    solicitado_por BIGINT UNSIGNED NOT NULL,
    fecha_solicitud DATETIME NOT NULL,
    fecha_resultado DATETIME NULL,
    resultado_resumen TEXT NULL,
    observaciones TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_examen_lab_evento
        FOREIGN KEY (evento_clinico_id) REFERENCES eventos_clinicos(id) ON DELETE CASCADE,
    CONSTRAINT fk_examen_lab_tipo
        FOREIGN KEY (tipo_examen_id) REFERENCES tipos_examen_laboratorio(id),
    CONSTRAINT fk_examen_lab_usuario
        FOREIGN KEY (solicitado_por) REFERENCES usuarios(id),
    KEY idx_examen_lab_evento (evento_clinico_id),
    KEY idx_examen_lab_tipo (tipo_examen_id)
) ENGINE=InnoDB;

CREATE TABLE examen_laboratorio_archivos (
    examen_laboratorio_id BIGINT UNSIGNED NOT NULL,
    archivo_id BIGINT UNSIGNED NOT NULL,
    descripcion VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (examen_laboratorio_id, archivo_id),
    CONSTRAINT fk_examen_archivo_examen
        FOREIGN KEY (examen_laboratorio_id) REFERENCES examenes_laboratorio(id) ON DELETE CASCADE,
    CONSTRAINT fk_examen_archivo_archivo
        FOREIGN KEY (archivo_id) REFERENCES archivos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE animal_archivos (
    animal_id BIGINT UNSIGNED NOT NULL,
    archivo_id BIGINT UNSIGNED NOT NULL,
    tipo_documento VARCHAR(100) NULL,
    descripcion VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (animal_id, archivo_id),
    CONSTRAINT fk_animal_archivo_animal
        FOREIGN KEY (animal_id) REFERENCES animales(id) ON DELETE CASCADE,
    CONSTRAINT fk_animal_archivo_archivo
        FOREIGN KEY (archivo_id) REFERENCES archivos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 12. CIRUGÍAS Y ANESTESIA
-- ============================================================

CREATE TABLE procedimientos_quirurgicos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(180) NOT NULL,
    descripcion TEXT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_procedimiento_quirurgico_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE funciones_equipo_quirurgico (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(50) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (id),
    UNIQUE KEY uq_funcion_equipo_quirurgico_codigo (codigo)
) ENGINE=InnoDB;

CREATE TABLE tipos_anestesia (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(50) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tipo_anestesia_codigo (codigo)
) ENGINE=InnoDB;

CREATE TABLE cirugias (
    evento_clinico_id BIGINT UNSIGNED NOT NULL,
    procedimiento_quirurgico_id BIGINT UNSIGNED NOT NULL,
    medico_responsable_id BIGINT UNSIGNED NOT NULL,
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME NULL,
    diagnostico_preoperatorio TEXT NULL,
    descripcion_procedimiento TEXT NULL,
    hallazgos TEXT NULL,
    complicaciones TEXT NULL,
    indicaciones_postoperatorias TEXT NULL,
    observaciones TEXT NULL,
    PRIMARY KEY (evento_clinico_id),
    CONSTRAINT fk_cirugia_evento
        FOREIGN KEY (evento_clinico_id) REFERENCES eventos_clinicos(id) ON DELETE CASCADE,
    CONSTRAINT fk_cirugia_procedimiento
        FOREIGN KEY (procedimiento_quirurgico_id) REFERENCES procedimientos_quirurgicos(id),
    CONSTRAINT fk_cirugia_medico
        FOREIGN KEY (medico_responsable_id) REFERENCES usuarios(id),
    KEY idx_cirugia_procedimiento (procedimiento_quirurgico_id),
    KEY idx_cirugia_medico (medico_responsable_id)
) ENGINE=InnoDB;

CREATE TABLE cirugia_equipo (
    cirugia_evento_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    funcion_id SMALLINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (cirugia_evento_id, usuario_id, funcion_id),
    CONSTRAINT fk_cirugia_equipo_cirugia
        FOREIGN KEY (cirugia_evento_id) REFERENCES cirugias(evento_clinico_id) ON DELETE CASCADE,
    CONSTRAINT fk_cirugia_equipo_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
    CONSTRAINT fk_cirugia_equipo_funcion
        FOREIGN KEY (funcion_id) REFERENCES funciones_equipo_quirurgico(id)
) ENGINE=InnoDB;

CREATE TABLE cirugia_anestesias (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cirugia_evento_id BIGINT UNSIGNED NOT NULL,
    responsable_id BIGINT UNSIGNED NOT NULL,
    formula_id BIGINT UNSIGNED NULL,
    formula_ejecucion_id BIGINT UNSIGNED NULL,
    tipo_anestesia_id SMALLINT UNSIGNED NULL,
    protocolo TEXT NULL,
    observaciones TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_cirugia_anestesia_cirugia
        FOREIGN KEY (cirugia_evento_id) REFERENCES cirugias(evento_clinico_id) ON DELETE CASCADE,
    CONSTRAINT fk_cirugia_anestesia_usuario
        FOREIGN KEY (responsable_id) REFERENCES usuarios(id),
    CONSTRAINT fk_cirugia_anestesia_formula
        FOREIGN KEY (formula_id) REFERENCES formulas(id) ON DELETE SET NULL,
    CONSTRAINT fk_anestesia_formula_ejecucion
        FOREIGN KEY (formula_ejecucion_id) REFERENCES formula_ejecuciones(id) ON DELETE SET NULL,
    CONSTRAINT fk_cirugia_anestesia_tipo
        FOREIGN KEY (tipo_anestesia_id) REFERENCES tipos_anestesia(id) ON DELETE SET NULL,
    KEY idx_cirugia_anestesia_cirugia (cirugia_evento_id)
) ENGINE=InnoDB;

CREATE TABLE cirugia_archivos (
    cirugia_evento_id BIGINT UNSIGNED NOT NULL,
    archivo_id BIGINT UNSIGNED NOT NULL,
    descripcion VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (cirugia_evento_id, archivo_id),
    CONSTRAINT fk_cirugia_archivo_cirugia
        FOREIGN KEY (cirugia_evento_id) REFERENCES cirugias(evento_clinico_id) ON DELETE CASCADE,
    CONSTRAINT fk_cirugia_archivo_archivo
        FOREIGN KEY (archivo_id) REFERENCES archivos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE cirugia_evoluciones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    cirugia_evento_id BIGINT UNSIGNED NOT NULL,
    registrado_por BIGINT UNSIGNED NOT NULL,
    fecha_hora DATETIME NOT NULL,
    evolucion TEXT NOT NULL,
    observaciones TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_cirugia_evolucion_cirugia
        FOREIGN KEY (cirugia_evento_id) REFERENCES cirugias(evento_clinico_id) ON DELETE CASCADE,
    CONSTRAINT fk_cirugia_evolucion_usuario
        FOREIGN KEY (registrado_por) REFERENCES usuarios(id),
    KEY idx_cirugia_evolucion_fecha (cirugia_evento_id, fecha_hora)
) ENGINE=InnoDB;

-- ============================================================
-- 13. TRATAMIENTOS Y APLICACIONES
-- ============================================================

CREATE TABLE tipos_tratamiento (
    id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(40) NOT NULL,
    nombre VARCHAR(80) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tipo_tratamiento_codigo (codigo)
) ENGINE=InnoDB;

CREATE TABLE frecuencias_administracion (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(50) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    intervalo_horas DECIMAL(8,2) NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (id),
    UNIQUE KEY uq_frecuencia_codigo (codigo)
) ENGINE=InnoDB;

CREATE TABLE tratamientos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    evento_clinico_id BIGINT UNSIGNED NOT NULL,
    tipo_tratamiento_id TINYINT UNSIGNED NOT NULL,
    indicado_por BIGINT UNSIGNED NOT NULL,
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME NULL,
    instrucciones_generales TEXT NULL,
    observaciones TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_tratamiento_evento
        FOREIGN KEY (evento_clinico_id) REFERENCES eventos_clinicos(id) ON DELETE CASCADE,
    CONSTRAINT fk_tratamiento_tipo
        FOREIGN KEY (tipo_tratamiento_id) REFERENCES tipos_tratamiento(id),
    CONSTRAINT fk_tratamiento_usuario
        FOREIGN KEY (indicado_por) REFERENCES usuarios(id),
    KEY idx_tratamiento_evento (evento_clinico_id),
    KEY idx_tratamiento_tipo (tipo_tratamiento_id)
) ENGINE=InnoDB;

CREATE TABLE tratamiento_medicamentos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tratamiento_id BIGINT UNSIGNED NOT NULL,
    farmaco_id BIGINT UNSIGNED NOT NULL,
    presentacion_id BIGINT UNSIGNED NULL,
    via_administracion_id SMALLINT UNSIGNED NULL,
    dosis_cantidad DECIMAL(18,6) NULL,
    dosis_unidad_id SMALLINT UNSIGNED NULL,
    frecuencia_id SMALLINT UNSIGNED NULL,
    frecuencia_texto VARCHAR(150) NULL,
    duracion_cantidad DECIMAL(10,2) NULL,
    duracion_unidad_id SMALLINT UNSIGNED NULL,
    formula_ejecucion_id BIGINT UNSIGNED NULL,
    instrucciones TEXT NULL,
    orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_tratamiento_medicamento_tratamiento
        FOREIGN KEY (tratamiento_id) REFERENCES tratamientos(id) ON DELETE CASCADE,
    CONSTRAINT fk_tratamiento_medicamento_farmaco
        FOREIGN KEY (farmaco_id) REFERENCES farmacos(id),
    CONSTRAINT fk_tratamiento_medicamento_presentacion
        FOREIGN KEY (presentacion_id) REFERENCES farmaco_presentaciones(id) ON DELETE SET NULL,
    CONSTRAINT fk_tratamiento_medicamento_via
        FOREIGN KEY (via_administracion_id) REFERENCES vias_administracion(id) ON DELETE SET NULL,
    CONSTRAINT fk_tratamiento_medicamento_unidad
        FOREIGN KEY (dosis_unidad_id) REFERENCES unidades_medida(id) ON DELETE SET NULL,
    CONSTRAINT fk_tratamiento_medicamento_frecuencia
        FOREIGN KEY (frecuencia_id) REFERENCES frecuencias_administracion(id) ON DELETE SET NULL,
    CONSTRAINT fk_tratamiento_medicamento_duracion_unidad
        FOREIGN KEY (duracion_unidad_id) REFERENCES unidades_tiempo(id) ON DELETE SET NULL,
    CONSTRAINT fk_tratamiento_medicamento_formula
        FOREIGN KEY (formula_ejecucion_id) REFERENCES formula_ejecuciones(id) ON DELETE SET NULL,
    KEY idx_tratamiento_medicamento_tratamiento (tratamiento_id),
    KEY idx_tratamiento_medicamento_farmaco (farmaco_id)
) ENGINE=InnoDB;

CREATE TABLE medicamento_aplicaciones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tratamiento_medicamento_id BIGINT UNSIGNED NOT NULL,
    aplicado_por BIGINT UNSIGNED NOT NULL,
    fecha_hora DATETIME NOT NULL,
    cantidad_aplicada DECIMAL(18,6) NULL,
    unidad_id SMALLINT UNSIGNED NULL,
    observaciones TEXT NULL,
    anulado_at DATETIME NULL,
    anulado_por BIGINT UNSIGNED NULL,
    motivo_anulacion TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_medicamento_aplicacion_prescripcion
        FOREIGN KEY (tratamiento_medicamento_id)
        REFERENCES tratamiento_medicamentos(id) ON DELETE CASCADE,
    CONSTRAINT fk_medicamento_aplicacion_usuario
        FOREIGN KEY (aplicado_por) REFERENCES usuarios(id),
    CONSTRAINT fk_medicamento_aplicacion_unidad
        FOREIGN KEY (unidad_id) REFERENCES unidades_medida(id) ON DELETE SET NULL,
    KEY idx_medicamento_aplicacion_fecha (tratamiento_medicamento_id, fecha_hora)
) ENGINE=InnoDB;

-- ============================================================
-- 14. INVENTARIO
-- ============================================================

CREATE TABLE categorias_producto (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(120) NOT NULL,
    descripcion VARCHAR(255) NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categoria_producto_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE productos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    categoria_producto_id BIGINT UNSIGNED NULL,
    codigo VARCHAR(80) NOT NULL,
    nombre VARCHAR(180) NOT NULL,
    descripcion TEXT NULL,
    unidad_base_id SMALLINT UNSIGNED NOT NULL,
    farmaco_presentacion_id BIGINT UNSIGNED NULL,
    vacuna_id BIGINT UNSIGNED NULL,
    controla_lote BOOLEAN NOT NULL DEFAULT FALSE,
    controla_vencimiento BOOLEAN NOT NULL DEFAULT FALSE,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_producto_categoria
        FOREIGN KEY (categoria_producto_id) REFERENCES categorias_producto(id) ON DELETE SET NULL,
    CONSTRAINT fk_producto_unidad
        FOREIGN KEY (unidad_base_id) REFERENCES unidades_medida(id),
    CONSTRAINT fk_producto_farmaco_presentacion
        FOREIGN KEY (farmaco_presentacion_id) REFERENCES farmaco_presentaciones(id) ON DELETE SET NULL,
    CONSTRAINT fk_producto_vacuna
        FOREIGN KEY (vacuna_id) REFERENCES vacunas(id) ON DELETE SET NULL,
    UNIQUE KEY uq_producto_codigo (codigo),
    KEY idx_producto_nombre (nombre)
) ENGINE=InnoDB;

CREATE TABLE inventarios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entorno_id BIGINT UNSIGNED NOT NULL,
    nombre VARCHAR(150) NOT NULL,
    descripcion VARCHAR(255) NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_inventario_entorno
        FOREIGN KEY (entorno_id) REFERENCES entornos(id),
    UNIQUE KEY uq_inventario_entorno_nombre (entorno_id, nombre)
) ENGINE=InnoDB;

CREATE TABLE inventario_productos (
    inventario_id BIGINT UNSIGNED NOT NULL,
    producto_id BIGINT UNSIGNED NOT NULL,
    stock_minimo DECIMAL(18,4) NULL,
    stock_maximo DECIMAL(18,4) NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (inventario_id, producto_id),
    CONSTRAINT fk_inventario_producto_inventario
        FOREIGN KEY (inventario_id) REFERENCES inventarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_inventario_producto_producto
        FOREIGN KEY (producto_id) REFERENCES productos(id)
) ENGINE=InnoDB;

CREATE TABLE lotes_producto (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    producto_id BIGINT UNSIGNED NOT NULL,
    numero_lote VARCHAR(120) NOT NULL,
    fecha_fabricacion DATE NULL,
    fecha_vencimiento DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_lote_producto
        FOREIGN KEY (producto_id) REFERENCES productos(id),
    UNIQUE KEY uq_producto_numero_lote (producto_id, numero_lote),
    KEY idx_lote_vencimiento (fecha_vencimiento)
) ENGINE=InnoDB;

CREATE TABLE tipos_movimiento_inventario (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(50) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    factor SMALLINT NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tipo_movimiento_codigo (codigo),
    CONSTRAINT chk_tipo_movimiento_factor CHECK (factor IN (-1, 1))
) ENGINE=InnoDB;

CREATE TABLE movimientos_inventario (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    inventario_id BIGINT UNSIGNED NOT NULL,
    producto_id BIGINT UNSIGNED NOT NULL,
    lote_id BIGINT UNSIGNED NULL,
    tipo_movimiento_id SMALLINT UNSIGNED NOT NULL,
    cantidad DECIMAL(18,4) NOT NULL,
    costo_unitario DECIMAL(18,6) NULL,
    realizado_por BIGINT UNSIGNED NOT NULL,
    fecha_movimiento DATETIME NOT NULL,
    referencia_tipo VARCHAR(60) NULL,
    referencia_id BIGINT UNSIGNED NULL,
    observaciones TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_movimiento_inventario
        FOREIGN KEY (inventario_id) REFERENCES inventarios(id),
    CONSTRAINT fk_movimiento_producto
        FOREIGN KEY (producto_id) REFERENCES productos(id),
    CONSTRAINT fk_movimiento_lote
        FOREIGN KEY (lote_id) REFERENCES lotes_producto(id) ON DELETE SET NULL,
    CONSTRAINT fk_movimiento_tipo
        FOREIGN KEY (tipo_movimiento_id) REFERENCES tipos_movimiento_inventario(id),
    CONSTRAINT fk_movimiento_usuario
        FOREIGN KEY (realizado_por) REFERENCES usuarios(id),
    CONSTRAINT chk_movimiento_cantidad CHECK (cantidad > 0),
    KEY idx_movimiento_inventario_producto (inventario_id, producto_id),
    KEY idx_movimiento_fecha (fecha_movimiento),
    KEY idx_movimiento_lote (lote_id)
) ENGINE=InnoDB;

-- ============================================================
-- 15. AGENDA Y NOTIFICACIONES
-- ============================================================

CREATE TABLE estados_cita (
    id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(40) NOT NULL,
    nombre VARCHAR(80) NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (id),
    UNIQUE KEY uq_estado_cita_codigo (codigo)
) ENGINE=InnoDB;

CREATE TABLE citas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entorno_id BIGINT UNSIGNED NOT NULL,
    animal_id BIGINT UNSIGNED NOT NULL,
    profesional_id BIGINT UNSIGNED NULL,
    estado_cita_id TINYINT UNSIGNED NOT NULL,
    fecha_inicio DATETIME NOT NULL,
    fecha_fin DATETIME NULL,
    motivo VARCHAR(255) NULL,
    observaciones TEXT NULL,
    creado_por BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_cita_animal_entorno
        FOREIGN KEY (animal_id, entorno_id) REFERENCES animales(id, entorno_id),
    CONSTRAINT fk_cita_profesional
        FOREIGN KEY (profesional_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_cita_estado
        FOREIGN KEY (estado_cita_id) REFERENCES estados_cita(id),
    CONSTRAINT fk_cita_creador
        FOREIGN KEY (creado_por) REFERENCES usuarios(id),
    KEY idx_cita_entorno_fecha (entorno_id, fecha_inicio),
    KEY idx_cita_animal_fecha (animal_id, fecha_inicio),
    KEY idx_cita_profesional_fecha (profesional_id, fecha_inicio)
) ENGINE=InnoDB;

CREATE TABLE canales_notificacion (
    id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(40) NOT NULL,
    nombre VARCHAR(80) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_canal_notificacion_codigo (codigo)
) ENGINE=InnoDB;

CREATE TABLE tipos_notificacion (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(60) NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tipo_notificacion_codigo (codigo)
) ENGINE=InnoDB;

CREATE TABLE notificaciones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entorno_id BIGINT UNSIGNED NULL,
    tipo_notificacion_id SMALLINT UNSIGNED NOT NULL,
    canal_id TINYINT UNSIGNED NOT NULL,
    destinatario_usuario_id BIGINT UNSIGNED NULL,
    destinatario_propietario_id BIGINT UNSIGNED NULL,
    asunto VARCHAR(200) NULL,
    mensaje TEXT NOT NULL,
    fecha_programada DATETIME NULL,
    fecha_envio DATETIME NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'PENDIENTE',
    referencia_tipo VARCHAR(60) NULL,
    referencia_id BIGINT UNSIGNED NULL,
    intentos SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    ultimo_error TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_notificacion_entorno
        FOREIGN KEY (entorno_id) REFERENCES entornos(id) ON DELETE SET NULL,
    CONSTRAINT fk_notificacion_tipo
        FOREIGN KEY (tipo_notificacion_id) REFERENCES tipos_notificacion(id),
    CONSTRAINT fk_notificacion_canal
        FOREIGN KEY (canal_id) REFERENCES canales_notificacion(id),
    CONSTRAINT fk_notificacion_usuario
        FOREIGN KEY (destinatario_usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_notificacion_propietario
        FOREIGN KEY (destinatario_propietario_id) REFERENCES propietarios(id) ON DELETE SET NULL,
    -- La validación de que exista al menos un destinatario se realiza en backend.
    -- MySQL no permite este CHECK sobre columnas usadas por FKs con ON DELETE SET NULL.
    KEY idx_notificacion_pendiente (estado, fecha_programada),
    KEY idx_notificacion_usuario (destinatario_usuario_id)
) ENGINE=InnoDB;

-- ============================================================
-- 16. VENTAS, PAGOS Y FACTURACIÓN
-- ============================================================

CREATE TABLE servicios (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(80) NOT NULL,
    nombre VARCHAR(180) NOT NULL,
    descripcion TEXT NULL,
    precio_base DECIMAL(14,2) NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_servicio_codigo (codigo)
) ENGINE=InnoDB;

CREATE TABLE ventas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entorno_id BIGINT UNSIGNED NOT NULL,
    propietario_entorno_id BIGINT UNSIGNED NULL,
    animal_id BIGINT UNSIGNED NULL,
    datos_fiscales_id BIGINT UNSIGNED NULL,
    fecha DATETIME NOT NULL,
    subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
    descuento DECIMAL(14,2) NOT NULL DEFAULT 0,
    impuestos DECIMAL(14,2) NOT NULL DEFAULT 0,
    total DECIMAL(14,2) NOT NULL DEFAULT 0,
    estado VARCHAR(30) NOT NULL DEFAULT 'BORRADOR',
    es_simulada BOOLEAN NOT NULL DEFAULT FALSE,
    creado_por BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_venta_entorno
        FOREIGN KEY (entorno_id) REFERENCES entornos(id),
    CONSTRAINT fk_venta_propietario_entorno
        FOREIGN KEY (propietario_entorno_id, entorno_id)
        REFERENCES propietarios_entornos(id, entorno_id),
    CONSTRAINT fk_venta_animal_entorno
        FOREIGN KEY (animal_id, entorno_id)
        REFERENCES animales(id, entorno_id),
    CONSTRAINT fk_venta_datos_fiscales
        FOREIGN KEY (datos_fiscales_id) REFERENCES propietarios_datos_fiscales(id) ON DELETE SET NULL,
    CONSTRAINT fk_venta_usuario
        FOREIGN KEY (creado_por) REFERENCES usuarios(id),
    KEY idx_venta_entorno_fecha (entorno_id, fecha),
    KEY idx_venta_propietario_entorno (propietario_entorno_id)
) ENGINE=InnoDB;

CREATE TABLE venta_detalles (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    venta_id BIGINT UNSIGNED NOT NULL,
    servicio_id BIGINT UNSIGNED NULL,
    producto_id BIGINT UNSIGNED NULL,
    descripcion VARCHAR(255) NOT NULL,
    cantidad DECIMAL(14,4) NOT NULL,
    precio_unitario DECIMAL(14,4) NOT NULL,
    descuento DECIMAL(14,2) NOT NULL DEFAULT 0,
    impuesto DECIMAL(14,2) NOT NULL DEFAULT 0,
    total DECIMAL(14,2) NOT NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_venta_detalle_venta
        FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE CASCADE,
    CONSTRAINT fk_venta_detalle_servicio
        FOREIGN KEY (servicio_id) REFERENCES servicios(id) ON DELETE SET NULL,
    CONSTRAINT fk_venta_detalle_producto
        FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE SET NULL,
    -- Regla de negocio: debe existir exactamente un origen (servicio o producto).
    -- Se valida en backend porque MySQL impide usar estas columnas en CHECK
    -- al participar en FKs con ON DELETE SET NULL.
    CONSTRAINT chk_venta_detalle_cantidad CHECK (cantidad > 0),
    KEY idx_detalle_venta (venta_id)
) ENGINE=InnoDB;

CREATE TABLE metodos_pago (
    id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(50) NOT NULL,
    nombre VARCHAR(100) NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (id),
    UNIQUE KEY uq_metodo_pago_codigo (codigo)
) ENGINE=InnoDB;

CREATE TABLE pagos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    venta_id BIGINT UNSIGNED NOT NULL,
    metodo_pago_id SMALLINT UNSIGNED NOT NULL,
    monto DECIMAL(14,2) NOT NULL,
    fecha_pago DATETIME NOT NULL,
    referencia VARCHAR(150) NULL,
    registrado_por BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_pago_venta
        FOREIGN KEY (venta_id) REFERENCES ventas(id),
    CONSTRAINT fk_pago_metodo
        FOREIGN KEY (metodo_pago_id) REFERENCES metodos_pago(id),
    CONSTRAINT fk_pago_usuario
        FOREIGN KEY (registrado_por) REFERENCES usuarios(id),
    CONSTRAINT chk_pago_monto CHECK (monto > 0),
    KEY idx_pago_venta (venta_id),
    KEY idx_pago_fecha (fecha_pago)
) ENGINE=InnoDB;

CREATE TABLE documentos_fiscales (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    venta_id BIGINT UNSIGNED NOT NULL,
    tipo_documento VARCHAR(30) NOT NULL,
    numero_documento VARCHAR(100) NULL,
    clave_acceso VARCHAR(100) NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'PENDIENTE',
    fecha_emision DATETIME NULL,
    archivo_pdf_id BIGINT UNSIGNED NULL,
    archivo_xml_id BIGINT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_documento_fiscal_venta
        FOREIGN KEY (venta_id) REFERENCES ventas(id),
    CONSTRAINT fk_documento_pdf
        FOREIGN KEY (archivo_pdf_id) REFERENCES archivos(id) ON DELETE SET NULL,
    CONSTRAINT fk_documento_xml
        FOREIGN KEY (archivo_xml_id) REFERENCES archivos(id) ON DELETE SET NULL,
    KEY idx_documento_fiscal_venta (venta_id),
    KEY idx_documento_fiscal_estado (estado)
) ENGINE=InnoDB;

CREATE TABLE contifico_documentos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    documento_fiscal_id BIGINT UNSIGNED NOT NULL,
    contifico_id VARCHAR(150) NULL,
    estado VARCHAR(40) NOT NULL DEFAULT 'PENDIENTE',
    request_payload JSON NULL,
    response_payload JSON NULL,
    intentos SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    ultimo_error TEXT NULL,
    enviado_at DATETIME NULL,
    confirmado_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_contifico_documento
        FOREIGN KEY (documento_fiscal_id)
        REFERENCES documentos_fiscales(id) ON DELETE CASCADE,
    UNIQUE KEY uq_contifico_documento_fiscal (documento_fiscal_id),
    KEY idx_contifico_estado (estado)
) ENGINE=InnoDB;

-- ============================================================
-- 17. MÓDULO ACADÉMICO
-- ============================================================

CREATE TABLE periodos_academicos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(60) NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (id),
    UNIQUE KEY uq_periodo_academico_codigo (codigo),
    CONSTRAINT chk_periodo_fechas CHECK (fecha_fin >= fecha_inicio)
) ENGINE=InnoDB;

CREATE TABLE asignaturas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(60) NOT NULL,
    nombre VARCHAR(160) NOT NULL,
    descripcion TEXT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (id),
    UNIQUE KEY uq_asignatura_codigo (codigo)
) ENGINE=InnoDB;

CREATE TABLE cursos_academicos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    entorno_id BIGINT UNSIGNED NOT NULL,
    asignatura_id BIGINT UNSIGNED NOT NULL,
    periodo_academico_id BIGINT UNSIGNED NOT NULL,
    codigo_seccion VARCHAR(30) NOT NULL DEFAULT 'GENERAL',
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_curso_entorno
        FOREIGN KEY (entorno_id) REFERENCES entornos(id),
    CONSTRAINT fk_curso_asignatura
        FOREIGN KEY (asignatura_id) REFERENCES asignaturas(id),
    CONSTRAINT fk_curso_periodo
        FOREIGN KEY (periodo_academico_id) REFERENCES periodos_academicos(id),
    UNIQUE KEY uq_curso_academico (
        entorno_id, asignatura_id, periodo_academico_id, codigo_seccion
    )
) ENGINE=InnoDB;

CREATE TABLE curso_docentes (
    curso_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (curso_id, usuario_id),
    CONSTRAINT fk_curso_docente_curso
        FOREIGN KEY (curso_id) REFERENCES cursos_academicos(id) ON DELETE CASCADE,
    CONSTRAINT fk_curso_docente_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE curso_estudiantes (
    curso_id BIGINT UNSIGNED NOT NULL,
    usuario_id BIGINT UNSIGNED NOT NULL,
    fecha_matricula DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (curso_id, usuario_id),
    CONSTRAINT fk_curso_estudiante_curso
        FOREIGN KEY (curso_id) REFERENCES cursos_academicos(id) ON DELETE CASCADE,
    CONSTRAINT fk_curso_estudiante_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE casos_clinicos_academicos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    curso_id BIGINT UNSIGNED NOT NULL,
    titulo VARCHAR(180) NOT NULL,
    descripcion TEXT NOT NULL,
    instrucciones TEXT NULL,
    creado_por BIGINT UNSIGNED NOT NULL,
    fecha_disponible DATETIME NULL,
    fecha_limite DATETIME NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_caso_academico_curso
        FOREIGN KEY (curso_id) REFERENCES cursos_academicos(id) ON DELETE CASCADE,
    CONSTRAINT fk_caso_academico_usuario
        FOREIGN KEY (creado_por) REFERENCES usuarios(id),
    KEY idx_caso_curso (curso_id)
) ENGINE=InnoDB;

CREATE TABLE caso_clinico_animales (
    caso_clinico_id BIGINT UNSIGNED NOT NULL,
    animal_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (caso_clinico_id, animal_id),
    CONSTRAINT fk_caso_animal_caso
        FOREIGN KEY (caso_clinico_id)
        REFERENCES casos_clinicos_academicos(id) ON DELETE CASCADE,
    CONSTRAINT fk_caso_animal_animal
        FOREIGN KEY (animal_id) REFERENCES animales(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE actividades_academicas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    caso_clinico_id BIGINT UNSIGNED NOT NULL,
    codigo VARCHAR(60) NOT NULL,
    nombre VARCHAR(160) NOT NULL,
    descripcion TEXT NULL,
    orden SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    CONSTRAINT fk_actividad_caso
        FOREIGN KEY (caso_clinico_id)
        REFERENCES casos_clinicos_academicos(id) ON DELETE CASCADE,
    UNIQUE KEY uq_actividad_caso_codigo (caso_clinico_id, codigo)
) ENGINE=InnoDB;

CREATE TABLE ejercicios_formula (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    formula_version_id BIGINT UNSIGNED NOT NULL,
    titulo VARCHAR(180) NOT NULL,
    enunciado TEXT NOT NULL,
    creado_por BIGINT UNSIGNED NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_ejercicio_formula_version
        FOREIGN KEY (formula_version_id) REFERENCES formula_versiones(id),
    CONSTRAINT fk_ejercicio_formula_creador
        FOREIGN KEY (creado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE actividad_ejercicios_formula (
    actividad_id BIGINT UNSIGNED NOT NULL,
    ejercicio_formula_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (actividad_id, ejercicio_formula_id),
    CONSTRAINT fk_actividad_ejercicio_actividad
        FOREIGN KEY (actividad_id) REFERENCES actividades_academicas(id) ON DELETE CASCADE,
    CONSTRAINT fk_actividad_ejercicio_formula
        FOREIGN KEY (ejercicio_formula_id) REFERENCES ejercicios_formula(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE ejercicio_formula_valores (
    ejercicio_id BIGINT UNSIGNED NOT NULL,
    formula_variable_id BIGINT UNSIGNED NOT NULL,
    valor DECIMAL(18,6) NOT NULL,
    visible_estudiante BOOLEAN NOT NULL DEFAULT TRUE,
    PRIMARY KEY (ejercicio_id, formula_variable_id),
    CONSTRAINT fk_ejercicio_valor_ejercicio
        FOREIGN KEY (ejercicio_id) REFERENCES ejercicios_formula(id) ON DELETE CASCADE,
    CONSTRAINT fk_ejercicio_valor_variable
        FOREIGN KEY (formula_variable_id) REFERENCES formula_variables(id)
) ENGINE=InnoDB;

CREATE TABLE ejercicio_formula_intentos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    ejercicio_id BIGINT UNSIGNED NOT NULL,
    estudiante_usuario_id BIGINT UNSIGNED NOT NULL,
    resultado_ingresado DECIMAL(18,6) NOT NULL,
    resultado_esperado DECIMAL(18,6) NOT NULL,
    correcto BOOLEAN NOT NULL,
    intento_numero SMALLINT UNSIGNED NOT NULL,
    fecha_intento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_intento_formula_ejercicio
        FOREIGN KEY (ejercicio_id) REFERENCES ejercicios_formula(id) ON DELETE CASCADE,
    CONSTRAINT fk_intento_formula_estudiante
        FOREIGN KEY (estudiante_usuario_id) REFERENCES usuarios(id),
    UNIQUE KEY uq_ejercicio_estudiante_intento (
        ejercicio_id, estudiante_usuario_id, intento_numero
    ),
    KEY idx_intento_formula_estudiante (estudiante_usuario_id)
) ENGINE=InnoDB;

CREATE TABLE entregas_academicas (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    actividad_id BIGINT UNSIGNED NOT NULL,
    estudiante_usuario_id BIGINT UNSIGNED NOT NULL,
    estado VARCHAR(30) NOT NULL DEFAULT 'EN_PROGRESO',
    iniciado_at DATETIME NULL,
    entregado_at DATETIME NULL,
    observaciones_estudiante TEXT NULL,
    observaciones_docente TEXT NULL,
    calificacion DECIMAL(5,2) NULL,
    revisado_por BIGINT UNSIGNED NULL,
    revisado_at DATETIME NULL,
    PRIMARY KEY (id),
    CONSTRAINT fk_entrega_actividad
        FOREIGN KEY (actividad_id) REFERENCES actividades_academicas(id) ON DELETE CASCADE,
    CONSTRAINT fk_entrega_estudiante
        FOREIGN KEY (estudiante_usuario_id) REFERENCES usuarios(id),
    CONSTRAINT fk_entrega_revisor
        FOREIGN KEY (revisado_por) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT chk_entrega_calificacion
        CHECK (calificacion IS NULL OR (calificacion >= 0 AND calificacion <= 100)),
    UNIQUE KEY uq_actividad_estudiante (actividad_id, estudiante_usuario_id)
) ENGINE=InnoDB;

CREATE TABLE entrega_eventos_clinicos (
    entrega_id BIGINT UNSIGNED NOT NULL,
    evento_clinico_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (entrega_id, evento_clinico_id),
    CONSTRAINT fk_entrega_evento_entrega
        FOREIGN KEY (entrega_id) REFERENCES entregas_academicas(id) ON DELETE CASCADE,
    CONSTRAINT fk_entrega_evento_clinico
        FOREIGN KEY (evento_clinico_id) REFERENCES eventos_clinicos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- 18. AUDITORÍA
-- ============================================================

CREATE TABLE auditoria (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NULL,
    entorno_id BIGINT UNSIGNED NULL,
    modulo VARCHAR(100) NOT NULL,
    accion VARCHAR(80) NOT NULL,
    tabla_afectada VARCHAR(100) NULL,
    registro_id BIGINT UNSIGNED NULL,
    datos_anteriores JSON NULL,
    datos_nuevos JSON NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(500) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_auditoria_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
    CONSTRAINT fk_auditoria_entorno
        FOREIGN KEY (entorno_id) REFERENCES entornos(id) ON DELETE SET NULL,
    KEY idx_auditoria_usuario_fecha (usuario_id, created_at),
    KEY idx_auditoria_entorno_fecha (entorno_id, created_at),
    KEY idx_auditoria_modulo (modulo),
    KEY idx_auditoria_registro (tabla_afectada, registro_id)
) ENGINE=InnoDB;

-- ============================================================
-- 19. INTEGRACIONES
-- ============================================================

CREATE TABLE integraciones (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(60) NOT NULL,
    nombre VARCHAR(120) NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_integracion_codigo (codigo)
) ENGINE=InnoDB;

-- ============================================================
-- 20. ÍNDICES ADICIONALES
-- ============================================================

CREATE INDEX idx_animales_entorno_propietario
ON animales(entorno_id, propietario_entorno_id);

CREATE INDEX idx_eventos_animal_tipo_fecha
ON eventos_clinicos(animal_id, tipo_evento_id, fecha_evento);

CREATE INDEX idx_lotes_producto_vencimiento
ON lotes_producto(producto_id, fecha_vencimiento);

CREATE INDEX idx_movimientos_inventario_fecha
ON movimientos_inventario(inventario_id, fecha_movimiento);

CREATE INDEX idx_formula_ejecuciones_usuario_fecha
ON formula_ejecuciones(ejecutado_por, fecha_ejecucion);

CREATE INDEX idx_entregas_estudiante
ON entregas_academicas(estudiante_usuario_id, estado);

-- ============================================================
-- 21. DATOS BASE / SEEDS
-- ============================================================

INSERT INTO tipos_entorno (codigo, nombre, descripcion) VALUES
('VETERINARIA', 'Veterinaria', 'Entorno operativo de clínica veterinaria'),
('HACIENDA', 'Hacienda', 'Entorno operativo de hacienda y manejo animal'),
('ACADEMICO', 'Académico', 'Entorno destinado a docentes, estudiantes y simulación');

INSERT INTO proveedores_autenticacion (codigo, nombre) VALUES
('GOOGLE', 'Google');

INSERT INTO roles (codigo, nombre, descripcion, es_global, protegido) VALUES
('SUPER_ADMINISTRADOR', 'Super Administrador', 'Acceso completo a toda la plataforma', 1, 1),
('ADMINISTRADOR', 'Administrador', 'Administrador operativo del entorno asignado', 0, 1),
('CLIENTE', 'Cliente', 'Propietario con acceso exclusivo a su información', 0, 1),
('INVITADO', 'Invitado', 'Acceso limitado de solo lectura', 0, 1),
('DOCENTE', 'Docente', 'Gestión y supervisión académica', 0, 1),
('ESTUDIANTE', 'Estudiante', 'Uso académico y prácticas simuladas', 0, 1);

INSERT INTO acciones_permiso (codigo, nombre) VALUES
('VER', 'Ver'),
('CREAR', 'Crear'),
('EDITAR', 'Editar'),
('ELIMINAR', 'Eliminar'),
('EXPORTAR', 'Exportar'),
('APROBAR', 'Aprobar'),
('ANULAR', 'Anular'),
('FACTURAR', 'Facturar'),
('CALCULAR', 'Calcular'),
('PUBLICAR', 'Publicar');

INSERT INTO tipos_identificacion (codigo, nombre) VALUES
('CEDULA', 'Cédula'),
('RUC', 'RUC'),
('PASAPORTE', 'Pasaporte'),
('CONSUMIDOR_FINAL', 'Consumidor final');

INSERT INTO categorias_animales (codigo, nombre) VALUES
('MASCOTA', 'Mascota'),
('BOVINO', 'Bovino'),
('OVINO', 'Ovino'),
('PORCINO', 'Porcino'),
('EQUINO', 'Equino'),
('CAPRINO', 'Caprino'),
('AVE', 'Ave'),
('ROEDOR', 'Roedor'),
('LAGOMORFO', 'Lagomorfo'),
('ACUATICO', 'Acuático'),
('OTRO', 'Otro');

INSERT INTO sexos_animales (codigo, nombre) VALUES
('MACHO', 'Macho'),
('HEMBRA', 'Hembra'),
('INDETERMINADO', 'Indeterminado');

INSERT INTO unidades_medida (codigo, nombre, simbolo, categoria) VALUES
('KG', 'Kilogramo', 'kg', 'MASA'),
('G', 'Gramo', 'g', 'MASA'),
('MG', 'Miligramo', 'mg', 'MASA'),
('L', 'Litro', 'L', 'VOLUMEN'),
('ML', 'Mililitro', 'ml', 'VOLUMEN'),
('MG_KG', 'Miligramo por kilogramo', 'mg/kg', 'DOSIS'),
('MG_ML', 'Miligramo por mililitro', 'mg/ml', 'CONCENTRACION'),
('ML_KG', 'Mililitro por kilogramo', 'ml/kg', 'DOSIS'),
('ML_H', 'Mililitro por hora', 'ml/h', 'VELOCIDAD'),
('PORCENTAJE', 'Porcentaje', '%', 'PORCENTAJE');

INSERT INTO unidades_tiempo (codigo, nombre) VALUES
('HORAS', 'Horas'),
('DIAS', 'Días'),
('SEMANAS', 'Semanas'),
('MESES', 'Meses');

INSERT INTO tipos_evento_clinico (codigo, nombre) VALUES
('CONSULTA_EXTERNA', 'Consulta externa'),
('VACUNACION', 'Vacunación'),
('DESPARASITACION', 'Desparasitación'),
('HOSPITALIZACION', 'Hospitalización'),
('LABORATORIO', 'Examen de laboratorio'),
('CIRUGIA', 'Cirugía');

INSERT INTO tipos_diagnostico (codigo, nombre) VALUES
('DIFERENCIAL', 'Diagnóstico diferencial'),
('PRESUNTIVO', 'Diagnóstico presuntivo'),
('DEFINITIVO', 'Diagnóstico definitivo');

INSERT INTO formas_farmaceuticas (codigo, nombre) VALUES
('TABLETA', 'Tableta'),
('CAPSULA', 'Cápsula'),
('SUSPENSION', 'Suspensión'),
('SOLUCION', 'Solución'),
('INYECTABLE', 'Inyectable'),
('CREMA', 'Crema'),
('UNGUENTO', 'Ungüento'),
('GOTAS', 'Gotas'),
('POLVO', 'Polvo'),
('OTRO', 'Otro');

INSERT INTO vias_administracion (codigo, nombre) VALUES
('ORAL', 'Oral'),
('INTRAVENOSA', 'Intravenosa'),
('INTRAMUSCULAR', 'Intramuscular'),
('SUBCUTANEA', 'Subcutánea'),
('TOPICA', 'Tópica'),
('OFTALMICA', 'Oftálmica'),
('OTICA', 'Ótica'),
('RECTAL', 'Rectal'),
('OTRA', 'Otra');

INSERT INTO categorias_formula (codigo, nombre) VALUES
('MEDICAMENTO', 'Medicamento'),
('FLUIDOTERAPIA', 'Fluidoterapia'),
('ANESTESIA', 'Anestesia'),
('NUTRICION', 'Nutrición'),
('PRODUCCION', 'Producción'),
('ACADEMICA', 'Fórmula académica'),
('OTRA', 'Otra');

INSERT INTO estados_formula_version (codigo, nombre) VALUES
('BORRADOR', 'Borrador'),
('PUBLICADA', 'Publicada'),
('INACTIVA', 'Inactiva');

INSERT INTO tipos_variable_formula (codigo, nombre) VALUES
('DECIMAL', 'Número decimal'),
('ENTERO', 'Número entero'),
('BOOLEANO', 'Sí / No');

INSERT INTO origenes_variable_formula (codigo, nombre) VALUES
('MANUAL', 'Ingreso manual'),
('PESO_ACTUAL', 'Peso actual del paciente'),
('EDAD_DIAS', 'Edad del paciente en días'),
('EDAD_MESES', 'Edad del paciente en meses'),
('EDAD_ANIOS', 'Edad del paciente en años');

INSERT INTO estados_hospitalizacion (codigo, nombre) VALUES
('ACTIVA', 'Activa'),
('ALTA', 'Alta médica'),
('TRASLADO', 'Traslado'),
('FALLECIDO', 'Fallecido'),
('CANCELADA', 'Cancelada');

INSERT INTO funciones_equipo_quirurgico (codigo, nombre) VALUES
('CIRUJANO', 'Cirujano'),
('AYUDANTE', 'Ayudante'),
('ANESTESISTA', 'Anestesista'),
('INSTRUMENTISTA', 'Instrumentista');

INSERT INTO tipos_anestesia (codigo, nombre) VALUES
('GENERAL', 'General'),
('LOCAL', 'Local'),
('REGIONAL', 'Regional'),
('SEDACION', 'Sedación');

INSERT INTO tipos_tratamiento (codigo, nombre) VALUES
('CLINICO', 'Tratamiento clínico'),
('CASA', 'Tratamiento en casa');

INSERT INTO frecuencias_administracion (codigo, nombre, intervalo_horas) VALUES
('CADA_4H', 'Cada 4 horas', 4),
('CADA_6H', 'Cada 6 horas', 6),
('CADA_8H', 'Cada 8 horas', 8),
('CADA_12H', 'Cada 12 horas', 12),
('CADA_24H', 'Cada 24 horas', 24),
('DOS_VECES_DIA', 'Dos veces al día', 12),
('UNA_VEZ_DIA', 'Una vez al día', 24),
('SEGUN_NECESIDAD', 'Según necesidad', NULL);

INSERT INTO tipos_movimiento_inventario (codigo, nombre, factor) VALUES
('ENTRADA', 'Entrada', 1),
('SALIDA', 'Salida', -1),
('AJUSTE_POSITIVO', 'Ajuste positivo', 1),
('AJUSTE_NEGATIVO', 'Ajuste negativo', -1),
('CONSUMO_CLINICO', 'Consumo clínico', -1),
('TRANSFERENCIA_ENTRADA', 'Transferencia entrada', 1),
('TRANSFERENCIA_SALIDA', 'Transferencia salida', -1);

INSERT INTO estados_cita (codigo, nombre) VALUES
('PENDIENTE', 'Pendiente'),
('CONFIRMADA', 'Confirmada'),
('ATENDIDA', 'Atendida'),
('CANCELADA', 'Cancelada'),
('NO_ASISTIO', 'No asistió');

INSERT INTO canales_notificacion (codigo, nombre) VALUES
('EMAIL', 'Correo electrónico'),
('WHATSAPP', 'WhatsApp'),
('INTERNA', 'Notificación interna');

INSERT INTO tipos_notificacion (codigo, nombre) VALUES
('CITA', 'Recordatorio de cita'),
('VACUNA', 'Recordatorio de vacunación'),
('DESPARASITACION', 'Recordatorio de desparasitación'),
('TRATAMIENTO', 'Recordatorio de tratamiento'),
('SISTEMA', 'Notificación del sistema'),
('ACADEMICA', 'Notificación académica');

INSERT INTO metodos_pago (codigo, nombre) VALUES
('EFECTIVO', 'Efectivo'),
('TRANSFERENCIA', 'Transferencia'),
('TARJETA', 'Tarjeta'),
('OTRO', 'Otro');

INSERT INTO integraciones (codigo, nombre) VALUES
('GOOGLE', 'Google Identity'),
('CONTIFICO', 'Contífico'),
('WHATSAPP', 'WhatsApp'),
('EMAIL', 'Correo electrónico');

-- ============================================================
-- 22. MÓDULOS BASE
-- ============================================================

INSERT INTO modulos (codigo, nombre, orden) VALUES
('DASHBOARD', 'Dashboard', 10),
('EMPRESAS', 'Empresas', 20),
('USUARIOS', 'Usuarios', 30),
('ROLES', 'Roles', 40),
('PERMISOS', 'Permisos', 50),
('PROPIETARIOS', 'Propietarios', 60),
('PACIENTES', 'Pacientes', 70),
('CONSULTAS', 'Consultas', 80),
('VACUNAS', 'Vacunación', 90),
('DESPARASITACION', 'Desparasitación', 100),
('HOSPITALIZACION', 'Hospitalización', 110),
('LABORATORIO', 'Laboratorio', 120),
('CIRUGIAS', 'Cirugías', 130),
('FORMULAS', 'Fórmulas Médicas', 140),
('TRATAMIENTOS', 'Tratamientos', 150),
('INVENTARIO', 'Inventario', 160),
('CITAS', 'Citas', 170),
('NOTIFICACIONES', 'Notificaciones', 180),
('VENTAS', 'Ventas', 190),
('FACTURACION', 'Facturación', 200),
('REPORTES', 'Reportes', 210),
('AUDITORIA', 'Auditoría', 220),
('ACADEMICO', 'Gestión Académica', 230);

-- Acciones soportadas por módulo.
-- CRUD estándar para la mayoría:
INSERT INTO modulo_acciones (modulo_id, accion_id)
SELECT m.id, a.id
FROM modulos m
CROSS JOIN acciones_permiso a
WHERE a.codigo IN ('VER','CREAR','EDITAR','ELIMINAR')
  AND m.codigo IN (
    'EMPRESAS','USUARIOS','ROLES','PERMISOS','PROPIETARIOS','PACIENTES',
    'CONSULTAS','VACUNAS','DESPARASITACION','HOSPITALIZACION','LABORATORIO',
    'CIRUGIAS','FORMULAS','TRATAMIENTOS','INVENTARIO','CITAS','NOTIFICACIONES',
    'VENTAS','ACADEMICO'
  );

-- Dashboard: solo VER
INSERT INTO modulo_acciones (modulo_id, accion_id)
SELECT m.id, a.id
FROM modulos m
CROSS JOIN acciones_permiso a
WHERE m.codigo = 'DASHBOARD'
  AND a.codigo = 'VER';

-- Auditoría: VER y EXPORTAR
INSERT INTO modulo_acciones (modulo_id, accion_id)
SELECT m.id, a.id
FROM modulos m
CROSS JOIN acciones_permiso a
WHERE m.codigo = 'AUDITORIA'
  AND a.codigo IN ('VER','EXPORTAR');

-- Reportes: VER y EXPORTAR
INSERT INTO modulo_acciones (modulo_id, accion_id)
SELECT m.id, a.id
FROM modulos m
CROSS JOIN acciones_permiso a
WHERE m.codigo = 'REPORTES'
  AND a.codigo IN ('VER','EXPORTAR');

-- Facturación: VER, CREAR, ANULAR, FACTURAR
INSERT INTO modulo_acciones (modulo_id, accion_id)
SELECT m.id, a.id
FROM modulos m
CROSS JOIN acciones_permiso a
WHERE m.codigo = 'FACTURACION'
  AND a.codigo IN ('VER','CREAR','ANULAR','FACTURAR');

-- Fórmulas: CALCULAR y PUBLICAR adicionales
INSERT IGNORE INTO modulo_acciones (modulo_id, accion_id)
SELECT m.id, a.id
FROM modulos m
CROSS JOIN acciones_permiso a
WHERE m.codigo = 'FORMULAS'
  AND a.codigo IN ('CALCULAR','PUBLICAR');

-- Generación automática de permisos según módulo + acción soportada
INSERT INTO permisos (modulo_id, accion_id, codigo, descripcion)
SELECT
    ma.modulo_id,
    ma.accion_id,
    CONCAT(LOWER(m.codigo), '.', LOWER(a.codigo)),
    CONCAT(a.nombre, ' ', m.nombre)
FROM modulo_acciones ma
JOIN modulos m ON m.id = ma.modulo_id
JOIN acciones_permiso a ON a.id = ma.accion_id;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- FIN DEL ESQUEMA
-- ============================================================

-- ============================================================
-- 25. SEEDS ADICIONALES DEL PROYECTO TU HUELLA VET
-- ============================================================

INSERT INTO empresas (nombre, nombre_comercial, activo)
SELECT 'Tu Huella Vet - Hacienda Agusbella', 'Tu Huella Vet', 1
WHERE NOT EXISTS (SELECT 1 FROM empresas WHERE nombre='Tu Huella Vet - Hacienda Agusbella');

SET @empresa_seed_id = (SELECT id FROM empresas WHERE nombre='Tu Huella Vet - Hacienda Agusbella' ORDER BY id LIMIT 1);

INSERT INTO entornos (empresa_id,tipo_entorno_id,nombre,codigo,descripcion,es_productivo,permite_facturacion_real,activo)
SELECT @empresa_seed_id, te.id, 'Tu Huella Vet','TU_HUELLA_VET','Entorno operativo real de la clínica veterinaria Tu Huella Vet',1,1,1
FROM tipos_entorno te WHERE te.codigo='VETERINARIA'
AND NOT EXISTS (SELECT 1 FROM entornos WHERE empresa_id=@empresa_seed_id AND codigo='TU_HUELLA_VET');

INSERT INTO entornos (empresa_id,tipo_entorno_id,nombre,codigo,descripcion,es_productivo,permite_facturacion_real,activo)
SELECT @empresa_seed_id, te.id, 'Hacienda Agusbella','HACIENDA_AGUSBELLA','Entorno operativo real de Hacienda Agusbella',1,0,1
FROM tipos_entorno te WHERE te.codigo='HACIENDA'
AND NOT EXISTS (SELECT 1 FROM entornos WHERE empresa_id=@empresa_seed_id AND codigo='HACIENDA_AGUSBELLA');

INSERT INTO entornos (empresa_id,tipo_entorno_id,nombre,codigo,descripcion,es_productivo,permite_facturacion_real,activo)
SELECT @empresa_seed_id, te.id, 'Académico','ACADEMICO','Entorno académico y de simulación para docentes y estudiantes',0,0,1
FROM tipos_entorno te WHERE te.codigo='ACADEMICO'
AND NOT EXISTS (SELECT 1 FROM entornos WHERE empresa_id=@empresa_seed_id AND codigo='ACADEMICO');

INSERT INTO especies (categoria_id,codigo,nombre_comun,nombre_cientifico,activo)
SELECT ca.id,'PERRO','Perro','Canis lupus familiaris',1 FROM categorias_animales ca WHERE ca.codigo='MASCOTA'
AND NOT EXISTS (SELECT 1 FROM especies WHERE codigo='PERRO');
INSERT INTO especies (categoria_id,codigo,nombre_comun,nombre_cientifico,activo)
SELECT ca.id,'GATO','Gato','Felis catus',1 FROM categorias_animales ca WHERE ca.codigo='MASCOTA'
AND NOT EXISTS (SELECT 1 FROM especies WHERE codigo='GATO');
INSERT INTO especies (categoria_id,codigo,nombre_comun,nombre_cientifico,activo)
SELECT ca.id,'BOVINO','Bovino','Bos taurus',1 FROM categorias_animales ca WHERE ca.codigo='BOVINO'
AND NOT EXISTS (SELECT 1 FROM especies WHERE codigo='BOVINO');
INSERT INTO especies (categoria_id,codigo,nombre_comun,nombre_cientifico,activo)
SELECT ca.id,'OVINO','Ovino','Ovis aries',1 FROM categorias_animales ca WHERE ca.codigo='OVINO'
AND NOT EXISTS (SELECT 1 FROM especies WHERE codigo='OVINO');
INSERT INTO especies (categoria_id,codigo,nombre_comun,nombre_cientifico,activo)
SELECT ca.id,'PORCINO','Cerdo','Sus scrofa domesticus',1 FROM categorias_animales ca WHERE ca.codigo='PORCINO'
AND NOT EXISTS (SELECT 1 FROM especies WHERE codigo='PORCINO');
INSERT INTO especies (categoria_id,codigo,nombre_comun,nombre_cientifico,activo)
SELECT ca.id,'CABALLO','Caballo','Equus caballus',1 FROM categorias_animales ca WHERE ca.codigo='EQUINO'
AND NOT EXISTS (SELECT 1 FROM especies WHERE codigo='CABALLO');
INSERT INTO especies (categoria_id,codigo,nombre_comun,nombre_cientifico,activo)
SELECT ca.id,'BURRO','Burro','Equus asinus',1 FROM categorias_animales ca WHERE ca.codigo='EQUINO'
AND NOT EXISTS (SELECT 1 FROM especies WHERE codigo='BURRO');
INSERT INTO especies (categoria_id,codigo,nombre_comun,nombre_cientifico,activo)
SELECT ca.id,'CABRA','Cabra','Capra hircus',1 FROM categorias_animales ca WHERE ca.codigo='CAPRINO'
AND NOT EXISTS (SELECT 1 FROM especies WHERE codigo='CABRA');
INSERT INTO especies (categoria_id,codigo,nombre_comun,nombre_cientifico,activo)
SELECT ca.id,'CUY','Cuy','Cavia porcellus',1 FROM categorias_animales ca WHERE ca.codigo='ROEDOR'
AND NOT EXISTS (SELECT 1 FROM especies WHERE codigo='CUY');
INSERT INTO especies (categoria_id,codigo,nombre_comun,nombre_cientifico,activo)
SELECT ca.id,'CONEJO','Conejo','Oryctolagus cuniculus',1 FROM categorias_animales ca WHERE ca.codigo='LAGOMORFO'
AND NOT EXISTS (SELECT 1 FROM especies WHERE codigo='CONEJO');
INSERT INTO especies (categoria_id,codigo,nombre_comun,nombre_cientifico,activo)
SELECT ca.id,'PATO','Pato',NULL,1 FROM categorias_animales ca WHERE ca.codigo='AVE'
AND NOT EXISTS (SELECT 1 FROM especies WHERE codigo='PATO');
INSERT INTO especies (categoria_id,codigo,nombre_comun,nombre_cientifico,activo)
SELECT ca.id,'GALLINA','Gallina','Gallus gallus domesticus',1 FROM categorias_animales ca WHERE ca.codigo='AVE'
AND NOT EXISTS (SELECT 1 FROM especies WHERE codigo='GALLINA');
INSERT INTO especies (categoria_id,codigo,nombre_comun,nombre_cientifico,activo)
SELECT ca.id,'PAVO_REAL','Pavo real','Pavo cristatus',1 FROM categorias_animales ca WHERE ca.codigo='AVE'
AND NOT EXISTS (SELECT 1 FROM especies WHERE codigo='PAVO_REAL');
INSERT INTO especies (categoria_id,codigo,nombre_comun,nombre_cientifico,activo)
SELECT ca.id,'PEZ','Pez',NULL,1 FROM categorias_animales ca WHERE ca.codigo='ACUATICO'
AND NOT EXISTS (SELECT 1 FROM especies WHERE codigo='PEZ');

INSERT INTO categorias_mantenimiento_fluido (especie_id,codigo,nombre,descripcion,activo)
SELECT e.id,'PERRO_PEQUENO','Perro pequeño','Categoría de mantenimiento para perros pequeños',1 FROM especies e WHERE e.codigo='PERRO'
AND NOT EXISTS (SELECT 1 FROM categorias_mantenimiento_fluido WHERE codigo='PERRO_PEQUENO');
INSERT INTO categorias_mantenimiento_fluido (especie_id,codigo,nombre,descripcion,activo)
SELECT e.id,'PERRO_MEDIANO','Perro mediano','Categoría de mantenimiento para perros medianos',1 FROM especies e WHERE e.codigo='PERRO'
AND NOT EXISTS (SELECT 1 FROM categorias_mantenimiento_fluido WHERE codigo='PERRO_MEDIANO');
INSERT INTO categorias_mantenimiento_fluido (especie_id,codigo,nombre,descripcion,activo)
SELECT e.id,'PERRO_GRANDE','Perro grande','Categoría de mantenimiento para perros grandes',1 FROM especies e WHERE e.codigo='PERRO'
AND NOT EXISTS (SELECT 1 FROM categorias_mantenimiento_fluido WHERE codigo='PERRO_GRANDE');

INSERT INTO tipos_examen_laboratorio (nombre,descripcion,activo) VALUES
('Hemograma','Evaluación hematológica general',1),
('Química sanguínea','Perfil bioquímico',1),
('Coproparasitario','Evaluación coproparasitaria',1),
('Urianálisis','Análisis de orina',1),
('Citología','Evaluación citológica',1),
('Otro','Tipo configurable',1);

INSERT INTO procedimientos_quirurgicos (nombre,descripcion,activo) VALUES
('Esterilización / castración','Procedimiento reproductivo',1),
('Cirugía de tejidos blandos','Procedimiento de tejidos blandos',1),
('Ortopedia','Procedimiento ortopédico',1),
('Otro','Procedimiento configurable',1);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 26. PERMISOS BASE POR ROL
-- ============================================================

-- Administrador: operación completa, sin empresas/roles/permisos/auditoría.
INSERT IGNORE INTO rol_permisos (rol_id,permiso_id)
SELECT r.id,p.id FROM roles r CROSS JOIN permisos p
WHERE r.codigo='ADMINISTRADOR'
  AND p.codigo NOT LIKE 'roles.%'
  AND p.codigo NOT LIKE 'permisos.%'
  AND p.codigo NOT LIKE 'auditoria.%'
  AND p.codigo NOT LIKE 'empresas.%'
  AND p.codigo NOT LIKE 'academico.%';

-- Cliente: lectura clínica propia (el backend del portal además filtra por propietario).
INSERT IGNORE INTO rol_permisos (rol_id,permiso_id)
SELECT r.id,p.id FROM roles r CROSS JOIN permisos p
WHERE r.codigo='CLIENTE'
  AND p.codigo IN ('dashboard.ver','pacientes.ver','consultas.ver','vacunas.ver','desparasitacion.ver','hospitalizacion.ver','laboratorio.ver','cirugias.ver');

-- Invitado: lectura operativa limitada.
INSERT IGNORE INTO rol_permisos (rol_id,permiso_id)
SELECT r.id,p.id FROM roles r CROSS JOIN permisos p
WHERE r.codigo='INVITADO' AND p.codigo IN ('dashboard.ver','pacientes.ver','propietarios.ver','consultas.ver','vacunas.ver','desparasitacion.ver','hospitalizacion.ver','laboratorio.ver','cirugias.ver','inventario.ver','citas.ver');

-- Docente: académico completo + lectura/creación clínica simulada.
INSERT IGNORE INTO rol_permisos (rol_id,permiso_id)
SELECT r.id,p.id FROM roles r CROSS JOIN permisos p
WHERE r.codigo='DOCENTE' AND (p.codigo LIKE 'academico.%' OR p.codigo IN ('dashboard.ver','pacientes.ver','pacientes.crear','pacientes.editar','consultas.ver','consultas.crear','hospitalizacion.ver','hospitalizacion.crear','hospitalizacion.editar','laboratorio.ver','laboratorio.crear','cirugias.ver','cirugias.crear','formulas.ver','formulas.calcular','tratamientos.ver','tratamientos.crear','tratamientos.editar'));

-- Estudiante: académico + práctica clínica simulada sin administración.
INSERT IGNORE INTO rol_permisos (rol_id,permiso_id)
SELECT r.id,p.id FROM roles r CROSS JOIN permisos p
WHERE r.codigo='ESTUDIANTE' AND p.codigo IN ('academico.ver','dashboard.ver','pacientes.ver','pacientes.crear','consultas.ver','consultas.crear','hospitalizacion.ver','hospitalizacion.crear','hospitalizacion.editar','laboratorio.ver','laboratorio.crear','formulas.ver','formulas.calcular','tratamientos.ver','tratamientos.crear');
