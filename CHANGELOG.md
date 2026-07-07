# CHANGELOG

Todos los cambios relevantes de este proyecto se documentan aquí.
Formato basado en [Keep a Changelog](https://keepachangelog.com/es/1.0.0/).

## [0.0.1] — 2026-07-06

### Añadido
- Estructura de carpetas del proyecto (`api/` backend PHP, `frontend/` Vue 3, `docs/`).
- Backend PHP con front controller (`api/index.php`) y router mínimo.
- Endpoint `GET /api/health` → `{"status":"ok","version":"0.0.1"}`.
- `.htaccess` de la API (rewrite a `index.php`) y del root (SPA fallback a `index.html`, excluyendo `/api`).
- `api/src/Database.php`: conexión PDO **agnóstica de driver** (SQLite en local, MySQL en producción), charset `utf8mb4`, leyendo `config.php`.
- Migración `001_init` en dos variantes: `001_init.mysql.sql` (canónica, para producción) y `001_init.sqlite.sql` (desarrollo local). Crea todas las tablas del modelo (`pp_*`).
- Runner de migraciones `api/migrate.php` (ejecuta la variante según el driver configurado).
- Proyecto Vue 3 + Vite + Vue Router + Pinia + Tailwind CSS. Vista `Home` que consume `/api/health` y muestra el estado de la API.
- Proxy de Vite (`/api` → servidor PHP local) para desarrollo.
- `api/config.example.php` (plantilla con soporte SQLite/MySQL), `.gitignore`, `CHANGELOG.md`.

### Decisiones
- **Framework CSS:** Tailwind CSS (fijado en v0.0.1, no se cambia).
- **Base de datos en desarrollo:** SQLite local (archivo en `api/data/`) para no depender de un servidor MySQL; la migración a MySQL se hará más adelante. El código de acceso a datos es agnóstico de driver.
- **Autenticación (a implementar en v0.1.0):** sesiones PHP con cookie `HttpOnly` + `SameSite=Lax`.
