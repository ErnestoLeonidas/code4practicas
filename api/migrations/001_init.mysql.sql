-- Migración 001_init (MySQL / MariaDB) — canónica para producción.
-- Ejecutar en phpMyAdmin o cliente MySQL. Todas las tablas con prefijo pp_.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS pp_usuarios (
    id                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre                 VARCHAR(100) NOT NULL,
    apellido               VARCHAR(100) NOT NULL,
    correo                 VARCHAR(190) NOT NULL,
    password_hash          VARCHAR(255) NOT NULL,
    rol                    ENUM('admin','docente') NOT NULL DEFAULT 'docente',
    debe_cambiar_password  TINYINT(1) NOT NULL DEFAULT 1,
    activo                 TINYINT(1) NOT NULL DEFAULT 1,
    creado_en              DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_correo (correo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pp_tokens_recuperacion (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id  INT UNSIGNED NOT NULL,
    token_hash  VARCHAR(255) NOT NULL,
    expira_en   DATETIME NOT NULL,
    usado       TINYINT(1) NOT NULL DEFAULT 0,
    creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tokens_usuario (usuario_id),
    CONSTRAINT fk_tokens_usuario FOREIGN KEY (usuario_id) REFERENCES pp_usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pp_carreras (
    id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre  VARCHAR(150) NOT NULL,
    escuela VARCHAR(150) NULL,
    activo  TINYINT(1) NOT NULL DEFAULT 1,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pp_estudiantes (
    id                        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre                    VARCHAR(100) NOT NULL,
    apellido                  VARCHAR(100) NOT NULL,
    rut                       VARCHAR(12) NOT NULL,
    correo_duoc               VARCHAR(190) NULL,
    telefono                  VARCHAR(30) NULL,
    carrera_id                INT UNSIGNED NULL,
    semestre_ingreso_practica VARCHAR(6) NULL,
    docente_id                INT UNSIGNED NULL,
    activo                    TINYINT(1) NOT NULL DEFAULT 1,
    creado_en                 DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_estudiantes_rut (rut),
    KEY idx_estudiantes_carrera (carrera_id),
    KEY idx_estudiantes_docente (docente_id),
    CONSTRAINT fk_estudiantes_carrera FOREIGN KEY (carrera_id) REFERENCES pp_carreras (id) ON DELETE SET NULL,
    CONSTRAINT fk_estudiantes_docente FOREIGN KEY (docente_id) REFERENCES pp_usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pp_empresas (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre     VARCHAR(190) NOT NULL,
    rut_empresa VARCHAR(15) NULL,
    giro       VARCHAR(190) NULL,
    direccion  VARCHAR(190) NULL,
    ciudad     VARCHAR(100) NULL,
    telefono   VARCHAR(30) NULL,
    sitio_web  VARCHAR(190) NULL,
    creado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pp_supervisores (
    id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id INT UNSIGNED NOT NULL,
    nombre     VARCHAR(100) NOT NULL,
    apellido   VARCHAR(100) NOT NULL,
    profesion  VARCHAR(150) NULL,
    cargo      VARCHAR(150) NULL,
    telefono   VARCHAR(30) NULL,
    correo     VARCHAR(190) NULL,
    creado_en  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_supervisores_empresa (empresa_id),
    CONSTRAINT fk_supervisores_empresa FOREIGN KEY (empresa_id) REFERENCES pp_empresas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pp_practicas (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    estudiante_id  INT UNSIGNED NOT NULL,
    empresa_id     INT UNSIGNED NOT NULL,
    supervisor_id  INT UNSIGNED NULL,
    semestre       VARCHAR(6) NULL,
    fecha_inicio   DATE NULL,
    fecha_termino  DATE NULL,
    estado         ENUM('pendiente','en_curso','avance_1','avance_2','informe_final',
                        'aprobada','reprobada','abandonada') NOT NULL DEFAULT 'pendiente',
    horas_totales  INT NULL,
    observaciones  TEXT NULL,
    creado_en      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_practicas_estudiante (estudiante_id),
    KEY idx_practicas_empresa (empresa_id),
    KEY idx_practicas_supervisor (supervisor_id),
    CONSTRAINT fk_practicas_estudiante FOREIGN KEY (estudiante_id) REFERENCES pp_estudiantes (id) ON DELETE CASCADE,
    CONSTRAINT fk_practicas_empresa FOREIGN KEY (empresa_id) REFERENCES pp_empresas (id) ON DELETE RESTRICT,
    CONSTRAINT fk_practicas_supervisor FOREIGN KEY (supervisor_id) REFERENCES pp_supervisores (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pp_seguimiento_semanal (
    id                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    practica_id              INT UNSIGNED NOT NULL,
    semana                   TINYINT UNSIGNED NOT NULL,
    foco                     VARCHAR(255) NULL,
    reunion_1a1              TINYINT(1) NOT NULL DEFAULT 0,
    orientaciones_claras     TINYINT(1) NOT NULL DEFAULT 0,
    retroalimentacion        TINYINT(1) NOT NULL DEFAULT 0,
    evidencia_registrada     TINYINT(1) NOT NULL DEFAULT 0,
    disponibilidad_comunicada TINYINT(1) NOT NULL DEFAULT 0,
    ajuste_individual        TINYINT(1) NOT NULL DEFAULT 0,
    reflexion_guiada         TINYINT(1) NOT NULL DEFAULT 0,
    etica_valores            TINYINT(1) NOT NULL DEFAULT 0,
    observaciones            TEXT NULL,
    fecha_registro           DATE NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_seguimiento_practica_semana (practica_id, semana),
    CONSTRAINT fk_seguimiento_practica FOREIGN KEY (practica_id) REFERENCES pp_practicas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pp_entregas (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    practica_id      INT UNSIGNED NOT NULL,
    tipo             ENUM('avance_1','avance_2','informe_final') NOT NULL,
    fecha_limite     DATE NULL,
    fecha_entrega    DATE NULL,
    entregado        TINYINT(1) NOT NULL DEFAULT 0,
    nota             DECIMAL(2,1) NULL,
    retroalimentacion TEXT NULL,
    creado_en        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_entregas_practica_tipo (practica_id, tipo),
    CONSTRAINT fk_entregas_practica FOREIGN KEY (practica_id) REFERENCES pp_practicas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pp_bitacora (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    practica_id INT UNSIGNED NOT NULL,
    usuario_id  INT UNSIGNED NULL,
    evento      VARCHAR(255) NOT NULL,
    detalle     TEXT NULL,
    creado_en   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_bitacora_practica (practica_id),
    KEY idx_bitacora_usuario (usuario_id),
    CONSTRAINT fk_bitacora_practica FOREIGN KEY (practica_id) REFERENCES pp_practicas (id) ON DELETE CASCADE,
    CONSTRAINT fk_bitacora_usuario FOREIGN KEY (usuario_id) REFERENCES pp_usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
