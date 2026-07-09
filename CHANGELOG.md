# CHANGELOG

Todos los cambios relevantes de este proyecto se documentan aquí.
Formato basado en [Keep a Changelog](https://keepachangelog.com/es/1.0.0/).

## [0.8.0] — 2026-07-09

### Añadido
- Backend: endpoints `GET /api/practicas/{id}/seguimiento`, `PUT /api/practicas/{id}/seguimiento/{semana}` para registrar el checklist semanal con cálculo de puntaje, porcentaje y riesgo por semana, además de KPIs resumen por práctica.
- Backend: endpoint `PUT /api/practicas/{id}/entregas/{tipo}` para registrar estado de entrega, fecha de entrega, nota (1.0–7.0) y retroalimentación; el resumen calcula la nota final ponderada 25/25/50 y la sugerencia de atraso.
- Al guardar la nota del informe final, el estado de la práctica avanza automáticamente a `aprobada` o `reprobada` cuando corresponde.
- Frontend: panel de seguimiento semanal y sección de entregas/nota en el detalle de práctica, con guardado inmediato desde la interfaz.

### Verificado
- Validación end-to-end con autenticación de admin: `GET /api/practicas/{id}/seguimiento` devolvió semanas con porcentaje/riesgo; `PUT /api/practicas/{id}/seguimiento/{semana}` actualizó la semana y los KPIs; `PUT /api/practicas/{id}/entregas/{tipo}` registró entrega y resumen de notas.
- Build frontend exitoso (`vite build`).

---

## [0.6.0] — 2026-07-09

### Añadido
- Backend: nuevos endpoints de prácticas `GET/POST/PUT /api/practicas`, `GET /api/practicas/{id}`, `PATCH /api/practicas/{id}/estado`.
- Creación automática de 12 semanas de seguimiento y 3 entregas por práctica con fechas límite sugeridas.
- Bitácora de práctica y transición de estados con validación de transiciones permitidas.
- Frontend: vista `Prácticas` con formulario de alta, tabla y detalle rápido de estado/seguimiento.
- Rutas y navegación actualizadas para acceder a la vista de prácticas desde la barra principal.

### Verificado
- Build frontend exitoso (`vite build`).
- End-to-end con admin autenticado: listado y detalle de práctica, generación automática de seguimiento/entregas y cambio de estado `pendiente → en_curso`.

---

## [0.5.0] — 2026-07-08

### Añadido
- Migración `003`: añade columna `activo` a `pp_empresas` y `pp_supervisores` (para mantener la convención de borrado lógico — las tablas no la incluían en `001_init`).
- CRUD `pp_empresas` (admin escribe, admin+docente lee): `GET/POST/PUT/DELETE /api/empresas`. Filtros por nombre/RUT y ciudad; paginado. Validaciones: nombre requerido, `rut_empresa` con dígito verificador chileno, `sitio_web` con `FILTER_VALIDATE_URL`. Borrado lógico; 409 `empresa_en_uso` si tiene prácticas activas.
- CRUD `pp_supervisores` anidado: `GET /api/empresas/{id}/supervisores`, `POST /api/empresas/{id}/supervisores`, `PUT /api/supervisores/{id}`, `DELETE /api/supervisores/{id}`. Correo validado con `Validaciones::emailValido()`.
- `GET /api/empresas/{id}` retorna la empresa con `supervisores: []` embebidos y `supervisor_count`.
- Modelos `Empresa` y `Supervisor` con `tienePracticasActivas()` para la guarda de integridad.
- Frontend: store `empresas` (paginado + filtros, acciones de empresa y supervisor), vista `Empresas` (tabla con badge de supervisores, filtros, paginación; panel de detalle modal con lista de supervisores y enlace `mailto:` en correo), componentes `EmpresaModal` y `SupervisorModal`.
- Enlace "Empresas" en navbar (visible para admin y docente).

### Nuevos códigos de error
`url_invalida`, `correo_invalido`, `empresa_en_uso`.

### Verificado
- Login admin → 200. POST empresa con RUT y URL válidos → 201. URL inválida → 422. POST supervisor con correo válido → 201. GET empresa/{id} incluye `supervisores`. DELETE supervisor → 200. PUT empresa → 200. DELETE empresa sin prácticas → 200.
- Build frontend: 47 módulos transformados, sin warnings.

---

## [0.4.0] — 2026-07-08

### Añadido
- CRUD `pp_carreras` (solo admin): `GET/POST/PUT/DELETE /api/carreras`. Borrado lógico; rechazo 409 `carrera_en_uso` si hay estudiantes activos asignados. Sin paginación (listado completo de activas).
- CRUD `pp_estudiantes`: `GET/POST/PUT/DELETE /api/estudiantes` con filtros server-side (`?page=&per_page=&q=&semestre=&carrera_id=&docente_id=`) y borrado lógico.
- Validaciones backend de estudiante: RUT chileno con dígito verificador (algoritmo DV, `Validaciones::rutValido()`), RUT único (409 `rut_duplicado`), formato de semestre `AAAA-[12]` (422 `semestre_invalido`), carrera activa existente (422 `carrera_invalida`), docente activo con rol correcto (422 `docente_invalido`).
- Restricción de rol en estudiantes: `docente` solo ve/edita sus propios estudiantes (filtra `docente_id` automáticamente); `admin` ve todos. El docente no puede crear ni desactivar.
- `GET /api/estudiantes` retorna `carrera_nombre` y `docente_nombre` (via JOIN).
- `api/seed_carreras.php` — idempotente; inserta 13 carreras de 4 escuelas Duoc UC (Informática, Administración, Salud, Ingeniería).
- Modelos `Carrera` y `Estudiante` con JOINs, filtros, paginación (LIMIT/OFFSET como `PARAM_INT`), `actualizado_en` explícito (compatibilidad SQLite).
- Frontend: store `carreras`, store `estudiantes` (paginado + filtros), vista `Estudiantes` (tabla con filtros semestre/carrera/búsqueda, paginación, acciones diferenciadas por rol), componente `EstudianteModal` (RUT readonly en edición, select de carrera y docente).
- Enlace "Estudiantes" en navbar (visible para admin y docente).

### Nuevos códigos de error
`rut_invalido`, `rut_duplicado`, `semestre_invalido`, `carrera_invalida`, `docente_invalido`, `carrera_en_uso`.

### Verificado
- 13 carreras sembradas; seed idempotente (segunda ejecución: 0 insertadas).
- Estudiante creado con RUT válido, carrera y semestre correctos → 201 con `carrera_nombre` en respuesta.
- RUT inválido (DV incorrecto) → 422 `rut_invalido`; RUT duplicado → 409 `rut_duplicado`; semestre `2026-3` → 422 `semestre_invalido`.
- GET con filtro `?semestre=2026-1` retorna solo los estudiantes del semestre.
- DELETE carrera con estudiantes activos → 409 `carrera_en_uso`.
- Docente ve solo sus propios estudiantes (filtro automático por `docente_id`).
- Build frontend: 43 módulos transformados, sin warnings.

---

## [0.3.0] — 2026-07-08

### Añadido
- `POST /api/auth/recuperar`: genera token aleatorio (`bin2hex(random_bytes(32))`), guarda solo el hash HMAC-SHA256 en `pp_tokens_recuperacion` con TTL de 60 min, envía enlace por correo. Respuesta siempre genérica (no revela si el correo existe en el sistema).
- `POST /api/auth/restablecer`: valida token no expirado y no usado, actualiza la contraseña del usuario, invalida todos los tokens del usuario y cierra la sesión activa si corresponde; retorna 422 `token_invalido` si el token es inválido, usado o expirado.
- Modelo `Token` (`src/Models/Token.php`): `crear`, `porHash`, `marcarUsado`, `invalidarPorUsuario`.
- Servicio `Mailer` completo con PHPMailer: reemplaza el stub de v0.2.0; dos métodos `enviarCredenciales` y `enviarRecuperacion` con plantillas HTML en español (inline). Clave SMTP `user`/`pass` (renombradas respecto al stub). Retorna `false` sin lanzar si no hay SMTP configurado.
- `api/config.example.php`: nuevas claves `app_secret` (HMAC de tokens) y sección `smtp` completa con `host/port/user/pass/from_email/from_name/secure`.
- Frontend: vista `RecuperarPassword` (`/recuperar-password`) — formulario de correo, respuesta siempre positiva en pantalla (no revela existencia), link "Volver al login".
- Frontend: vista `RestablecerPassword` (`/restablecer?token=...`) — detecta token ausente antes de mostrar el formulario, validaciones cliente (≥8 chars, coincidencia), maneja `token_invalido` con link a recuperar.
- Link "¿Olvidaste tu contraseña?" en `Login.vue` (link discreto debajo del botón).
- Guard del router: regla 2 (`debeCambiarPassword`) exenta a rutas con `meta.publico` para no romper el flujo de recuperación.

### Nuevos códigos de error
`token_invalido`.

### Verificado
- Correo existente → 200 genérico; correo inexistente → mismo 200 (no filtra); formato inválido → 422 `datos_invalidos`.
- Flujo completo: token insertado directo en BD → `restablecer` → login con nueva contraseña → 200 con `debe_cambiar_password: false`.
- Token ya usado → 422 `token_invalido`; token inexistente → 422 `token_invalido`.
- Build frontend: 39 módulos transformados, sin advertencias.

---

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
