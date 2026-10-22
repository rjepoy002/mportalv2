<?php

header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Content-Type: text/html; charset=utf-8');

$module = require __DIR__ . '/../../config/new-connection.php';
if (($module['enabled'] ?? false) !== true) {
    // Gate before session startup, database bootstrap or shell rendering.
    http_response_code(404);
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Page unavailable</title><h1>Page unavailable</h1><p>This page is not available.</p></html>';
    return;
}
if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    return;
}

require_once __DIR__ . '/../middleware/new-connection-auth.php';
require_once __DIR__ . '/../helpers/session-security.php';
try {
    portalSessionStart();
    // PHP's session cache limiter can replace the header set before startup.
    header('Cache-Control: no-store, private');
    $applicant = newConnectionApplicant($_SESSION, static function (): PDO {
        require __DIR__ . '/../../config/database.php';
        return $pdo;
    });
} catch (Throwable $failure) {
    if ($failure->getCode() === 401) {
        header('Location: index.php');
        http_response_code(302);
    } elseif ($failure->getCode() === 403) {
        http_response_code(403);
        echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Access unavailable</title><h1>Access unavailable</h1><p>An active account with a verified email is required.</p><a href="index.php">Return to sign in</a></html>';
    } else {
        http_response_code(503);
        echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Temporarily unavailable</title><h1>Temporarily unavailable</h1><p>Please try again later.</p></html>';
    }
    return;
}

// Roadmap only: no workflow records, forms, uploads, assessment or staff actions.
$stages = [
    ['Application preparation and submission', 'Complete your application details and preview the required forms online.'],
    ['Supporting-document verification', 'Provide the required documents for PALECO personnel to review.'],
    ['Internal billing clearance', 'PALECO personnel review the applicable billing clearance requirements.'],
    ['Online PMES and assessment', 'Complete the required learning materials and assessment.'],
    ['Technical inspection', 'PALECO personnel arrange and record the technical inspection.'],
    ['Payment visit and signed-form verification', 'Visit PALECO for offline payment and handwritten signatures on the official printed forms.'],
    ['Installation scheduling', 'PALECO personnel schedule installation after all required approvals.'],
];
$portalActivePage = 'new-connection';
$portalPageTitle = 'New Connection';
$portalUserDisplayName = trim((string) $applicant['name']) ?: 'Applicant';
$portalUserEmail = (string) $applicant['email'];
require __DIR__ . '/../views/pages/new-connection.php';
