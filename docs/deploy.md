# Despliegue

Fecha base de esta guía: 2026-07-10.

Objetivo: publicar la SPA en `/public_html/` y la API en `/public_html/api/` sobre hosting compartido con Apache/cPanel.

## 1. Requisitos previos

- Acceso a cPanel o al administrador de archivos del hosting.
- Dominio apuntando al hosting.
- PHP 8.1+ activo en el dominio.
- Extensión `pdo_mysql` habilitada.
- Base de datos MySQL/MariaDB creada en cPanel.
- Credenciales SMTP del dominio o proveedor de correo.

## 2. Compilar frontend

Desde la raíz del repo:

```bash
npm run build --prefix frontend
```

Verificaciones:

- El build debe terminar sin errores.
- `frontend/dist/` debe contener `index.html`, `assets/` y `.htaccess`.
- `frontend/vite.config.js` usa `base: '/'`, correcto para publicar en la raíz del dominio.

## 3. Preparar backend

Antes de subir:

- Confirmar que `api/config.php` no se va a versionar.
- Confirmar que `api/config.example.php` sirve como base para producción.
- Si usas dependencias instaladas con Composer, subir también `api/vendor/`.

## 4. Subir archivos

Destino esperado:

- Subir el contenido de `frontend/dist/` a `/public_html/`.
- Subir la carpeta `api/` completa a `/public_html/api/`.

Validaciones después de subir:

- `/public_html/index.html` existe.
- `/public_html/.htaccess` existe.
- `/public_html/api/index.php` existe.
- `/public_html/api/.htaccess` existe.

## 5. Crear `api/config.php` de producción

Partir desde `api/config.example.php` y ajustar al menos lo siguiente:

```php
'app_version' => '1.0.0',
'env' => 'prod',
'app_url' => 'https://tudominio.cl',
'db' => [
    'driver' => 'mysql',
    'host' => 'localhost',
    'port' => 3306,
    'database' => 'tu_base',
    'username' => 'tu_usuario',
    'password' => 'tu_clave',
    'charset' => 'utf8mb4',
],
'app_secret' => 'un-secreto-largo-y-aleatorio',
'smtp' => [
    'host' => 'smtp.tudominio.cl',
    'port' => 465,
    'user' => 'no-reply@tudominio.cl',
    'pass' => 'tu-clave-smtp',
    'from_email' => 'no-reply@tudominio.cl',
    'from_name' => 'Prácticas Duoc UC',
    'secure' => 'ssl',
],
```

Notas:

- `app_url` debe ser la URL final pública con `https`.
- `env = 'prod'` oculta errores PHP en la respuesta.
- `app_secret` no puede quedar con el valor de ejemplo.

## 6. Crear base de datos y ejecutar migraciones

En cPanel:

1. Crear base de datos MySQL.
2. Crear usuario MySQL.
3. Asignar el usuario a la base con permisos completos.

Luego en phpMyAdmin, ejecutar manualmente en este orden:

1. `api/migrations/001_init.mysql.sql`
2. `api/migrations/002_login_intentos.mysql.sql`
3. `api/migrations/003_activo_empresas_supervisores.mysql.sql`

Verificar que existan las tablas `pp_usuarios`, `pp_estudiantes`, `pp_empresas`, `pp_practicas`, `pp_seguimiento_semanal`, `pp_entregas`, `pp_bitacora`.

## 7. Crear administrador inicial

Idealmente por CLI del hosting:

```bash
php api/seed_admin.php
```

Si el hosting no ofrece CLI, usar una copia local con la misma BD de producción no es recomendable. En ese caso, la alternativa segura es habilitar temporalmente acceso SSH o pedirlo al proveedor.

Después del seed:

- Guardar la contraseña mostrada.
- Iniciar sesión una sola vez con el correo administrador.
- Cambiar la contraseña inmediatamente.

## 8. Verificar `.htaccess`

El repo ya deja configurado:

- Fallback SPA en `/public_html/.htaccess`.
- Rewrite de `/api` a su propio front controller.
- Redirect `301` a HTTPS en frontend y API.
- Headers `X-Content-Type-Options`, `X-Frame-Options` y `Referrer-Policy`.

Validaciones manuales:

- `http://tudominio.cl` redirige a `https://tudominio.cl`.
- `http://tudominio.cl/api/health` redirige a `https://tudominio.cl/api/health`.
- Refrescar una ruta interna como `/practicas` no produce 404.

## 9. Prueba de humo en producción

Ejecutar este recorrido mínimo:

1. Login admin.
2. Cambio de contraseña obligatorio.
3. Recuperación de contraseña con correo real.
4. Crear estudiante.
5. Crear empresa y supervisor.
6. Crear práctica.
7. Registrar una semana de seguimiento.
8. Registrar una nota de entrega.
9. Abrir dashboard.
10. Descargar `estudiantes.csv` y `practicas.csv`.

## 10. Actualizar la aplicación

Flujo recomendado para una nueva versión:

1. Respaldar base de datos desde phpMyAdmin.
2. Generar nuevo build del frontend.
3. Re-subir `/public_html/` con el contenido nuevo de `frontend/dist/`.
4. Re-subir `/public_html/api/` con los cambios del backend.
5. Si hay migraciones nuevas, ejecutarlas en phpMyAdmin antes de probar.
6. Repetir la prueba de humo.

## 11. Respaldo

Frecuencia mínima recomendada:

- Antes de cada despliegue.
- Semanalmente mientras el sistema esté en uso.

Procedimiento:

1. Abrir phpMyAdmin.
2. Seleccionar la base de datos de la aplicación.
3. Ir a `Exportar`.
4. Elegir formato SQL.
5. Descargar el archivo y guardarlo con fecha.

Respaldo adicional recomendable:

- Copia de `api/config.php` fuera del hosting.
- Copia de credenciales SMTP y BD en un gestor seguro.

## 12. Problemas frecuentes

`404` al refrescar rutas Vue:

- Falta el `.htaccess` de `frontend/dist/` en `/public_html/`.

`500` en `/api`:

- Revisar credenciales MySQL en `config.php`.
- Revisar versión de PHP y extensión `pdo_mysql`.
- Revisar permisos de archivos.

No inicia sesión:

- Revisar `dominios_permitidos`.
- Verificar que la cookie de sesión se esté creando bajo HTTPS.

No llegan correos:

- Revisar host, puerto y modo `ssl`/`tls` del SMTP.
- Verificar autenticación del remitente en el hosting.
