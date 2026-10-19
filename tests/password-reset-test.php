<?php
require_once __DIR__ . '/source-helpers.php';

putenv('APP_ENV=test');
require_once dirname(__DIR__) . '/config/app.php';
require_once dirname(__DIR__) . '/includes/password-recovery.php';
putenv('PASSWORD_RESET_DEBUG=1');

function check($condition, string $label): void
{
    if (!$condition) throw new RuntimeException($label);
}

// No real accounts or SMTP traffic: execute controllers against PDO/mail doubles.
function sendEmail($email, $name, $subject, $html, $plain): bool
{
    $GLOBALS['mail'] = compact('subject', 'html', 'plain');
    return $GLOBALS['delivery'] ?? true;
}

class ResetDatabase extends PDO
{
    public $known = true;
    public $records = [];
    public $password;
    public $loseClaim = false;
    public $failUpdate = false;
    public $failInsert = false;
    public $claims = 0;
    private $snapshot;
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new ResetStatement($this, $query); }
    public function beginTransaction(): bool { $this->snapshot = [$this->records, $this->password]; return true; }
    public function commit(): bool { $this->snapshot = null; return true; }
    public function rollBack(): bool { [$this->records, $this->password] = $this->snapshot; $this->snapshot = null; return true; }
    public function inTransaction(): bool { return $this->snapshot !== null; }
}

class ResetStatement extends PDOStatement
{
    private $db;
    private $sql;
    private $params;
    private $affected = 0;
    public function __construct($db, $sql) { $this->db = $db; $this->sql = $sql; }
    public function execute(?array $params = null): bool
    {
        $this->params = $params;
        if (str_contains($this->sql, 'INSERT INTO password_resets')) {
            if ($this->db->failInsert) throw new PDOException('sentinel-private-database-detail');
            check(str_contains($this->sql, 'DATE_ADD(NOW(), INTERVAL 1 HOUR)'), 'One-hour expiry SQL');
            $this->db->records[$params[':token_hash']] = ['reset_id' => count($this->db->records) + 1, 'user_id' => 1, 'expires' => time() + 3600, 'used' => false];
        } elseif (str_contains($this->sql, 'UPDATE password_resets SET used_at')) {
            check($this->db->inTransaction(), 'Claim inside transaction');
            foreach (['used_at IS NULL', 'expires_at > NOW()', 'token_hash = :token_hash', 'user_id = :user_id'] as $predicate) {
                check(str_contains($this->sql, $predicate), 'Atomic claim predicate: ' . $predicate);
            }
            $r = $this->db->records[$params[':token_hash']] ?? null;
            if ($r && !$r['used'] && $r['expires'] > time() && !$this->db->loseClaim) {
                $this->db->records[$params[':token_hash']]['used'] = true;
                $this->affected = 1;
                $this->db->claims++;
            }
        } elseif (str_contains($this->sql, 'UPDATE users')) {
            check($this->db->claims > 0, 'Password update follows token claim');
            if ($this->db->failUpdate) throw new RuntimeException('Simulated update failure');
            $this->db->password = $params[':password'];
            $this->affected = 1;
        } elseif (str_contains($this->sql, 'DELETE FROM password_resets')) {
            if (isset($params[':token_hash'])) {
                unset($this->db->records[$params[':token_hash']]);
            } else {
                check(str_contains($this->sql, 'expires_at <= NOW() OR used_at IS NOT NULL'), 'Cleanup preserves valid links');
                $this->db->records = array_filter($this->db->records, fn ($r) => !$r['used'] && $r['expires'] > time());
            }
        }
        return true;
    }
    public function rowCount(): int { return $this->affected; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        if (str_contains($this->sql, 'FROM users WHERE email')) {
            return $this->db->known ? ['id' => 1, 'name' => 'Test', 'email' => 'test@example.com'] : false;
        }
        check(str_contains($this->sql, 'password_resets.used_at IS NULL'), 'Unused lookup predicate');
        check(str_contains($this->sql, 'password_resets.expires_at > NOW()'), 'Expiry lookup predicate');
        $r = $this->db->records[$this->params[':token_hash']] ?? null;
        return $r && !$r['used'] && $r['expires'] > time() ? $r : false;
    }
}

