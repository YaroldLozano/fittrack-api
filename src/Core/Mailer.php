<?php

namespace App\Core;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

class Mailer
{
    /** @param array{host: string, port: int, username: string, password: string, fromEmail: string, fromName: string} $config */
    public function __construct(private readonly array $config)
    {
    }

    public function sendPasswordResetCode(string $toEmail, string $toName, string $code, int $ttlMinutes): void
    {
        if ($this->config['host'] === '' || $this->config['username'] === '' || $this->config['fromEmail'] === '') {
            throw new RuntimeException('El envío de correo no está configurado en el servidor');
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = $this->config['host'];
            $mail->Port = $this->config['port'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['username'];
            $mail->Password = $this->config['password'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($this->config['fromEmail'], $this->config['fromName']);
            $mail->addAddress($toEmail, $toName);

            $safeName = htmlspecialchars($toName, ENT_QUOTES, 'UTF-8');
            $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');

            $mail->Subject = 'Recupera tu contraseña — ' . $this->config['fromName'];
            $mail->isHTML(true);
            $mail->Body = <<<HTML
                <p>Hola {$safeName},</p>
                <p>Recibimos una solicitud para restablecer tu contraseña. Este es tu código de recuperación:</p>
                <p style="font-size:18px;font-weight:bold;letter-spacing:1px;font-family:monospace;word-break:break-all">{$safeCode}</p>
                <p>Vence en {$ttlMinutes} minutos. Si no fuiste tú, ignora este correo — tu contraseña no cambiará.</p>
                HTML;
            $mail->AltBody = "Tu código de recuperación es: $code (vence en $ttlMinutes minutos). Si no fuiste tú, ignora este correo.";

            $mail->send();
        } catch (PHPMailerException $e) {
            throw new RuntimeException('No se pudo enviar el correo de recuperación', 0, $e);
        }
    }
}
