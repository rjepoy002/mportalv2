<?php

require_once dirname(__DIR__) . '/includes/password-recovery.php';

if (($argv[1] ?? '') === '--worker') {
    [$script, $mode, $directory, $kind, $index] = $argv;
    putenv('PASSWORD_RESET_RATE_DIR=' . $directory);
    file_put_contents($directory . '/ready-' . $index, '1');
    $deadline = microtime(true) + 15;
    while (!is_file($directory . '/go')) {
        if (microtime(true) > $deadline) exit(2);
        usleep(1000);
    }
    $email = $kind === 'email' ? 'same@example.com' : 'user' . $index . '@example.com';
    $ip = $kind === 'email' ? '192.0.2.' . ($index + 1) : '192.0.2.1';
    echo recovery_request_allowed($email, $ip) ? '1' : '0';
    exit;
}

$directory = __DIR__ . '/recovery-concurrent-' . bin2hex(random_bytes(6));
mkdir($directory, 0700);
try {
    foreach (['email' => [8, 1], 'ip' => [16, 10]] as $kind => [$count, $expected]) {
        $workers = [];
        for ($i = 0; $i < $count; $i++) {
            $process = proc_open([PHP_BINARY, __FILE__, '--worker', $directory, $kind, (string) $i], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!is_resource($process)) throw new RuntimeException('Unable to start worker');
            fclose($pipes[0]);
            $workers[] = [$process, $pipes];
        }
        $deadline = microtime(true) + 10;
        while (count(glob($directory . '/ready-*')) !== $count) {
            if (microtime(true) > $deadline) throw new RuntimeException('Worker startup timeout');
            usleep(1000);
        }
        file_put_contents($directory . '/go', '1');
        $allowed = 0;
        foreach ($workers as [$process, $pipes]) {
            $output = stream_get_contents($pipes[1]);
            $errors = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            if (proc_close($process) !== 0 || $errors !== '') throw new RuntimeException('Worker failed');
            $allowed += (int) $output;
        }
        if ($allowed !== $expected) throw new RuntimeException('Concurrent ' . $kind . ' limit failed');
        foreach (glob($directory . '/*') as $file) unlink($file);
    }
    echo "Concurrent limiter tests passed (8 email workers, 16 IP workers).\n";
} finally {
    foreach (glob($directory . '/*') as $file) unlink($file);
    rmdir($directory);
}
