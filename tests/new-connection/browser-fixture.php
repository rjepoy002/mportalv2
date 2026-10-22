<?php
// Included only by the isolated CLI security test, never by application routes.
if (PHP_SAPI !== 'cli' || !isset($dir, $root, $address)) exit;
foreach (['app/views/pages/index.php','app/views/pages/forgot-password.php','app/views/pages/reset-password.php',
    'app/controllers/forgot-password.php','app/controllers/reset-password.php','app/controllers/logout.php',
    'app/controllers/new-connection.php','app/middleware/new-connection-auth.php','app/services/password-recovery.php',
    'app/helpers/recovery-log.php','app/helpers/portal-navigation.php','app/views/partials/portal-header.php',
    'app/views/partials/portal-sidebar.php','app/views/partials/session-action-dialogs.php',
    'app/views/pages/new-connection.php','config/app.php','config/new-connection.php'] as $file) {
    if (!is_dir(dirname($dir.'/'.$file))) mkdir(dirname($dir.'/'.$file),0700,true);
    copy($root.'/'.$file,$dir.'/'.$file);
}
if (getenv('MCO_LOCAL_ASSETS_ACCEPTANCE') === '1') {
    foreach (['app/views/pages/dashboard.php','app/controllers/dashboard.php','app/middleware/account-context.php',
        'app/views/partials/account-linking-form.php','app/views/partials/ebills-ui.php',
        'resources/ebills-terms.html','tests/new-connection/dashboard-presentation-fixture.php'] as $file) {
        if (!is_dir(dirname($dir.'/'.$file))) mkdir(dirname($dir.'/'.$file),0700,true);
        copy($root.'/'.$file,$dir.'/'.$file);
    }
}
foreach (['css','js','images'] as $folder) {
    mkdir($dir.'/assets/'.$folder,0700,true);
    foreach (glob($root.'/assets/'.$folder.'/*') as $file) if(is_file($file)) copy($file,$dir.'/assets/'.$folder.'/'.basename($file));
}
if (is_dir($root.'/assets/vendor')) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/assets/vendor',FilesystemIterator::SKIP_DOTS)) as $file) {
        if (!$file->isFile()) continue;
        $relative=substr($file->getPathname(),strlen($root.'/assets/vendor'));
        $target=$dir.'/assets/vendor'.$relative;
        if(!is_dir(dirname($target)))mkdir(dirname($target),0700,true);
        copy($file->getPathname(),$target);
    }
}
// Extend the existing fake PDO only; production database/bootstrap stays untouched.
$database=file_get_contents($dir.'/config/database.php');
$database=str_replace('public function __construct() {}', 'public function __construct() {} public function beginTransaction(): bool {return true;} public function commit(): bool {return true;} public function rollBack(): bool {return true;} public function inTransaction(): bool {return false;}', $database);
$database=str_replace('private array $params = [];', 'private array $params = []; public function rowCount(): int {return 1;}', $database);
$database=str_replace('$this->params = $params ?? [];', <<<'PHP'
$this->params = $params ?? [];
if (str_contains($this->sql,'UPDATE users SET password_hash')) file_put_contents(dirname(__DIR__).'/password-hash',$params[':password']);
if (str_contains($this->sql,'INSERT INTO users') && isset($params[0])) file_put_contents(dirname(__DIR__).'/registered-user',json_encode(['id'=>7,'email'=>$params[0],'name'=>$params[1],'password_hash'=>$params[2],'google_id'=>null,'status'=>'active','email_verified_at'=>date('Y-m-d H:i:s')]));
if (str_contains($this->sql,'INSERT INTO password_resets')) file_put_contents(dirname(__DIR__).'/reset-record',json_encode(['hash'=>$params[':token_hash'],'used'=>false]));
if (str_contains($this->sql,'UPDATE password_resets SET used_at')) { $r=json_decode(file_get_contents(dirname(__DIR__).'/reset-record'),true);$r['used']=true;file_put_contents(dirname(__DIR__).'/reset-record',json_encode($r)); }
PHP, $database);
$database=str_replace("if (\$identity==='signup@example.invalid') return false;", "if (str_contains(\$identity,'signup@')) return is_file(dirname(__DIR__).'/registered-user') ? json_decode(file_get_contents(dirname(__DIR__).'/registered-user'),true) : false;", $database);
$database=str_replace('$identity = (string) reset($this->params);', <<<'PHP'
$identity = (string) reset($this->params);
if (str_contains($this->sql,'FROM password_resets')) {
    $r=is_file(dirname(__DIR__).'/reset-record')?json_decode(file_get_contents(dirname(__DIR__).'/reset-record'),true):null;
    return $r && !$r['used'] && $identity===$r['hash'] ? ['reset_id'=>1,'user_id'=>7,'expires_at'=>date('Y-m-d H:i:s',time()+3600),'name'=>'Fixture','email'=>'recovery@example.invalid'] : false;
}
PHP, $database);
$database=str_replace("password_hash('correct123', PASSWORD_DEFAULT)", "(is_file(dirname(__DIR__).'/password-hash') ? file_get_contents(dirname(__DIR__).'/password-hash') : password_hash('correct123', PASSWORD_DEFAULT))",$database);
file_put_contents($dir.'/config/database.php',$database);
file_put_contents($dir.'/app/services/mailer.php', <<<'PHP'
<?php
function sendEmail($email,$name,$subject,$html,$plain,...$unused): bool {
    if(preg_match('/code is: (\d{6})/',$plain,$match)) file_put_contents(dirname(__DIR__,2).'/fixture-otp',$match[1]);
    if(preg_match('/reset-password.php\?token=([a-f0-9]{64})/',$plain,$match)) file_put_contents(dirname(__DIR__,2).'/fixture-reset-token',$match[1]);
    return true;
}
PHP);
file_put_contents($dir.'/nc-flag','1');
file_put_contents($dir.'/router.php', <<<'PHP'
<?php
session_save_path(__DIR__.'/sessions');
putenv('MCO_AUTH_RATE_DIR='.__DIR__.'/limits'); putenv('PASSWORD_RESET_RATE_DIR='.__DIR__.'/recovery-limits');
putenv('APP_ENV=test'); putenv('APP_URL=http://'.$_SERVER['SERVER_NAME'].':'.$_SERVER['SERVER_PORT']);
putenv('MCO_NEW_CONNECTION_ENABLED='.trim(file_get_contents(__DIR__.'/nc-flag')));
$route=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(str_starts_with($route,'/assets/')) {
    $file=realpath(__DIR__.$route);$assets=realpath(__DIR__.'/assets').DIRECTORY_SEPARATOR;
    if(!$file || !str_starts_with($file,$assets) || !is_file($file)){http_response_code(404);return;}
    header('Content-Type: '.match(pathinfo($file,PATHINFO_EXTENSION)){'js'=>'text/javascript','css'=>'text/css','png'=>'image/png','woff'=>'font/woff','woff2'=>'font/woff2',default=>'application/octet-stream'});
    readfile($file);return;
}
if($route==='/presentation.php' && getenv('MCO_LOCAL_ASSETS_ACCEPTANCE')==='1') {
    require __DIR__.'/tests/new-connection/dashboard-presentation-fixture.php';return;
}
if($route==='/dashboard.php') {
    require __DIR__.'/app/middleware/auth.php';
    if($_SERVER['REQUEST_METHOD']==='POST' && getenv('MCO_LOCAL_ASSETS_ACCEPTANCE')==='1') {
        header('Content-Type: application/json');
        if(!is_string($_POST['csrf_token']??null) || !hash_equals($_SESSION['dashboard_csrf']??'',$_POST['csrf_token'])){http_response_code(419);echo json_encode(['ok'=>false]);return;}
        if(($_POST['action']??'')==='ebills_details') {
            echo json_encode(['ok'=>true,'details'=>['account'=>['AcctCode'=>'1234567890','Name'=>'SYNTHETIC ACCOUNT','Address'=>'Synthetic test address'],
                'profile'=>['first_name'=>'Synthetic','last_name'=>'Applicant','email'=>'synthetic@example.invalid','contact_number'=>'09123456789'],
                'enrolled'=>false,'problem'=>'','terms_token'=>'synthetic','terms_version'=>'sha256:'.hash('sha256',file_get_contents(__DIR__.'/resources/ebills-terms.html'))]]);return;
        }
        if(($_POST['action']??'')==='ebills_accept_terms'){echo json_encode(['ok'=>true]);return;}
        http_response_code(405);echo json_encode(['ok'=>false]);return;
    }
    echo '<!doctype html><title>Isolated signed-in fixture</title><p id="signed-in">Signed in (fixture destination; dashboard controller deliberately not invoked).</p>';return;
}
$routes=['/index.php'=>'index.php','/register.php'=>'register.php','/forgot-password.php'=>'forgot-password.php','/reset-password.php'=>'reset-password.php','/logout.php'=>'logout.php','/new-connection.php'=>'new-connection.php','/google-login.php'=>'google-login.php','/google-callback.php'=>'google-callback.php'];
if(!isset($routes[$route])){http_response_code(404);return;}
require __DIR__.'/app/controllers/'.$routes[$route];
PHP);
$browserConfig=[
    'base'=>'http://'.$address,'fixture'=>$dir,'root'=>$root,
    'inactive'=>authSeed(['user_id'=>2]),'unverified'=>authSeed(['user_id'=>3]),
    'expired'=>authSeed(['user_id'=>7,'portal_created_at'=>time()-2000,'portal_last_seen'=>time()-1801]),
];
file_put_contents($dir.'/browser-config.json',json_encode($browserConfig));
$process=proc_open(['C:/Program Files/nodejs/node.exe',__DIR__.'/browser-acceptance-test.js',$dir.'/browser-config.json'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$browserPipes);
if(!is_resource($process))throw new RuntimeException('Cannot run browser acceptance.');
fclose($browserPipes[0]);
echo stream_get_contents($browserPipes[1]);fclose($browserPipes[1]);
$browserError=stream_get_contents($browserPipes[2]);fclose($browserPipes[2]);
$browserStatus=proc_close($process);
if($browserStatus!==0)throw new RuntimeException('Browser acceptance failed: '.$browserError);
