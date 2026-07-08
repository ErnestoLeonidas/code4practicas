<?php

namespace App\Controllers;

use App\Http\Request;
use App\Http\Response;
use App\Models\Usuario;
use App\Services\Auth;
use App\Services\Mailer;
use App\Services\Password;
use App\Support\Validaciones;

/**
 * Gestión de usuarios (rutas SOLO admin, protegidas por RoleMiddleware).
 *
 * El sistema genera las contraseñas (nunca el usuario) y obliga a cambiarlas en
 * el primer ingreso (debe_cambiar_password). Las respuestas usan
 * Usuario::publicoAdmin(); NUNCA se expone password_hash.
 */
final class UsuarioController
{
    /** Roles válidos para un usuario del sistema. */
    private const ROLES = ['admin', 'docente'];

    /** Paginación del listado. */
    private const PER_PAGE_DEF = 20;
    private const PER_PAGE_MAX = 100;

    /** Longitud de las contraseñas generadas por el sistema. */
    private const LONGITUD_PASSWORD = 14;

    /**
     * GET /api/usuarios?page=&per_page=&q=&rol=&activo=
     */
    public function index(array $params): void
    {
        $page = max(1, (int) ($_GET['page'] ?? 1));

        $perPage = (int) ($_GET['per_page'] ?? self::PER_PAGE_DEF);
        if ($perPage < 1) {
            $perPage = self::PER_PAGE_DEF;
        }
        $perPage = min($perPage, self::PER_PAGE_MAX);

        $filtros = [];

        $q = trim((string) ($_GET['q'] ?? ''));
        if ($q !== '') {
            $filtros['q'] = $q;
        }

        $rol = trim((string) ($_GET['rol'] ?? ''));
        if (in_array($rol, self::ROLES, true)) {
            $filtros['rol'] = $rol;
        }

        $activo = (string) ($_GET['activo'] ?? '');
        if ($activo === '0' || $activo === '1') {
            $filtros['activo'] = (int) $activo;
        }

        $total  = Usuario::contar($filtros);
        $offset = ($page - 1) * $perPage;
        $filas  = Usuario::listar($filtros, $perPage, $offset);

        $data = array_map(
            static fn (array $u): array => Usuario::publicoAdmin($u),
            $filas
        );

        Response::json([
            'data'     => $data,
            'page'     => $page,
            'per_page' => $perPage,
            'total'    => $total,
        ]);
    }

    /**
     * POST /api/usuarios  { nombre, apellido, correo, rol }
     */
    public function store(array $params): void
    {
        $datos = $this->validarDatos(Request::json());
        if ($datos === null) {
            return; // validarDatos ya respondió con el error.
        }

        if (Usuario::correoExiste($datos['correo'])) {
            Response::error('correo_duplicado', 'Ya existe un usuario con ese correo.', 422);
            return;
        }

        // El sistema genera la contraseña; solo se guarda el hash.
        $passwordPlano = Password::generar(self::LONGITUD_PASSWORD);

        $id = Usuario::crear([
            'nombre'                => $datos['nombre'],
            'apellido'              => $datos['apellido'],
            'correo'                => $datos['correo'],
            'password_hash'         => Password::hash($passwordPlano),
            'rol'                   => $datos['rol'],
            'debe_cambiar_password' => 1,
            'activo'                => 1,
        ]);

        $usuario = Usuario::porId($id);

        $correoEnviado = Mailer::enviarCredenciales($datos['correo'], $datos['nombre'], $passwordPlano);

        Response::json([
            'usuario'           => Usuario::publicoAdmin($usuario),
            'password_generada' => $passwordPlano,
            'correo_enviado'    => $correoEnviado,
        ], 201);
    }

    /**
     * PUT /api/usuarios/{id}  { nombre, apellido, correo, rol, activo? }
     * No cambia la contraseña.
     */
    public function update(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);

        $usuario = Usuario::porId($id);
        if ($usuario === null) {
            Response::error('no_encontrado', 'Usuario no encontrado.', 404);
            return;
        }

        $cuerpo = Request::json();

        $datos = $this->validarDatos($cuerpo);
        if ($datos === null) {
            return;
        }

        if (Usuario::correoExiste($datos['correo'], $id)) {
            Response::error('correo_duplicado', 'Ya existe un usuario con ese correo.', 422);
            return;
        }

        // activo es opcional: si no viene, se conserva el estado actual.
        $activo = (int) $usuario['activo'];
        if (array_key_exists('activo', $cuerpo)) {
            $activo = $this->normalizarActivo($cuerpo['activo']);
        }

