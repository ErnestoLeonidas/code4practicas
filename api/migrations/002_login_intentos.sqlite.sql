-- Migración 002_login_intentos (SQLite) — desarrollo local.
-- Equivalente a 002_login_intentos.mysql.sql. Registra intentos de login
-- para el rate limiting del endpoint de auth. creado_en se inserta desde PHP.

CREATE TABLE IF NOT EXISTS pp_login_intentos (
    id        INTEGER PRIMARY KEY AUTOINCREMENT,
    correo    TEXT NOT NULL,
    ip        TEXT,
    exitoso   INTEGER NOT NULL DEFAULT 0,
    creado_en TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_login_correo_fecha ON pp_login_intentos (correo, creado_en);