function controller($file, $pdo, $post, $get = []): array
{
    if ($file === 'forgot-password.php' && $post && !array_key_exists('csrf_token', $post)) {
        $post['csrf_token'] = recovery_csrf_token();
    }
    $_SERVER['REQUEST_METHOD'] = $post ? 'POST' : 'GET';
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
    $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.' . random_int(1, 254);
    $_POST = $post;
    $_GET = $get;
    $source = str_replace("\r\n", "\n", portalPageSource($file));
    $source = preg_replace('/^[ \t]*require_once .*;$/m', '', $source);
    $source = str_replace("header('Location: index.php?reset=success');", '$GLOBALS["redirect"] = "index.php?reset=success";', $source);
    $source = preg_replace('/\bexit;/', 'return;', $source);
    ob_start();
    try { eval('?>' . $source); $output = ob_get_contents(); } finally { ob_end_clean(); }
    return [$message, $output];
}

$original = getenv('APP_URL');
$directory = __DIR__ . '/recovery-test-' . bin2hex(random_bytes(6));
mkdir($directory, 0700);
session_save_path($directory);
recovery_csrf_token();
putenv('PASSWORD_RESET_RATE_DIR=' . $directory);
ini_set('error_log', $directory . '/events.log');
function clearLimits(): void { @unlink(getenv('PASSWORD_RESET_RATE_DIR') . '/limits.json'); }
try {
    putenv('APP_URL');
    $_SERVER['HTTP_HOST'] = 'attacker.example';
    $_SERVER['HTTP_X_FORWARDED_HOST'] = 'attacker.example';
    check(app_url('reset-password.php?token=abc') === 'http://localhost/mportalv2/reset-password.php?token=abc', 'Default local URL');
    foreach ([
        'https://example.com' => 'https://example.com/reset-password.php?token=abc',
        'https://example.com///' => 'https://example.com/reset-password.php?token=abc',
        'https://example.com/portal/v2/' => 'https://example.com/portal/v2/reset-password.php?token=abc',
        'http://127.0.0.1/portal' => 'http://127.0.0.1/portal/reset-password.php?token=abc',
        'http://[::1]/portal' => 'http://[::1]/portal/reset-password.php?token=abc',
    ] as $base => $expected) {
        putenv('APP_URL=' . $base);
        check(app_url('reset-password.php?token=abc') === $expected, 'Configured URL');
    }
    foreach (['', '/portal', '//example.com', 'ftp://example.com', 'https:///portal', 'https://u:p@example.com', 'https://example.com?', 'https://example.com#', 'http://example.com', 'http://localhost.example.com', "https://example.com/\n", 'https://example.com/\\path'] as $invalid) {
        putenv('APP_URL=' . $invalid);
        $rejected = false;
        try { app_url(); } catch (InvalidArgumentException $e) { $rejected = true; }
        check($rejected, 'Invalid URL rejected');
    }
    putenv('APP_ENV=production');
    foreach ([null, 'http://localhost/mportalv2', 'http://example.com'] as $invalid) {
        $invalid === null ? putenv('APP_URL') : putenv('APP_URL=' . $invalid);
        $rejected = false;
        try { app_url(); } catch (InvalidArgumentException $e) { $rejected = true; }
        check($rejected, 'Production URL fails safely');
    }
    putenv('APP_URL=https://example.com/portal');
    check(app_url('reset-password.php') === 'https://example.com/portal/reset-password.php', 'Production HTTPS');
    putenv('APP_ENV=test');

    $db = new ResetDatabase();
    [$csrfMessage] = controller('forgot-password.php', $db, ['email' => 'test@example.com', 'csrf_token' => 'invalid']);
    check($csrfMessage === 'Please reload the page and try again.' && !$db->records && !isset($GLOBALS['mail']), 'CSRF fails before persistence or mail');
    [$knownMessage, $output] = controller('forgot-password.php', $db, ['email' => 'test@example.com']);
    check($GLOBALS['mail']['subject'] === 'Reset Your PALECO MCO Portal Password', 'Official recovery email subject');
    foreach (['html', 'plain'] as $format) {
        check(str_contains($GLOBALS['mail'][$format], 'reset your PALECO MCO Portal password'), 'Official recovery body branding');
        check(!str_contains($GLOBALS['mail'][$format], 'MPortalV2'), 'No legacy recovery branding');
        check(!preg_match('/(?<!PALECO )MCO Portal/', $GLOBALS['mail'][$format]), 'No shortened recovery branding');
    }
    preg_match('~https://example.com/portal/reset-password.php\?token=([a-f0-9]{64})~', $GLOBALS['mail']['plain'], $matches);
    check(isset($matches[1]), 'Configured plain-text link and 32-byte random token');
    $token = $matches[1];
    $hash = hash('sha256', $token);
    check(isset($db->records[$hash]), 'Only hashed token stored');
    check(str_contains($GLOBALS['mail']['html'], htmlspecialchars($matches[0], ENT_QUOTES, 'UTF-8')), 'HTML email link');
    check(!str_contains($output, $token), 'No token in request response');
    unset($GLOBALS['mail']);
    [$throttled] = controller('forgot-password.php', $db, ['email' => 'TEST@example.com']);
    check($knownMessage === $throttled && !isset($GLOBALS['mail']) && count($db->records) === 1, 'Cooldown case normalization and original link preserved');
    $db->known = false;
    [$unknownMessage] = controller('forgot-password.php', $db, ['email' => 'unknown@example.com']);
    check($knownMessage === $unknownMessage && !isset($GLOBALS['mail']), 'Unknown email generic response');
    $db->known = true;
    clearLimits();
    putenv('APP_URL=invalid');
    [$configurationFailure] = controller('forgot-password.php', $db, ['email' => 'test@example.com']);
    check($configurationFailure === $knownMessage && !isset($GLOBALS['mail']) && count($db->records) === 1, 'Invalid application URL logged with generic response before persistence');
    putenv('APP_URL=https://example.com/portal');
    $db->failInsert = true;
    [$databaseFailure] = controller('forgot-password.php', $db, ['email' => 'test@example.com']);
    check($databaseFailure === $knownMessage && !isset($GLOBALS['mail']) && count($db->records) === 1, 'Persistence failure generic response without sending');
    $db->failInsert = false;
    clearLimits();
    $GLOBALS['delivery'] = false;
    [$failedMessage] = controller('forgot-password.php', $db, ['email' => 'test@example.com']);
    check($failedMessage === $knownMessage && count($db->records) === 1 && isset($db->records[$hash]), 'SMTP failure preserves old link and removes replacement');
    $GLOBALS['delivery'] = true;
    clearLimits();
    controller('forgot-password.php', $db, ['email' => 'test@example.com']);
    check(count($db->records) === 2 && isset($db->records[$hash]), 'Successful replacement retains previously valid link');

    [, $form] = controller('reset-password.php', $db, [], ['token' => $token]);
    check(str_contains($form, 'value="' . $token . '"'), 'Hidden POST token');
    $invalidMessage = 'This password reset link is invalid or has expired.';
    foreach (['', 'bad', str_repeat('a', 64)] as $invalid) {
        [$message] = controller('reset-password.php', $db, [], ['token' => $invalid]);
        check($message === $invalidMessage, 'Invalid token');
    }
    foreach ([['abcdefgh', 'abcdefgh'], ['12345678', '12345678'], ['Abc1', 'Abc1'], ['éééA1', 'éééA1'], ['Password123', ''], ['Password123', 'different']] as [$password, $confirmation]) {
        [$message] = controller('reset-password.php', $db, ['token' => $token, 'password' => $password, 'confirm_password' => $confirmation]);
        check($message !== '' && !$db->password && !$db->records[$hash]['used'], 'Invalid password cannot consume token');
    }
    $db->records[$hash]['expires'] = time() - 1;
    [$expired] = controller('reset-password.php', $db, ['token' => $token, 'password' => 'Password123', 'confirm_password' => 'Password123']);
    check($expired === $invalidMessage && !$db->password, 'Expired token');
    $db->records[$hash]['expires'] = time() + 3600;
    $db->loseClaim = true;
    controller('reset-password.php', $db, ['token' => $token, 'password' => 'Password123', 'confirm_password' => 'Password123']);
    check(!$db->password && !$db->inTransaction(), 'Lost concurrent claim rolls back without password update');
    $db->loseClaim = false;
    $db->failUpdate = true;
    controller('reset-password.php', $db, ['token' => $token, 'password' => 'Password123', 'confirm_password' => 'Password123']);
    check(!$db->records[$hash]['used'] && !$db->password, 'Database failure rolls back claim');
    $db->failUpdate = false;
    controller('reset-password.php', $db, ['token' => $token, 'password' => 'Password123', 'confirm_password' => 'Password123']);
    check(password_verify('Password123', $db->password) && $db->records[$hash]['used'], 'Password hashed and token consumed');
    check(($GLOBALS['redirect'] ?? '') === 'index.php?reset=success', 'Redirect to login');
    [$reused] = controller('reset-password.php', $db, [], ['token' => $token]);
    check($reused === $invalidMessage, 'Already-used token');
    $before = $db->password;
    check(!recovery_reset_password($db, $db->records[$hash], $hash, 'Other12345') && $db->password === $before, 'Stale concurrent reader cannot update password');

    clearLimits();
    for ($i = 0; $i < 10; $i++) check(recovery_request_allowed('email' . $i . '@example.com', '192.0.2.1'), 'IP allowed within quota');
    check(!recovery_request_allowed('extra@example.com', '192.0.2.1'), 'IP quota enforced');
    check(!recovery_request_allowed('EMAIL0@example.com', '192.0.2.2'), 'Email cooldown across IPs');
    $limits = json_decode(file_get_contents($directory . '/limits.json'), true);
    foreach ($limits as &$entry) $entry['until'] = time() - 1;
    unset($entry);
    file_put_contents($directory . '/limits.json', json_encode($limits));
    check(recovery_request_allowed('email0@example.com', '192.0.2.1'), 'Expired limiter windows reset');
    file_put_contents($directory . '/limits.json', 'corrupt');
    unset($GLOBALS['mail']);
    [$unavailable] = controller('forgot-password.php', $db, ['email' => 'test@example.com']);
    check($unavailable === $knownMessage && !isset($GLOBALS['mail']), 'Limiter failure fails closed with generic response');
    $logs = file_get_contents($directory . '/events.log');
    check(!str_contains($logs, 'sentinel-private-database-detail'), 'No raw exception message');
    foreach (['request_received', 'request_validation_completed', 'user_lookup_completed', 'reset_token_persistence_completed', 'token_persistence'] as $event) {
        check(str_contains($logs, $event), 'Structured workflow event: ' . $event);
    }
    check(str_contains($logs, 'request_id') && str_contains($logs, 'timestamp'), 'Correlation and UTC timestamp recorded');
    check(!str_contains($logs, $token) && !str_contains($logs, 'test@example.com') && !str_contains($logs, '?token='), 'Logs omit tokens, links and email');
    foreach (['forgot-password.php', 'reset-password.php'] as $file) {
        $source = portalPageSource($file);
        foreach (['Referrer-Policy: no-referrer', 'Cache-Control: no-store', 'Pragma: no-cache'] as $header) check(str_contains($source, $header), 'Privacy header');
    }
    echo "Password recovery PHP regression tests passed (PDO/mail doubles; real file limiter).\n";
} finally {
    session_destroy();
    $original === false ? putenv('APP_URL') : putenv('APP_URL=' . $original);
    foreach (glob($directory . '/*') as $file) unlink($file);
    rmdir($directory);
}
