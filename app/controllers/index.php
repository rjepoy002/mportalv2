<?php

session_start();

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

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $error = 'Please enter a valid email address.';

    } elseif ($password === '') {

        $error = 'Please enter your password.';

    } else {

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

        if (!$user || !password_verify($password, $user['password_hash'])) {

            $error = 'Invalid email or password.';

            if (
                $user &&
                !empty($user['google_id'])
            ) {

                $error .= ' If you previously signed in with Google, you can continue with Google or reset your password.';

            }

        } elseif ($user['status'] !== 'active') {

            $error = 'Your account is not active.';

        } elseif ($user['email_verified_at'] === null) {

            $error = 'Please verify your email address first.';

        } else {

            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_name'] = $user['name'];
            unset($_SESSION['SelectedAcctNo']);

            header('Location: dashboard.php');
            exit;
        }
    }
}
require __DIR__ . '/../views/pages/index.php';
