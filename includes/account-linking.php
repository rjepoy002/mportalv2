<?php

if (
    PHP_SAPI !== 'cli'
    && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === __FILE__
) {
    http_response_code(404);
    exit;
}
/**
 * Account-linking input and session safeguards. Legacy master data remains read-only.
 */
function normalizePalecoAccCode(string $value): ?string
{
    $value = trim($value);

    if (
        $value === ''
        || !preg_match('/^(?:[A-Za-z0-9]{10}|[A-Za-z0-9]{2}-[A-Za-z0-9]{4}-[A-Za-z0-9]{4})$/', $value)
    ) {
        return null;
    }

    $normalized = strtoupper(str_replace('-', '', $value));

    return $normalized;
}

function palecoAccountVerificationSql(): string
{
    return <<<'SQL'
SELECT AcctNo, AcctCode, MeterSerial, Name, Status
FROM palecxzp_pal_db.master
WHERE REPLACE(TRIM(AcctCode), '-', '') = :acct_code
  AND TRIM(MeterSerial) = :meter_serial
LIMIT 1
SQL;
}

function normalizePalecoMeterSerial(string $value): ?string
{
    $value = trim($value);

    if ($value === '' || strlen($value) > 20 || preg_match('/[\x00-\x1F\x7F]/', $value)) {
        return null;
    }

    return $value;
}

/** Canonical DECIMAL(19,2), without rounding or binary floating point. */
function normalizePalecoBillAmount(string $value): ?string
{
    if (strlen($value) > 80 || preg_match('/[\x00-\x1F\x7F]/', $value)) return null;
    $value = trim($value);
    $value = preg_replace('/^\x{20B1}\s*/u', '', $value);
    if (!is_string($value) || !preg_match('/^(?:[0-9]+|[1-9][0-9]{0,2}(?:,[0-9]{3})+)(?:\.([0-9]{1,2}))?$/D', $value)) {
        return null;
    }
    $parts = explode('.', str_replace(',', '', $value));
    $whole = ltrim($parts[0], '0');
    $whole = $whole === '' ? '0' : $whole;
    if (strlen($whole) > 17) return null;
    return $whole . '.' . str_pad($parts[1] ?? '', 2, '0');
}

function palecoLinkMonthValid(string $month): bool
{
    return preg_match('/^[1-9][0-9]{3}(?:0[1-9]|1[0-2])$/D', $month) === 1;
}

function palecoLinkMonthLabel(string $month): string
{
    if (!palecoLinkMonthValid($month)) return '';
    $date = DateTimeImmutable::createFromFormat('!Ym', $month);
    return $date ? $date->format('F Y') : '';
}

/**
 * All mutations serialize on the authenticated users row. Shared account rate
 * rows then serialize guesses across different users and different PHP sessions.
 * Only portal-owned tables are writable. Bill amounts never leave this service.
 */
final class PalecoAccountLinker
{
    private const FAILURE = 'The information could not be verified. Please check your statement and try again.';
    private const UNAVAILABLE = 'Online account linking verification is unavailable. Please contact PALECO customer support.';

    public function __construct(private PDO $pdo, private array $config) {}

    public function enabled(): bool
    {
        return !empty($this->config['enabled']);
    }

    private function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    private function audit(int $userId, string $event): void
    {
        // Fixed event names only. Never log request inputs or exception messages.
        error_log('Account linking: ' . $event . ' user_id=' . $userId);
    }

    private function result(bool $ok, string $message): array
    {
        return ['ok' => $ok, 'message' => $message];
    }

    private function clear(int $userId): void
    {
        $this->query('DELETE FROM mco_portal.account_link_challenges WHERE user_id = ?', [$userId]);
    }

