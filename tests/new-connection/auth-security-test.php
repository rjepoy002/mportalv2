<?php
// CLI only. Actual controllers in an isolated tree with synthetic PDO/Google APIs.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__, 2);
$dir = sys_get_temp_dir() . '/mco-auth-' . bin2hex(random_bytes(8));
mkdir($dir, 0700); $server = null; $checks = 0;
function assertAuth(bool $ok, string $label): void {
    global $checks; $checks++; if (!$ok) throw new RuntimeException($label);
}
function authRequest(string $path, string $cookie = '', array $post = [], string $method = 'GET'): array {
    global $address;
    $body = file_get_contents('http://' . $address . '/' . $path, false, stream_context_create(['http' => [
        'method' => $method, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 5,
        'header' => "Cookie: PHPSESSID=$cookie\r\nContent-Type: application/x-www-form-urlencoded\r\n",
        'content' => http_build_query($post),
    ]]));
    $headers = $http_response_header; $nextCookie = $cookie;
    foreach ($headers as $header) if (preg_match('/^Set-Cookie: PHPSESSID=([^;]+)/i', $header, $match)) $nextCookie = $match[1];
    return [$body, $headers, $nextCookie];
}
function authSeed(array $data): string {
    session_id(bin2hex(random_bytes(16))); session_start(); $_SESSION = $data;
    $id = session_id(); session_write_close(); return $id;
}
function removeAuthFixture(string $dir): void {
    foreach (new FilesystemIterator($dir) as $file) {
        if ($file->isDir()) removeAuthFixture($file->getPathname()); else unlink($file->getPathname());
    }
    rmdir($dir);
}
try {
    foreach (['app/helpers/session-security.php', 'app/services/auth-rate-limit.php', 'app/middleware/auth.php',
        'app/controllers/index.php', 'app/controllers/register.php', 'app/controllers/google-login.php', 'app/controllers/google-callback.php',
        'app/services/ebills-enrollment.php', 'app/helpers/philippine-mobile.php', 'app/views/pages/register.php'] as $file) {
        if (!is_dir(dirname($dir . '/' . $file))) mkdir(dirname($dir . '/' . $file), 0700, true);
        copy($root . '/' . $file, $dir . '/' . $file);
    }
    foreach (['sessions', 'limits', 'vendor', 'config', 'app/views/pages'] as $folder) {
        if (!is_dir($dir . '/' . $folder)) mkdir($dir . '/' . $folder, 0700, true);
    }
    // Views only expose fixture state, never real user data. Actual forms checked below.
    file_put_contents($dir . '/app/views/pages/index.php', '<?php echo json_encode(["csrf"=>portalAuthCsrf(),"error"=>$error]);');
    file_put_contents($dir . '/app/services/mailer.php', <<<'PHP'
<?php
function sendEmail($email,$name,$subject,$html,$plain): bool {
    preg_match('/code is: (\d{6})/', $plain, $match);
    file_put_contents(dirname(__DIR__,2).'/fixture-otp', $match[1]);
    return true;
}
PHP);
    file_put_contents($dir . '/config/google.php', "<?php define('GOOGLE_CLIENT_ID','fixture'); define('GOOGLE_CLIENT_SECRET','fixture'); define('GOOGLE_REDIRECT_URI','https://example.invalid/callback');");
    file_put_contents($dir . '/config/database.php', <<<'PHP'
<?php
file_put_contents(dirname(__DIR__).'/db-access', "connect\n", FILE_APPEND);
class AuthPDO extends PDO {
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false { return new AuthStatement($query); }
}
class AuthStatement extends PDOStatement {
    private array $params = [];
    public function __construct(private string $sql) {}
    public function execute(?array $params = null): bool {
        $this->params = $params ?? [];
        file_put_contents(dirname(__DIR__).'/queries', preg_replace('/\s+/', ' ', $this->sql)."\n", FILE_APPEND);
        if (str_contains($this->sql, 'INSERT INTO email_verifications')) {
            file_put_contents(dirname(__DIR__).'/verification', json_encode(['id'=>1,'email'=>$params[0],'otp_hash'=>$params[1],'expires_at'=>$params[2],'resend_available_at'=>$params[3],'verified_at'=>null,'attempts'=>0]));
        }
        return true;
    }
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed {
        $identity = (string) reset($this->params);
        if (str_contains($this->sql,'FROM email_verifications')) return json_decode(file_get_contents(dirname(__DIR__).'/verification'),true);
        if ($identity==='signup@example.invalid') return false;
        if ($identity === '2') $identity = 'inactive@example.invalid';
        if ($identity === '3') $identity = 'unverified@example.invalid';
        if (str_contains($identity, 'missing')) return false;
        $name = explode('@', $identity)[0];
        return ['id'=>7, 'name'=>'Fixture', 'email'=>$identity, 'password_hash'=>password_hash('correct123', PASSWORD_DEFAULT),
            'google_id'=>str_contains($identity,'unlinked') ? null : 'google-fixture',
            'status'=>str_contains($identity,'inactive') ? 'inactive' : 'active',
            'email_verified_at'=>str_contains($identity,'unverified') ? null : '2026-10-20 00:00:00'];
    }
}
$pdo = new AuthPDO();
PHP);
    file_put_contents($dir . '/vendor/autoload.php', <<<'PHP'
<?php
namespace Google;
class Client {
    private string $state;
    public function setClientId($v) {} public function setClientSecret($v) {} public function setRedirectUri($v) {}
    public function addScope($v) {} public function setPrompt($v) {} public function setAccessToken($v) {}
    public function setState($v) { $this->state=$v; }
    public function createAuthUrl() { return 'https://example.invalid/oauth?state='.$this->state; }
    public function fetchAccessTokenWithAuthCode($v) { file_put_contents(dirname(__DIR__).'/google-access', "exchange\n", FILE_APPEND); return ['access_token'=>'synthetic']; }
}
namespace Google\Service;
class Oauth2 {
    public object $userinfo;
    public function __construct($client) {
        $this->userinfo = new class {
            public function get() {
                $scenario = trim(file_get_contents(dirname(__DIR__).'/scenario'));
                return (object)['id'=>'google-fixture','email'=>$scenario.'@example.invalid','name'=>'Fixture','verified_email'=>$scenario!=='unverified','hd'=>null];
            }
        };
    }
}
PHP);
    // Routing simulates trusted server TLS configuration, never request proxy headers.
    file_put_contents($dir . '/router.php', <<<'PHP'
<?php
session_save_path(__DIR__.'/sessions'); putenv('MCO_AUTH_RATE_DIR='.__DIR__.'/limits');
$route = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($route === '/https') $_SERVER['HTTPS']='on';
if (in_array($route, ['/session','/https','/fake-proxy'], true)) {
    if ($route === '/fake-proxy') $_SERVER['HTTP_X_FORWARDED_PROTO']='https';
    require __DIR__.'/app/helpers/session-security.php'; portalSessionStart();
    echo json_encode(['id'=>session_id(),'strict'=>ini_get('session.use_strict_mode'),'csrf'=>portalAuthCsrf(),'user'=>$_SESSION['user_id']??null]); return;
}
if ($route === '/protected') { require __DIR__.'/app/middleware/auth.php'; echo 'authorized'; return; }
$map=['/login'=>'index.php','/register'=>'register.php','/oauth'=>'google-login.php','/callback'=>'google-callback.php'];
if (!isset($map[$route])) { http_response_code(404); return; }
require __DIR__.'/app/controllers/'.$map[$route];
PHP);
    file_put_contents($dir . '/scenario', 'active');
    session_save_path($dir . '/sessions');
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
    if (!$socket) throw new RuntimeException('Cannot allocate fixture port.');
    $address = stream_socket_get_name($socket, false); fclose($socket);
    $server = proc_open([PHP_BINARY, '-S', $address, $dir . '/router.php'], [0=>['pipe','r'],1=>['file',$dir.'/server.log','a'],2=>['file',$dir.'/server.log','a']], $pipes);
    if (!is_resource($server)) throw new RuntimeException('Cannot launch fixture.'); fclose($pipes[0]);
    $deadline=microtime(true)+10;
    do { $ready=@stream_socket_client('tcp://'.$address,$errno,$error,.2); if($ready){fclose($ready);break;} usleep(20000); } while(microtime(true)<$deadline);
    assertAuth((bool)$ready, 'Fixture startup');
    foreach (['session'=>false,'https'=>true,'fake-proxy'=>false] as $route=>$secure) {
        [$body,$headers,$cookie]=authRequest($route,'attacker-chosen-session');
        $data=json_decode($body,true,512,JSON_THROW_ON_ERROR);
        $setCookie=implode(';',array_filter($headers,fn($v)=>str_starts_with($v,'Set-Cookie:')));
        assertAuth($cookie!=='attacker-chosen-session' && $data['strict']==='1', 'Strict-mode rejects unknown session ID');
        assertAuth(str_contains(strtolower($setCookie),'httponly') && str_contains($setCookie,'SameSite=Lax'), 'Cookie HttpOnly/Lax');
        assertAuth(str_contains(strtolower($setCookie),'; secure')===$secure, 'TLS cookie behavior');
    }
    foreach ([['portal_created_at'=>time()-50,'portal_last_seen'=>time()-1801],['portal_created_at'=>time()-43201,'portal_last_seen'=>time()]] as $times) {
        $old=authSeed($times+['user_id'=>7,'dashboard_csrf'=>'old']);
        [$body,$headers,$cookie]=authRequest('session',$old);
        assertAuth(json_decode($body,true)['user']===null && $cookie!==$old, 'Idle/absolute expiry clears authentication and rotates ID');
    }
    foreach (['login','register'] as $route) {
        [$body,$headers]=authRequest($route,'',['action'=>'resend_otp','csrf_token'=>['bad']],'POST');
        assertAuth(str_contains($headers[0],'419'), 'CSRF rejection: '.$route);
        assertAuth(!is_file($dir.'/db-access'), 'CSRF rejected before DB/mail: '.$route);
    }
    [$body,$headers,$cookie]=authRequest('register');
    preg_match('/name="csrf_token" value="([a-f0-9]+)"/', $body, $match); $csrf=$match[1];
    [$body,$headers]=authRequest('register',$cookie,['action'=>'register','csrf_token'=>$csrf,'email'=>'signup@example.invalid',
        'password'=>'correct123','confirm_password'=>'correct123','first_name'=>'Test','last_name'=>'Applicant','terms'=>'1'],'POST');
    assertAuth(str_contains($headers[0],'200') && is_file($dir.'/fixture-otp'), 'Registration with valid CSRF reaches synthetic mail transport');
    [$body,$headers]=authRequest('register',$cookie,['action'=>'verify_otp','csrf_token'=>$csrf,'otp'=>file_get_contents($dir.'/fixture-otp')],'POST');
    assertAuth(str_contains($headers[0],'302') && in_array('Location: index.php?registered=1',$headers,true), 'Registration OTP completes with valid CSRF');
    $errors=[];
    foreach (['missing','inactive','unverified','active'] as $name) {
        [$body,$headers,$cookie]=authRequest('login'); $csrf=json_decode($body,true)['csrf'];
        [$body,$headers,$newCookie]=authRequest('login',$cookie,['csrf_token'=>$csrf,'email'=>$name.'@example.invalid','password'=>$name==='active'?'wrong':'correct123'],'POST');
        $errors[]=json_decode($body,true)['error'];
    }
    assertAuth(count(array_unique($errors))===1 && $errors[0]==='Invalid email or password.', 'Generic credential/status/verification errors');
    [$body,$headers,$cookie]=authRequest('login'); $csrf=json_decode($body,true)['csrf'];
    [$body,$headers,$newCookie]=authRequest('login',$cookie,['csrf_token'=>$csrf,'email'=>'success@example.invalid','password'=>'correct123'],'POST');
    assertAuth(str_contains($headers[0],'302') && in_array('Location: dashboard.php',$headers,true) && $newCookie!==$cookie, 'Successful login regenerates session');
    [$body]=authRequest('session',$newCookie); assertAuth(json_decode($body,true)['user']===7, 'Successful login session retained');
    foreach ([2,3] as $userId) {
        $inactive=authSeed(['user_id'=>$userId]);
        [$body,$headers]=authRequest('protected',$inactive); assertAuth(str_contains($headers[0],'302'), 'Current-user middleware denies inactive/unverified identity');
    }
    [$body,$headers]=authRequest('protected',authSeed(['user_id'=>7]));
    assertAuth($body==='authorized', 'Current-user middleware permits active verified identity');
    for($i=0;$i<11;$i++) {
        [$body,$headers,$cookie]=authRequest('login'); $csrf=json_decode($body,true)['csrf'];
        [$body,$headers]=authRequest('login',$cookie,['csrf_token'=>$csrf,'email'=>'limited@example.invalid','password'=>'wrong'],'POST');
    }
    assertAuth(str_contains($headers[0],'429'), 'Persistent cross-session email limit');
    assertAuth(!str_contains(file_get_contents($dir.'/limits/limits.json'),'limited@example.invalid'), 'Limiter stores no raw email');
    $limits=file_get_contents($dir.'/limits/limits.json');
    file_put_contents($dir.'/limits/limits.json','invalid-json');
    [$body,$headers,$cookie]=authRequest('login'); $csrf=json_decode($body,true)['csrf'];
    [$body,$headers]=authRequest('login',$cookie,['csrf_token'=>$csrf,'email'=>'failure@example.invalid','password'=>'correct123'],'POST');
    assertAuth(str_contains($headers[0],'429'), 'Unavailable limiter fails closed');
    file_put_contents($dir.'/limits/limits.json',$limits);
    unlink($dir.'/db-access');
    foreach (['', '?state=wrong&code=synthetic'] as $suffix) {
        [$body,$headers]=authRequest('callback'.$suffix);
        assertAuth(str_contains($headers[0],'400') && !is_file($dir.'/db-access') && !is_file($dir.'/google-access'), 'Invalid OAuth state rejected before external activity');
    }
    [$body,$headers,$cookie]=authRequest('oauth');
    $location=current(array_filter($headers,fn($v)=>str_starts_with($v,'Location:')));
    parse_str(parse_url(substr($location,10),PHP_URL_QUERY),$query); $state=$query['state'];
    file_put_contents($dir.'/scenario','unverified');
    $before=file_get_contents($dir.'/queries');
    [$body,$headers]=authRequest('callback?state='.$state.'&code=synthetic',$cookie);
    assertAuth(!str_contains($body,'dashboard') && file_get_contents($dir.'/queries')===$before, 'Unverified Google email denied before query');
    [$sessionBody]=authRequest('session',$cookie);
    assertAuth(json_decode($sessionBody,true)['user']===null, 'Unverified Google email creates no authenticated session');
    $before=file_get_contents($dir.'/queries');
    [$body,$headers]=authRequest('callback?state='.$state.'&code=synthetic',$cookie);
    assertAuth(str_contains($headers[0],'400') && file_get_contents($dir.'/queries')===$before, 'OAuth state replay denied');
    [$body,$headers,$cookie]=authRequest('oauth'); $location=current(array_filter($headers,fn($v)=>str_starts_with($v,'Location:')));
    parse_str(parse_url(substr($location,10),PHP_URL_QUERY),$query);
    file_put_contents($dir.'/scenario','active');
    [$body,$headers,$newCookie]=authRequest('callback?state='.$query['state'].'&code=synthetic',$cookie);
    assertAuth(str_contains($headers[0],'302') && $newCookie!==$cookie, 'Verified Google sign-in regenerates session');
    foreach (['inactive','unlinked'] as $scenario) {
        [$body,$headers,$cookie]=authRequest('oauth'); $location=current(array_filter($headers,fn($v)=>str_starts_with($v,'Location:')));
        parse_str(parse_url(substr($location,10),PHP_URL_QUERY),$query);
        file_put_contents($dir.'/scenario',$scenario);
        $before=file_get_contents($dir.'/queries');
        [$body,$headers]=authRequest('callback?state='.$query['state'].'&code=synthetic',$cookie);
        $additional=substr(file_get_contents($dir.'/queries'),strlen($before));
        assertAuth(!str_contains($headers[0],'302') && !str_contains($additional,'UPDATE'), 'Inactive/third-party unlinked Google identity cannot mutate user');
    }
    require_once $root.'/app/helpers/session-security.php';
    $_SESSION=['oauth_pending'=>['state'=>'old','created'=>time()-601]];
    assertAuth(!portalOAuthStateValid('old') && !isset($_SESSION['oauth_pending']), 'Expired OAuth state consumed');
    $_SESSION=['oauth_pending'=>['state'=>'expected','created'=>time()]];
    assertAuth(!portalOAuthStateValid('wrong') && !isset($_SESSION['oauth_pending']), 'Mismatched pending state consumed');
    assertAuth(!portalGoogleIdentity('id','test@example.invalid',false) && portalGoogleIdentity('id','test@example.invalid',true), 'Google verified-email strict handling');
    foreach (['index.php'=>1,'register.php'=>2] as $view=>$count) {
        assertAuth(substr_count(file_get_contents($root.'/app/views/pages/'.$view),'name="csrf_token"')===$count, 'Actual form CSRF fields: '.$view);
    }
    assertAuth(str_contains(file_get_contents($root.'/assets/js/register.js'),'action=resend_otp&csrf_token='), 'OTP AJAX transmits CSRF');
    assertAuth(!str_contains(file_get_contents($dir.'/server.log'),'synthetic'), 'No OAuth code/token in application log');
    if (getenv('MCO_AUTH_BROWSER_ACCEPTANCE') === '1') require __DIR__ . '/browser-fixture.php';
    echo "Authentication security tests passed ($checks assertions; isolated HTTP and server-TLS fixtures, synthetic identities only).\n";
} finally {
    if(is_resource($server)){proc_terminate($server);proc_close($server);}
    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
    removeAuthFixture($dir);
}
