<?php
// Private fixture bootstrap; copied to a temporary root with no actual portal configuration.
if(PHP_SAPI!=='cli-server'||!is_file(__DIR__.'/private/config.json')){http_response_code(404);exit;}
ini_set('display_errors','0');ini_set('log_errors','0');ini_set('error_log',__DIR__.'/private/errors.log');date_default_timezone_set('UTC');
header('Cache-Control: private, no-store');header('Content-Type: application/json');header('Referrer-Policy: no-referrer');header("Content-Security-Policy: default-src 'none'; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'");
function fixtureAuthReply(int $status,array $data): never {http_response_code($status);echo json_encode($data,JSON_THROW_ON_ERROR);exit;}
set_exception_handler(static fn(Throwable $e)=>fixtureAuthReply(503,['message'=>'Synthetic authentication unavailable.']));
$cfg=json_decode(file_get_contents(__DIR__.'/private/config.json'),true,32,JSON_THROW_ON_ERROR);$control=json_decode(file_get_contents(__DIR__.'/private/control.json'),true)?:[];
if(!in_array($_SERVER['HTTP_HOST']??'',$cfg['hosts'],true)||!in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true))fixtureAuthReply(403,[]);
require __DIR__.'/app/helpers/session-security.php';require __DIR__.'/app/helpers/auth-return-intent.php';
session_save_path(__DIR__.'/private/sessions');portalSessionStart(time()+(int)($control['session_offset']??0));
putenv('MCO_AUTH_RATE_DIR='.__DIR__.'/private/auth-limits');putenv('PASSWORD_RESET_RATE_DIR='.__DIR__.'/private/reset-limits');putenv('APP_ENV=test');putenv('APP_URL=http://'.$cfg['host']);
function fixtureAtomicClaim(string $digest,int $expires,int $now): bool {
    global $control;if(!empty($control['claim_failure']))throw new RuntimeException('Synthetic claim infrastructure failure');
    $handle=fopen(__DIR__.'/private/claims.json','c+');if(!$handle||!flock($handle,LOCK_EX))throw new RuntimeException('Claim store unavailable.');
    try{$raw=stream_get_contents($handle);$claims=$raw===''?[]:json_decode($raw,true,32,JSON_THROW_ON_ERROR);if(!is_array($claims))throw new RuntimeException('Invalid claim state.');foreach($claims as $key=>$until)if($until<=$now)unset($claims[$key]);if(isset($claims[$digest]))return false;if(count($claims)>=10000)throw new RuntimeException('Claim capacity.');$claims[$digest]=$expires;$encoded=json_encode($claims,JSON_THROW_ON_ERROR);rewind($handle);if(!ftruncate($handle,0)||fwrite($handle,$encoded)!==strlen($encoded)||!fflush($handle))throw new RuntimeException('Claim write failed.');return true;}finally{flock($handle,LOCK_UN);fclose($handle);}
}
$intent=new PortalAuthReturnIntent($control['canonical']??'http://'.$cfg['host'],['environment'=>'isolated-auth-test','environment_permitted'=>($control['environment']??true),'feature_enabled'=>($control['feature']??true)],'fixtureAtomicClaim');
function fixtureAuthFlow(): mixed {return $_POST['auth_flow']??$_GET['auth_flow']??null;}
function fixtureAuthNow(): int {global $control;return time()+(int)($control['intent_offset']??0);}
function fixtureAuthCapture(bool $google=false): array {global $intent,$fixtureOAuthFlow;return $intent->take($_SESSION,$google?($fixtureOAuthFlow??null):fixtureAuthFlow(),fixtureAuthNow());}
function fixtureAuthTarget(array $outcome): string {global $intent;return $intent->target($outcome);}
function fixtureAuthSignIn(string $result): string {global $intent;$flow=fixtureAuthFlow();$valid=$intent->match($_SESSION,$flow,fixtureAuthNow());return '/login?'.$result.($valid['status']==='module'?'&auth_flow='.$flow:'');}
function fixtureOAuthValid(mixed $state): bool {
    global $fixtureOAuthFlow;$pending=$_SESSION['oauth_pending']??null;$fixtureOAuthFlow=null;
    $ok=portalOAuthStateValid($state);if($ok&&is_array($pending))$fixtureOAuthFlow=$pending['return_flow']??null;
    return $ok;
}
function fixtureRecoveryFlow(): mixed {return $_SESSION['fixture_recovery_flow']??null;}
function fixtureRecoveryReturn(): string {global $intent;$flow=fixtureRecoveryFlow();return '/login?reset=success'.($intent->match($_SESSION,$flow,fixtureAuthNow())['status']==='module'?'&auth_flow='.$flow:'');}
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$routes=['/login'=>'index.php','/register'=>'register.php','/oauth'=>'google-login.php','/callback'=>'google-callback.php','/forgot'=>'forgot-password.php','/reset'=>'reset-password.php','/logout'=>'logout.php'];
if($path==='/session')fixtureAuthReply(200,['csrf'=>portalAuthCsrf(),'recovery_csrf'=>$_SESSION['recovery_csrf']??null,'logout_csrf'=>$_SESSION['dashboard_csrf']??null,'id'=>$_SESSION['user_id']??null,'keys'=>array_keys($_SESSION)]);
if($path==='/begin'){
    if($_SERVER['REQUEST_METHOD']!=='POST'||!portalAuthCsrfValid($_POST['csrf_token']??null))fixtureAuthReply(419,[]);
    fixtureAuthReply(200,['flow'=>$intent->begin($_SESSION,PortalAuthReturnIntent::DESTINATION,fixtureAuthNow())]);
}
if(in_array($path,['/module','/owned'],true)){
    require __DIR__.'/config/database.php';require __DIR__.'/app/middleware/new-connection-auth.php';
    try{$user=newConnectionApplicant($_SESSION,static fn()=>$pdo);}catch(Throwable $e){fixtureAuthReply($e->getCode()===401?401:403,[]);}
    if($path==='/owned'){$id=filter_var($_GET['record']??null,FILTER_VALIDATE_INT);$q=$pdo->prepare('SELECT id FROM fixture_owned_records WHERE id=? AND owner_user_id=?');$q->execute([$id,$user['id']]);if(!$q->fetch())fixtureAuthReply(403,[]);}
    fixtureAuthReply(200,['user_id'=>$user['id'],'verified'=>true]);
}
if(!isset($routes[$path]))fixtureAuthReply(404,[]);
if($path==='/forgot'&&$_SERVER['REQUEST_METHOD']==='POST'&&is_string($_POST['auth_flow']??null)&&isset($_SESSION['recovery_csrf'])&&hash_equals($_SESSION['recovery_csrf'],(string)($_POST['csrf_token']??''))&&$intent->match($_SESSION,$_POST['auth_flow'],fixtureAuthNow())['status']==='module')$_SESSION['fixture_recovery_flow']=$_POST['auth_flow'];
require __DIR__.'/app/controllers/'.$routes[$path];
