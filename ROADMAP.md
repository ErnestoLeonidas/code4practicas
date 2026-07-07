# ROADMAP — Sistema de Seguimiento de Prácticas Profesionales (Duoc UC)

> Documento guía para construir la aplicación por etapas con Claude Code.
> Cada versión es un incremento funcional, probado y desplegable.
> **Regla de trabajo:** completar y verificar una versión antes de pasar a la siguiente. Al cerrar cada versión, actualizar `CHANGELOG.md`.

---

## 1. Contexto del proyecto

Aplicación web para que el/la docente de práctica realice el **seguimiento de las prácticas profesionales** de sus estudiantes: registro de estudiantes, empresas y supervisores (jefes), seguimiento semanal (plan de 12 semanas), control de entregas (Avance 1 en semana 5, Avance 2 en semana 8, Informe Final) y estados del proceso de práctica.

Reglas del proceso académico que la app debe respetar:

- Evaluación del informe: **Avance 1 = 25%, Avance 2 = 25%, Informe Final = 50%**.
- La práctica se aprueba solo si la **nota del Informe Final ≥ 4.0** (escala 1.0 a 7.0).
- La no entrega de un avance en la fecha establecida se evalúa con **nota 1.0**.
- El seguimiento semanal se basa en el plan de 12 semanas con checklist docente (1:1 realizada, orientaciones claras, retroalimentación entregada, evidencia registrada, disponibilidad comunicada, ajuste individual, reflexión guiada, ética/valores abordados), con **% de cumplimiento y nivel de riesgo** por semana.
- La carga inicial de datos (estudiantes, empresas) será **manual desde la interfaz de administración**.

## 2. Stack tecnológico y restricciones

| Capa | Tecnología | Notas |
|---|---|---|
| Backend | **PHP 8.1+** (API REST, sin framework pesado; usar **Slim 4** o estructura propia con front-controller) | Debe correr en hosting compartido (cPanel, Apache, mod_php o PHP-FPM) |
| Base de datos | **MySQL / MariaDB** con PDO + prepared statements | Una sola BD, prefijo de tablas `pp_` |
| Frontend | **Vue 3** + Vite + Vue Router + Pinia | El build (`dist/`) se sube al hosting; **no hay Node en el servidor** |
| Estilos | Tailwind CSS (o Bootstrap 5 si se prefiere simplicidad) | Definir en v0.0.1 y no cambiar |
| Autenticación | Sesiones PHP con cookie `HttpOnly` + `SameSite=Lax` (alternativa: JWT) | Elegir sesiones por simplicidad en hosting compartido |
| Correo | **PHPMailer** vía SMTP del hosting | Para envío de credenciales y recuperación de contraseña |
| Despliegue | Subida por FTP/File Manager: `/public_html/api/` (PHP) y `/public_html/` (build Vue) | `.htaccess` para rewrite de rutas SPA y de la API |

**Restricciones de hosting compartido a respetar en todo el desarrollo:**
- Sin procesos en segundo plano ni websockets; sin Composer garantizado en servidor (vendor/ se sube ya instalado).
- Rutas de la API bajo `/api/*` resueltas con `.htaccess` → `api/index.php`.
- Variables sensibles en archivo `config.php` fuera de control de versiones (usar `config.example.php` en el repo).

## 3. Roles de usuario

| Rol | Permisos |
|---|---|
| `admin` | Todo: gestión de usuarios docentes, estudiantes, carreras, empresas, parámetros |
| `docente` | Gestión de sus estudiantes asignados, seguimiento, entregas, notas |

(El estudiante **no** tiene acceso en la v1.0.0; queda como idea post-1.0.)

## 4. Modelo de datos (esquema objetivo v1.0.0)

