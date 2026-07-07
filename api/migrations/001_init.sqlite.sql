-- Migración 001_init (SQLite) — desarrollo local.
-- Equivalente a 001_init.mysql.sql. ENUM -> TEXT + CHECK; AUTO_INCREMENT -> AUTOINCREMENT.
-- La actualización de `actualizado_en` se maneja en el backend (SQLite no tiene ON UPDATE).

CREATE TABLE IF NOT EXISTS pp_usuarios (
    id                    INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre                TEXT NOT NULL,
    apellido              TEXT NOT NULL,
    correo                TEXT NOT NULL UNIQUE,
    password_hash         TEXT NOT NULL,
    rol                   TEXT NOT NULL DEFAULT 'docente' CHECK (rol IN ('admin','docente')),
    debe_cambiar_password INTEGER NOT NULL DEFAULT 1,
    activo                INTEGER NOT NULL DEFAULT 1,
    creado_en             TEXT NOT NULL DEFAULT (datetime('now')),
    actualizado_en        TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS pp_tokens_recuperacion (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    usuario_id INTEGER NOT NULL REFERENCES pp_usuarios (id) ON DELETE CASCADE,
    token_hash TEXT NOT NULL,
    expira_en  TEXT NOT NULL,
    usado      INTEGER NOT NULL DEFAULT 0,
    creado_en  TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_tokens_usuario ON pp_tokens_recuperacion (usuario_id);

CREATE TABLE IF NOT EXISTS pp_carreras (
    id      INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre  TEXT NOT NULL,
    escuela TEXT,
    activo  INTEGER NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS pp_estudiantes (
    id                        INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre                    TEXT NOT NULL,
    apellido                  TEXT NOT NULL,
    rut                       TEXT NOT NULL UNIQUE,
    correo_duoc               TEXT,
    telefono                  TEXT,
    carrera_id                INTEGER REFERENCES pp_carreras (id) ON DELETE SET NULL,
    semestre_ingreso_practica TEXT,
    docente_id                INTEGER REFERENCES pp_usuarios (id) ON DELETE SET NULL,
    activo                    INTEGER NOT NULL DEFAULT 1,
    creado_en                 TEXT NOT NULL DEFAULT (datetime('now')),
    actualizado_en            TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_estudiantes_carrera ON pp_estudiantes (carrera_id);
CREATE INDEX IF NOT EXISTS idx_estudiantes_docente ON pp_estudiantes (docente_id);

CREATE TABLE IF NOT EXISTS pp_empresas (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    nombre      TEXT NOT NULL,
    rut_empresa TEXT,
    giro        TEXT,
    direccion   TEXT,
    ciudad      TEXT,
    telefono    TEXT,
    sitio_web   TEXT,
    creado_en   TEXT NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS pp_supervisores (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    empresa_id INTEGER NOT NULL REFERENCES pp_empresas (id) ON DELETE CASCADE,
    nombre     TEXT NOT NULL,
    apellido   TEXT NOT NULL,
    profesion  TEXT,
    cargo      TEXT,
    telefono   TEXT,
    correo     TEXT,
    creado_en  TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_supervisores_empresa ON pp_supervisores (empresa_id);

CREATE TABLE IF NOT EXISTS pp_practicas (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    estudiante_id  INTEGER NOT NULL REFERENCES pp_estudiantes (id) ON DELETE CASCADE,
    empresa_id     INTEGER NOT NULL REFERENCES pp_empresas (id) ON DELETE RESTRICT,
    supervisor_id  INTEGER REFERENCES pp_supervisores (id) ON DELETE SET NULL,
    semestre       TEXT,
    fecha_inicio   TEXT,
    fecha_termino  TEXT,
    estado         TEXT NOT NULL DEFAULT 'pendiente'
                   CHECK (estado IN ('pendiente','en_curso','avance_1','avance_2',
                                     'informe_final','aprobada','reprobada','abandonada')),
    horas_totales  INTEGER,
    observaciones  TEXT,
    creado_en      TEXT NOT NULL DEFAULT (datetime('now')),
    actualizado_en TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_practicas_estudiante ON pp_practicas (estudiante_id);
CREATE INDEX IF NOT EXISTS idx_practicas_empresa ON pp_practicas (empresa_id);
CREATE INDEX IF NOT EXISTS idx_practicas_supervisor ON pp_practicas (supervisor_id);

CREATE TABLE IF NOT EXISTS pp_seguimiento_semanal (
    id                        INTEGER PRIMARY KEY AUTOINCREMENT,
    practica_id               INTEGER NOT NULL REFERENCES pp_practicas (id) ON DELETE CASCADE,
    semana                    INTEGER NOT NULL,
    foco                      TEXT,
    reunion_1a1               INTEGER NOT NULL DEFAULT 0,
    orientaciones_claras      INTEGER NOT NULL DEFAULT 0,
    retroalimentacion         INTEGER NOT NULL DEFAULT 0,
    evidencia_registrada      INTEGER NOT NULL DEFAULT 0,
    disponibilidad_comunicada INTEGER NOT NULL DEFAULT 0,
    ajuste_individual         INTEGER NOT NULL DEFAULT 0,
    reflexion_guiada          INTEGER NOT NULL DEFAULT 0,
    etica_valores             INTEGER NOT NULL DEFAULT 0,
    observaciones             TEXT,
    fecha_registro            TEXT,
    UNIQUE (practica_id, semana)
);

CREATE TABLE IF NOT EXISTS pp_entregas (
    id                INTEGER PRIMARY KEY AUTOINCREMENT,
    practica_id       INTEGER NOT NULL REFERENCES pp_practicas (id) ON DELETE CASCADE,
    tipo              TEXT NOT NULL CHECK (tipo IN ('avance_1','avance_2','informe_final')),
    fecha_limite      TEXT,
    fecha_entrega     TEXT,
    entregado         INTEGER NOT NULL DEFAULT 0,
    nota              REAL,
    retroalimentacion TEXT,
    creado_en         TEXT NOT NULL DEFAULT (datetime('now')),
    actualizado_en    TEXT NOT NULL DEFAULT (datetime('now')),
    UNIQUE (practica_id, tipo)
);

CREATE TABLE IF NOT EXISTS pp_bitacora (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    practica_id INTEGER NOT NULL REFERENCES pp_practicas (id) ON DELETE CASCADE,
    usuario_id  INTEGER REFERENCES pp_usuarios (id) ON DELETE SET NULL,
    evento      TEXT NOT NULL,
    detalle     TEXT,
    creado_en   TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_bitacora_practica ON pp_bitacora (practica_id);
CREATE INDEX IF NOT EXISTS idx_bitacora_usuario ON pp_bitacora (usuario_id);
