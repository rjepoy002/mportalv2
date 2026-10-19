<?php

header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
header('Pragma: no-cache');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../services/mailer.php';
require_once __DIR__ . '/../services/password-recovery.php';

$message = '';
$messageType = '';
$email = '';
$csrfToken = recovery_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $requestId = bin2hex(random_bytes(16));
    $stage = 'request_validation';
    recovery_log($requestId, 'request_received');

    $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';

    // Validate email
    if (!is_string($_POST['csrf_token'] ?? null) || !hash_equals($csrfToken, $_POST['csrf_token'])) {
        recovery_log($requestId, 'request_validation_failed', 'csrf_invalid');
        $message = 'Please reload the page and try again.';
        $messageType = 'error';
    } elseif ($email === '') {

        $message = 'Please enter your email address.';
        $messageType = 'error';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = 'Please enter a valid email address.';
        $messageType = 'error';

    } else {
        recovery_log($requestId, 'request_validation_completed');

        try {
            $stage = 'app_configuration';
            require_once __DIR__ . '/../../config/app.php';
            app_base_url();
            $stage = 'rate_limiter';
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $ip = filter_var($ip, FILTER_VALIDATE_IP) ? (inet_ntop(inet_pton($ip))) : 'unknown';
            $allowed = recovery_request_allowed($email, $ip);
            if (!$allowed) recovery_log($requestId, 'request_throttled', 'rate_limited');

            // Find user by email
            if ($allowed) {
                $stage = 'user_lookup';
                $stmt = $pdo->prepare(
                    'SELECT id, name, email FROM users WHERE email = :email LIMIT 1'
                );

                $stmt->execute([
                    ':email' => $email
                ]);

                $user = $stmt->fetch();
                recovery_log($requestId, 'user_lookup_completed');

                /*
                 * Always show the same message whether the email
                 * exists or not. This prevents account enumeration.
                 */
                if ($user) {
                    $stage = 'token_generation';

                    // Generate secure reset token
                    $token = bin2hex(random_bytes(32));

                    // Store only the token hash
                    $tokenHash = hash('sha256', $token);

                    // Preserve existing valid links, including when SMTP delivery fails.
                    // Store new reset token
                    $stage = 'token_persistence';
                    $insertStmt = $pdo->prepare(
                        'INSERT INTO password_resets
                        (user_id, token_hash, expires_at)
                        VALUES
                        (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 1 HOUR))'
                    );

                    $insertStmt->execute([
                        ':user_id' => $user['id'],
                        ':token_hash' => $tokenHash
                    ]);
                    recovery_log($requestId, 'reset_token_persistence_completed');

                    // Create reset link
                    $resetLink =
                        app_url('reset-password.php?token=' . rawurlencode($token));

                    // Email content
                    $subject = 'Reset Your PALECO MCO Portal Password';

                    $htmlBody = '
                        <h2>Password Reset Request</h2>

                        <p>Hello ' . htmlspecialchars($user['name']) . ',</p>

                        <p>
                            We received a request to reset your PALECO MCO Portal password.
                        </p>

                        <p>
                            Click the button below to create a new password:
                        </p>

                        <p>
                            <a href="' . htmlspecialchars($resetLink, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"
                               style="
                                   display:inline-block;
                                   padding:12px 20px;
                                   background:#1a73e8;
                                   color:#ffffff;
                                   text-decoration:none;
                                   border-radius:6px;
                               ">
                                Reset Password
                            </a>
                        </p>

                        <p>
                            This link will expire in <strong>1 hour</strong>.
                        </p>

                        <p>
                            If you did not request a password reset, you can safely
                            ignore this email.
                        </p>

                        <p>
                            Regards,<br>
                            PALECO MCO Portal
                        </p>
                    ';

                    $plainBody =
                        "Hello {$user['name']},\n\n"
                        . "We received a request to reset your PALECO MCO Portal password.\n\n"
                        . "Reset your password using this link:\n"
                        . $resetLink . "\n\n"
                        . "This link will expire in 1 hour.\n\n"
                        . "If you did not request a password reset, you can safely "
                        . "ignore this email.\n\n"
                        . "Regards,\n"
                        . "PALECO MCO Portal";

                    $stage = 'email_delivery';
                    $delivered = sendEmail(
                        $user['email'],
                        $user['name'],
                        $subject,
                        $htmlBody,
                        $plainBody,
                        $requestId
                    );
                    if (!$delivered) {
                        recovery_log($requestId, 'email_delivery_failed', 'mailer_returned_false');
                        $stage = 'token_cleanup';
                        // Remove only this undelivered replacement, never an earlier link.
                        $cleanup = $pdo->prepare(
                            'DELETE FROM password_resets WHERE token_hash = :token_hash AND user_id = :user_id'
                        );
                        $cleanup->execute([':token_hash' => $tokenHash, ':user_id' => $user['id']]);
                    } else {
                        $stage = 'token_cleanup';
                        $cleanup = $pdo->prepare(
                            'DELETE FROM password_resets WHERE user_id = :user_id AND (expires_at <= NOW() OR used_at IS NOT NULL)'
                        );
                        $cleanup->execute([':user_id' => $user['id']]);
                    }
                }
            }
        } catch (Throwable $e) {
            // Never include exception details: they may contain mail content or SQL parameters.
            recovery_log($requestId, 'request_failed', $stage, $e);
        }

        $message =
            'If an account exists with that email address, '
            . 'you will receive a password reset link if the request can be processed.';

        $messageType = 'success';
    }
}

require __DIR__ . '/../views/pages/forgot-password.php';