```
pp_usuarios
  id, nombre, apellido, correo (UNIQUE, dominio @duoc.cl / @duocuc.cl / @profesor.duoc.cl),
  password_hash, rol ENUM('admin','docente'), debe_cambiar_password TINYINT,
  activo TINYINT, creado_en, actualizado_en

pp_tokens_recuperacion
  id, usuario_id FK, token_hash, expira_en, usado TINYINT, creado_en

pp_carreras
  id, nombre, escuela, activo

pp_estudiantes
  id, nombre, apellido, rut (UNIQUE), correo_duoc, telefono,
  carrera_id FK, semestre_ingreso_practica VARCHAR(6) (ej: '2026-1'),
  docente_id FK -> pp_usuarios, activo, creado_en, actualizado_en

pp_empresas
  id, nombre, rut_empresa, giro, direccion, ciudad, telefono, sitio_web, creado_en

pp_supervisores  (el "jefe" en el centro de práctica)
  id, empresa_id FK, nombre, apellido, profesion, cargo, telefono, correo, creado_en

pp_practicas   (una práctica = un estudiante en una empresa, en un semestre)
  id, estudiante_id FK, empresa_id FK, supervisor_id FK,
  semestre VARCHAR(6), fecha_inicio, fecha_termino,
  estado ENUM('pendiente','en_curso','avance_1','avance_2','informe_final',
              'aprobada','reprobada','abandonada') DEFAULT 'pendiente',
  horas_totales INT NULL, observaciones TEXT, creado_en, actualizado_en

pp_seguimiento_semanal   (checklist docente, 12 semanas por práctica)
  id, practica_id FK, semana TINYINT (1..12), foco VARCHAR(255),
  reunion_1a1 TINYINT, orientaciones_claras TINYINT, retroalimentacion TINYINT,
  evidencia_registrada TINYINT, disponibilidad_comunicada TINYINT,
  ajuste_individual TINYINT, reflexion_guiada TINYINT, etica_valores TINYINT,
  observaciones TEXT, fecha_registro DATE,
  -- calculados en backend: puntaje (0-8), porcentaje, riesgo ENUM('bajo','medio','alto')
  UNIQUE(practica_id, semana)

pp_entregas
  id, practica_id FK,
  tipo ENUM('avance_1','avance_2','informe_final'),
  fecha_limite DATE, fecha_entrega DATE NULL,
  entregado TINYINT, nota DECIMAL(2,1) NULL (1.0-7.0),
  retroalimentacion TEXT, creado_en, actualizado_en,
  UNIQUE(practica_id, tipo)

pp_bitacora   (historial de cambios de estado y eventos relevantes)
  id, practica_id FK, usuario_id FK, evento VARCHAR(255), detalle TEXT, creado_en
```

**Reglas de negocio derivadas:**
- Nota final ponderada = `avance_1*0.25 + avance_2*0.25 + informe_final*0.50`.
- Estado `aprobada` solo si `informe_final.nota >= 4.0`; si `< 4.0` → `reprobada`.
- Si `fecha_limite` pasó y `entregado = 0` → el sistema sugiere nota 1.0 (el docente confirma).
- Riesgo semanal: `% >= 85` → bajo, `60–84` → medio, `< 60` → alto (semáforo verde/amarillo/rojo).
- Al crear una práctica, se generan automáticamente las 12 filas de seguimiento con los focos predefinidos del plan y las 3 entregas (fechas límite editables; avance 1 sugerido en semana 5, avance 2 en semana 8).

## 5. Estructura de carpetas propuesta

```
/ (repositorio)
├── ROADMAP.md
├── CHANGELOG.md
├── api/                      # Backend PHP
│   ├── index.php             # Front controller / router
│   ├── .htaccess
│   ├── config.example.php
│   ├── src/
│   │   ├── Controllers/
│   │   ├── Models/
│   │   ├── Middleware/       # auth, cors, roles
│   │   ├── Services/         # mailer, passwords, notas
│   │   └── Database.php
│   ├── migrations/           # 001_..., 002_... (SQL plano, ejecutable en phpMyAdmin)
│   └── vendor/               # PHPMailer, Slim (subido al hosting)
├── frontend/                 # Vue 3 + Vite
│   ├── src/
│   │   ├── views/
│   │   ├── components/
│   │   ├── stores/           # Pinia
│   │   ├── router/
│   │   └── services/api.js   # wrapper fetch/axios
│   └── vite.config.js        # base y proxy a /api en dev
└── docs/
    └── deploy.md             # guía de despliegue a cPanel
```

---

# PLAN DE VERSIONES

## v0.0.1 — Fundaciones del proyecto

**Objetivo:** repositorio funcionando en local con "hola mundo" full-stack.

- [ ] Crear estructura de carpetas descrita arriba.
- [ ] `api/index.php` con router mínimo y endpoint `GET /api/health` → `{"status":"ok","version":"0.0.1"}`.
- [ ] `.htaccess` de la API (rewrite a `index.php`) y del root (SPA fallback a `index.html`, excluyendo `/api`).
- [ ] `Database.php` con conexión PDO (charset utf8mb4) leyendo `config.php`.
- [ ] Migración `001_init.sql`: crear todas las tablas del modelo de datos.
- [ ] Proyecto Vue 3 con Vite + Router + Pinia + framework CSS elegido; vista `Home` que consume `/api/health` y muestra el estado.
- [ ] Configurar proxy de Vite hacia la API en desarrollo.
- [ ] `config.example.php`, `.gitignore`, `CHANGELOG.md`.

