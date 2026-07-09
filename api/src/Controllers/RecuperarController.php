<?php

namespace App\Controllers;

use App\Config;
use App\Http\HttpException;
use App\Http\Request;
use App\Http\Response;
use App\Models\Token;
use App\Models\Usuario;
use App\Services\Auth;
use App\Services\Mailer;
use App\Services\Password;
use App\Support\Validaciones;

/**
 * Flujo de recuperación de contraseña por correo.
 *
 * POST /api/auth/recuperar   — solicita el enlace (pública)
 * POST /api/auth/restablecer — aplica la nueva contraseña con el token (pública)
 */
final class RecuperarController
{
    /** Tiempo de vida del token en minutos. */
    private const TTL_MIN = 60;

    /**
     * POST /api/auth/recuperar
     *
     * Genera un token HMAC y lo envía por correo. La respuesta es siempre 200
     * para no revelar si el correo existe en el sistema.
     */
    public function recuperar(array $params): void
    {
        $cuerpo = Request::json();
        $correo = trim((string) ($cuerpo['correo'] ?? ''));

        // Validación de formato: sí revelamos errores de formato (no es información sensible).
        if (!Validaciones::emailValido($correo)) {
            throw new HttpException(422, 'datos_invalidos', 'El formato del correo no es válido.');
        }

        // Respuesta por defecto (idéntica tanto si el usuario existe como si no).
        $respuesta = [
            'ok'      => true,
            'mensaje' => 'Si el correo existe, recibirás instrucciones para restablecer tu contraseña.',
        ];

        $usuario = Usuario::porCorreo($correo);

        if ($usuario !== null && (int) $usuario['activo'] === 1) {
            // Genera token aleatorio de 64 chars hex.
            $tokenPlano = bin2hex(random_bytes(32));
            $secretKey  = (string) Config::get('app_secret', 'dev-secret-change-me');
            $tokenHash  = hash_hmac('sha256', $tokenPlano, $secretKey);

            $expiraEn = date('Y-m-d H:i:s', time() + self::TTL_MIN * 60);

            Token::crear((int) $usuario['id'], $tokenHash, $expiraEn);

            $enlace = rtrim((string) Config::get('app_url', 'http://localhost:51731'), '/')
                . '/restablecer?token=' . $tokenPlano;

            Mailer::enviarRecuperacion(
                $correo,
                trim($usuario['nombre'] . ' ' . $usuario['apellido']),
                $enlace
            );
        }

        Response::json($respuesta);
    }

    /**
     * POST /api/auth/restablecer
     *
     * Valida el token y aplica la nueva contraseña.
     */
    public function restablecer(array $params): void
    {
        $cuerpo       = Request::json();
        $tokenPlano   = trim((string) ($cuerpo['token'] ?? ''));
        $passwordNueva = (string) ($cuerpo['password_nueva'] ?? '');

        // Validaciones básicas de los campos.
        if ($tokenPlano === '') {
            throw new HttpException(422, 'datos_invalidos', 'El token es obligatorio.');
        }
        if (mb_strlen($passwordNueva) < 8) {
            throw new HttpException(422, 'datos_invalidos', 'La contraseña debe tener al menos 8 caracteres.');
        }

        // Busca el token por su hash HMAC.
        $secretKey = (string) Config::get('app_secret', 'dev-secret-change-me');
        $tokenHash = hash_hmac('sha256', $tokenPlano, $secretKey);

        $fila = Token::porHash($tokenHash);

        // Token inexistente, ya usado o expirado.
        if (
            $fila === null
            || (int) $fila['usado'] === 1
            || strtotime((string) $fila['expira_en']) < time()
        ) {
            throw new HttpException(422, 'token_invalido', 'Token inválido o expirado.');
        }

        $usuarioId = (int) $fila['usuario_id'];

        // Actualiza la contraseña y limpia el flag de cambio forzado.
        Usuario::actualizarPassword($usuarioId, Password::hash($passwordNueva), 0);

        // Invalida todos los tokens de recuperación pendientes del usuario.
        Token::invalidarPorUsuario($usuarioId);

        // Si el usuario de la sesión activa es el mismo, cierra la sesión para
        // que deba autenticarse con la nueva contraseña.
        $usuarioSesion = Auth::usuario();
        if ($usuarioSesion !== null && (int) $usuarioSesion['id'] === $usuarioId) {
            Auth::logout();
        }

        Response::json(['ok' => true]);
    }
}
