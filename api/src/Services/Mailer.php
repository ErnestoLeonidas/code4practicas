<?php

namespace App\Services;

use App\Config;

/**
 * Envío de correos transaccionales.
 *
 * Usa PHPMailer si está disponible en vendor/ y hay smtp.host configurado.
 * Si no hay SMTP o PHPMailer falla, registra en error_log y retorna false.
 * NUNCA propaga excepciones hacia el controlador.
 */
final class Mailer
{
    /**
     * Envía las credenciales de acceso generadas por el sistema a un usuario
     * (alta nueva o regeneración de contraseña). Retorna true solo si el correo
     * se envió exitosamente.
     */
    public static function enviarCredenciales(string $correo, string $nombre, string $passwordPlano): bool
    {
        $asunto = 'Tus credenciales de acceso — Prácticas Duoc UC';

        $cuerpo = self::plantillaCredenciales($nombre, $correo, $passwordPlano);

        return self::enviar($correo, $nombre, $asunto, $cuerpo);
    }

    /**
     * Envía un enlace para restablecer la contraseña. Retorna true solo si el
     * correo se envió exitosamente.
     */
    public static function enviarRecuperacion(string $correo, string $nombre, string $enlace): bool
    {
        $asunto = 'Recuperar contraseña — Prácticas Duoc UC';

        $cuerpo = self::plantillaRecuperacion($nombre, $enlace);

        return self::enviar($correo, $nombre, $asunto, $cuerpo);
    }

    // -------------------------------------------------------------------------
    // Internos
    // -------------------------------------------------------------------------

    /**
     * Envía un correo HTML. Retorna false sin lanzar si no hay SMTP o falla.
     */
    private static function enviar(string $correo, string $nombre, string $asunto, string $cuerpo): bool
    {
        $host = (string) Config::get('smtp.host', '');

        if ($host === '') {
            // Sin SMTP configurado no se envía (modo desarrollo).
            return false;
        }

        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            error_log('[Mailer] PHPMailer no disponible; no se envió correo a ' . $correo . '.');
            return false;
        }

        try {
            $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);

            $mailer->isSMTP();
            $mailer->Host       = $host;
            $mailer->SMTPAuth   = true;
            $mailer->Username   = (string) Config::get('smtp.user', '');
            $mailer->Password   = (string) Config::get('smtp.pass', '');
            $mailer->Port       = (int) Config::get('smtp.port', 587);
            $mailer->SMTPDebug  = 0;
            $mailer->CharSet    = 'UTF-8';

            $secure = (string) Config::get('smtp.secure', 'tls');
            if ($secure !== '') {
                $mailer->SMTPSecure = $secure;
            }

            $mailer->setFrom(
                (string) Config::get('smtp.from_email', 'no-reply@tudominio.cl'),
                (string) Config::get('smtp.from_name', 'Prácticas Duoc UC')
            );
            $mailer->addAddress($correo, $nombre);

