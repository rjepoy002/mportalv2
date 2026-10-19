<?php

/**
 * PALECO MCO Portal
 * dashboard.php
 *
 * LOCAL XAMPP DEVELOPMENT ONLY
 *
 * DATABASE SEPARATION
 * ---------------------------------------------------------
 * mco_portal
 *   users              READ / WRITE
 *   paleco_accounts    READ / WRITE
 *
 * palecxzp_pal_db
 *   ebills             READ ONLY
 *   master             READ ONLY
 *   billhistory        READ ONLY
 *   arledger           READ ONLY
 *   member             READ ONLY
 */


/* =========================================================
   LOCALHOST ONLY
   ========================================================= */

$allowedHosts = [
    'localhost',
    '127.0.0.1',
    '::1',
];

$currentHost = $_SERVER['HTTP_HOST'] ?? '';

if (str_starts_with($currentHost, '[')) {

    $closingBracket = strpos($currentHost, ']');

    if ($closingBracket !== false) {
        $currentHost = substr(
            $currentHost,
            1,
            $closingBracket - 1
        );
    }

} else {

    $currentHost = preg_replace(
        '/:\d+$/',
        '',
        $currentHost
    );
}

$currentHost = strtolower(trim($currentHost));

if (!in_array($currentHost, $allowedHosts, true)) {

    http_response_code(403);

    exit(
        '<!doctype html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <meta
                name="viewport"
                content="width=device-width, initial-scale=1"
            >
            <title>403 - Local Development Only</title>
        </head>
        <body>
            <h1>403 - Local Development Only</h1>
            <p>
                This PALECO MCO Portal development build
                can only run on localhost.
            </p>
        </body>
        </html>'
    );
}


/* =========================================================
   BOOTSTRAP
   ========================================================= */

require_once __DIR__ . '/../middleware/account-context.php';
require_once __DIR__ . '/../services/account-linking.php';
require_once __DIR__ . '/../services/account-unlinking.php';
require_once __DIR__ . '/../services/ebills-enrollment.php';
require_once __DIR__ . '/../services/billing-service.php';


/* =========================================================
   AUTHENTICATION
   ========================================================= */

$userId = (int) ($_SESSION['user_id'] ?? 0);

if ($userId <= 0) {
    header('Location: index.php');
    exit;
}


/* =========================================================
   USER INFORMATION

   Keep using session values because these are already
   populated by the existing login system.
   ========================================================= */

$userEmail = trim(
    (string) ($_SESSION['user_email'] ?? '')
);

$userDisplayName = trim(
    (string) ($_SESSION['user_name'] ?? '')
);

if ($userDisplayName === '') {
    $userDisplayName = $userEmail !== ''
        ? $userEmail
        : 'Member';
}


/* =========================================================
   DISPLAY HELPERS
   ========================================================= */

function formatBillMonth(?string $billMonth): string
{
    $billMonth = trim((string) $billMonth);

    if (!preg_match('/^(\d{4})(\d{2})$/', $billMonth, $match)) {
        return $billMonth !== '' ? $billMonth : '—';
    }

    $year = (int) $match[1];
    $month = (int) $match[2];

    if ($month < 1 || $month > 12) {
        return $billMonth;
    }

    return strtoupper(
        date(
            'M Y',
            mktime(0, 0, 0, $month, 1, $year)
        )
    );
}


function formatPortalDate(?string $date): string
{
    $date = trim((string) $date);

    if ($date === '') {
        return '—';
    }

    $timestamp = strtotime($date);

    if ($timestamp === false) {
        return $date;
    }

    return date('M j, Y', $timestamp);
}


function unpaidBillStatus(?string $dueDate): array
{
    $dueDate = trim((string) $dueDate);

    if ($dueDate === '') {
        return [
            'label' => 'Due Date Unavailable',
            'class' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
        ];
    }

    $timestamp = strtotime($dueDate);

    if ($timestamp === false) {
        return [
            'label' => 'Due Date Unavailable',
            'class' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
        ];
    }

    if (date('Y-m-d', $timestamp) < date('Y-m-d')) {
        return [
            'label' => 'Overdue',
            'class' => 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300',
        ];
    }

    return [
        'label' => 'Not Yet Due',
        'class' => 'bg-paleco-50 text-paleco-700 dark:bg-paleco-900 dark:text-paleco-100',
    ];
}

