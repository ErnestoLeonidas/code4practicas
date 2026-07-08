-- Migración 002_login_intentos (MySQL / MariaDB) — canónica para producción.
-- Registra los intentos de login para el rate limiting del endpoint de auth.

CREATE TABLE IF NOT EXISTS pp_login_intentos (
    id        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    correo    VARCHAR(190) NOT NULL,
    ip        VARCHAR(45) NULL,
    exitoso   TINYINT(1) NOT NULL DEFAULT 0,
    creado_en DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_login_correo_fecha (correo, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
