# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project state

**Built through v0.2.0** (see `CHANGELOG.md`): full-stack scaffolding (`api/` + `frontend/`), SQLite-backed schema, authentication (login/logout/me with PHP sessions + route guard), and admin user management (CRUD `/api/usuarios`, system-generated passwords, forced first-login password change, role-restricted routes). Not yet committed to git. `ROADMAP.md` is the authoritative spec and build plan; read the relevant version section before building the next one. Do not invent architecture that contradicts it.

The app is a **web system for tracking professional internships ("prácticas profesionales") at Duoc UC**. The internship-supervising teacher (`docente`) manages students, companies, workplace supervisors, a 12-week follow-up checklist, and three graded deliverables. Students have no login in v1.0.0.

**Dev ports (configured in `frontend/vite.config.js` + `api/config.php`):** Vite dev server on **51731**, PHP API on **18081**; Vite proxies `/api` → `127.0.0.1:18081`. `app_url` (CORS origin) must match the Vite port.

## Working method (non-negotiable)

- Build **one version at a time** following the `# PLAN DE VERSIONES` section of `ROADMAP.md` (v0.0.1 → v1.0.0). Each version is a functional, tested, deployable increment.
- **Do not start the next version** until the current one meets its "Criterio de cierre" and is verified.
- On closing each version, update `CHANGELOG.md`.
- **Claude does not run git commits.** When development for a task/version is finished, announce that it's done (and summarize what changed) — the user makes the commits. If preparing a commit message for the user, use Spanish, format: `feat(v0.4.0): CRUD de estudiantes con filtros`.
- New dependencies must be justified in `CHANGELOG.md`.

## Hard constraint: shared hosting (cPanel/Apache)

Every technical decision is shaped by deploying to **shared hosting with no Node and no guaranteed Composer on the server**:

- No background processes, no websockets, no cron-driven app logic.
- **`vendor/` is committed pre-installed** (PHPMailer, optionally Slim) and uploaded as-is — you cannot rely on `composer install` running on the server.
- Frontend ships as a static `dist/` build; the server never runs `npm`.
- API routes live under `/api/*` and are resolved via `.htaccess` → `api/index.php` (front controller). Root `.htaccess` does SPA fallback to `index.html`, excluding `/api`.
- Secrets live in `api/config.php`, which is **never committed** — the repo carries `config.example.php`. Never commit `config.php`, passwords, or tokens.

## Architecture

Two-tier, one database:

- **Backend** — `api/`: PHP 8.1+ REST API. Front controller `api/index.php` routes to `src/Controllers/`, with `src/Models/`, `src/Middleware/` (auth, cors, roles), `src/Services/` (mailer, passwords, grade calc), and `src/Database.php` (PDO, `utf8mb4`, reading `config.php`). SQL migrations in `api/migrations/` (`001_init.sql`, ...) are plain SQL run manually in phpMyAdmin, **in order**.
- **Frontend** — `frontend/`: Vue 3 + Vite + Vue Router + Pinia. `src/services/api.js` wraps fetch/axios; Pinia `stores/` hold auth/session state; router guards protect routes. Dev proxies `/api` to the PHP server via `vite.config.js`.
- **Database** — MySQL/MariaDB via PDO **prepared statements only**. All tables prefixed `pp_`. **Local development uses SQLite** (a file DB) to avoid needing a MySQL server; production/target is MySQL and we migrate later. Therefore `Database.php` must be driver-agnostic (pick `sqlite`/`mysql` from `config.php`), keep SQL portable, and migrations ship in both a MySQL variant (canonical, per roadmap) and a SQLite variant for local dev.
- **Auth** — PHP sessions with `HttpOnly` + `SameSite=Lax` cookies (chosen over JWT for shared-hosting simplicity).

## Domain rules that span multiple files

These are business invariants, not UI details — enforce them in the **backend** even if the frontend also validates:

