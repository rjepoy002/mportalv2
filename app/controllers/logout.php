<?php

header('Cache-Control: no-store, private');
$json = ($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json';
function logoutFailure(int $status, string $message): never
{
    global $json;
    http_response_code($status);
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => $message]);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<p role="alert">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p><a href="index.php">Return to sign in</a>';
    }
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    logoutFailure(405, 'Please use the Sign Out confirmation in the portal.');
}
require_once __DIR__ . '/../helpers/session-security.php';
try { portalSessionStart(); }
catch (Throwable $failure) { logoutFailure(503, 'Your session is temporarily unavailable. Please try again.'); }
if (empty($_SESSION['user_id'])) logoutFailure(401, 'Your session has expired. Please return to sign in.');
$token = $_POST['csrf_token'] ?? null;
$expected = $_SESSION['dashboard_csrf'] ?? '';
if (!is_string($token) || !is_string($expected) || $expected === '' || !hash_equals($expected, $token)) {
    logoutFailure(419, 'Your session could not be verified. Refresh this page and try again.');
}
$intent = $_POST['intent'] ?? '';
if (!in_array($intent, ['logout', 'reset'], true)) logoutFailure(400, 'The requested action is invalid. Please refresh and try again.');
$destination = $intent === 'reset' ? 'forgot-password.php' : 'index.php';

// Destroy the server session before expiring the browser cookie.
$previous = $_SESSION;
$_SESSION = [];
if (!session_destroy()) {
    $_SESSION = $previous;
    logoutFailure(503, 'We could not sign you out. Please try again.');
}

// Delete the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        ['expires' => time() - 42000, 'path' => $params['path'], 'domain' => $params['domain'],
         'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Lax']
    );
}

if ($json) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'redirect' => $destination]);
    exit;
}

// Redirect to login page
header('Location: ' . $destination);
exit;
