<?php

/** Trust only the web server's TLS indicator, never client forwarding headers. */
function portalHttps(array $server): bool
{
    return in_array(strtolower((string) ($server['HTTPS'] ?? '')), ['on', '1'], true);
}

function portalSessionStart(?int $now = null): void
{
    $now ??= time();
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        session_set_cookie_params([
            'lifetime' => 0, 'path' => '/', 'secure' => portalHttps($_SERVER),
            'httponly' => true, 'samesite' => 'Lax',
        ]);
        if (!session_start()) throw new RuntimeException('Session unavailable.');
        // Upgrade an existing cookie too; session_start need not resend it.
        $cookie = session_get_cookie_params();
        setcookie(session_name(), session_id(), [
            'expires' => 0, 'path' => $cookie['path'], 'secure' => $cookie['secure'],
            'httponly' => true, 'samesite' => 'Lax',
        ]);
    }
    $created = $_SESSION['portal_created_at'] ?? $now;
    $last = $_SESSION['portal_last_seen'] ?? $now;
    if (!is_int($created) || !is_int($last) || $created > $now || $last > $now
        || $now - $last >= 1800 || $now - $created >= 43200) {
        $_SESSION = [];
        session_regenerate_id(true);
        $created = $now;
    }
    $_SESSION['portal_created_at'] = $created;
    $_SESSION['portal_last_seen'] = $now;
    header('Cache-Control: no-store, private');
}

function portalAuthenticated(array $user): void
{
    session_regenerate_id(true);
    // Clear pre-authentication state, stale account context, and CSRF/OAuth tokens.
    $_SESSION = [
        'user_id' => $user['id'], 'user_email' => $user['email'], 'user_name' => $user['name'],
        'portal_created_at' => time(), 'portal_last_seen' => time(),
    ];
}

function portalAuthCsrf(): string
{
    if (empty($_SESSION['auth_csrf'])) $_SESSION['auth_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['auth_csrf'];
}

function portalAuthCsrfValid(mixed $token): bool
{
    return is_string($token) && isset($_SESSION['auth_csrf'])
        && hash_equals($_SESSION['auth_csrf'], $token);
}

function portalActiveUser(PDO $pdo, mixed $id): array|false
{
    $id = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) return false;
    $query = $pdo->prepare('SELECT id, name, email, status, email_verified_at FROM mco_portal.users WHERE id = ? LIMIT 1');
    $query->execute([$id]);
    $user = $query->fetch(PDO::FETCH_ASSOC);
    return $user && $user['status'] === 'active' && !empty($user['email_verified_at']) ? $user : false;
}

/** One-use OAuth state with a ten-minute lifetime, consumed even on failure. */
function portalOAuthStateValid(mixed $state, ?int $now = null): bool
{
    $pending = $_SESSION['oauth_pending'] ?? null;
    unset($_SESSION['oauth_pending']);
    $now ??= time();
    return is_array($pending) && is_string($state) && is_string($pending['state'] ?? null)
        && is_int($pending['created'] ?? null) && $pending['created'] <= $now
        && $now - $pending['created'] < 600 && hash_equals($pending['state'], $state);
}

function portalGoogleIdentity(mixed $id, mixed $email, mixed $verified): bool
{
    return is_string($id) && $id !== '' && is_string($email) && strlen($email) <= 255
        && filter_var($email, FILTER_VALIDATE_EMAIL) !== false && $verified === true;
}
