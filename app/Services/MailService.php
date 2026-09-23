<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;
use RuntimeException;

class MailService
{
    public function send(
        string $to,
        string $subject,
        string $html,
        string $plainText = ''
    ): void {
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException(
                'El destinatario del correo no es válido.'
            );
        }

        $host = trim((string) ($_ENV['MAIL_HOST'] ?? ''));
        $username = trim((string) ($_ENV['MAIL_USERNAME'] ?? ''));
        $password = (string) ($_ENV['MAIL_PASSWORD'] ?? '');
        $from = trim((string) ($_ENV['MAIL_FROM_ADDRESS'] ?? ''));

        if (
            $host === '' ||
            $username === '' ||
            $password === '' ||
            !filter_var($from, FILTER_VALIDATE_EMAIL)
        ) {
            throw new RuntimeException(
                'La configuración SMTP está incompleta.'
            );
        }

        $port = (int) ($_ENV['MAIL_PORT'] ?? 587);

        if ($port < 1 || $port > 65535) {
            throw new RuntimeException(
                'El puerto SMTP no es válido.'
            );
        }

        $encryption = strtolower(
            trim((string) ($_ENV['MAIL_ENCRYPTION'] ?? 'tls'))
        );

        if (!in_array($encryption, ['tls', 'ssl'], true)) {
            throw new RuntimeException(
                'El tipo de cifrado SMTP no es válido.'
            );
        }

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();

            $mail->Host = $host;
            $mail->SMTPAuth = true;
            $mail->Username = $username;
            $mail->Password = $password;
            $mail->Port = $port;

            $mail->SMTPSecure = $encryption === 'ssl'
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;

            $mail->CharSet = 'UTF-8';

            $mail->setFrom(
                $from,
                (string) (
                    $_ENV['MAIL_FROM_NAME'] ?? 'Tu Huella Vet'
                )
            );

            $mail->addAddress($to);

            $mail->isHTML(true);

            $mail->Subject = $subject;
            $mail->Body = $html;

            $mail->AltBody = $plainText !== ''
                ? $plainText
                : trim(strip_tags($html));

            $mail->send();
        } catch (MailException $e) {

            error_log(
                'Error SMTP al enviar correo: ' .
                    $e->getMessage()
            );

            throw new RuntimeException(
                'No se pudo enviar el correo electrónico.'
            );
        }
    }
}
