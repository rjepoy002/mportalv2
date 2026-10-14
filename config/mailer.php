<?php

use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/recovery-log.php';

function sendEmail(
    string $recipientEmail,
    string $recipientName,
    string $subject,
    string $htmlBody,
    string $plainBody = '',
    string $requestId = '',
    ?callable $mailerFactory = null
): bool {

    $requestId = $requestId ?: bin2hex(random_bytes(16));
    $config = require __DIR__ . '/mail.php';

    $missing = [];
    foreach (['username' => 'SMTP_USERNAME', 'password' => 'SMTP_PASSWORD', 'from_email' => 'SMTP_FROM_EMAIL'] as $key => $variable) {
        if ($config[$key] === '') $missing[] = $variable;
    }
    if ($missing) {
        // Report configuration names only, never credentials or message content.
        error_log('SMTP configuration incomplete: ' . implode(', ', $missing) . '.');
        recovery_log($requestId, 'email_delivery_failed', 'missing_mail_configuration');
        return false;
    }
    if (trim($config['host']) === '' || $config['port'] < 1 || $config['port'] > 65535
        || !in_array($config['encryption'], ['tls', 'ssl'], true)) {
        recovery_log($requestId, 'email_delivery_failed', 'invalid_mail_configuration');
        return false;
    }

    try {
        $mail = $mailerFactory ? $mailerFactory() : new PHPMailer(true);

        $mail->isSMTP();
        $mail->SMTPDebug = 0;
        $mail->Timeout = 15;

        $mail->Host       = $config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['username'];
        $mail->Password   = $config['password'];
        $mail->Port       = $config['port'];

        if ($config['encryption'] === 'tls') {

            $mail->SMTPSecure =
                PHPMailer::ENCRYPTION_STARTTLS;

        } else {

            $mail->SMTPSecure =
                PHPMailer::ENCRYPTION_SMTPS;
        }

        $mail->CharSet = 'UTF-8';

        $mail->setFrom(
            $config['from_email'],
            $config['from_name']
        );

        $mail->addAddress(
            $recipientEmail,
            $recipientName
        );

        $mail->isHTML(true);

        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;

        $mail->AltBody =
            $plainBody !== ''
                ? $plainBody
                : strip_tags($htmlBody);

        recovery_log($requestId, 'email_delivery_attempted');
        $accepted = $mail->send();
        recovery_log($requestId, $accepted ? 'email_transport_accepted' : 'email_delivery_failed',
            $accepted ? '' : 'transport_rejected');
        return $accepted;

    } catch (Throwable $e) {

        error_log(
            'SMTP delivery failed.'
        );
        // PHPMailer diagnostics can contain recipients, server replies or credentials.
        // Inspect only to classify; never emit their contents.
        $detail = isset($mail) ? $mail->ErrorInfo : '';
        $category = stripos($detail, 'authenticate') !== false ? 'authentication_failed' : 'transport_exception';
        recovery_log($requestId, 'email_delivery_failed', $category, $e);

        return false;
    }
}