    /** Display data comes only from the authenticated user's verified challenge. */
    public function pending(int $userId): ?array
    {
        if (!$this->enabled() || $userId <= 0) return null;
        $row = $this->query('SELECT c.challenge_id, c.allowed_months, c.expires_at,
            c.friendly_label, m.AcctCode, m.Name, m.Address
            FROM mco_portal.account_link_challenges c
            INNER JOIN palecxzp_pal_db.master m ON m.AcctNo = c.AcctNo
            WHERE c.user_id = ?', [$userId])->fetch();
        if (!$row || (int) $row['expires_at'] <= time()) return null;
        return [
            'id' => $row['challenge_id'],
            'months' => json_decode($row['allowed_months'], true, 512, JSON_THROW_ON_ERROR),
            'expires_at' => (int) $row['expires_at'],
            'summary' => [
                'account_number' => trim((string) $row['AcctCode']),
                'description' => (string) ($row['friendly_label'] ?? ''),
                'consumer_name' => (string) ($row['Name'] ?? ''),
                'address' => (string) ($row['Address'] ?? ''),
            ],
        ];
    }

    /** Fixed windows plus a full cooldown after exhaustion. No session counters. */
    private function rateRows(string $kind, int $userId, ?string $code, int $limit): ?array
    {
        $keys = [hash('sha256', $kind . ':user:' . $userId)];
        if ($code !== null) $keys[] = hash('sha256', $kind . ':account:' . $code);
        sort($keys, SORT_STRING);
        $now = time();
        $window = $this->config['rate_window'];
        $rows = [];
        foreach ($keys as $key) {
            $this->query('INSERT INTO mco_portal.account_link_limits (scope_hash, attempts, window_started, blocked_until) VALUES (?, 0, ?, 0) ON DUPLICATE KEY UPDATE scope_hash = VALUES(scope_hash)', [$key, $now]);
            $row = $this->query('SELECT * FROM mco_portal.account_link_limits WHERE scope_hash = ? FOR UPDATE', [$key])->fetch();
            if ((int) $row['blocked_until'] > $now) return null;
            if ($now - (int) $row['window_started'] >= $window) {
                $this->query('UPDATE mco_portal.account_link_limits SET attempts = 0, window_started = ?, blocked_until = 0 WHERE scope_hash = ?', [$now, $key]);
                $row['attempts'] = 0;
            }
            if ((int) $row['attempts'] >= $limit) return null;
            $rows[] = $key;
        }
        return $rows;
    }

    private function charge(array $keys, int $limit): void
    {
        foreach ($keys as $key) {
            $this->query('UPDATE mco_portal.account_link_limits SET blocked_until = CASE WHEN attempts + 1 >= ? THEN ? ELSE blocked_until END, attempts = attempts + 1 WHERE scope_hash = ?', [$limit, time() + $this->config['rate_window'], $key]);
        }
    }

    /** CSRF and authentication are also enforced here for every service caller. */
    public function handle(int $userId, array $input, string $csrf): array
    {
        if ($userId <= 0) return $this->result(false, 'Please sign in to continue.');
        if ($csrf === '' || !is_string($input['csrf_token'] ?? null) || !hash_equals($csrf, $input['csrf_token'])) {
            $this->audit($userId, 'csrf_rejected');
            return $this->result(false, 'Your session expired. Please try again.');
        }
        if (!$this->enabled()) return $this->result(false, self::UNAVAILABLE);
        $this->pdo->beginTransaction();
        try {
            $user = $this->query('SELECT id FROM mco_portal.users WHERE id = ? FOR UPDATE', [$userId])->fetchColumn();
            if (!$user) {
                $result = $this->result(false, 'Please sign in to continue.');
            } elseif (($input['action'] ?? '') === 'add') {
                $result = $this->start($userId, $input);
            } elseif (($input['action'] ?? '') === 'verify_link') {
                $result = $this->finish($userId, $input);
            } elseif (($input['action'] ?? '') === 'restart_link') {
                $this->clear($userId);
                $result = $this->result(true, 'Enter your account information to restart verification.');
            } else {
                $result = $this->result(false, self::FAILURE);
            }
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            $this->audit($userId, 'service_error');
            // Fail closed, including when the migration has not been installed.
            return $this->result(false, self::UNAVAILABLE);
        }
    }

    private function text(array $input, string $key): string
    {
        return is_string($input[$key] ?? null) ? $input[$key] : '';
    }

    private function start(int $userId, array $input): array
    {
        $this->clear($userId);
        $code = normalizePalecoAccCode($this->text($input, 'account_code'));
        $meter = normalizePalecoMeterSerial($this->text($input, 'meter_number'));
        $label = trim($this->text($input, 'label'));
        $rates = $this->rateRows('identity', $userId, $code, $this->config['identity_limit']);
        if ($rates === null) {
            $this->audit($userId, 'identity_throttled');
            return $this->result(false, 'Too many verification attempts. Please wait 15 minutes and try again.');
        }
        $this->charge($rates, $this->config['identity_limit']);
        if ($code === null || $meter === null || !mb_check_encoding($label, 'UTF-8') || mb_strlen($label) > 100 || preg_match('/[\x00-\x1F\x7F]/', $label)) {
            $this->audit($userId, 'identity_rejected');
            return $this->result(false, self::FAILURE);
        }
        $account = $this->query(palecoAccountVerificationSql(), [':acct_code' => $code, ':meter_serial' => $meter])->fetch();
        if (!$account || trim((string) $account['AcctNo']) === '') {
            $this->audit($userId, 'identity_rejected');
            return $this->result(false, self::FAILURE);
        }
        $acctNo = trim((string) $account['AcctNo']);
        // YYYYMM lexical order is chronological after strict validation.
        $months = $this->query("SELECT DISTINCT BillMonth FROM palecxzp_pal_db.billhistory
            WHERE AcctNo = ? AND BillMonth REGEXP '^[1-9][0-9]{3}(0[1-9]|1[0-2])$'
              AND CHAR_LENGTH(BillMonth) = 6 AND TotalBill IS NOT NULL AND TotalBill >= 0
            ORDER BY BillMonth DESC LIMIT 3", [$acctNo])->fetchAll(PDO::FETCH_COLUMN);
        if (!$months) {
            $this->audit($userId, 'history_unavailable');
            return $this->result(false, self::UNAVAILABLE);
        }
        $now = time();
        $this->query('INSERT INTO mco_portal.account_link_challenges
            (user_id, challenge_id, AcctNo, account_code, meter_serial, friendly_label, allowed_months, created_at, expires_at, failures)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0)', [
                $userId, bin2hex(random_bytes(32)), $acctNo, $code, $meter,
                $label !== '' ? $label : null, json_encode($months, JSON_THROW_ON_ERROR), $now, $now + $this->config['challenge_ttl'],
            ]);
        $this->audit($userId, 'challenge_created');
        return $this->result(true, 'Step 1 complete. Select a billing month and enter the Total Bill Amount from your statement.');
    }

