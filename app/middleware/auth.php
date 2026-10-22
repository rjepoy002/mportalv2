<?php

require_once __DIR__ . '/../helpers/session-security.php';
portalSessionStart();
$portalSignIn = str_ends_with((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/api/soa.php')
    ? '../index.php' : 'index.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . $portalSignIn);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
try { $currentPortalUser = portalActiveUser($pdo, $_SESSION['user_id']); }
catch (Throwable $failure) { http_response_code(503); exit('Please try again later.'); }
if (!$currentPortalUser) {
    $_SESSION = [];
    session_regenerate_id(true);
    header('Location: ' . $portalSignIn);
    exit;
}
$_SESSION['user_name'] = $currentPortalUser['name'];
$_SESSION['user_email'] = $currentPortalUser['email'];
