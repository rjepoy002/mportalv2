<?php

require_once __DIR__ . '/../helpers/session-security.php';
portalSessionStart();
require_once __DIR__ . '/../services/auth-rate-limit.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !portalAuthCsrfValid($_POST['csrf_token'] ?? null)) {
    http_response_code(419);
    exit('Please reload the sign-in page and try again.');
}

require_once __DIR__ . '/../../config/database.php';

$error = '';
$success = '';

if (isset($_GET['registered']) && $_GET['registered'] === '1') {

    $success =
        'Your account has been successfully created. You can now sign in.';

}

if (isset($_GET['reset']) && $_GET['reset'] === 'success') {

    $success =
        'Your password has been changed successfully. You can now sign in with your new password.';

}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = is_string($_POST['email'] ?? null) ? trim($_POST['email']) : '';
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    try { $allowed = portalAuthAttemptAllowed('login', $email); }
    catch (Throwable $failure) { $allowed = false; }

    if (!$allowed) {
        http_response_code(429);
        $error = 'Sign-in is temporarily unavailable. Please try again later.';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) {

        $error = 'Please enter a valid email address.';

    } elseif ($password === '') {

        $error = 'Please enter your password.';

    } else {

        try {
        $stmt = $pdo->prepare("
            SELECT
                id,
                email,
                name,
                password_hash,
                google_id,
                status,
                email_verified_at
            FROM users
            WHERE email = ?
            LIMIT 1
        ");

        $stmt->execute([$email]);

        $user = $stmt->fetch();

        $passwordValid = password_verify($password, $user['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if (!$user || !$passwordValid || $user['status'] !== 'active' || $user['email_verified_at'] === null) {

            $error = 'Invalid email or password.';

        } else {

            portalAuthenticated($user);

            header('Location: dashboard.php');
            exit;
        }
        } catch (Throwable $failure) {
            http_response_code(503);
            $error = 'Sign-in is temporarily unavailable. Please try again later.';
        }
    }
}
require __DIR__ . '/../views/pages/index.php';