**Criterio de cierre:** `npm run dev` muestra la SPA consumiendo la API local; las tablas existen en MySQL.

## v0.1.0 — Autenticación (login con correo Duoc)

**Objetivo:** login/logout con sesiones y protección de rutas.

- [ ] `POST /api/auth/login` (correo + contraseña): valida dominio institucional (`@duoc.cl`, `@duocuc.cl`, `@profesor.duoc.cl` — lista configurable en `config.php`), verifica `password_verify`, crea sesión.
- [ ] `POST /api/auth/logout` y `GET /api/auth/me` (datos del usuario en sesión).
- [ ] Middleware `AuthMiddleware` (401 si no hay sesión) y `RoleMiddleware` (403 por rol).
- [ ] Rate limiting simple de login (máx. 5 intentos / 15 min por correo, tabla o sesión).
- [ ] Seed inicial: script SQL/PHP que crea el usuario `admin` con contraseña generada e impresa una sola vez.
- [ ] Frontend: vista `Login`, store `auth` (Pinia), guard de router (redirige a `/login` si no autenticado), layout base con navbar y botón salir.
- [ ] Manejo de errores de API estandarizado: `{ "error": { "code", "message" } }`.

**Criterio de cierre:** solo usuarios autenticados ven la app; login rechaza correos no institucionales.

## v0.2.0 — Gestión de usuarios y contraseñas generadas por el sistema

**Objetivo:** el admin crea cuentas; la contraseña la genera el sistema, nunca el usuario al inicio.

- [ ] CRUD de usuarios (solo `admin`): `GET/POST/PUT/DELETE /api/usuarios` (borrado lógico con `activo=0`).
- [ ] Al crear usuario: generar contraseña aleatoria segura (12+ caracteres) en backend, guardar solo el hash (`password_hash` con bcrypt/argon2), marcar `debe_cambiar_password=1`.
- [ ] Entregar la contraseña generada: mostrarla una única vez en pantalla al admin **y** (si SMTP está configurado) enviarla por correo con PHPMailer.
- [ ] Flujo de cambio obligatorio: si `debe_cambiar_password=1`, el frontend fuerza pantalla de cambio antes de continuar. `POST /api/auth/cambiar-password`.
- [ ] Botón "Regenerar contraseña" para el admin (invalida la anterior).
- [ ] Frontend: vista `Usuarios` (tabla, modal crear/editar, badge de rol y estado).

**Criterio de cierre:** admin crea un docente, el docente entra con la contraseña generada y es forzado a cambiarla.

## v0.3.0 — Recuperación de contraseña

**Objetivo:** flujo completo "olvidé mi contraseña" por correo.

- [ ] `POST /api/auth/recuperar` (recibe correo): genera token aleatorio, guarda **hash** del token con expiración de 60 min en `pp_tokens_recuperacion`, envía enlace por PHPMailer. Respuesta siempre genérica (no revelar si el correo existe).
- [ ] `POST /api/auth/restablecer` (token + nueva contraseña): valida token no usado/no expirado, actualiza hash, marca token usado, invalida sesiones.
- [ ] Servicio `Mailer` centralizado (plantillas HTML simples: credenciales, recuperación).
- [ ] Frontend: vistas `RecuperarPassword` (formulario correo) y `RestablecerPassword` (ruta `/restablecer?token=...`).
- [ ] Configuración SMTP documentada en `config.example.php` (host, puerto, usuario del hosting).

**Criterio de cierre:** flujo completo probado con SMTP real o Mailtrap; tokens expirados/usados son rechazados.

## v0.4.0 — Administrador de estudiantes (carreras y semestres)

**Objetivo:** CRUD de estudiantes con su carrera y semestre; carga manual cómoda.

- [ ] CRUD `pp_carreras` (admin): nombre + escuela. Seed con carreras iniciales de la sede.
- [ ] CRUD `pp_estudiantes`: nombre, apellido, RUT (con validación de dígito verificador chileno), correo, teléfono, carrera, semestre de práctica (formato `AAAA-S`, ej. `2026-1`), docente asignado.
- [ ] Validaciones backend: RUT único, formato de semestre, carrera existente.
- [ ] Listado con **filtros por semestre, carrera y docente** + búsqueda por nombre/RUT + paginación (server-side, `?page=&per_page=&semestre=&carrera_id=&q=`).
- [ ] El rol `docente` solo ve/edita sus propios estudiantes; `admin` ve todos.
- [ ] Frontend: vista `Estudiantes` (tabla con filtros arriba), modal de crear/editar, confirmación de desactivación.

