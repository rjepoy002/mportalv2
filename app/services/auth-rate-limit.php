<?php

/** Atomic single-host limiter. Counts all permitted attempts, not just failures. */
function portalAuthAttemptAllowed(string $action, string $email): bool
{
    $directory = getenv('MCO_AUTH_RATE_DIR') ?: dirname(__DIR__, 2) . '/storage' . '/auth-rate-limits';
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        throw new RuntimeException('Authentication limiter unavailable.');
    }
    $handle = @fopen($directory . '/limits.json', 'c+');
    if (!$handle) throw new RuntimeException('Authentication limiter unavailable.');
    try {
        if (!flock($handle, LOCK_EX)) throw new RuntimeException('Authentication limiter unavailable.');
        $raw = stream_get_contents($handle);
        $state = $raw === '' ? [] : json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($state)) throw new RuntimeException('Authentication limiter unavailable.');
        $now = time();
        foreach ($state as $key => $entry) if ($entry['until'] <= $now) unset($state[$key]);
        if (count($state) > 10000) throw new RuntimeException('Authentication limiter capacity reached.');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ip = filter_var($ip, FILTER_VALIDATE_IP) ? inet_ntop(inet_pton($ip)) : 'unknown';
        $allowed = true;
        $limits = match ($action) {
            'login' => [50, 10],
            'registration_verify' => [60, 15],
            default => [20, 5],
        };
        foreach (['ip' => [$ip, $limits[0]], 'email' => [strtolower(trim($email)), $limits[1]]] as $scope => [$value, $limit]) {
            $key = $action . ':' . $scope . ':' . hash('sha256', $value);
            $entry = $state[$key] ?? ['until' => $now + 900, 'count' => 0];
            if ($entry['count'] >= $limit) $allowed = false;
            $entry['count'] = min($limit, $entry['count'] + 1);
            $state[$key] = $entry;
        }
        $json = json_encode($state, JSON_THROW_ON_ERROR);
        rewind($handle);
        if (!ftruncate($handle, 0) || fwrite($handle, $json) !== strlen($json) || !fflush($handle)) {
            throw new RuntimeException('Authentication limiter unavailable.');
        }
        return $allowed;
    } finally { flock($handle, LOCK_UN); fclose($handle); }
}
