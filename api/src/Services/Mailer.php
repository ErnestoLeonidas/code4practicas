<?php

namespace App\Services;

use App\Config;

/**
 * Envío de correos (stub de v0.2.0).
 *
 * En desarrollo no hay SMTP configurado (smtp.host vacío), así que
 * enviarCredenciales() devuelve false y el controlador refleja
 * correo_enviado:false. En producción, PHPMailer se instala vía Composer
 * (vendor/) y se usa si la clase está disponible.
 *
 * NUNCA propaga excepciones hacia el controlador: cualquier fallo se captura y
 * se registra con error_log.
 *
 * v0.3.0 completará las plantillas HTML institucionales y el flujo de
 * recuperación de contraseña.
 */
final class Mailer
{
    /**
     * Intenta enviar las credenciales generadas a un usuario (alta o
     * regeneración de contraseña). Devuelve true solo si el correo se envió.
     */
    public static function enviarCredenciales(string $correo, string $nombre, string $passwordPlano): bool
    {
        $host = (string) Config::get('smtp.host', '');

        // Desarrollo: sin SMTP configurado no se envía nada.
        if ($host === '') {
            return false;
        }

        // Producción: si PHPMailer no está vendido (vendor/), no se puede enviar.
        if (!class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            error_log('[Mailer] PHPMailer no está disponible; no se envió el correo a ' . $correo . '.');
            return false;
        }

        try {
            $mailer = new \PHPMailer\PHPMailer\PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host     = $host;
            $mailer->Port     = (int) Config::get('smtp.port', 587);
            $mailer->SMTPAuth = true;
            $mailer->Username = (string) Config::get('smtp.username', '');
            $mailer->Password = (string) Config::get('smtp.password', '');

            $secure = (string) Config::get('smtp.secure', 'tls');
            if ($secure !== '') {
                $mailer->SMTPSecure = $secure;
            }

            $mailer->CharSet = 'UTF-8';
            $mailer->setFrom(
                (string) Config::get('smtp.from_email', 'no-reply@example.com'),
                (string) Config::get('smtp.from_name', 'Seguimiento de Prácticas')
            );
            $mailer->addAddress($correo, $nombre);

            $mailer->Subject = 'Tus credenciales de acceso';
            // v0.3.0 reemplazará este cuerpo por una plantilla HTML institucional.
            $mailer->Body =
                "Hola {$nombre},\n\n" .
                "Se creó tu cuenta en el sistema de Seguimiento de Prácticas.\n" .
                "Correo: {$correo}\n" .
                "Contraseña temporal: {$passwordPlano}\n\n" .
                "Deberás cambiarla en tu primer ingreso.";

            $mailer->send();

            return true;
        } catch (\Throwable $e) {
            // El fallo del correo nunca debe romper la operación del controlador.
            error_log('[Mailer] No se pudo enviar credenciales a ' . $correo . ': ' . $e->getMessage());
            return false;
        }
    }
}