function formatConsumerAccountCode(array $account): string
{
    $accountCode = trim((string) ($account['AcctCode'] ?? ''));

    return $accountCode !== ''
        ? 'Account ' . $accountCode
        : 'Account code unavailable';
}
/* =========================================================
   LEGACY EBILLS SYNCHRONIZATION

   READ:
       palecxzp_pal_db.ebills
       palecxzp_pal_db.master

   WRITE:
       mco_portal.paleco_accounts only
   ========================================================= */

function syncLegacyEbillsAccounts(
    PDO $pdo,
    int $userId,
    string $userEmail
): void {

    $userEmail = trim($userEmail);

    if ($userId <= 0 || $userEmail === '') {
        return;
    }

    /*
     * Existing eBills registrations:
     *
     * ebills.Email
     *      ↓
     * ebills.AccCode
     *      ↓
     * master.AcctCode
     *      ↓
     * master.AcctNo
     */

    $stmt = $pdo->prepare("
        SELECT DISTINCT
            m.AcctNo
        FROM palecxzp_pal_db.ebills AS e
        INNER JOIN palecxzp_pal_db.master AS m
            ON m.AcctCode = e.AccCode
        WHERE LOWER(TRIM(e.Email)) = LOWER(TRIM(?))
          AND e.AccCode IS NOT NULL
          AND e.AccCode <> ''
          AND m.AcctNo IS NOT NULL
          AND m.AcctNo <> ''
    ");

    $stmt->execute([$userEmail]);

    $legacyAccounts = $stmt->fetchAll(
        PDO::FETCH_COLUMN
    );

    if (!$legacyAccounts) {
        return;
    }

    $notificationEvents = [];
    try {

        $pdo->beginTransaction();

        /*
         * Lock portal user.
         */

        $lock = $pdo->prepare("
            SELECT id
            FROM mco_portal.users
            WHERE id = ?
            FOR UPDATE
        ");

        $lock->execute([$userId]);


        /*
         * Find an existing portal relationship.
         */

        $check = $pdo->prepare("
            SELECT
                id,
                status
            FROM mco_portal.paleco_accounts
            WHERE user_id = ?
              AND AcctNo = ?
            LIMIT 1
            FOR UPDATE
        ");


        /*
         * Add a missing portal relationship.
         */

        $insert = $pdo->prepare("
            INSERT INTO mco_portal.paleco_accounts
            (
                user_id,
                AcctNo,
                account_nickname,
                is_primary,
                status,
                verified_at
            )
            VALUES
            (
                ?,
                ?,
                NULL,
                0,
                'active',
                NOW()
            )
        ");


        foreach ($legacyAccounts as $acctNo) {

            $acctNo = trim((string) $acctNo);

            if ($acctNo === '') {
                continue;
            }

            $check->execute([
                $userId,
                $acctNo
            ]);

            $existing = $check->fetch(
                PDO::FETCH_ASSOC
            );

            if ($existing) {
                // Archived links reflect a user's unlink choice. Relink manually.
                continue;
            }

            $insert->execute([
                $userId,
                $acctNo
            ]);
            $notificationEvents[] = queueAccountNotification($pdo, $userId, $acctNo, 'linked');
        }


        /*
         * Check for primary account.
         */

        $primary = $pdo->prepare("
            SELECT id
            FROM mco_portal.paleco_accounts
            WHERE user_id = ?
              AND status = 'active'
              AND is_primary = 1
            LIMIT 1
        ");

        $primary->execute([$userId]);

        $hasPrimary = $primary->fetchColumn();


        /*
         * If no primary exists, use oldest active account.
         */

        if (!$hasPrimary) {

            $first = $pdo->prepare("
                SELECT id
                FROM mco_portal.paleco_accounts
                WHERE user_id = ?
                  AND status = 'active'
                ORDER BY id ASC
                LIMIT 1
            ");

            $first->execute([$userId]);

            $firstId = $first->fetchColumn();

            if ($firstId) {

                $setPrimary = $pdo->prepare("
                    UPDATE mco_portal.paleco_accounts
                    SET is_primary = 1
                    WHERE id = ?
                      AND user_id = ?
                ");

                $setPrimary->execute([
                    $firstId,
                    $userId
                ]);
            }
        }

        $pdo->commit();
        foreach ($notificationEvents as $event) dispatchAccountNotification($pdo, $event);

    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'Legacy eBills synchronization failed: '
            . $e->getMessage()
        );

        /*
         * Do not block dashboard access just because
         * legacy account synchronization failed.
         */
    }
}


/* =========================================================
   SYNCHRONIZE EBILLS ACCOUNTS
   ========================================================= */

syncLegacyEbillsAccounts(
    $pdo,
    $userId,
    $userEmail
);


/* =========================================================
   NAVIGATION
   ========================================================= */

$nav = [
    'dashboard'        => 'Dashboard',
    'bills'            => 'My Bills',
    'account-settings' => 'Account & Settings',
];

/* Keep legacy bookmarks functional without retaining redundant navigation. */
$legacyPageRoutes = [
    'bill-history' => ['page' => 'bills', 'tab' => 'history'],
    'consumption'  => ['page' => 'dashboard'],
    'accounts'     => ['page' => 'account-settings'],
    'profile'      => ['page' => 'account-settings'],
];

$requestedPage = $_GET['page'] ?? 'dashboard';
$legacyRoute = is_string($requestedPage)
    ? ($legacyPageRoutes[$requestedPage] ?? null)
    : null;

$page = $legacyRoute['page'] ?? (
    is_string($requestedPage)
    && array_key_exists($requestedPage, $nav)
        ? $requestedPage
        : 'dashboard'
);

$requestedBillsTab = $legacyRoute['tab'] ?? ($_GET['tab'] ?? 'unpaid');
$billsTab = (
    $page === 'bills'
    && is_string($requestedBillsTab)
    && in_array($requestedBillsTab, ['unpaid', 'history'], true)
)
    ? $requestedBillsTab
    : 'unpaid';

/* Explicit configuration only; localhost alone never enables this notice. */
$appEnvironment = strtolower((string) (getenv('MCO_APP_ENV') ?: 'production'));
$isDevelopmentMode = in_array(
    $appEnvironment,
    ['development', 'local'],
    true
);


$accountLinker = new PalecoAccountLinker($pdo, require __DIR__ . '/../../config/account-linking.php');
$ebills = new EBillsEnrollment($pdo);

/* =========================================================
   POST ACTIONS
   ========================================================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = (string) ($_POST['action'] ?? '');

    $returnPage = 'account-settings';

    if ($action === 'switch') {
        $requestedReturnPage = (string) ($_POST['return_page'] ?? 'dashboard');
        $returnPage = array_key_exists($requestedReturnPage, $nav)
            ? $requestedReturnPage
            : 'dashboard';
    }


    /* -----------------------------------------------------
       CSRF
       ----------------------------------------------------- */

    if (
        !hash_equals(
            dashboardCsrfToken(),
            (string) ($_POST['csrf_token'] ?? '')
        )
    ) {

        dashboardReturn(
            $returnPage,
            'Your session expired. Please try again.',
            'error'
        );
    }


    $acctNo = dashboardResolveAccountSelectionToken(
        trim((string) ($_POST['account_token'] ?? ''))
    ) ?? '';


    try {

        /* =================================================
           SWITCH ACCOUNT
           ================================================= */

        if ($action === 'switch') {

            $stmt = $pdo->prepare("
                SELECT AcctNo
                FROM mco_portal.paleco_accounts
                WHERE user_id = ?
                  AND AcctNo = ?
                  AND status = 'active'
                LIMIT 1
            ");

            $stmt->execute([
                $userId,
                $acctNo
            ]);

            if (!$stmt->fetch()) {

                dashboardReturn(
                    $returnPage,
                    'That account is not linked to your profile.',
                    'error'
                );
            }

            $_SESSION['SelectedAcctNo'] = $acctNo;

            dashboardReturn(
                $returnPage,
                'Viewing account updated.'
            );
        }


        /* =================================================
           ADD ACCOUNT

           Manual verification:
               master.AcctCode
               +
               master.MeterSerial

           master remains READ ONLY.
           ================================================= */

        if (in_array($action, ['save_profile','ebills_details','ebills_accept_terms','ebills_enroll','finish_ebills'],true)) {
            try {
                if ($action === 'finish_ebills') {
                    unset($_SESSION['ebills_step3_acct']);
                    header('Location: dashboard.php?page=account-settings'); exit;
                }
                if ($action === 'save_profile') {
                    $ebills->saveProfile($userId,$_POST,dashboardCsrfToken());
                    dashboardReturn('account-settings','Profile information saved and confirmed.');
                }
                if ($action === 'ebills_details') {
                    $response=['ok'=>true,'details'=>$ebills->details($userId,$acctNo)];
                } elseif ($action === 'ebills_accept_terms') {
                    $ebills->acceptTerms($userId,$acctNo,$_POST,dashboardCsrfToken());
                    $response=['ok'=>true];
                } else {
                    $created=$ebills->enroll($userId,$acctNo,$_POST,dashboardCsrfToken());
                    $response=['ok'=>true,'created'=>$created,'message'=>$created ? 'Successfully Enrolled in eBills. Your PALECO account has been enrolled in eBills. Future Statements of Account will be sent to your registered email address through PALECO\'s billing system.' : 'Already Enrolled in eBills. This PALECO account is already registered for eBills.'];
                }
            } catch (DomainException $e) { $response=['ok'=>false,'message'=>$e->getMessage()]; }
              catch (Throwable $e) { error_log('eBills profile/enrollment operation unavailable.'); $response=['ok'=>false,'message'=>'We could not complete that request. Your linked account is unchanged. Please try again.']; }
            if (($_SERVER['HTTP_ACCEPT']??'')!=='application/json') dashboardReturn('account-settings',$response['message'],$response['ok']?'success':'error');
            header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, private');
            if(!$response['ok'])http_response_code(400);
            echo json_encode($response);exit;
        }

        if (in_array($action, ['add', 'verify_link', 'restart_link'], true)) {
            $result = $accountLinker->handle($userId, $_POST, dashboardCsrfToken());
            if($action==='verify_link'&&$result['ok'])$_SESSION['ebills_step3_acct']=$accountLinker->linkedAccountForEnrollment();
            elseif($action==='add'||$action==='restart_link')unset($_SESSION['ebills_step3_acct']);
            if (!$result['ok']) {
                $_SESSION['account_link_form_error'] = $result['message'];
            }
            if (($_SERVER['HTTP_ACCEPT'] ?? '') === 'application/json') {
                $csrf = dashboardCsrfToken();
                $accountLinkPending = $accountLinker->pending($userId);
                $accountLinkFormError = $result['ok'] ? null : $result['message'];
                unset($_SESSION['account_link_form_error']);
                $linked = $action === 'verify_link' && $result['ok'];
                $ebillsStep3=prepareEBillsStep3($pdo,$userId,$_SESSION['ebills_step3_acct']??null);
                unset($_SESSION['dashboard_flash']);
                ob_start();
                if($ebillsStep3)require __DIR__ . '/../views/partials/ebills-step3.php';
                else
                require __DIR__ . '/../views/partials/account-linking-form.php';
                $html = ob_get_clean();
                header('Content-Type: application/json; charset=utf-8');
                header('Cache-Control: no-store, private');
                echo json_encode(['ok' => $result['ok'], 'linked' => $linked, 'optional_step' => (bool)$ebillsStep3, 'html' => $html]);
                exit;
            }
            if (!$result['ok'] || $action !== 'verify_link') {
                unset($_SESSION['dashboard_flash']);
                header('Location: dashboard.php?page=account-settings#account-linking-panel');
                exit;
            }
            unset($_SESSION['dashboard_flash']);
            header('Location: dashboard.php?page=account-settings#account-linking-panel');exit;
        }

        /* =================================================
           FRIENDLY LABEL
           ================================================= */

        if ($action === 'unlink') {
            if ($acctNo === '') {
                dashboardReturn('account-settings', 'The account link is no longer available. Please refresh your linked accounts.');
            }
            unlinkPalecoAccount($pdo, $userId, $acctNo, (string) ($_POST['csrf_token'] ?? ''), dashboardCsrfToken());
            if (($_SESSION['SelectedAcctNo'] ?? '') === $acctNo) unset($_SESSION['SelectedAcctNo']);
            foreach ($_SESSION['dashboard_account_selection_tokens'] ?? [] as $selectionToken => $selectedAcct) {
                if ($selectedAcct === $acctNo) unset($_SESSION['dashboard_account_selection_tokens'][$selectionToken]);
            }
            dashboardReturn('account-settings', 'PALECO account unlinked from your profile.');
        }

        if ($action === 'save_label' || $action === 'remove_label') {
            $isRemoval = $action === 'remove_label';
            $label = trim((string) ($_POST['label'] ?? ''));

            if (!$isRemoval && $label === '') {
                dashboardReturn('account-settings', 'Enter a friendly label before saving.', 'error');
            }

            if (!$isRemoval && mb_strlen($label) > 100) {
                dashboardReturn('account-settings', 'The label must be 100 characters or fewer.', 'error');
            }

            $check = $pdo->prepare("
                SELECT id FROM mco_portal.paleco_accounts
                WHERE user_id = ? AND AcctNo = ? AND status = 'active'
                LIMIT 1
            ");
            $check->execute([$userId, $acctNo]);

            if (!$check->fetch()) {
                dashboardReturn('account-settings', 'That account is not linked to your profile.', 'error');
            }

            $stmt = $pdo->prepare("
                UPDATE mco_portal.paleco_accounts
                SET account_nickname = ?
                WHERE user_id = ? AND AcctNo = ? AND status = 'active'
            ");
            $stmt->execute([$isRemoval ? null : $label, $userId, $acctNo]);

            dashboardReturn(
                'account-settings',
                $isRemoval ? 'Friendly label removed.' : 'Account label saved.'
            );
        }

        /* =================================================
           MAKE PRIMARY
           ================================================= */

        if ($action === 'primary') {

            $pdo->beginTransaction();


            $lock = $pdo->prepare("
                SELECT id
                FROM mco_portal.users
                WHERE id = ?
                FOR UPDATE
            ");

            $lock->execute([$userId]);


            /*
             * Validate ownership before changing primary.
             */

            $check = $pdo->prepare("
                SELECT id
                FROM mco_portal.paleco_accounts
                WHERE user_id = ?
                  AND AcctNo = ?
                  AND status = 'active'
                LIMIT 1
                FOR UPDATE
            ");

            $check->execute([
                $userId,
                $acctNo
            ]);

            if (!$check->fetch()) {

                $pdo->rollBack();

                dashboardReturn(
                    'account-settings',
                    'That account is not linked to your profile.',
                    'error'
                );
            }


            /*
             * Clear previous primary.
             */

            $clear = $pdo->prepare("
                UPDATE mco_portal.paleco_accounts
                SET is_primary = 0
                WHERE user_id = ?
                  AND status = 'active'
            ");

            $clear->execute([$userId]);


            /*
             * Set new primary.
             */

            $set = $pdo->prepare("
                UPDATE mco_portal.paleco_accounts
                SET is_primary = 1
                WHERE user_id = ?
                  AND AcctNo = ?
                  AND status = 'active'
            ");

            $set->execute([
                $userId,
                $acctNo
            ]);


            $pdo->commit();


            dashboardReturn(
                'account-settings',
                'Primary account updated.'
            );
        }


        dashboardReturn(
            'dashboard',
            'Unknown action.',
            'error'
        );


    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'Dashboard action failed: '
            . $e->getMessage()
        );

        dashboardReturn(
            $returnPage,
            'We could not save that change. Please try again.',
            'error'
        );
    }
}


/* =========================================================
   LOAD LINKED ACCOUNTS

   IMPORTANT:
   We do this directly here instead of relying on
   dashboardLinkedAccounts(), because master lives in
   palecxzp_pal_db while paleco_accounts lives in mco_portal.
   ========================================================= */

$linkedStmt = $pdo->prepare("
    SELECT
        pa.id,
        pa.user_id,
        pa.AcctNo,
        pa.account_nickname,
        pa.is_primary,
        pa.status,
        pa.verified_at,

        m.AcctCode,
        m.Name,
        m.Address,
        m.Status AS MasterStatus,
        m.Area,
        m.Book,
        m.MeterBrand,
        m.MemberCode,
        m.ConnectionType

    FROM mco_portal.paleco_accounts AS pa

    LEFT JOIN palecxzp_pal_db.master AS m
        ON m.AcctNo = pa.AcctNo

    WHERE pa.user_id = ?
      AND pa.status = 'active'

    ORDER BY
        pa.is_primary DESC,
        pa.id ASC
");

$linkedStmt->execute([$userId]);

$linkedAccounts = $linkedStmt->fetchAll(
    PDO::FETCH_ASSOC
);
$ebillsStatus = null;
try { $ebillsStatus = $ebills->statuses($linkedAccounts); } catch (Throwable $e) { error_log('eBills status lookup unavailable.'); }
$portalProfile = $ebills->profile($userId);



/*
 * Normalize master Status so existing UI can continue
 * using $account['Status'].
 */

foreach ($linkedAccounts as &$account) {
    $account['Status'] = $account['MasterStatus'] ?? null;
}

unset($account);


/* =========================================================
   DETERMINE SELECTED ACCOUNT
   ========================================================= */

$selectedAccount = null;

$sessionAcctNo = trim(
    (string) ($_SESSION['SelectedAcctNo'] ?? '')
);


/*
 * First preference:
 * currently selected authorized account.
 */

if ($sessionAcctNo !== '') {

    foreach ($linkedAccounts as $account) {

        if (
            (string) $account['AcctNo']
            === $sessionAcctNo
        ) {

            $selectedAccount = $account;

            break;
        }
    }
}


/*
 * Second preference:
 * primary account.
 */

if (!$selectedAccount) {

    foreach ($linkedAccounts as $account) {

        if ((int) $account['is_primary'] === 1) {

            $selectedAccount = $account;

            break;
        }
    }
}


/*
 * Last fallback:
 * first linked account.
 */

if (
    !$selectedAccount
    && !empty($linkedAccounts)
) {

    $selectedAccount = $linkedAccounts[0];
}


/*
 * Keep selected account in session.
 */

if ($selectedAccount) {

    $_SESSION['SelectedAcctNo']
        = $selectedAccount['AcctNo'];
}$accountSelectionTokens = dashboardIssueAccountSelectionTokens($linkedAccounts);


/*
 * No accounts yet:
 * send user to My Accounts.
 */

if (
    !$selectedAccount
    && $page !== 'account-settings'
) {

    header(
        'Location: dashboard.php?page=account-settings'
    );

    exit;
}


/* =========================================================
   UNPAID BILLS

   The billing service verifies that the selected account is an
   active portal link before it reads the legacy, read-only ledger.
   ========================================================= */

$unpaidBills = [];
$unpaidBillSummary = [
    'total_due' => 0.0,
    'unpaid_count' => 0,
    'overdue_count' => 0,
];
$unpaidBillsError = false;

if ($selectedAccount) {
    try {
        $unpaidBills = getUnpaidBills(
            $pdo,
            $userId,
            (string) $selectedAccount['AcctNo']
        );
        $unpaidBillSummary = summarizeUnpaidBills($unpaidBills);
    } catch (Throwable $e) {
        $unpaidBillsError = true;
        error_log('Unable to load unpaid PALECO bills: ' . $e->getMessage());
    }
}

/* =========================================================
   BILL HISTORY

   palecxzp_pal_db.billhistory = READ ONLY
   ========================================================= */

$billHistory = [];
$latestBill = null;
$consumptionHistory = [];

if ($selectedAccount) {

    $selectedAcctNo = trim(
        (string) $selectedAccount['AcctNo']
    );


    $billStmt = $pdo->prepare("
        SELECT
            TransID,
            BillMonth,
            BillNo,
            ReadingDate,
            BillDate,
            DueDate,
            PrsRdngKWH,
            PrvRdngKWH,
            KWH,
            Evat,
            PowerBill,
            TotalOC,
            TotalBill,
            MeterReader
        FROM palecxzp_pal_db.billhistory
        WHERE AcctNo = ?
        ORDER BY BillMonth DESC
        LIMIT 12
    ");

    $billStmt->execute([
        $selectedAcctNo
    ]);

    $billHistory = $billStmt->fetchAll(
        PDO::FETCH_ASSOC
    );


    $latestBill = $billHistory[0] ?? null;


    /*
     * Oldest -> newest for chart.
     */

    $consumptionHistory = array_reverse(
        $billHistory
    );
}


/* =========================================================
   CHART DATA
   ========================================================= */

$chartLabels = [];
$chartValues = [];

foreach ($consumptionHistory as $bill) {

    $chartLabels[] = formatBillMonth(
        $bill['BillMonth'] ?? ''
    );

    $chartValues[] = (float) (
        $bill['KWH'] ?? 0
    );
}


/* =========================================================
   FLASH + CSRF
   ========================================================= */

$flash = $_SESSION['dashboard_flash']
    ?? null;

unset($_SESSION['dashboard_flash']);

$accountLinkFormError = $_SESSION['account_link_form_error'] ?? null;
unset($_SESSION['account_link_form_error']);

$accountLinkPending = null;
try {
    $accountLinkPending = $accountLinker->pending($userId);
} catch (Throwable $e) {
    error_log('Account linking: pending_state_unavailable');
    $accountLinkFormError = 'Online account linking verification is unavailable. Please contact PALECO.';
}

$csrf = dashboardCsrfToken();
$ebillsStep3=prepareEBillsStep3($pdo,$userId,$_SESSION['ebills_step3_acct']??null);

require __DIR__ . '/../views/pages/dashboard.php';