**Criterio de cierre:** se pueden cargar a mano estudiantes reales, filtrarlos por semestre y carrera sin recargar la página.

## v0.5.0 — Empresas y supervisores (jefes)

**Objetivo:** registrar centros de práctica y sus jefes/supervisores.

- [ ] CRUD `pp_empresas`: nombre, RUT empresa, giro, dirección, ciudad, teléfono, sitio web.
- [ ] CRUD `pp_supervisores` anidado en empresa: nombre, apellido, profesión, cargo, teléfono, **correo del jefe** (validación de formato).
- [ ] Endpoint `GET /api/empresas/{id}/supervisores` para selects encadenados.
- [ ] Frontend: vista `Empresas` (tabla → detalle de empresa con sus supervisores), formularios modales.
- [ ] Enlace `mailto:` en el correo del jefe para contacto rápido.

**Criterio de cierre:** se puede registrar una empresa con uno o más jefes y sus correos.

## v0.6.0 — Prácticas y estados

**Objetivo:** vincular estudiante + empresa + supervisor en una práctica con ciclo de estados.

- [ ] CRUD `pp_practicas`: seleccionar estudiante, empresa, supervisor (filtrado por empresa), semestre, fechas de inicio/término.
- [ ] Al crear la práctica, generar automáticamente:
  - 12 filas en `pp_seguimiento_semanal` con los focos predefinidos del plan (Semana 1: Inducción y expectativas … Semana 12: Cierre y evaluación final).
  - 3 filas en `pp_entregas` con fechas límite sugeridas (avance 1 ≈ inicio + 5 semanas; avance 2 ≈ inicio + 8 semanas; informe final ≈ fecha de término), editables.
- [ ] Máquina de estados con transiciones válidas:
  `pendiente → en_curso → avance_1 → avance_2 → informe_final → aprobada|reprobada`, y `abandonada` desde cualquier estado activo.
- [ ] `PATCH /api/practicas/{id}/estado` que valida la transición y registra el cambio en `pp_bitacora`.
- [ ] Frontend: vista `Prácticas` (tabla con columna de estado como badge de color), formulario de creación con selects encadenados, detalle de práctica con línea de tiempo de estados (desde bitácora).

**Criterio de cierre:** crear una práctica genera su plan de 12 semanas y 3 entregas; los estados solo avanzan por transiciones válidas.

## v0.7.0 — Seguimiento semanal

**Objetivo:** digitalizar el checklist docente de 12 semanas con semáforo de riesgo.

- [ ] `GET /api/practicas/{id}/seguimiento` (las 12 semanas) y `PUT /api/practicas/{id}/seguimiento/{semana}`.
- [ ] Cálculo en backend por semana: puntaje (0–8 ítems marcados), % de cumplimiento y riesgo (verde ≥85%, amarillo 60–84%, rojo <60%).
- [ ] KPIs por práctica: cumplimiento global (promedio de semanas registradas), n° de semanas en riesgo alto, n° de 1:1 realizadas, n° de retroalimentaciones entregadas.
- [ ] Frontend: vista `Seguimiento` dentro del detalle de práctica: grilla de 12 semanas, cada semana expandible con sus 8 checkboxes + observaciones + fecha; semáforo visible por semana; tarjetas de KPI arriba.
- [ ] Vista rápida "checklist de hoy": marcar los ítems de la semana en curso en pocos clics (pensada para completar durante o después de la reunión con el estudiante).

**Criterio de cierre:** el docente registra una semana en <1 minuto y el semáforo y KPIs se actualizan de inmediato.

## v0.8.0 — Entregas y notas

**Objetivo:** control de avances e informe final con cálculo de nota y aprobación.