            $mailer->isHTML(true);
            $mailer->Subject = $asunto;
            $mailer->Body    = $cuerpo;
            $mailer->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $cuerpo));

            $mailer->send();

            return true;
        } catch (\Throwable $e) {
            error_log('[Mailer] Error al enviar a ' . $correo . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Plantilla HTML para correo de credenciales de acceso.
     */
    private static function plantillaCredenciales(string $nombre, string $correo, string $passwordPlano): string
    {
        $nombreEsc   = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
        $correoEsc   = htmlspecialchars($correo, ENT_QUOTES, 'UTF-8');
        $passwordEsc = htmlspecialchars($passwordPlano, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Credenciales de acceso</title></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:24px 0;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;">
        <!-- Cabecera -->
        <tr><td style="background:#003366;padding:24px 32px;">
          <p style="margin:0;color:#ffffff;font-size:20px;font-weight:bold;">
            Sistema de Prácticas Profesionales — Duoc UC
          </p>
        </td></tr>
        <!-- Cuerpo -->
        <tr><td style="padding:32px;">
          <p style="margin:0 0 16px;font-size:16px;color:#333333;">
            Hola <strong>{$nombreEsc}</strong>,
          </p>
          <p style="margin:0 0 24px;font-size:15px;color:#555555;line-height:1.6;">
            Se ha creado tu cuenta en el sistema de seguimiento de prácticas profesionales de Duoc UC.
            A continuación encontrarás tus credenciales de acceso:
          </p>
          <!-- Bloque de credenciales -->
          <table width="100%" cellpadding="0" cellspacing="0"
                 style="background:#f0f4ff;border-left:4px solid #003366;border-radius:4px;margin-bottom:24px;">
            <tr><td style="padding:20px 24px;">
              <p style="margin:0 0 8px;font-size:14px;color:#555555;">
                <strong>Correo:</strong>&nbsp;{$correoEsc}
              </p>
              <p style="margin:0;font-size:14px;color:#555555;">
                <strong>Contraseña temporal:</strong>&nbsp;
                <span style="font-family:monospace;font-size:16px;color:#003366;">{$passwordEsc}</span>
              </p>
            </td></tr>
          </table>
          <p style="margin:0 0 16px;font-size:15px;color:#d9534f;font-weight:bold;">
            ⚠ Debes cambiar esta contraseña la primera vez que ingreses al sistema.
          </p>
          <p style="margin:0;font-size:13px;color:#888888;line-height:1.5;">
            Si no solicitaste esta cuenta o crees que se trata de un error, ignora este correo o
            contáctate con el administrador del sistema.
          </p>
        </td></tr>
        <!-- Pie -->
        <tr><td style="background:#f4f4f4;padding:16px 32px;border-top:1px solid #eeeeee;">
          <p style="margin:0;font-size:12px;color:#aaaaaa;text-align:center;">
            Este es un correo automático — por favor no respondas directamente.
          </p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }

    /**
     * Plantilla HTML para correo de recuperación de contraseña.
     */
    private static function plantillaRecuperacion(string $nombre, string $enlace): string
    {
        $nombreEsc = htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8');
        $enlaceEsc = htmlspecialchars($enlace, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Recuperar contraseña</title></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f4;padding:24px 0;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;">
        <!-- Cabecera -->
        <tr><td style="background:#003366;padding:24px 32px;">
          <p style="margin:0;color:#ffffff;font-size:20px;font-weight:bold;">
            Sistema de Prácticas Profesionales — Duoc UC
          </p>
        </td></tr>
        <!-- Cuerpo -->
        <tr><td style="padding:32px;">
          <p style="margin:0 0 16px;font-size:16px;color:#333333;">
            Hola <strong>{$nombreEsc}</strong>,
          </p>
          <p style="margin:0 0 24px;font-size:15px;color:#555555;line-height:1.6;">
            Recibimos una solicitud para restablecer la contraseña de tu cuenta.
            Haz clic en el botón a continuación para crear una nueva contraseña:
          </p>
          <!-- Botón -->
          <table cellpadding="0" cellspacing="0" style="margin-bottom:24px;">
            <tr><td style="background:#003366;border-radius:6px;padding:14px 28px;">
              <a href="{$enlaceEsc}"
                 style="color:#ffffff;font-size:15px;font-weight:bold;text-decoration:none;display:inline-block;">
                Restablecer contraseña
              </a>
            </td></tr>
          </table>
          <p style="margin:0 0 16px;font-size:13px;color:#888888;line-height:1.5;">
            O copia y pega este enlace en tu navegador:<br>
            <a href="{$enlaceEsc}" style="color:#003366;word-break:break-all;">{$enlaceEsc}</a>
          </p>
          <p style="margin:0 0 16px;font-size:14px;color:#d9534f;font-weight:bold;">
            ⚠ Este enlace expirará en 60 minutos.
          </p>
          <p style="margin:0;font-size:13px;color:#888888;line-height:1.5;">
            Si no solicitaste restablecer tu contraseña, ignora este correo.
            Tu contraseña actual permanecerá sin cambios.
          </p>
        </td></tr>
        <!-- Pie -->
        <tr><td style="background:#f4f4f4;padding:16px 32px;border-top:1px solid #eeeeee;">
          <p style="margin:0;font-size:12px;color:#aaaaaa;text-align:center;">
            Este es un correo automático — por favor no respondas directamente.
          </p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
    }
}
