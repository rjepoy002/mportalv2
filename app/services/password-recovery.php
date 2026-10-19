<?php

require_once __DIR__ . '/../helpers/recovery-log.php';

function recovery_csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE && !session_start()) {
        throw new RuntimeException('Recovery session unavailable.');
    }
    if (empty($_SESSION['recovery_csrf'])) $_SESSION['recovery_csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['recovery_csrf'];
}

/** Atomic, persistent limits for all PHP workers on this server. No proxy headers. */
function recovery_request_allowed(string $email, string $ip): bool
{
    $directory = getenv('PASSWORD_RESET_RATE_DIR') ?: sys_get_temp_dir() . '/mco-portal-reset-limits';
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Recovery limiter unavailable.');
    }
    $handle = @fopen($directory . '/limits.json', 'c+');
    if (!$handle) throw new RuntimeException('Recovery limiter unavailable.');
    try {
        if (!flock($handle, LOCK_EX)) throw new RuntimeException('Recovery limiter unavailable.');
        $raw = stream_get_contents($handle);
        $state = $raw === '' ? [] : json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($state)) throw new RuntimeException('Recovery limiter unavailable.');
        $now = time();
        foreach ($state as $key => $entry) {
            if ($entry['until'] <= $now) unset($state[$key]);
        }
        $emailKey = 'email:' . hash('sha256', strtolower(trim($email)));
        $ipKey = 'ip:' . hash('sha256', $ip);
        $ipEntry = $state[$ipKey] ?? ['until' => $now + 900, 'count' => 0];
        // Count attempts, including email-cooldown rejections, toward the IP limit.
        $allowed = !isset($state[$emailKey]) && $ipEntry['count'] < 10;
        $ipEntry['count'] = min(10, $ipEntry['count'] + 1);
        $state[$ipKey] = $ipEntry;
        if ($allowed) $state[$emailKey] = ['until' => $now + 60, 'count' => 1];
        $json = json_encode($state, JSON_THROW_ON_ERROR);
        rewind($handle);
        if (!ftruncate($handle, 0) || fwrite($handle, $json) !== strlen($json) || !fflush($handle)) {
            throw new RuntimeException('Recovery limiter unavailable.');
        }
        return $allowed;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

/** Claim before updating the password; InnoDB serializes competing updates. */
function recovery_reset_password(PDO $pdo, array $record, string $tokenHash, string $password): bool
{
    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    $pdo->beginTransaction();
    try {
        $claim = $pdo->prepare(
            'UPDATE password_resets SET used_at = NOW()
             WHERE id = :reset_id AND user_id = :user_id AND token_hash = :token_hash
               AND used_at IS NULL AND expires_at > NOW()'
        );
        $claim->execute([
            ':reset_id' => $record['reset_id'],
            ':user_id' => $record['user_id'],
            ':token_hash' => $tokenHash,
        ]);
        if ($claim->rowCount() !== 1) {
            $pdo->rollBack();
            return false;
        }
        $update = $pdo->prepare('UPDATE users SET password_hash = :password WHERE id = :user_id');
        $update->execute([':password' => $passwordHash, ':user_id' => $record['user_id']]);
        if ($update->rowCount() !== 1) throw new RuntimeException('Recovery password persistence failed.');
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
