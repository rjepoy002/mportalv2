<?php

/** Trusted deployment settings and direct connection addresses only; no session/DB side effects. */
function newConnectionPreviewAvailable(array $server): bool
{
    $config = require __DIR__ . '/../../config/new-connection-preview.php';
    $loopback = ['127.0.0.1', '::1'];
    return $config['enabled'] === true && $config['development'] === true
        && in_array($server['SERVER_ADDR'] ?? '', $loopback, true)
        && in_array($server['REMOTE_ADDR'] ?? '', $loopback, true)
        && !isset($server['HTTP_FORWARDED']) && !isset($server['HTTP_X_FORWARDED_FOR'])
        && !isset($server['HTTP_X_FORWARDED_HOST'])
        && preg_match('/^(?:localhost|127\.0\.0\.1|\[::1\])(?::[0-9]{1,5})?$/Di', $server['HTTP_HOST'] ?? '') === 1;
}
