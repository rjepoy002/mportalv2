<?php

/** Allowlisted diagnostics: never serialize exception messages, mail content or SQL. */
function recovery_log(string $requestId, string $event, string $category = '', ?Throwable $exception = null): void
{
    $debug = getenv('PASSWORD_RESET_DEBUG') === '1';
    if ($category === '' && !$debug) return;
    $entry = [
        'component' => 'FORGOT_PASSWORD',
        'request_id' => $requestId,
        'event' => $event,
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
    ];
    if ($category !== '') $entry['category'] = $category;
    // The class is useful for diagnosis without copying potentially sensitive messages.
    if ($exception) $entry['exception_type'] = get_class($exception);
    error_log(json_encode($entry, JSON_UNESCAPED_SLASHES));
}
