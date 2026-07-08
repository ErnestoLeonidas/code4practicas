# CHANGELOG

Todos los cambios relevantes de este proyecto se documentan aquí.
Formato basado en [Keep a Changelog](https://keepachangelog.com/es/1.0.0/).

## [0.1.0] — 2026-07-06

### Añadido
- Autenticación por sesión: `POST /api/auth/login`, `POST /api/auth/logout`, `GET /api/auth/me`. Cookie `pp_sesion` (`HttpOnly` + `SameSite=Lax`, `Secure` solo en producción).
- Login valida dominio institucional (`@duoc.cl`, `@duocuc.cl`, `@profesor.duoc.cl`, configurable) y verifica la contraseña con `password_verify`.
- Rate limiting de login: máx. 5 intentos fallidos / 15 min por correo (tabla `pp_login_intentos`, migración `002`). El umbral se calcula en PHP para ser portable SQLite/MySQL.
- Router con soporte de **middleware** por ruta; `AuthMiddleware` (401 `no_autenticado`) y `RoleMiddleware::permitir(...)` (403 `sin_permiso`, listo para v0.2.0).
- `App\Http\HttpException` + captura centralizada en el front controller → errores `{ "error": { "code", "message" } }`.
- Servicio `Password` (generación segura con CSPRNG, `hash`/`verify`), servicio `Auth` (sesión), modelos `Usuario` y `LoginIntento`, helper `Request` (JSON/IP).
- `api/seed_admin.php`: crea el usuario `admin` con contraseña generada e impresa una sola vez (`--force` la regenera); `debe_cambiar_password=1`.
- Frontend: store Pinia `auth`, vista `Login`, guard de router (`requiereAuth` → redirige a `/login` con `redirect`), navbar con usuario/rol y botón **Salir**, saludo en `Home`.

### Cambios
- `app_url` (origen CORS) apunta al dev server de Vite (`http://localhost:51731`); backend PHP en dev corre en `18081`.

### Verificado
- Integración end-to-end a través del proxy de Vite: la sesión (`pp_sesion`) sobrevive el salto navegador→Vite→PHP; `me` responde 200 con sesión y 401 sin ella; login rechaza dominios no institucionales (422) y aplica rate limit (429).

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