- **Grading:** final grade = `avance_1*0.25 + avance_2*0.25 + informe_final*0.50`. Scale 1.0–7.0, one decimal.
- **Approval:** `aprobada` only if `informe_final.nota >= 4.0`, else `reprobada`.
- **Late deliverable:** if `fecha_limite` passed and not delivered, system *suggests* nota 1.0 — the `docente` confirms it (never auto-applied).
- **Weekly risk semáforo:** `% >= 85` → bajo (green), `60–84` → medio (yellow), `< 60` → alto (red). Percentage is derived from the 8 checklist items (score 0–8).
- **On creating a `pp_practicas` row, auto-generate:** 12 `pp_seguimiento_semanal` rows (predefined weekly focos) and 3 `pp_entregas` rows (avance 1 ≈ start + 5 weeks, avance 2 ≈ start + 8 weeks, informe final ≈ end date; dates editable).
- **Práctica state machine:** `pendiente → en_curso → avance_1 → avance_2 → informe_final → aprobada|reprobada`; `abandonada` reachable from any active state. State changes go through validation and are logged to `pp_bitacora`.
- **Roles:** `admin` sees/manages everything; `docente` only sees/edits their own assigned students and their prácticas.
- **Login:** only institutional email domains (`@duoc.cl`, `@duocuc.cl`, `@profesor.duoc.cl` — list configurable in `config.php`). Passwords are system-generated on account creation (stored only as hash), with forced change on first login (`debe_cambiar_password`).

The full target schema and per-version task lists are in `ROADMAP.md` §4 and the version plan. Consult it rather than duplicating table definitions here.

## Conventions

- **Language:** identifiers in Spanish, matching the schema (`estudiantes`, `practicas`, `seguimiento_semanal`); brief comments.
- **API:** always JSON. Correct HTTP codes (200/201/400/401/403/404/422/500). Standardized errors: `{ "error": { "code", "message" } }`.
- **List endpoints:** server-side pagination/filtering via query params, e.g. `?page=&per_page=&semestre=&carrera_id=&q=`.
- Deletes are logical (`activo=0`), not physical.

## Commands

Local dev needs two processes running at once (from the repo root):

- **Backend API** (PHP built-in server, dev only): `php -S 127.0.0.1:18081 api/router.php` — `api/router.php` funnels every request to the front controller.
- **Frontend** dev server (proxies `/api` → PHP): `npm run dev --prefix frontend` (serves on `:51731`).
- **Frontend build** (output `dist/`, includes `public/.htaccess` SPA fallback): `npm run build --prefix frontend`.
- **Migrations** (idempotent, picks the variant for the configured driver): `php api/migrate.php`. Files are `migrations/NNN_*.{mysql,sqlite}.sql`; on shared hosting run the `*.mysql.sql` files manually in phpMyAdmin instead.
- **Seed admin** (prints a generated password once; `--force` regenerates): `php api/seed_admin.php [correo]`. Default correo `admin@profesor.duoc.cl`.

First-time local setup: `cp api/config.example.php api/config.php` (driver defaults to `sqlite`), then `php api/migrate.php` and `php api/seed_admin.php`.

Contracts: `GET /api/health`. Auth: `POST /api/auth/login {correo,password}`, `POST /api/auth/logout`, `GET /api/auth/me`, `POST /api/auth/cambiar-password {password_actual,password_nueva}` (session cookie `pp_sesion`, HttpOnly + SameSite=Lax). Admin-only (RoleMiddleware `permitir('admin')`): `GET/POST/PUT/DELETE /api/usuarios` (paginated `?page=&per_page=&q=&rol=&activo=`, logical delete), `POST /api/usuarios/{id}/regenerar-password`. Passwords are always system-generated (`Password::generar`), returned once as `password_generada`; email delivery via `Services/Mailer` (stub until v0.3.0, returns false without SMTP). Error codes so far: `datos_invalidos`, `dominio_no_institucional`, `credenciales_invalidas`, `demasiados_intentos`, `no_autenticado`, `sin_permiso`, `correo_duplicado`, `rol_invalido`, `no_encontrado`, `no_puede_desactivarse`, `ultimo_admin`, `password_actual_incorrecta`, `password_debil`.

Guard invariants: never leave zero active admins and no self-deactivation (enforced in `UsuarioController` via `Usuario::contarAdminsActivos`). Domain/email validation lives in `Support/Validaciones` (reused by auth + usuarios). SQLite has no `ON UPDATE`, so writes set `actualizado_en` explicitly.

## Backend request lifecycle

`api/index.php` (front controller): loads `config.php` → sets CORS from `app_url` → handles OPTIONS → `Auth::iniciar()` (starts the session) → registers routes on the `Router` → `dispatch()`. Routes take an optional 3rd arg: a list of middleware (`[[AuthMiddleware::class,'handle']]`). Middleware run before the handler and **throw `App\Http\HttpException`** to abort; the front controller catches it and renders `{error:{code,message}}` with the right status. Controllers may either throw `HttpException` or call `Response::error(...)` directly (both used in the codebase). Session-backed auth lives in `App\Services\Auth` (lazy-loads the active user per request); never trust the client for role/identity.
