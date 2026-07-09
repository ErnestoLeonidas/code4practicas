-- Migración 003 (SQLite) — añade columna activo a pp_empresas y pp_supervisores.
ALTER TABLE pp_empresas ADD COLUMN activo INTEGER NOT NULL DEFAULT 1;
ALTER TABLE pp_supervisores ADD COLUMN activo INTEGER NOT NULL DEFAULT 1;
