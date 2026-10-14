<?php

require_once dirname(__DIR__) . '/config/mailer.php';

class RecoveryFakeMailer extends \PHPMailer\PHPMailer\PHPMailer
{
    public static string $result = 'accepted';
    public function send(): bool
    {
        if (self::$result === 'authentication') {
            $this->ErrorInfo = 'Could not authenticate: sentinel-not-a-real-password';
            throw new \PHPMailer\PHPMailer\Exception('sentinel-token recipient@example.com');
        }
        return self::$result === 'accepted';
    }
}

$originals = [];
foreach (['SMTP_HOST', 'SMTP_PORT', 'SMTP_ENCRYPTION', 'SMTP_USERNAME', 'SMTP_PASSWORD', 'SMTP_FROM_EMAIL', 'PASSWORD_RESET_DEBUG'] as $key) $originals[$key] = getenv($key);
$path = __DIR__ . '/mailer-test-' . bin2hex(random_bytes(6)) . '.log';
$originalLog = ini_set('error_log', $path);
try {
    putenv('SMTP_HOST=smtp.example.com');
    putenv('SMTP_PORT=587');
    putenv('SMTP_ENCRYPTION=tls');
    putenv('PASSWORD_RESET_DEBUG=1');
    // Invalid sender fails before any network connection; exercise actual PHPMailer catch.
    putenv('SMTP_USERNAME=test@example.com');
    putenv('SMTP_PASSWORD=sentinel-not-a-real-password');
    putenv('SMTP_FROM_EMAIL=invalid-sender');
    $body = '<a href="https://example.com/reset-password.php?token=sentinel-token">Reset</a>';
    if (sendEmail('recipient@example.com', 'Test', 'Reset', $body, $body) !== false) {
        throw new RuntimeException('Mailer failure must return false');
    }
    $log = file_get_contents($path);
    foreach (['sentinel-token', 'sentinel-not-a-real-password', 'recipient@example.com', 'invalid-sender', '?token='] as $sensitive) {
        if (str_contains($log, $sensitive)) throw new RuntimeException('Mailer diagnostic leaked sensitive content');
    }
    if (!str_contains($log, 'SMTP delivery failed.')) throw new RuntimeException('Missing safe failure log');
    putenv('SMTP_FROM_EMAIL=test@example.com');
    foreach (['authentication', 'rejected', 'accepted'] as $result) {
        RecoveryFakeMailer::$result = $result;
        $accepted = sendEmail('recipient@example.com', 'Test', 'Reset', $body, $body, 'test-correlation', fn () => new RecoveryFakeMailer(true));
        if ($accepted !== ($result === 'accepted')) throw new RuntimeException('Unexpected fake transport result');
    }
    putenv('SMTP_ENCRYPTION=invalid');
    if (sendEmail('recipient@example.com', 'Test', 'Reset', $body, $body, 'test-correlation', function () { throw new RuntimeException('Must not initialize invalid transport'); })) {
        throw new RuntimeException('Invalid encryption must fail closed');
    }
    putenv('SMTP_ENCRYPTION=tls');
    putenv('SMTP_USERNAME');
    putenv('SMTP_PASSWORD');
    putenv('SMTP_FROM_EMAIL');
    if (sendEmail('recipient@example.com', 'Test', 'Reset', $body, $body) !== false) {
        throw new RuntimeException('Missing SMTP configuration must fail closed');
    }
    $log = file_get_contents($path);
    if (!str_contains($log, 'SMTP configuration incomplete: SMTP_USERNAME, SMTP_PASSWORD, SMTP_FROM_EMAIL.')) {
        throw new RuntimeException('Missing safe configuration diagnostic');
    }
    foreach (['authentication_failed', 'transport_rejected', 'email_transport_accepted', 'email_delivery_attempted', 'missing_mail_configuration', 'invalid_mail_configuration', 'test-correlation'] as $expected) {
        if (!str_contains($log, $expected)) throw new RuntimeException('Missing structured transport event: ' . $expected);
    }
    foreach (['sentinel-token', 'sentinel-not-a-real-password', 'recipient@example.com', '?token='] as $sensitive) {
        if (str_contains($log, $sensitive)) throw new RuntimeException('Structured diagnostic leaked sensitive content');
    }
    putenv('PASSWORD_RESET_DEBUG=0');
    $size = strlen(file_get_contents($path));
    recovery_log('test-correlation', 'debug_disabled_sentinel');
    if (strlen(file_get_contents($path)) !== $size) throw new RuntimeException('Debug logging must be disabled by default');
    echo "Mailer configuration, authentication, rejection, acceptance and log privacy tests passed (no network).\n";
} finally {
    foreach ($originals as $key => $value) $value === false ? putenv($key) : putenv($key . '=' . $value);
    ini_set('error_log', $originalLog);
    if (is_file($path)) unlink($path);
}