        // Guarda: la operación no puede dejar el sistema sin administradores
        // activos. Si tras el cambio este usuario ya no es admin activo y no
        // queda ningún otro admin activo, se rechaza.
        $seguiraSiendoAdminActivo = ($datos['rol'] === 'admin' && $activo === 1);
        if (!$seguiraSiendoAdminActivo && Usuario::contarAdminsActivos($id) === 0) {
            Response::error('ultimo_admin', 'No puedes dejar el sistema sin administradores activos.', 422);
            return;
        }

        Usuario::actualizar($id, [
            'nombre'   => $datos['nombre'],
            'apellido' => $datos['apellido'],
            'correo'   => $datos['correo'],
            'rol'      => $datos['rol'],
            'activo'   => $activo,
        ]);

        Response::json(['usuario' => Usuario::publicoAdmin(Usuario::porId($id))]);
    }

    /**
     * DELETE /api/usuarios/{id} — borrado lógico (activo = 0).
     */
    public function destroy(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);

        $usuario = Usuario::porId($id);
        if ($usuario === null) {
            Response::error('no_encontrado', 'Usuario no encontrado.', 404);
            return;
        }

        $actual = Auth::usuario();
        if ($actual !== null && (int) $actual['id'] === $id) {
            Response::error('no_puede_desactivarse', 'No puedes desactivar tu propia cuenta.', 422);
            return;
        }

        $esAdminActivo = $usuario['rol'] === 'admin' && (int) $usuario['activo'] === 1;
        if ($esAdminActivo && Usuario::contarAdminsActivos($id) === 0) {
            Response::error('ultimo_admin', 'No puedes desactivar al último administrador activo.', 422);
            return;
        }

        Usuario::desactivar($id);

        Response::json(['ok' => true]);
    }

    /**
     * POST /api/usuarios/{id}/regenerar-password
     * Genera una nueva contraseña (invalida la anterior) y obliga a cambiarla.
     */
    public function regenerarPassword(array $params): void
    {
        $id = (int) ($params['id'] ?? 0);

        $usuario = Usuario::porId($id);
        if ($usuario === null) {
            Response::error('no_encontrado', 'Usuario no encontrado.', 404);
            return;
        }

        $passwordPlano = Password::generar(self::LONGITUD_PASSWORD);
        Usuario::actualizarPassword($id, Password::hash($passwordPlano), 1);

        $correoEnviado = Mailer::enviarCredenciales(
            (string) $usuario['correo'],
            (string) $usuario['nombre'],
            $passwordPlano
        );

        Response::json([
            'password_generada' => $passwordPlano,
            'correo_enviado'    => $correoEnviado,
        ]);
    }

    /**
     * Valida y normaliza los campos comunes de crear/actualizar.
     * Devuelve los datos saneados o null si ya respondió un error de validación.
     *
     * @param array<string, mixed> $cuerpo
     * @return array{nombre: string, apellido: string, correo: string, rol: string}|null
     */
    private function validarDatos(array $cuerpo): ?array
    {
        $nombre   = trim((string) ($cuerpo['nombre'] ?? ''));
        $apellido = trim((string) ($cuerpo['apellido'] ?? ''));
        $correo   = strtolower(trim((string) ($cuerpo['correo'] ?? '')));
        $rol      = trim((string) ($cuerpo['rol'] ?? ''));

        if ($nombre === '' || $apellido === '' || $correo === '' || $rol === '') {
            Response::error('datos_invalidos', 'Nombre, apellido, correo y rol son obligatorios.', 422);
            return null;
        }

        if (!Validaciones::emailValido($correo) || !Validaciones::dominioPermitido($correo)) {
            Response::error('dominio_no_institucional', 'Debes usar un correo institucional Duoc válido.', 422);
            return null;
        }

        if (!in_array($rol, self::ROLES, true)) {
            Response::error('rol_invalido', 'El rol debe ser admin o docente.', 422);
            return null;
        }

        return [
            'nombre'   => $nombre,
            'apellido' => $apellido,
            'correo'   => $correo,
            'rol'      => $rol,
        ];
    }

    /**
     * Normaliza el valor de activo recibido en JSON (true/false, 1/0, "1"/"0")
     * a un entero 0/1.
     */
    private function normalizarActivo(mixed $valor): int
    {
        if (is_bool($valor)) {
            return $valor ? 1 : 0;
        }
        if (is_int($valor)) {
            return $valor === 1 ? 1 : 0;
        }
        if (is_string($valor)) {
            return ($valor === '1' || strtolower($valor) === 'true') ? 1 : 0;
        }
        return 0;
    }
}
