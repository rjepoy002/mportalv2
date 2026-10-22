<?php
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../helpers/new-connection-preview.php';
if (!newConnectionPreviewAvailable($_SERVER)) {
    http_response_code(404);
    echo '<h1>Page unavailable</h1>';
    return;
}
if (!in_array($_SERVER['REQUEST_METHOD'] ?? '', ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    return;
}
require_once __DIR__ . '/../helpers/session-security.php';
require_once __DIR__ . '/../middleware/new-connection-auth.php';
// Fail closed even if legacy DB bootstrap terminates instead of throwing.
http_response_code(503);
try {
    portalSessionStart();
    header('Cache-Control: private, no-store');
    $applicant = newConnectionApplicant($_SESSION, static function (): PDO {
        // The existing bootstrap may print connection errors; never forward them to a preview.
        ob_start(static fn(string $output): string => '');
        try { require __DIR__ . '/../../config/database.php'; }
        finally { ob_end_clean(); }
        return $pdo;
    });
} catch (Throwable $failure) {
    if ($failure->getCode() === 401) {
        header('Location: index.php');
        http_response_code(302);
    } elseif ($failure->getCode() === 403) {
        http_response_code(403);
        echo '<h1>Access unavailable</h1><p>An active account with a verified email is required.</p>';
    } else {
        http_response_code(503);
        echo '<h1>Temporarily unavailable</h1>';
    }
    return;
}
http_response_code(200);
$previewMode = true;
$stages = [
    ['Applicant Information and Submission', 'Planned online preparation and review of applicant information.'],
    ['Document Submission and Verification', 'Planned supporting-document checks by PALECO personnel.'],
    ['Billing Clearance', 'Planned internal billing clearance.'],
    ['Online PMES', 'Planned online learning and assessment.'],
    ['Technical Inspection', 'Planned staff-managed inspection of the proposed service location.'],
    ['Payment and Handwritten Form Signing', 'PALECO staff prepare the printed forms. The applicant visits the office to sign them by hand and make payment offline.'],
    ['Installation Scheduling', 'Planned scheduling after required approvals. Scheduling does not mean the service has already been energized.'],
];
$portalActivePage = 'new-connection';
$portalPageTitle = 'New Connection';
$portalUserDisplayName = trim((string) $applicant['name']) ?: 'Applicant';
$portalUserEmail = (string) $applicant['email'];
require __DIR__ . '/../views/pages/new-connection.php';
