# CHANGELOG

Todos los cambios relevantes de este proyecto se documentan aquí.
Formato basado en [Keep a Changelog](https://keepachangelog.com/es/1.0.0/).

## [0.2.0] — 2026-07-08

### Añadido
- CRUD de usuarios (solo `admin`): `GET/POST/PUT/DELETE /api/usuarios` con paginación y filtros server-side (`?page=&per_page=&q=&rol=&activo=`) y borrado lógico (`activo=0`).
- Al crear un usuario, el sistema genera una contraseña segura (14 caracteres), guarda solo el hash y marca `debe_cambiar_password=1`. La contraseña se devuelve una única vez (`password_generada`) y, si SMTP está configurado, se envía por correo (`correo_enviado`).
- `POST /api/usuarios/{id}/regenerar-password`: el admin regenera la contraseña de un usuario (invalida la anterior y fuerza cambio).
- `POST /api/auth/cambiar-password` (autenticado): verifica la contraseña actual, exige mínimo 8 caracteres y limpia `debe_cambiar_password`.
- Guardas de integridad: un admin no puede desactivarse a sí mismo (`no_puede_desactivarse`) ni dejar el sistema sin administradores activos (`ultimo_admin`).
- `Mailer` (stub para v0.3.0): envía credenciales por PHPMailer si hay SMTP y la clase está disponible; en dev devuelve `false` sin lanzar.
- `Support/Validaciones` (email + dominio institucional), reutilizado por `AuthController` y `UsuarioController`.
- Frontend: vista `Usuarios` (tabla con badges de rol/estado, buscador y filtros, paginación, crear/editar en modal, mostrar la contraseña generada una sola vez con botón copiar, regenerar, activar/desactivar), vista `CambiarPassword`, store Pinia `usuarios`, getter `esAdmin` y acción `cambiarPassword` en el store `auth`.
- Guard de router: fuerza la pantalla de cambio de contraseña cuando `debe_cambiar_password` está activo y restringe `/usuarios` a administradores; enlace "Usuarios" en la navbar solo para admin.

### Nuevos códigos de error
`correo_duplicado`, `rol_invalido`, `no_encontrado`, `no_puede_desactivarse`, `ultimo_admin`, `password_actual_incorrecta`, `password_debil`.

### Verificado
- Integración end-to-end por el proxy de Vite: el admin crea un docente, el docente entra con la contraseña generada, es forzado a cambiarla (`debe_cambiar_password` pasa de `true` a `false`) y la contraseña anterior queda invalidada (401); el rol `docente` recibe 403 en los endpoints de administración.

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
