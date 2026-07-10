# QA Checklist

Fecha base de esta guía: 2026-07-10.

## Preparación

- Levantar backend y frontend en entorno local.
- Confirmar acceso con un usuario `admin`.
- Confirmar acceso con un usuario `docente`.
- Verificar que existan al menos 1 carrera, 1 estudiante, 1 empresa y 1 práctica para pruebas de seguimiento.

## Autenticación

- Login válido con `admin`: debe redirigir al dashboard.
- Login válido con `docente`: debe redirigir al dashboard filtrado por su información.
- Login inválido: debe mostrar mensaje amable sin error PHP visible.
- Logout: debe cerrar sesión y volver a `/login`.
- Recuperación de contraseña: enviar correo y verificar respuesta genérica.
- Restablecimiento con token inválido o usado: debe mostrar error controlado.

## Dashboard

- Dashboard carga sin errores visibles para `admin`.
- Dashboard carga sin errores visibles para `docente`.
- Se muestran tarjetas de totales por estado.
- La sección `Requiere atención` mezcla riesgo alto y entregas atrasadas.
- Los botones `Exportar estudiantes` y `Exportar prácticas` descargan archivos CSV legibles en Excel.
- Si no hay datos en alguna sección, debe aparecer un estado vacío legible.

## Usuarios

- `admin` puede listar usuarios.
- `admin` puede crear un docente.
- `admin` puede editar usuario existente.
- `docente` no puede acceder a `/usuarios`.

## Carreras

- `admin` puede crear una carrera.
- `admin` puede editar una carrera.
- `admin` no puede eliminar una carrera con estudiantes activos asociados.

## Estudiantes

- `admin` puede crear estudiante con RUT válido.
- Validación de RUT inválido responde con error controlado.
- Validación de semestre inválido responde con error controlado.
- `docente` solo ve sus estudiantes asignados.
- Exportación CSV de estudiantes respeta el filtro por docente.

## Empresas y supervisores

- `admin` puede crear empresa.
- `admin` puede crear supervisor dentro de empresa.
- `admin` puede editar empresa y supervisor.
- `docente` puede leer empresas y supervisores, pero no crear ni editar.

## Prácticas

- Crear práctica genera 12 semanas de seguimiento y 3 entregas.
- Cambio de estado manual solo permite transiciones válidas.
- Detalle rápido carga seguimiento y entregas sin errores visibles.
- Exportación CSV de prácticas incluye estado y notas.

## Seguimiento semanal

- Guardar una semana actualiza puntaje, porcentaje y riesgo.
- Los KPI del seguimiento cambian inmediatamente.
- Exportación CSV de seguimiento por práctica descarga el detalle de las 12 semanas.

## Entregas y notas

- Guardar `Avance 1` con nota válida `1.0` a `7.0` funciona.
- Guardar nota con más de un decimal debe fallar con mensaje amable.
- Una entrega vencida y no entregada debe marcarse como `Atrasado`.
- Debe mostrarse sugerencia manual de nota `1.0` en entregas atrasadas.
- Cuando existen las tres notas, se calcula la nota final ponderada `25/25/50`.
- Al guardar nota de `Informe final`, la práctica cambia a `aprobada` o `reprobada` según corresponda.

## Seguridad básica

- No deben mostrarse warnings o stack traces de PHP en la interfaz.
- La cookie de sesión debe ser `HttpOnly`.
- Las respuestas deben incluir headers de seguridad configurados por `.htaccess`.
- CORS debe aceptar solo el origen configurado.

## Cierre

- Abrir `estudiantes.csv` en Excel y verificar acentos y columnas.
- Abrir `practicas.csv` en Excel y verificar acentos y columnas.
- Abrir el CSV de seguimiento de una práctica y verificar que incluya semanas, porcentaje y riesgo.
- Registrar resultado final del recorrido manual antes de cerrar la versión.
