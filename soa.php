<?php
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
function soaResponse(int $status, array $body): never { http_response_code($status); echo json_encode($body, JSON_UNESCAPED_SLASHES); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') soaResponse(405, ['ok' => false, 'message' => 'Method not allowed.']);
$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId <= 0) soaResponse(401, ['ok' => false, 'message' => 'Your session has expired. Please sign in again.']);
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/account-context.php';
require_once __DIR__ . '/includes/soa-service.php';
if (!hash_equals(dashboardCsrfToken(), (string) ($_POST['csrf_token'] ?? ''))) soaResponse(419, ['ok' => false, 'message' => 'Your session expired. Please try again.']);
$acctNo = trim((string) ($_SESSION['SelectedAcctNo'] ?? ''));
if ($acctNo === '') soaResponse(403, ['ok' => false, 'message' => 'Unauthorized account.']);
try {
    $account = soaAuthorizedSelectedAccount($pdo, $userId, $acctNo);
    $month = soaLatestBillMonth($pdo, $userId, $acctNo);
    if ($month === null) soaResponse(404, ['ok' => false, 'message' => 'Statement of Account is not yet available.']);
    $result = soaRequestPdfUrl((string) $account['AcctCode'], $month, require __DIR__ . '/config/soa.php');
    soaResponse($result['ok'] ? 200 : 502, $result + ['month' => $month, 'account_code' => (string) $account['AcctCode']]);
} catch (DomainException $e) { soaResponse(403, ['ok' => false, 'message' => 'Unauthorized account.']); }
catch (Throwable $e) { error_log('SOA request failed: ' . $e->getMessage()); soaResponse(500, ['ok' => false, 'message' => 'Statement of Account is currently unavailable.']); }