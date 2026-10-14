<?php
// Read-only local inspection. Never print credentials, account data or reset records.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/vendor/autoload.php';
echo 'PHP: ' . PHP_VERSION . PHP_EOL;
$lock = json_decode(file_get_contents(dirname(__DIR__) . '/composer.lock'), true);
foreach (['phpmailer/phpmailer', 'google/apiclient'] as $package) {
    $installed = Composer\InstalledVersions::getPrettyVersion($package);
    $locked = array_values(array_filter($lock['packages'], fn ($entry) => $entry['name'] === $package))[0]['version'];
    echo $package . ': installed=' . $installed . ', locked=' . $locked . PHP_EOL;
    if ($installed !== $locked) throw new RuntimeException('Installed dependency differs from lockfile.');
}
foreach (['openssl', 'pdo_mysql', 'mbstring', 'curl'] as $extension) {
    echo $extension . ': ' . (extension_loaded($extension) ? 'loaded' : 'missing') . PHP_EOL;
}
foreach (['APP_ENV', 'APP_URL', 'SMTP_HOST', 'SMTP_PORT', 'SMTP_ENCRYPTION', 'SMTP_USERNAME', 'SMTP_PASSWORD', 'SMTP_FROM_EMAIL'] as $key) {
    echo $key . ': ' . (getenv($key) === false || getenv($key) === '' ? 'unset' : 'set') . PHP_EOL;
}
try {
    $pdo = new PDO('mysql:host=localhost;dbname=mco_portal;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo 'Connected database: ' . $pdo->query('SELECT DATABASE()')->fetchColumn() . PHP_EOL;
    foreach (['users', 'password_resets'] as $table) {
        $stmt = $pdo->prepare('SELECT COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION');
        $stmt->execute([$table]);
        echo $table . ' columns: ' . json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)) . PHP_EOL;
        $stmt = $pdo->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->execute([$table]);
        echo $table . ' engine: ' . $stmt->fetchColumn() . PHP_EOL;
        echo $table . ' indexes: ' . json_encode($pdo->query('SHOW INDEX FROM ' . $table)->fetchAll(PDO::FETCH_ASSOC)) . PHP_EOL;
    }
} catch (Throwable $e) {
    echo 'Database inspection unavailable (' . get_class($e) . '); no data modified.' . PHP_EOL;
    exit(1);
}
