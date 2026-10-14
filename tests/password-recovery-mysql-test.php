<?php

require_once dirname(__DIR__) . '/includes/password-recovery.php';

// Deliberately opt-in: never create or mutate tables in the application database.
$dsn = getenv('PASSWORD_RESET_TEST_DSN');
if (!$dsn) {
    echo "SKIP MySQL token concurrency test: PASSWORD_RESET_TEST_DSN is not configured.\n";
    exit;
}
if (!preg_match('/(?:^|;)dbname=mportal_password_recovery_test(?:;|$)/', substr($dsn, 6)) || !str_starts_with($dsn, 'mysql:')) {
    throw new RuntimeException('Use only the dedicated mportal_password_recovery_test database.');
}
function testConnection(): PDO
{
    return new PDO(getenv('PASSWORD_RESET_TEST_DSN'), getenv('PASSWORD_RESET_TEST_USER') ?: 'root', getenv('PASSWORD_RESET_TEST_PASSWORD') ?: '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}
$pdo = testConnection();
if (($argv[1] ?? '') === '--worker') {
    $directory = $argv[2];
    $hash = file_get_contents($directory . '/hash');
    file_put_contents($directory . '/ready-' . $argv[3], '1');
    $deadline = microtime(true) + 15;
    while (!is_file($directory . '/go')) {
        if (microtime(true) > $deadline) exit(2);
        usleep(1000);
    }
    echo recovery_reset_password($pdo, ['reset_id' => 1, 'user_id' => 1], $hash, 'Password123') ? '1' : '0';
    exit;
}
if ($pdo->query('SHOW TABLES')->fetchColumn() !== false) throw new RuntimeException('Dedicated test database must be empty.');
$directory = __DIR__ . '/recovery-mysql-' . bin2hex(random_bytes(6));
mkdir($directory, 0700);
$created = [];
try {
    $pdo->exec('CREATE TABLE users (id INT PRIMARY KEY, password_hash VARCHAR(255) NOT NULL) ENGINE=InnoDB');
    $created[] = 'users';
    $pdo->exec('CREATE TABLE password_resets (id INT PRIMARY KEY, user_id INT NOT NULL, token_hash CHAR(64) NOT NULL, expires_at DATETIME NOT NULL, used_at DATETIME NULL) ENGINE=InnoDB');
    $created[] = 'password_resets';
    $pdo->exec("INSERT INTO users VALUES (1, 'unchanged')");
    $hash = hash('sha256', bin2hex(random_bytes(32)));
    $insert = $pdo->prepare('INSERT INTO password_resets VALUES (1, 1, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), NULL)');
    $insert->execute([$hash]);
    file_put_contents($directory . '/hash', $hash);
    $workers = [];
    for ($i = 0; $i < 2; $i++) {
        $process = proc_open([PHP_BINARY, __FILE__, '--worker', $directory, (string) $i], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) throw new RuntimeException('Unable to start MySQL worker');
        fclose($pipes[0]);
        $workers[] = [$process, $pipes];
    }
    $deadline = microtime(true) + 10;
    while (count(glob($directory . '/ready-*')) !== 2) {
        if (microtime(true) > $deadline) throw new RuntimeException('MySQL worker startup timeout');
        usleep(1000);
    }
    file_put_contents($directory . '/go', '1');
    $wins = 0;
    foreach ($workers as [$process, $pipes]) {
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        if (proc_close($process) !== 0 || $errors !== '') throw new RuntimeException('MySQL worker failed');
        $wins += (int) $output;
    }
    $stored = $pdo->query('SELECT password_hash FROM users WHERE id = 1')->fetchColumn();
    if ($wins !== 1 || !password_verify('Password123', $stored)) throw new RuntimeException('Concurrent token consumption failed');
    $pdo->exec('UPDATE password_resets SET used_at = NULL, expires_at = DATE_SUB(NOW(), INTERVAL 1 SECOND) WHERE id = 1');
    if (recovery_reset_password($pdo, ['reset_id' => 1, 'user_id' => 1], $hash, 'Different123')) throw new RuntimeException('Expired token claimed');
    echo "MySQL token concurrency and expiry tests passed (two independent connections).\n";
} finally {
    foreach (array_reverse($created) as $table) $pdo->exec('DROP TABLE ' . $table);
    foreach (glob($directory . '/*') as $file) unlink($file);
    rmdir($directory);
}
