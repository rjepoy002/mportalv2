<?php

header('Referrer-Policy: no-referrer');
header('Cache-Control: no-store');
header('Pragma: no-cache');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../services/password-recovery.php';

$message = '';
$messageType = '';

/*
 * ============================================
 * RESET TOKEN
 * ============================================
 *
 * Accept the token from either GET or POST.
 * The hidden POST field keeps the token available
 * when the password form is submitted.
 */

$token = trim(
    $_GET['token']
    ?? $_POST['token']
    ?? ''
);

$user = null;
$resetRecord = null;


/*
 * ============================================
 * VALIDATE RESET TOKEN
 * ============================================
 */

if (
    $token === ''
    || !preg_match('/^[a-f0-9]{64}$/i', $token)
) {

    $message =
        'This password reset link is invalid or has expired.';

    $messageType = 'error';

} else {

    $tokenHash = hash('sha256', $token);

    $stmt = $pdo->prepare(
        'SELECT
            password_resets.id AS reset_id,
            password_resets.user_id,
            password_resets.expires_at,
            users.name,
            users.email
         FROM password_resets
         INNER JOIN users
            ON users.id = password_resets.user_id
         WHERE password_resets.token_hash = :token_hash
           AND password_resets.used_at IS NULL
           AND password_resets.expires_at > NOW()
         LIMIT 1'
    );

    $stmt->execute([
        ':token_hash' => $tokenHash
    ]);

    $resetRecord = $stmt->fetch();

    if (!$resetRecord) {

        $message =
            'This password reset link is invalid or has expired.';

        $messageType = 'error';

    } else {

        $user = $resetRecord;
    }
}


/*
 * ============================================
 * HANDLE PASSWORD RESET
 * ============================================
 */

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $user
    && $resetRecord
) {

    $newPassword =
        $_POST['password'] ?? '';

    $confirmPassword =
        $_POST['confirm_password'] ?? '';


    /*
     * -----------------------------------------
     * PASSWORD VALIDATION
     * -----------------------------------------
     */

    if ($newPassword === '') {

        $message =
            'Please enter a new password.';

        $messageType = 'error';

    } elseif (preg_match_all('/./us', $newPassword) < 8) {

        $message =
            'Password must be at least 8 characters.';

        $messageType = 'error';

    } elseif (!preg_match('/[A-Za-z]/', $newPassword)) {

        $message =
            'Password must contain at least one letter.';

        $messageType = 'error';

    } elseif (!preg_match('/[0-9]/', $newPassword)) {

        $message =
            'Password must contain at least one number.';

        $messageType = 'error';

    } elseif ($confirmPassword === '') {

        $message = 'Please confirm your password.';
        $messageType = 'error';

    } elseif ($newPassword !== $confirmPassword) {

        $message =
            'Passwords do not match.';

        $messageType = 'error';

    } else {

        /*
         * -----------------------------------------
         * HASH NEW PASSWORD
         * -----------------------------------------
         */

        try {
            if (!recovery_reset_password($pdo, $resetRecord, $tokenHash, $newPassword)) {
                $message = 'This password reset link is invalid or has expired.';
                $messageType = 'error';
                $user = null;
            } else {
                header('Location: index.php?reset=success');
                exit;
            }
        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {

                $pdo->rollBack();
            }


            error_log(
                'Password reset failed.'
            );


            $message =
                'Something went wrong. Please try again.';

            $messageType = 'error';
        }
    }
}

require __DIR__ . '/../views/pages/reset-password.php';
