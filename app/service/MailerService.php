<?php

use PHPMailer\PHPMailer\Exception as PhpMailerException;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Envío SMTP (Gmail) vía PHPMailer.
 * Credenciales en ~/websites/.env-bi: GMAIL_USER, GMAIL_APP_PASSWORD.
 */
final class MailerService
{
    public function enviar(
        string $para,
        string $asunto,
        string $cuerpoHtml,
        string $cuerpoTexto = ''
    ): void {
        $user = trim((string) ($_ENV['GMAIL_USER'] ?? ''));
        $pass = trim((string) ($_ENV['GMAIL_APP_PASSWORD'] ?? ''));
        $from = trim((string) ($_ENV['GMAIL_FROM'] ?? $user));
        $fromName = trim((string) ($_ENV['GMAIL_FROM_NAME'] ?? 'Bingo CCM'));

        if ($user === '' || $pass === '') {
            throw new RuntimeException(
                'Falta configurar GMAIL_USER y GMAIL_APP_PASSWORD en .env-bi.'
            );
        }

        $para = trim($para);

        if ($para === '' || filter_var($para, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException(
                'El destinatario no es un correo válido.'
            );
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = (string) ($_ENV['GMAIL_HOST'] ?? 'smtp.gmail.com');
            $mail->SMTPAuth = true;
            $mail->Username = $user;
            $mail->Password = $pass;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int) ($_ENV['GMAIL_PORT'] ?? 587);
            $mail->CharSet = 'UTF-8';

            $mail->setFrom($from !== '' ? $from : $user, $fromName);
            $mail->addAddress($para);
            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body = $cuerpoHtml;
            $mail->AltBody = $cuerpoTexto !== ''
                ? $cuerpoTexto
                : strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $cuerpoHtml));

            $mail->send();
        } catch (PhpMailerException $exception) {
            error_log('MailerService: ' . $exception->getMessage());

            throw new RuntimeException(
                'No fue posible enviar el correo. Revisa Gmail y la clave de aplicación.'
            );
        }
    }
}
