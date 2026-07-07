# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project state

This is a **greenfield repository**: only `ROADMAP.md` exists — no source code, no commits yet. `ROADMAP.md` is the authoritative spec and build plan; read it before writing anything. Do not invent architecture that contradicts it.

The app is a **web system for tracking professional internships ("prácticas profesionales") at Duoc UC**. The internship-supervising teacher (`docente`) manages students, companies, workplace supervisors, a 12-week follow-up checklist, and three graded deliverables. Students have no login in v1.0.0.

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

The scaffolding does not exist yet — these apply once v0.0.1 creates `frontend/` and `api/`:

- Frontend dev server (proxies `/api`): `cd frontend && npm run dev`
- Frontend production build (output `dist/`): `cd frontend && npm run build`
- Backend: run under Apache with `mod_php`/PHP-FPM, or locally via `php -S localhost:8000 -t api` (route through `api/index.php`).
- Migrations: execute `api/migrations/*.sql` in order in phpMyAdmin / MySQL client (no migration runner on shared hosting).

Health check contract for v0.0.1: `GET /api/health` → `{"status":"ok","version":"0.0.1"}`.