- [ ] `PUT /api/practicas/{id}/entregas/{tipo}`: marcar entregado, fecha de entrega, nota (1.0–7.0, un decimal), retroalimentación escrita.
- [ ] Regla de atraso: si hoy > fecha_limite y no entregado, mostrar alerta y sugerir nota 1.0 (aplicación manual por el docente).
- [ ] Cálculo automático de **nota final ponderada** (25/25/50) cuando las tres notas existen.
- [ ] Al registrar nota del informe final: si ≥ 4.0 habilitar transición a `aprobada`; si < 4.0, a `reprobada`.
- [ ] Frontend: sección `Entregas` en el detalle de práctica: 3 tarjetas (Avance 1, Avance 2, Informe Final) con fecha límite, estado (pendiente/entregado/atrasado), nota y retroalimentación; resumen de nota final ponderada.
- [ ] Registro en `pp_bitacora` de cada nota ingresada o modificada.

**Criterio de cierre:** con las 3 notas cargadas, la nota final y el estado de aprobación se calculan solos y coinciden con la regla 25/25/50 y el mínimo 4.0.

## v0.9.0 — Dashboard, reportes y endurecimiento

**Objetivo:** visión global para el docente/admin y preparación para producción.

- [ ] `GET /api/dashboard`: totales por estado, prácticas en riesgo (semanas rojas recientes), entregas próximas a vencer (7 días) y atrasadas, distribución por carrera y semestre.
- [ ] Vista `Dashboard` como página de inicio post-login: tarjetas de totales, lista "requiere atención" (riesgo alto o entrega atrasada), accesos rápidos.
- [ ] Exportación CSV (server-side): estudiantes, prácticas con estado/notas, seguimiento de una práctica.
- [ ] Endurecimiento de seguridad: revisar prepared statements en todo el código, sanitización de salida en Vue, headers de seguridad en `.htaccess` (X-Content-Type-Options, X-Frame-Options), sesión con `HttpOnly` + `SameSite`, CORS restringido al dominio propio, ocultar errores PHP en producción (`display_errors=0`, log a archivo).
- [ ] Revisión de UX: estados vacíos, spinners de carga, mensajes de error amables, responsive básico (el docente puede usarlo desde el celular).
- [ ] Pruebas manuales guiadas: checklist de QA en `docs/qa-checklist.md` recorriendo todos los flujos.

**Criterio de cierre:** checklist de QA aprobado completo; ningún error visible de PHP; exportaciones abren bien en Excel (UTF-8 BOM).

## v1.0.0 — Despliegue a producción

**Objetivo:** aplicación corriendo en el hosting compartido.

- [ ] `npm run build` del frontend con `base` correcta; verificar rutas de assets.
- [ ] Subir `dist/` a `/public_html/` y `api/` (incluyendo `vendor/`) a `/public_html/api/`.
- [ ] Crear BD y usuario MySQL en cPanel; ejecutar migraciones en phpMyAdmin en orden.
- [ ] Crear `config.php` de producción (credenciales BD, SMTP del hosting, dominios de correo permitidos, URL base).
- [ ] Verificar `.htaccess`: SPA fallback, rewrite de `/api`, HTTPS forzado (redirect 301).
- [ ] Ejecutar seed del usuario admin en producción y cambiar su contraseña de inmediato.
- [ ] Prueba de humo en producción: login, recuperación de contraseña (correo real), crear estudiante, empresa, práctica, registrar una semana de seguimiento y una nota.
- [ ] Documentar en `docs/deploy.md`: pasos de despliegue, cómo actualizar (re-subir dist y api), y cómo respaldar la BD (export periódico desde phpMyAdmin).
- [ ] Etiquetar release `v1.0.0` y cerrar `CHANGELOG.md`.

**Criterio de cierre:** flujo completo funcionando en el dominio real con HTTPS.

---

## 6. Ideas post-1.0.0 (no construir aún)

- Portal del estudiante (ver su cronograma, subir sus informes en PDF).
- Notificaciones automáticas por correo al jefe/supervisor y recordatorios de entregas.
- Digitalización de la pauta de evaluación del informe (rúbrica con indicadores y notas 1–7 por aspecto).
- Encuesta de evaluación docente integrada.
- Importación masiva de estudiantes desde Excel/CSV.
- Multi-sede / multi-escuela.

## 7. Convenciones para Claude Code

- Idioma del código: identificadores en español consistente con el esquema (`estudiantes`, `practicas`), comentarios breves.
- API: JSON siempre; códigos HTTP correctos (200/201/400/401/403/404/422/500); validación en backend aunque el frontend valide.
- Commits por tarea completada, mensaje en español: `feat(v0.4.0): CRUD de estudiantes con filtros`.
- No introducir dependencias nuevas sin justificarlo en el `CHANGELOG.md`.
- Nunca commitear `config.php`, contraseñas ni tokens.
