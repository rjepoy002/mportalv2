<?php
/** Explicit CLI entry only; no permanent HTTP route. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (($argv[1] ?? '') !== '--start') {
    fwrite(STDERR, "Explicit initiation required: php tests/new-connection/stage1-local-development-runner.php --start\n");
    exit(2);
}
define('NC_MANUAL_DEVELOPMENT', true);
require __DIR__.'/stage1-http-test.php';
