<?php

// Environment variables must be supplied to PHP/Apache; .env is not loaded.
$environment = strtolower((string) (getenv('APP_ENV') ?: getenv('MCO_APP_ENV') ?: 'production'));
$loopback = ['127.0.0.1', '::1'];
$localTesting = getenv('PALECO_LINK_LOCAL_TESTING') === '1'
    && in_array($environment, ['local', 'development', 'test'], true)
    && in_array($_SERVER['SERVER_ADDR'] ?? '', $loopback, true)
    && in_array($_SERVER['REMOTE_ADDR'] ?? '', $loopback, true);

return [
    'enabled' => $localTesting || (getenv('PALECO_LINK_BILLING_ENABLED') === '1'
        && trim((string) getenv('PALECO_LINK_POLICY_APPROVAL')) !== ''),
    'challenge_ttl' => 600,
    'challenge_attempts' => 5,
    'rate_window' => 900,
    'identity_limit' => 20,
    'billing_limit' => 5,
];