    private function finish(int $userId, array $input): array
    {
        $row = $this->query('SELECT * FROM mco_portal.account_link_challenges WHERE user_id = ? FOR UPDATE', [$userId])->fetch();
        if (!$row || !hash_equals($row['challenge_id'], $this->text($input, 'challenge_id'))) {
            $this->audit($userId, 'challenge_rejected');
            return $this->result(false, 'Verification is unavailable or has already been used. Please restart Step 1.');
        }
        if ((int) $row['expires_at'] <= time() || (int) $row['failures'] >= $this->config['challenge_attempts']) {
            $this->clear($userId);
            $this->audit($userId, 'challenge_expired');
            return $this->result(false, 'Verification expired. Please restart Step 1.');
        }
        $rates = $this->rateRows('billing', $userId, $row['AcctNo'], $this->config['billing_limit']);
        if ($rates === null) {
            $this->clear($userId);
            $this->audit($userId, 'billing_throttled');
            return $this->result(false, 'Too many verification attempts. Please wait 15 minutes and restart Step 1.');
        }
        $month = $this->text($input, 'bill_month');
        $amount = normalizePalecoBillAmount($this->text($input, 'bill_amount'));
        $allowed = json_decode($row['allowed_months'], true, 512, JSON_THROW_ON_ERROR);
        $match = false;
        if (palecoLinkMonthValid($month) && in_array($month, $allowed, true) && $amount !== null) {
            // Revalidate the exact master mapping. Client account identifiers are ignored.
            $identity = $this->query(palecoAccountVerificationSql(), [':acct_code' => $row['account_code'], ':meter_serial' => $row['meter_serial']])->fetch();
            if ($identity && trim((string) $identity['AcctNo']) === $row['AcctNo']) {
                // Any historically stored matching record is accepted by policy.
                // DECIMAL SQL equality is exact; never fetch expected amounts into PHP.
                $match = (bool) $this->query('SELECT EXISTS (
                    SELECT 1 FROM palecxzp_pal_db.billhistory
                    WHERE AcctNo = ? AND BillMonth = ? AND TotalBill >= 0
                      AND TotalBill = CAST(? AS DECIMAL(19,2))
                )', [$row['AcctNo'], $month, $amount])->fetchColumn();
            }
        }
        if (!$match) {
            $this->charge($rates, $this->config['billing_limit']);
            $this->query('UPDATE mco_portal.account_link_challenges SET failures = failures + 1 WHERE user_id = ?', [$userId]);
            $exhausted = (int) $row['failures'] + 1 >= $this->config['challenge_attempts'];
            if ($exhausted) $this->clear($userId);
            $this->audit($userId, 'billing_rejected');
            return $this->result(false, $exhausted ? 'Too many verification attempts. Please wait 15 minutes and restart Step 1.' : self::FAILURE);
        }
        if ((int) $row['expires_at'] <= time()) {
            $this->clear($userId);
            $this->audit($userId, 'challenge_expired');
            return $this->result(false, 'Verification expired. Please restart Step 1.');
        }
        $existing = $this->query('SELECT id, status FROM mco_portal.paleco_accounts WHERE user_id = ? AND AcctNo = ? FOR UPDATE', [$userId, $row['AcctNo']])->fetch();
        $this->clear($userId);
        if ($existing && $existing['status'] === 'active') {
            $this->audit($userId, 'already_linked');
            return $this->result(false, 'This account is already linked.');
        }
        $first = (int) $this->query("SELECT COUNT(*) FROM mco_portal.paleco_accounts WHERE user_id = ? AND status = 'active'", [$userId])->fetchColumn() === 0;
        if ($existing) {
            $this->query("UPDATE mco_portal.paleco_accounts SET account_nickname = ?, status = 'active', is_primary = ?, verified_at = NOW() WHERE id = ? AND user_id = ?", [$row['friendly_label'], $first ? 1 : 0, $existing['id'], $userId]);
        } else {
            $this->query("INSERT INTO mco_portal.paleco_accounts (user_id, AcctNo, account_nickname, is_primary, status, verified_at) VALUES (?, ?, ?, ?, 'active', NOW())", [$userId, $row['AcctNo'], $row['friendly_label'], $first ? 1 : 0]);
        }
        $this->audit($userId, 'linked');
        return $this->result(true, 'PALECO account linked successfully.');
    }
}
