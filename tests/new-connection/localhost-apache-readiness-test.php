<?php
/** 06J: separate Apache, outside htdocs; no database connection or provider/mail traffic. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__,2);$temp=sys_get_temp_dir().'/mco-apache-'.bin2hex(random_bytes(8));$process=null;$checks=[];$exit=0;
function apacheCheck(bool $ok,string $label): void {global $checks;if(!$ok)throw new RuntimeException($label);$checks[]=$label;}
function apacheWrite(string $file,string $value): void {if(!is_dir(dirname($file)))mkdir(dirname($file),0700,true);file_put_contents($file,$value);}
function apacheRequest(string $path,string &$cookie,array $post=[],string $method='GET',?string $base=null): array
{
    global $origin;$body=file_get_contents(($base??$origin).$path,false,stream_context_create(['http'=>['method'=>$method,'timeout'=>8,'follow_location'=>0,'ignore_errors'=>true,'header'=>"Cookie: PHPSESSID=$cookie\r\nContent-Type: application/x-www-form-urlencoded\r\n",'content'=>http_build_query($post)]]));
    $status=0;$location='';$set=[];foreach($http_response_header as $h){if(preg_match('/^HTTP\/\S+ (\d+)/',$h,$m))$status=(int)$m[1];if(preg_match('/^Set-Cookie: PHPSESSID=([^;]*)/i',$h,$m)){$cookie=$m[1];$set[]=$h;}if(str_starts_with($h,'Location: '))$location=substr($h,10);}
    return ['status'=>$status,'body'=>$body,'headers'=>$http_response_header,'location'=>$location,'cookies'=>$set,'json'=>json_decode($body,true)];
}
function apacheRemove(string $dir): void {foreach(new FilesystemIterator($dir) as $item){if($item->isDir())apacheRemove($item->getPathname());else unlink($item->getPathname());}rmdir($dir);}
try {
    mkdir($temp,0700);mkdir($temp.'/public',0700);mkdir($temp.'/private/sessions',0700,true);
    foreach(['app/helpers/session-security.php','app/helpers/auth-return-intent.php','app/controllers/logout.php','app/controllers/google-login.php','app/controllers/new-connection.php','app/views/pages/index.php','app/views/pages/register.php','config/new-connection.php'] as $file)apacheWrite($temp.'/private/'.$file,file_get_contents($root.'/'.$file));
    foreach(['assets/css/login.css','assets/css/register.css','assets/js/login.js','assets/js/register.js','assets/images/logo.png','assets/vendor/bootstrap-icons/bootstrap-icons.min.css'] as $file)if(is_file($root.'/'.$file))apacheWrite($temp.'/public/'.$file,file_get_contents($root.'/'.$file));
    $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);$address=stream_socket_get_name($socket,false);fclose($socket);$port=(int)substr(strrchr($address,':'),1);$origin='http://localhost:'.$port;
    apacheWrite($temp.'/private/config/google.php',"<?php define('GOOGLE_CLIENT_ID','synthetic');define('GOOGLE_CLIENT_SECRET','synthetic');define('GOOGLE_REDIRECT_URI','".$origin."/mportalv2/google-callback.php');");
    $double=file_get_contents(__DIR__.'/auth-return-google-double.php');$double=str_replace('public function setRedirectUri($v){}',"public function setRedirectUri(\$v){file_put_contents(dirname(__DIR__).'/private-redirect.json',json_encode(\$v));}",$double);apacheWrite($temp.'/private/vendor/autoload.php',$double);
    $bootstrap= <<<'PHP'
<?php
if(!in_array($_SERVER['REMOTE_ADDR']??'', ['127.0.0.1','::1'],true)){http_response_code(403);exit;}
ini_set('display_errors','0');ini_set('log_errors','0');session_save_path(__DIR__.'/sessions');
putenv('MCO_NEW_CONNECTION_ENABLED');
require_once __DIR__.'/app/helpers/session-security.php';
$page=basename($_SERVER['SCRIPT_FILENAME']);
if($page==='new-connection.php'){require __DIR__.'/app/controllers/new-connection.php';exit;}
portalSessionStart();
if($page==='probe.php'){
    header('Content-Type: application/json');
    if($_SERVER['REQUEST_METHOD']==='POST'){
        if(!portalAuthCsrfValid($_POST['csrf_token']??null)){http_response_code(419);echo '{}';exit;}
        if(($_POST['action']??'')==='regenerate')portalAuthenticated(['id'=>1,'email'=>'apache@example.invalid','name'=>'Synthetic Apache']);
        if(($_POST['action']??'')==='logout_token')$_SESSION['dashboard_csrf']=bin2hex(random_bytes(32));
    }
    echo json_encode(['csrf'=>portalAuthCsrf(),'id'=>$_SESSION['user_id']??null,'logout_csrf'=>$_SESSION['dashboard_csrf']??null,'sapi'=>PHP_SAPI,'strict'=>ini_get('session.use_strict_mode')]);exit;
}
if($page==='logout.php'){require __DIR__.'/app/controllers/logout.php';exit;}
if($page==='google-login.php'){require __DIR__.'/app/controllers/google-login.php';exit;}
header('Content-Type: text/html; charset=utf-8');
$error='';$success='';$errors=[];$otpError='';$showOtp=isset($_GET['fixture_otp']);$resendSeconds=60;
require __DIR__.'/app/views/pages/'.($page==='register.php'?'register.php':'index.php');
PHP;
    apacheWrite($temp.'/private/bootstrap.php',$bootstrap);
    foreach(['index','register','probe','logout','google-login','new-connection'] as $page)apacheWrite($temp.'/public/'.$page.'.php',"<?php require dirname(__DIR__).'/private/bootstrap.php';");
    $path=str_replace('\\','/',$temp);
    $conf= <<<'CONF'
ServerRoot "C:/xampp/apache"
ServerName localhost
LoadModule authz_core_module modules/mod_authz_core.so
LoadModule authz_host_module modules/mod_authz_host.so
LoadModule dir_module modules/mod_dir.so
LoadModule mime_module modules/mod_mime.so
LoadModule alias_module modules/mod_alias.so
LoadFile "C:/xampp/php/php8ts.dll"
LoadFile "C:/xampp/php/libpq.dll"
LoadFile "C:/xampp/php/libsqlite3.dll"
LoadModule php_module "C:/xampp/php/php8apache2_4.dll"
PHPIniDir "C:/xampp/php"
TypesConfig "C:/xampp/apache/conf/mime.types"
DirectoryIndex index.php
<Directory />
    Require all denied
</Directory>
CONF;
    $conf.="\nListen 127.0.0.1:$port\nPidFile \"$path/private/httpd.pid\"\nErrorLog \"$path/private/httpd-error.log\"\nDocumentRoot \"$path/public\"\nAlias /mportalv2/ \"$path/public/\"\nRedirectMatch 301 ^/mportalv2$ /mportalv2/\n<Directory \"$path/public\">\nRequire local\nAllowOverride None\nOptions -Indexes\n<FilesMatch \\\.php$>\nSetHandler application/x-httpd-php\n</FilesMatch>\n</Directory>\n";
    apacheWrite($temp.'/private/httpd.conf',$conf);
    $process=proc_open(['C:/xampp/apache/bin/httpd.exe','-X','-f',$temp.'/private/httpd.conf'],[0=>['pipe','r'],1=>['file','NUL','a'],2=>['file','NUL','a']],$pipes,'C:/xampp/apache');
    if(!is_resource($process))throw new RuntimeException('Isolated Apache unavailable');fclose($pipes[0]);
    $until=microtime(true)+10;do{$ready=@stream_socket_client('tcp://127.0.0.1:'.$port,$errno,$error,.2);if($ready){fclose($ready);break;}usleep(30000);}while(microtime(true)<$until);
    apacheCheck((bool)$ready,'Separate loopback Apache starts without changing running XAMPP');
    $cookie='';$login=apacheRequest('/mportalv2/',$cookie);
    apacheCheck($login['status']===200&&str_contains($login['body'],'Continue with Google')&&str_contains($login['body'],'name="password"'),'Actual copied login template renders under Apache subdirectory');
    apacheCheck(str_contains($login['body'],'name="csrf_token"')&&str_contains($login['body'],'href="register.php"'),'Login renders existing CSRF field and relative registration link');
    $reg=apacheRequest('/mportalv2/register.php',$cookie);apacheCheck($reg['status']===200&&str_contains($reg['body'],'name="first_name"'),'Actual copied registration template renders under Apache');
    $otp=apacheRequest('/mportalv2/register.php?fixture_otp=1',$cookie);apacheCheck($otp['status']===200&&str_contains($otp['body'],'otp'),'OTP presentation branch renders; no OTP verification/mail performed by this Apache fixture');
    apacheCheck(apacheRequest('/',$cookie)['status']===200,'Dedicated fixture root rendering works');
    $slash=apacheRequest('/mportalv2',$cookie);apacheCheck($slash['status']===301&&str_contains($slash['location'],'/mportalv2/'),'Subdirectory slash redirect preserves application prefix');
    $state=apacheRequest('/mportalv2/probe.php',$cookie);apacheCheck($state['json']['sapi']==='apache2handler','Requests execute actual Apache PHP module');
    apacheCheck($state['json']['strict']==='1','Apache session strict mode enabled');
    $headers=implode('\n',$state['cookies']);apacheCheck(stripos($headers,'HttpOnly')!==false&&stripos($headers,'SameSite=Lax')!==false&&stripos($headers,'; secure')===false,'Apache HTTP session cookie is HttpOnly/Lax without Secure');
    $csrfRejected=apacheRequest('/mportalv2/probe.php',$cookie,['csrf_token'=>'invalid','action'=>'regenerate'],'POST');
    apacheCheck(in_array($csrfRejected['status'],[419,500],true)&&trim($csrfRejected['body'])==='{}','Session-helper CSRF denies invalid token under Apache (wire status '.$csrfRejected['status'].')');
    $compatibilityFailures=[];if($csrfRejected['status']!==419)$compatibilityFailures[]='Expected CSRF status 419, observed Apache wire status '.$csrfRejected['status'];
    $before=$cookie;$authed=apacheRequest('/mportalv2/probe.php',$cookie,['csrf_token'=>$state['json']['csrf'],'action'=>'regenerate'],'POST');
    apacheCheck($authed['json']['id']===1&&$before!==$cookie,'Actual session helper regenerates Apache session using synthetic stand-in identity');
    apacheCheck($authed['json']['csrf']!==$state['json']['csrf'],'Authentication clears pre-authentication CSRF state');
    $token=apacheRequest('/mportalv2/probe.php',$cookie,['csrf_token'=>$authed['json']['csrf'],'action'=>'logout_token'],'POST')['json']['logout_csrf'];
    apacheCheck(apacheRequest('/mportalv2/logout.php',$cookie)['status']===405,'Copied logout controller requires confirmation POST');
    $badLogout=apacheRequest('/mportalv2/logout.php',$cookie,['intent'=>'logout','csrf_token'=>'wrong'],'POST');
    apacheCheck(in_array($badLogout['status'],[419,500],true)&&str_contains($badLogout['body'],'could not be verified'),'Copied logout rejects invalid token under Apache (wire status '.$badLogout['status'].')');
    if($badLogout['status']!==419)$compatibilityFailures[]='Expected logout CSRF status 419, observed Apache wire status '.$badLogout['status'];
    $logout=apacheRequest('/mportalv2/logout.php',$cookie,['intent'=>'logout','csrf_token'=>$token],'POST');apacheCheck($logout['status']===302&&$logout['location']==='index.php','Copied logout uses relative login redirect compatible with subdirectory');
    apacheCheck(apacheRequest('/mportalv2/probe.php',$cookie)['json']['id']===null,'Copied logout destroys synthetic session');
    $google=apacheRequest('/mportalv2/google-login.php',$cookie);apacheCheck($google['status']===302&&str_starts_with($google['location'],'https://provider.example.invalid/'),'Copied Google initiation builds local-double redirect without contacting provider');
    apacheCheck(json_decode(file_get_contents($temp.'/private/private-redirect.json'),true)===$origin.'/mportalv2/google-callback.php','Fixture callback constant retains subdirectory; not inferred from Host');
    $off=apacheRequest('/mportalv2/new-connection.php',$cookie);apacheCheck($off['status']===404&&str_contains($off['body'],'Page unavailable'),'Actual copied module controller denies feature-disabled access before DB bootstrap');
    apacheCheck(apacheRequest('/mportalv2/private/bootstrap.php',$cookie)['status']===404,'Private fixture code inaccessible from public Apache alias');
    apacheCheck(apacheRequest('/mportalv2/assets/css/login.css',$cookie)['status']===200,'Relative local CSS resource resolves under subdirectory');
    // The only request to the existing port-80 portal is its feature gate, whose disabled path has no DB bootstrap.
    $liveCookie='';$actual=apacheRequest('/mportalv2/new-connection.php',$liveCookie,[],'GET','http://localhost');
    apacheCheck($actual['status']===404&&str_contains($actual['body'],'Page unavailable'),'Existing localhost/mportalv2 module is feature-disabled (read-only gate request)');
    $result=['date'=>'2026-10-21','checks'=>count($checks),'results'=>$checks,'compatibility_failures'=>$compatibilityFailures,'deployment_ready'=>$compatibilityFailures===[],'runtime'=>'Apache 2.4.58 / PHP apache2handler','synthetic_only'=>true,'database_connections'=>0,'provider_or_email_requests'=>0,'limitations'=>['No actual portal login/register/recovery controller request; their bootstrap selects actual DB.','Separate Apache configuration, not existing virtual-host end-to-end authentication.','OTP presentation only under Apache; complete journeys are disposable PHP -S regressions.','No real Google callback, HTTPS cookie or visual dialog acceptance.']];
}catch(Throwable $e){fwrite(STDERR,'Apache readiness failed: '.($e instanceof RuntimeException?$e->getMessage():'isolated fixture failure')."\n");$exit=1;}
finally{
    if(is_resource($process)){proc_terminate($process);proc_close($process);}
    $resolved=realpath($temp);if($resolved){if(dirname($resolved)!==realpath(sys_get_temp_dir())||!preg_match('/^mco-apache-[a-f0-9]{16}$/D',basename($resolved)))throw new RuntimeException('Unsafe Apache cleanup');apacheRemove($resolved);}
}
if($exit===0){$result['cleanup']='Separate Apache stopped and temporary resources removed';file_put_contents(__DIR__.'/localhost-apache-readiness-results.json',json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo 'Apache localhost checks completed ('.count($checks).' assertions; '.count($compatibilityFailures)." compatibility failures; no DB/provider/mail).\n";}exit($exit);
