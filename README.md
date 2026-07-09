# Seguimiento de Prácticas Profesionales — Duoc UC

Aplicación web para que el/la **docente de práctica** realice el seguimiento de las prácticas profesionales de sus estudiantes: registro de estudiantes, empresas y supervisores (jefes), plan de seguimiento semanal de 12 semanas con checklist docente y semáforo de riesgo, control de entregas (Avance 1, Avance 2, Informe Final) y estados del proceso de práctica.

> Se construye por versiones incrementales. El plan completo está en **[`ROADMAP.md`](ROADMAP.md)** y el detalle de cada entrega en **[`CHANGELOG.md`](CHANGELOG.md)**.

## Stack

| Capa | Tecnología |
|---|---|
| Backend | PHP 8.1+ (API REST, front controller propio, sin framework pesado) |
| Base de datos | SQLite en desarrollo · MySQL/MariaDB en producción (PDO + prepared statements, prefijo `pp_`) |
| Frontend | Vue 3 + Vite + Vue Router + Pinia + Tailwind CSS |
| Autenticación | Sesiones PHP con cookie `HttpOnly` + `SameSite=Lax` |
| Despliegue | Hosting compartido (cPanel/Apache), build estático + API PHP |

## Requisitos

- **PHP 8.1+** con extensiones `pdo_sqlite` (desarrollo) y `pdo_mysql` (producción).
- **Node.js 18+** y **npm** (solo para desarrollo/compilación del frontend; el servidor de producción no ejecuta Node).

## Puesta en marcha (desarrollo local)

### 1. Configuración

Copia la plantilla de configuración (el archivo real `config.php` no se versiona):

```bash
cp api/config.example.php api/config.php
```

Por defecto usa **SQLite** (`api/data/app.sqlite`), así que no necesitas un servidor MySQL para desarrollar.

### 2. Base de datos y usuario administrador

```bash
php api/migrate.php        # crea las tablas (aplica migrations/*.sqlite.sql)
php api/seed_admin.php      # crea el admin e imprime su contraseña UNA sola vez
```

Guarda la contraseña que imprime `seed_admin.php`: el correo por defecto es `admin@profesor.duoc.cl`. En el primer ingreso se te pedirá cambiarla.

### 3. Backend (API PHP)

```bash
php -S 127.0.0.1:18081 api/router.php
```

### 4. Frontend (SPA Vue)

En otra terminal:

```bash
npm install --prefix frontend      # solo la primera vez
npm run dev --prefix frontend      # abre http://localhost:51731
```

El dev server de Vite sirve la SPA en **`:51731`** y hace proxy de `/api` hacia el backend PHP en **`:18081`**.

### Compilar para producción

```bash
npm run build --prefix frontend    # genera frontend/dist/ (incluye .htaccess de SPA)
```

## Estructura del proyecto

```
/
├── api/                    # Backend PHP
│   ├── index.php           # Front controller (CORS, sesión, ruteo)
│   ├── router.php          # Router del servidor embebido de PHP (solo dev)
│   ├── migrate.php         # Runner de migraciones (idempotente, por driver)
│   ├── seed_admin.php      # Crea el usuario admin
│   ├── config.example.php  # Plantilla de configuración (config.php no se versiona)
│   ├── migrations/         # 00N_*.{mysql,sqlite}.sql
│   └── src/
│       ├── Controllers/    # HealthController, AuthController, ...
│       ├── Models/         # Usuario, LoginIntento, ...
│       ├── Middleware/     # AuthMiddleware, RoleMiddleware
│       ├── Services/       # Auth (sesión), Password, Mailer
│       ├── Http/           # Router, Response, Request, HttpException
│       ├── Support/        # Utilidades (validaciones)
│       ├── Config.php · Database.php · autoload.php
│       └── data/           # SQLite local (no versionado)
├── frontend/               # SPA Vue 3 + Vite
│   ├── src/
│   │   ├── views/          # Login, Home, ...
│   │   ├── components/
│   │   ├── stores/         # Pinia (auth, ...)
│   │   ├── router/
│   │   └── services/api.js # wrapper de fetch
│   └── vite.config.js      # proxy /api → :18081
├── ROADMAP.md · CHANGELOG.md · CLAUDE.md
```

## API

Todas las rutas cuelgan de `/api`. Respuestas en JSON; los errores tienen la forma `{ "error": { "code", "message" } }`.

| Método | Ruta | Descripción |
|---|---|---|
| `GET` | `/api/health` | Estado y versión de la API |
| `POST` | `/api/auth/login` | Inicia sesión (correo institucional + contraseña) |
| `POST` | `/api/auth/logout` | Cierra sesión |
| `GET` | `/api/auth/me` | Datos del usuario en sesión |
| `POST` | `/api/auth/cambiar-password` | Cambia la contraseña propia (obligatorio en el primer ingreso) |
| `POST` | `/api/auth/recuperar` | Solicita un enlace de recuperación (respuesta siempre genérica) |
| `POST` | `/api/auth/restablecer` | Restablece la contraseña con el token del correo (TTL 60 min) |
| `GET/POST/PUT/DELETE` | `/api/usuarios` | Gestión de usuarios — **solo admin** (paginado, borrado lógico) |
| `POST` | `/api/usuarios/{id}/regenerar-password` | Regenera la contraseña de un usuario — **solo admin** |
| `GET/POST/PUT/DELETE` | `/api/carreras` | Gestión de carreras — **solo admin** |
| `GET/POST/PUT/DELETE` | `/api/estudiantes` | Gestión de estudiantes (docente ve solo los suyos) |

La autenticación usa una cookie de sesión `pp_sesion` (`HttpOnly` + `SameSite=Lax`). El login solo admite correos de dominios institucionales (configurables en `config.php`) y aplica límite de intentos (5 por correo cada 15 minutos). Las cuentas las crea el admin: **la contraseña la genera el sistema** (se muestra una vez y, si hay SMTP, se envía por correo) y el usuario debe cambiarla en su primer ingreso. Las siguientes versiones (estudiantes, empresas, prácticas, seguimiento y notas) están descritas en `ROADMAP.md`.

## Base de datos

- **Desarrollo:** SQLite en un archivo (`api/data/app.sqlite`). Cero configuración.
- **Producción:** MySQL/MariaDB. Ejecuta manualmente las migraciones `migrations/*.mysql.sql` en phpMyAdmin, en orden.

El código de acceso a datos es agnóstico de driver (se elige en `config.php`), por lo que el mismo backend corre sobre SQLite o MySQL.

## Despliegue (hosting compartido)

Resumen (guía detallada en `docs/deploy.md`, a partir de v1.0.0):

1. `npm run build --prefix frontend` y sube el contenido de `frontend/dist/` a `/public_html/`.
2. Sube `api/` (incluyendo `vendor/` ya instalado) a `/public_html/api/`.
3. Crea la BD MySQL en cPanel y ejecuta las migraciones `*.mysql.sql` en phpMyAdmin.
4. Crea `config.php` de producción (credenciales de BD, SMTP, dominios permitidos, `env` = `prod`).
5. Verifica el `.htaccess` (fallback SPA y rewrite de `/api`) y ejecuta el seed del admin.

## Convenciones

- Identificadores y esquema en **español** (`estudiantes`, `practicas`, `pp_usuarios`).
- Validación **en el backend** aunque el frontend también valide; códigos HTTP correctos.
- Nunca se versionan `config.php`, contraseñas ni tokens.
- Se trabaja **una versión a la vez**: se cierra y verifica antes de pasar a la siguiente, actualizando `CHANGELOG.md`.
