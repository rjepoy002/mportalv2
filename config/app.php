<?php

/** APP_URL must be supplied to the PHP process; no dotenv loader is used. */
function app_base_url(): string
{
    $configured = getenv('APP_URL');
    // Only CLI and a direct loopback web request get implicit development defaults.
    // SERVER_ADDR/REMOTE_ADDR come from the connection, never Host/proxy headers.
    $loopback = ['127.0.0.1', '::1'];
    $localRequest = in_array($_SERVER['SERVER_ADDR'] ?? '', $loopback, true)
        && in_array($_SERVER['REMOTE_ADDR'] ?? '', $loopback, true);
    $defaultEnvironment = PHP_SAPI === 'cli' || $localRequest ? 'development' : 'production';
    $environment = strtolower(getenv('APP_ENV') ?: $defaultEnvironment);
    $production = !in_array($environment, ['local', 'development', 'test'], true);
    if ($production && ($configured === false || $configured === '')) {
        throw new InvalidArgumentException('APP_URL is required outside local development.');
    }
    $url = $configured === false ? 'http://localhost/mportalv2' : $configured;
    $parts = parse_url($url);

    if (
        !filter_var($url, FILTER_VALIDATE_URL)
        || $parts === false
        || empty($parts['host'])
        || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
        || isset($parts['user']) || isset($parts['pass'])
        || isset($parts['query']) || isset($parts['fragment'])
        || preg_match('/[\x00-\x20\x7f\\\\]/', $url)
    ) {
        throw new InvalidArgumentException('APP_URL must be an absolute HTTP/HTTPS URL without credentials, query, or fragment.');
    }

    $local = in_array(strtolower($parts['host']), ['localhost', '127.0.0.1', '[::1]'], true);
    if (($production || !$local) && strtolower($parts['scheme']) !== 'https') {
        throw new InvalidArgumentException('APP_URL must use HTTPS for non-local deployments.');
    }

    return rtrim($url, '/');
}

/** Append an application-relative path while retaining the configured base path. */
function app_url(string $path = ''): string
{
    return app_base_url() . ($path === '' ? '' : '/' . ltrim($path, '/'));
}

// Fail early on invalid deployment configuration, even before sending email.
app_base_url();
