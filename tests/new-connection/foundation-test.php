<?php
// Isolated copy of actual route + synthetic PDO/users/sessions. No real DB or email.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
$temporary = sys_get_temp_dir() . '/mco-nc-' . bin2hex(random_bytes(8));
mkdir($temporary, 0700);
$server = null;
$checks = 0;
function ncCheck(bool $condition, string $message): void {
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($message);
}
function ncRequest(string $path, string $session = '', string $method = 'GET', array $data = []): array {
    global $address;
    $context = stream_context_create(['http' => [
        'method' => $method, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 5,
        'header' => "Cookie: PHPSESSID=$session\r\nContent-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
        'content' => http_build_query($data),
    ]]);
    $body = file_get_contents('http://' . $address . '/' . $path, false, $context);
    return [$body, $http_response_header];
}
function ncSeed(int $id): string {
    $session = bin2hex(random_bytes(16));
    session_id($session); session_start();
    // No linked-account state; stale session name/email must not be rendered.
    $_SESSION = ['user_id' => $id, 'user_name' => 'STALE', 'user_email' => 'stale@example.invalid'];
    session_write_close();
    return $session;
}
function ncRemoveFixture(string $directory): void {
    foreach (new FilesystemIterator($directory) as $file) {
        if ($file->isDir()) ncRemoveFixture($file->getPathname());
        else unlink($file->getPathname());
    }
    rmdir($directory);
}
try {
    $files = [
        'new-connection.php', 'config/new-connection.php', 'app/controllers/new-connection.php',
        'app/middleware/new-connection-auth.php', 'app/views/pages/new-connection.php',
        'app/helpers/portal-navigation.php', 'app/views/partials/portal-sidebar.php',
        'app/helpers/session-security.php',
        'app/helpers/new-connection-preview.php', 'config/new-connection-preview.php',
        'app/views/partials/portal-header.php', 'app/views/partials/session-action-dialogs.php',
        'logout.php', 'app/controllers/logout.php',
    ];
    foreach ($files as $file) {
        $destination = $temporary . '/' . $file;
        if (!is_dir(dirname($destination))) mkdir(dirname($destination), 0700, true);
        copy($root . '/' . $file, $destination);
    }
    mkdir($temporary . '/sessions', 0700);
    session_save_path($temporary . '/sessions');
    // Only fixture config changes flag per test; actual flag parser still runs.
    $flagSource = file_get_contents($temporary . '/config/new-connection.php');
    file_put_contents($temporary . '/config/new-connection.php', "<?php session_save_path(__DIR__ . '/../sessions'); putenv('MCO_NEW_CONNECTION_ENABLED=' . trim(file_get_contents(__DIR__ . '/../flag'))); ?>" . $flagSource);
    file_put_contents($temporary . '/flag', '0');
    file_put_contents($temporary . '/logout.php', "<?php session_save_path(__DIR__ . '/sessions'); ?>" . file_get_contents($temporary . '/logout.php'));
    file_put_contents($temporary . '/config/database.php', <<<'PHP'
<?php
file_put_contents(__DIR__ . '/../connections', "connect\n", FILE_APPEND);
class FixtureStatement extends PDOStatement {
    private int $id;
    public function execute(?array $params = null): bool { $this->id = $params[0]; return true; }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed {
        if ($this->id === 99) return false;
        if ($this->id === 98) throw new RuntimeException('Synthetic database failure');
        return ['id' => $this->id, 'name' => '<Applicant>', 'email' => 'applicant@example.invalid',
            'status' => $this->id === 2 ? 'inactive' : ($this->id === 4 ? 'suspended' : 'active'),
            'email_verified_at' => $this->id === 3 ? null : '2026-10-20 00:00:00'];
    }
}
class FixturePDO extends PDO {
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false {
        if ($query !== 'SELECT id, name, email, status, email_verified_at FROM mco_portal.users WHERE id = ? LIMIT 1') {
            throw new LogicException('Unexpected query: fixture permits the single identity SELECT only.');
        }
        file_put_contents(__DIR__ . '/../queries', "identity-select\n", FILE_APPEND);
        return new FixtureStatement();
    }
}
$pdo = new FixturePDO();
PHP);
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (!$socket) throw new RuntimeException('Cannot allocate test port.');
    $address = stream_socket_get_name($socket, false); fclose($socket);
    $server = proc_open([PHP_BINARY, '-S', $address, '-t', $temporary],
        [0 => ['pipe', 'r'], 1 => ['file', $temporary . '/server.log', 'a'], 2 => ['file', $temporary . '/server.log', 'a']], $pipes);
    if (!is_resource($server)) throw new RuntimeException('Cannot start fixture server.');
    fclose($pipes[0]);
    $deadline = microtime(true) + 10;
    do {
        $ready = @stream_socket_client('tcp://' . $address, $errno, $error, .2);
        if ($ready) { fclose($ready); break; }
        usleep(20000);
    } while (microtime(true) < $deadline);
    ncCheck((bool) $ready, 'Fixture server did not start.');
    foreach (['0', '', 'true', 'unexpected'] as $flag) {
        file_put_contents($temporary . '/flag', $flag);
        [$body, $headers] = ncRequest('new-connection.php');
        ncCheck(str_contains($headers[0], '404'), 'Feature flag did not fail closed.');
        ncCheck(!is_file($temporary . '/connections'), 'Disabled route opened database.');
        ncCheck(!glob($temporary . '/sessions/*'), 'Disabled route started session.');
    }
    file_put_contents($temporary . '/flag', '1');
    [$body, $headers] = ncRequest('new-connection.php');
    ncCheck(str_contains($headers[0], '302') && in_array('Location: index.php', $headers, true), 'Unauthenticated redirect incorrect: ' . json_encode([$headers, $body]));
    ncCheck(!is_file($temporary . '/connections'), 'Unauthenticated route opened database.');
    foreach ([2, 3, 4, 99] as $id) {
        [$body, $headers] = ncRequest('new-connection.php', ncSeed($id));
        ncCheck(str_contains($headers[0], '403'), 'Inactive/unverified/missing user accepted.');
        ncCheck(!str_contains($body, 'nc-stages'), 'Denied request rendered landing page.');
    }
    [$body, $headers] = ncRequest('new-connection.php', ncSeed(98));
    ncCheck(str_contains($headers[0], '503') && !str_contains($body, 'Synthetic'), 'Database exception exposed.');
    $id = ncSeed(1);
    [$body, $headers] = ncRequest('new-connection.php', $id);
    ncCheck(str_contains($headers[0], '200'), 'Verified user without linked accounts denied.');
    ncCheck(substr_count($body, 'class="nc-number"') === 7, 'Seven stages missing.');
    ncCheck(str_contains($body, 'handwritten signatures') && str_contains($body, 'Applications are not open'), 'Required information missing.');
    ncCheck(str_contains($body, '&lt;Applicant&gt;') && !str_contains($body, 'STALE'), 'Current identity not safely rendered.');
    ncCheck(str_contains($body, 'name="viewport"') && str_contains($body, 'portal-shell.css') && str_contains($body, 'theme.js'), 'Responsive/theme shell missing.');
    ncCheck(!str_contains($body, 'type="file"') && !str_contains($body, 'action="new-connection'), 'Active application action exposed.');
    ncCheck(in_array('Cache-Control: no-store, private', $headers, true), 'Private response caching policy missing.');
    [$head, $headers] = ncRequest('new-connection.php', $id, 'HEAD');
    ncCheck(str_contains($headers[0], '200') && $head === '', 'HEAD behavior incorrect.');
    $queriesBefore = file_get_contents($temporary . '/queries');
    [$body, $headers] = ncRequest('new-connection.php', $id, 'POST');
    ncCheck(str_contains($headers[0], '405'), 'POST accepted by informational landing.');
    ncCheck(file_get_contents($temporary . '/queries') === $queriesBefore, 'Rejected method queried database.');
    // Logout uses the real, unchanged endpoint and the token issued by the shared shell.
    session_id($id); session_start(); $token = $_SESSION['dashboard_csrf']; session_write_close();
    [$body, $headers] = ncRequest('logout.php', $id, 'POST', ['csrf_token' => $token, 'intent' => 'logout']);
    $result = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    ncCheck($result['ok'] && $result['redirect'] === 'index.php', 'Shared-shell logout failed.');
    [$body, $headers] = ncRequest('new-connection.php', $id);
    ncCheck(str_contains($headers[0], '302'), 'Logged-out session retained access.');
    // Isolated tree has no mailer/account services: successful rendering cannot load them.
    require_once $root . '/app/helpers/portal-navigation.php';
    $navigation = portalNavigationItems();
    $newConnection = $navigation['new-connection'] ?? [];
    ncCheck(($newConnection['label'] ?? '') === 'New Connection' && ($newConnection['icon'] ?? '') === 'plug', 'New Connection navigation missing.');
    if (newConnectionPreviewAvailable($_SERVER)) {
        ncCheck(($newConnection['href'] ?? '') === 'new-connection-preview.php' && $newConnection['status'] === 'Preview', 'Preview link is not fixed.');
    } else {
        ncCheck(($newConnection['disabled'] ?? false) === true && !isset($newConnection['href']) && $newConnection['status'] === 'Coming Soon', 'Unavailable navigation is actionable.');
    }
    foreach (['dashboard'=>'dashboard.php?page=dashboard','bills'=>'dashboard.php?page=bills','account-settings'=>'dashboard.php?page=account-settings','consumer-education'=>'consumer-education.php'] as $key=>$href) {
        ncCheck(($navigation[$key]['href'] ?? '') === $href, 'Existing navigation target changed.');
    }
    $savedFlag = getenv('MCO_NEW_CONNECTION_ENABLED'); putenv('MCO_NEW_CONNECTION_ENABLED');
    ncCheck((require $root . '/config/new-connection.php')['enabled'] === false, 'Application feature is not default-off.');
    $savedFlag === false ? putenv('MCO_NEW_CONNECTION_ENABLED') : putenv('MCO_NEW_CONNECTION_ENABLED=' . $savedFlag);
    foreach (['app/controllers/dashboard.php', 'app/services/account-linking.php',
        'app/services/ebills-enrollment.php', 'app/services/mailer.php'] as $file) {
        exec('git -C ' . escapeshellarg($root) . ' diff --exit-code -- ' . escapeshellarg($file), $output, $status);
        ncCheck($status === 0, 'Existing behavior source modified: ' . $file);
    }
    echo "New Connection foundation passed ($checks assertions; isolated HTTP, synthetic users, SELECT-only PDO).\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    ncRemoveFixture($temporary);
}
