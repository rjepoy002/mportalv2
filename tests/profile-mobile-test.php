<?php
require_once __DIR__ . '/../app/services/ebills-enrollment.php';
$fixtures = json_decode(file_get_contents(__DIR__ . '/profile-mobile-fixtures.json'), true, 512, JSON_THROW_ON_ERROR);
$checks = 0;
function mobileCheck(bool $ok, string $message): void {
    global $checks; $checks++;
    if (!$ok) throw new RuntimeException($message);
}
final class MobileProfilePDO extends PDO {
    public array $row = ['id'=>7,'first_name'=>'Ana','last_name'=>'Cruz','middle_name'=>null,'extension_name'=>null,'email'=>'fixture@example.test','contact_number'=>'09123456789'];
    public int $writes = 0;
    private ?array $snapshot = null;
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new MobileProfileStatement($this, $query); }
    public function beginTransaction(): bool { $this->snapshot = $this->row; return true; }
    public function commit(): bool { $this->snapshot = null; return true; }
    public function rollBack(): bool { $this->row = $this->snapshot; $this->snapshot = null; return true; }
    public function inTransaction(): bool { return $this->snapshot !== null; }
}
final class MobileProfileStatement extends PDOStatement {
    public function __construct(private MobileProfilePDO $db, private string $sql) {}
    public function execute(?array $params = null): bool {
        mobileCheck(($params[array_key_last($params)] ?? null) === 7, 'Profile operation must target authenticated fixture user.');
        if (str_starts_with($this->sql, 'UPDATE mco_portal.users SET')) {
            mobileCheck($this->db->inTransaction(), 'Profile update must retain transaction.');
            foreach (['first_name','last_name','middle_name','extension_name','contact_number'] as $i=>$key) $this->db->row[$key] = $params[$i];
            $this->db->writes++;
        } else mobileCheck(str_contains($this->sql, 'FOR UPDATE'), 'Profile must be locked before saving.');
        return true;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed { return $this->db->row; }
}
$db = new MobileProfilePDO(); $service = new EBillsEnrollment($db);
$input = ['first_name'=>'Ana','last_name'=>'Cruz','middle_name'=>'','extension_name'=>'','csrf_token'=>'fixture'];
if (($argv[1] ?? '') === '--endpoint') {
    $_POST = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    $_SERVER['HTTP_ACCEPT'] = 'text/html'; $_SESSION = [];
    $ebills = $service; $userId = 7; $action = 'save_profile';
    function dashboardCsrfToken(): string { return 'fixture'; }
    function dashboardReturn($page, $message, $type = 'success'): never {
        global $db;
        echo json_encode(['page'=>$page,'message'=>$message,'type'=>$type,'row'=>$db->row,'writes'=>$db->writes,'error'=>$_SESSION['profile_contact_error']??null]);
        exit;
    }
    $source = file_get_contents(__DIR__ . '/../app/controllers/dashboard.php');
    $start = strpos($source, "        if (in_array(\$action, ['save_profile'");
    $end = strpos($source, "        if (in_array(\$action, ['add',", $start);
    if ($start === false || $end === false) throw new RuntimeException('Profile endpoint branch not found.');
    eval(substr($source, $start, $end - $start));
    exit;
}
foreach ($fixtures['valid'] as $raw=>$expected) {
    $raw = (string) $raw; // PHP converts digit-only JSON object keys to integers.
    mobileCheck(portalNormalizeMobileNumber($raw) === $expected, 'Normalization mismatch.');
    $service->saveProfile(7, $input + ['contact_number'=>$raw], 'fixture');
    mobileCheck($db->row['contact_number'] === $expected, 'Update must persist canonical contact.');
    mobileCheck($db->row['email'] === 'fixture@example.test' && $db->row['first_name'] === 'Ana', 'Unrelated fields changed.');
}
foreach ([...$fixtures['invalid'], [], ['09123456789'], 9123456789, false] as $raw) {
    $before = $db->row; $writes = $db->writes; $rejected = false;
    try { $service->saveProfile(7, $input + ['contact_number'=>$raw], 'fixture'); }
    catch (PortalMobileNumberException $e) { $rejected = true; }
    mobileCheck($rejected && $db->row === $before && $db->writes === $writes, 'Invalid contact must not modify profile.');
}
foreach (['09123456789', null] as $existing) {
    $db->row['contact_number'] = $existing;
    $service->saveProfile(7, $input + ['contact_number'=>$existing], 'fixture');
    mobileCheck($db->row['contact_number'] === $existing, 'Existing canonical/empty number must be preserved.');
}
$before = $db->row; $writes = $db->writes;
try { $service->saveProfile(7, array_replace($input, ['csrf_token'=>'invalid','contact_number'=>'+639123456789']), 'fixture'); throw new RuntimeException('CSRF accepted.'); }
catch (DomainException $e) { mobileCheck($db->row === $before && $db->writes === $writes, 'Invalid CSRF changed profile.'); }
foreach (['+63 912-345-6789', 'invalid', '09123456789'] as $raw) {
    $process = proc_open([PHP_BINARY, __FILE__, '--endpoint'], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Cannot start endpoint fixture.');
    fwrite($pipes[0], json_encode($input + ['contact_number'=>$raw])); fclose($pipes[0]);
    $result = stream_get_contents($pipes[1]); fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]); fclose($pipes[2]);
    mobileCheck(proc_close($process) === 0, 'Endpoint fixture failed: ' . $error);
    $result = json_decode($result, true, 512, JSON_THROW_ON_ERROR);
    mobileCheck($result['page'] === 'account-settings', 'Profile endpoint redirect changed.');
    if ($raw === 'invalid') {
        mobileCheck($result['writes'] === 0 && $result['type'] === 'error' && $result['error']['value'] === $raw, 'Endpoint must preserve invalid input for inline error without saving.');
    } else mobileCheck($result['writes'] === 1 && $result['row']['contact_number'] === '09123456789' && $result['type'] === 'success', 'Endpoint must save normalized contact.');
}
$template = file_get_contents(__DIR__ . '/../app/views/pages/dashboard.php');
$start = strpos($template, '<section id="profile-settings"');
$section = substr($template, $start, strpos($template, '</section>', $start) + 10 - $start);
function dashboardEscape($value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
$_SESSION = ['profile_contact_error'=>['message'=>'Invalid mobile fixture','value'=>'<script>']];
$portalProfile = $db->row; $csrf = 'fixture';
ob_start(); eval('?>' . $section); $html = ob_get_clean();
mobileCheck(str_contains($html, 'aria-invalid="true"') && str_contains($html, 'Invalid mobile fixture'), 'Server-rendered inline error missing.');
mobileCheck(str_contains($html, 'value="&lt;script&gt;"') && !str_contains($html, 'value="<script>"'), 'Invalid contact must be escaped.');
mobileCheck(!isset($_SESSION['profile_contact_error']), 'Inline error must be consumed after redirect.');
echo "Profile mobile normalization/update tests passed ($checks assertions; PDO doubles, no live writes).\n";
