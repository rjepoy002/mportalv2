<?php
// Executable ONLY in the generated loopback fixture. No fallback/static PHP serving.
if(PHP_SAPI!=='cli-server'||!is_file(__DIR__.'/private/fixture.json')){http_response_code(404);exit;}
ini_set('display_errors','0');ini_set('log_errors','0');
set_exception_handler(static function(Throwable $e): void {fixtureReply(503,['message'=>'Service temporarily unavailable. No details are exposed.']);});
$cfg=json_decode(file_get_contents(__DIR__.'/private/fixture.json'),true,32,JSON_THROW_ON_ERROR);
header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');
$nonce=base64_encode(random_bytes(16));header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-$nonce'; style-src 'self'; img-src 'self'; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
function fixtureReply(int $status,array $body): never {http_response_code($status);header('Cache-Control: private, no-store');header('Content-Type: application/json; charset=utf-8');echo json_encode($body,JSON_HEX_TAG|JSON_THROW_ON_ERROR);exit;}
if(!in_array($_SERVER['REMOTE_ADDR']??'', ['127.0.0.1','::1'],true)||!in_array($_SERVER['HTTP_HOST']??'',$cfg['hosts'],true))fixtureReply(403,['message'=>'Request denied.']);
$origin='http://'.$_SERVER['HTTP_HOST'];$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(str_starts_with($path,'/assets/')){
    $f=realpath(__DIR__.$path);if(!$f||!str_starts_with($f,realpath(__DIR__.'/assets').DIRECTORY_SEPARATOR)||!is_file($f)||!in_array(pathinfo($f,PATHINFO_EXTENSION),['css','js','png','woff','woff2','svg'],true))fixtureReply(404,['message'=>'Resource unavailable.']);
    header('Content-Type: '.match(pathinfo($f,PATHINFO_EXTENSION)){'css'=>'text/css','js'=>'text/javascript','png'=>'image/png','svg'=>'image/svg+xml','woff2'=>'font/woff2','woff'=>'font/woff'});readfile($f);exit;
}
$methods=['/login'=>'GET','/app'=>'GET','/api/login'=>'POST','/api/create'=>'POST','/api/applications'=>'GET','/api/catalog'=>'GET','/api/draft'=>'GET','/api/receipt'=>'GET','/api/save'=>'POST','/api/review'=>'POST','/api/submit'=>'POST','/api/validate'=>'POST'];
if(!isset($methods[$path]))fixtureReply(404,['message'=>'Resource unavailable.']);
// IP/route limits live outside sessions, preventing cookie rotation from resetting them.
function fixtureLimit(string $route,string $kind,bool $charge=true): bool
{
    $file=__DIR__.'/private/limits.json';$handle=fopen($file,'c+');flock($handle,LOCK_EX);$all=json_decode(stream_get_contents($handle),true)?:[];
    $key=hash('sha256',($_SERVER['REMOTE_ADDR']??'').$route.$kind);$now=time();$entry=$all[$key]??['start'=>$now,'count'=>0];
    if($now-$entry['start']>=60)$entry=['start'=>$now,'count'=>0];
    $limit=$kind==='invalid'?12:($kind==='read'?60:200);$allowed=$entry['count']<$limit;if($charge)$entry['count']++;$all[$key]=$entry;
    rewind($handle);ftruncate($handle,0);fwrite($handle,json_encode($all));flock($handle,LOCK_UN);fclose($handle);return $allowed;
}
function fixtureError(int $code,string $message,array $extra=[]): never {global $path;fixtureLimit($path,'invalid');fixtureReply($code,['message'=>$message]+$extra);}
if(!fixtureLimit($path,'invalid',false)||!fixtureLimit($path,$methods[$path]==='GET'?'read':'request')){header('Retry-After: 60');fixtureReply(429,['message'=>'Too many requests. Try later.']);}
if($_SERVER['REQUEST_METHOD']!==$methods[$path]){header('Allow: '.$methods[$path]);fixtureError(405,'Method not allowed.');}
if(!in_array($_SERVER['HTTP_SEC_FETCH_SITE']??'',['','same-origin','none'],true)||(isset($_SERVER['HTTP_ORIGIN'])&&$_SERVER['HTTP_ORIGIN']!==$origin)||($methods[$path]==='POST'&&($_SERVER['HTTP_ORIGIN']??'')!==$origin))fixtureError(403,'Request origin denied.');
if($methods[$path]==='POST'&&!preg_match('/^application\/json(?:\s*;\s*charset=utf-8)?$/i',$_SERVER['CONTENT_TYPE']??''))fixtureError(415,'JSON content type required.');
require __DIR__.'/app/helpers/session-security.php';session_name('NC_HTTP_FIXTURE');session_save_path(__DIR__.'/private/sessions');$control=json_decode(file_get_contents(__DIR__.'/private/control.json'),true)?:[];portalSessionStart(time()+(int)($control['session_clock_offset']??0));
$csrf=portalAuthCsrf();if(str_starts_with($path,'/api/')&&$path!=='/api/login'&&empty($_SESSION['user_id']))fixtureError(401,'Sign in again to recover the saved draft.');
$command=null;
if($methods[$path]==='POST'){
    if((int)($_SERVER['CONTENT_LENGTH']??0)>98304)fixtureError(413,'Request too large.');
    $body=file_get_contents('php://input',false,null,0,98305);if(strlen($body)>98304)fixtureError(413,'Request too large.');
    try{$command=json_decode($body,false,32,JSON_THROW_ON_ERROR);}catch(Throwable $e){fixtureError(400,'Malformed JSON.');}
    if(!$command instanceof stdClass)fixtureError(400,'JSON object required.');
    if(!portalAuthCsrfValid($command->csrf??null))fixtureError(419,'Request token expired.');
}
function fixtureKeys(object $command,array $keys): void {$actual=array_keys(get_object_vars($command));$keys[]='csrf';sort($actual);sort($keys);if($actual!==$keys)fixtureError(400,'Unexpected command fields.');}
function fixtureReference(mixed $r): string {if(!is_string($r)||!preg_match('/^[a-f0-9]{32}$/D',$r))fixtureError(400,'Invalid reference.');return $r;}
try{
    if($path==='/login'){
        header('Content-Type: text/html; charset=utf-8');$return=isset($_GET['r'])?fixtureReference($_GET['r']):'';
        echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Synthetic fixture sign-in</title><link rel="stylesheet" href="/assets/css/portal-utilities.css"><link rel="stylesheet" href="/assets/css/new-connection-prototype.css"><body><main class="proto-content"><h1>Synthetic test sign-in only</h1><p>No actual portal login or customer accounts.</p><form id="fixture-login"><label for="login">Test login</label><input id="login" value="demo-a"><label for="password">Fixture password</label><input id="password" type="password" value="fixture-only"><button>Sign in to fixture</button></form><p id="login-error" role="alert"></p></main><script id="login-context" type="application/json">'.json_encode(['csrf'=>$csrf,'reference'=>$return],JSON_HEX_TAG).'</script><script src="/assets/js/stage1-http-login.js"></script></body></html>';exit;
    }
    require __DIR__.'/draft-test-support.php'; // This copied fixture support allows cli-server below.
    require __DIR__.'/app/services/new-connection/submission.php';
    $control=json_decode(file_get_contents(__DIR__.'/private/control.json'),true)?:[];
    $policy=empty($cfg['governed'])?require __DIR__.'/submission-test-policy.php':null;
    if($policy!==null&&isset($control['notice_version']))$policy['notices']['privacy_notice']['version']=$control['notice_version'];
    if($policy!==null&&!empty($control['withdraw_notice']))$policy['notices']['privacy_notice']['published']=false;
    $db=draftTestConnection($cfg['schema']);
    if($path==='/api/login'){
        fixtureKeys($command,['login','password']);
        $identities=['demo-a'=>1,'demo-b'=>2,'demo-c'=>3,'other'=>4,'inactive'=>5,'unverified'=>6];
        $id=is_string($command->login??null)?($identities[$command->login]??0):0;
        if(($command->password??null)!=='fixture-only')$id=0;
        $q=$db->prepare('SELECT id,email,status,email_verified_at FROM users WHERE id=?');$q->execute([$id]);$user=$q->fetch();
        if(!$user||$user['status']!=='active'||empty($user['email_verified_at']))fixtureError(403,'Sign-in unsuccessful.');
        portalAuthenticated($user+['name'=>'Synthetic Applicant']);$csrf=portalAuthCsrf();if(str_starts_with($path,'/api/')&&$path!=='/api/login'&&empty($_SESSION['user_id']))fixtureError(401,'Sign in again to recover the saved draft.');fixtureReply(200,['csrf'=>$csrf]);
    }
    // Use the existing session helper's actual expiry rules, not a browser-supplied identity.
    $id=filter_var($_SESSION['user_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
    if($id===false)fixtureError(401,'Sign in again to recover the saved draft.');
    $q=$db->prepare('SELECT id,email,status,email_verified_at FROM users WHERE id=?');$q->execute([$id]);$user=$q->fetch();
    if(!$user||$user['status']!=='active'||empty($user['email_verified_at']))fixtureError(403,'Active verified test identity required.');
    if($path==='/app'){
        header('Content-Type: text/html; charset=utf-8');$reference=isset($_GET['r'])?fixtureReference($_GET['r']):null;
        $context=require __DIR__.'/prototype-data.php';if(!empty($cfg['governed'])){$g=new NewConnectionPublication($db,$cfg['schema']);$context['catalog']=$g()['catalog'];}$context['email']=$user['email'];$initial=NewConnectionApplicantContract::emptyInput();$initial['applicant']=array_replace($initial['applicant'],$context['profile']);
        $expires=min($_SESSION['portal_last_seen']+1800,$_SESSION['portal_created_at']+43200);define('NC_PROTOTYPE_FIXTURE',true);
        require __DIR__.'/stage1-http-page.php';exit;
    }
    // Release the PHP session lock BEFORE DB operations so parallel requests really race.
    session_write_close();
    if(!empty($control['force_failure']))throw new RuntimeException('Synthetic private infrastructure detail');
    $provider=empty($cfg['governed'])?static fn()=>$policy:new NewConnectionPublication($db,$cfg['schema']);
    $keys=base64_decode($cfg['review_key']);
    if(!empty($cfg['governed'])){
        $mode=$control['key_mode']??'current';$k1=base64_decode($cfg['review_key']);$k2=base64_decode($cfg['review_key2']);
        $keys=match($mode){'previous'=>new NewConnectionReviewKeys('k2',['k1'=>$k1,'k2'=>$k2],[],0,['k1'=>['issued_through'=>$control['cutover'],'verify_until'=>$control['cutover']+300]]),'revoked'=>new NewConnectionReviewKeys('k2',['k1'=>$k1,'k2'=>$k2],['k1']),'unknown'=>new NewConnectionReviewKeys('k2',['k2'=>$k2]),default=>new NewConnectionReviewKeys('k1',['k1'=>$k1])};
    }
    $service=new NewConnectionStage1Submission($db,$cfg['schema'],$provider,$keys,static fn()=>time()+(int)($control['proof_clock_offset']??0));
    if($path==='/api/applications'){
        if($_GET)fixtureError(400,'No query fields allowed.');
        $q=$db->prepare('SELECT public_reference AS reference,lifecycle_status AS status FROM new_connection_applications WHERE applicant_user_id=? ORDER BY id DESC LIMIT 20');$q->execute([$id]);fixtureReply(200,['applications'=>$q->fetchAll()]);
    }
    if($path==='/api/catalog'){
        if($_GET||empty($cfg['governed']))fixtureError(404,'Resource unavailable.');
        $db->beginTransaction();try{$published=$provider();$db->commit();}catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
        fixtureReply(200,['catalog'=>$published['catalog']]);
    }
    if(in_array($path,['/api/draft','/api/receipt'],true)){
        if(array_keys($_GET)!==['reference'])fixtureError(400,'Reference query required.');$r=fixtureReference($_GET['reference']);
        fixtureReply(200,$path==='/api/draft'?$service->retrieve($id,$r):$service->submissionReceipt($id,$r));
    }
    $keys=match($path){'/api/create'=>['key'],'/api/save'=>['reference','expected','key','input'],'/api/review'=>['reference','expected'],'/api/submit'=>['reference','expected','key','proof','selections'],'/api/validate'=>['input','complete']};fixtureKeys($command,$keys);
    if($path==='/api/validate'){
        if(!is_bool($command->complete))fixtureError(400,'Invalid validation mode.');
        $r=NewConnectionApplicantContract::validate(json_encode($command->input,JSON_THROW_ON_ERROR),['email'=>$user['email'],'catalog'=>empty($cfg['governed'])?$policy['catalog']:$provider()['catalog']],$command->complete);fixtureReply(200,$r);
    }
    if(isset($command->key)&&(!is_string($command->key)||!preg_match('/^[a-f0-9]{64}$/D',$command->key)))fixtureError(400,'Invalid command key.');
    if($path==='/api/create')fixtureReply(200,$service->create($id,$command->key));
    $r=fixtureReference($command->reference);if(!$command->expected instanceof stdClass)fixtureError(400,'Invalid versions.');$expected=get_object_vars($command->expected);
    if($path==='/api/save')fixtureReply(200,$service->save($id,$r,$expected,$command->key,json_encode($command->input,JSON_THROW_ON_ERROR)));
    if($path==='/api/review'){
        $result=$service->review($id,$r,$expected);
        if(!empty($cfg['governed'])){
            // Labels must come from the exact catalog digest bound by this server-issued proof.
            // If a publication changed between these reads, require a new review instead.
            $published=$provider();$envelope=json_decode(base64_decode(strtr(explode('.',$result['proof'])[0],'-_','+/')),true,32,JSON_THROW_ON_ERROR);
            if(!hash_equals($envelope['binding']['catalog_sha256'],hash('sha256',NewConnectionApplicantContract::canonical($published['catalog']))))throw new NewConnectionConflict('Catalog changed during review.');
            $result['catalog']=$published['catalog'];
        }
        fixtureReply(200,$result);
    }
    if(!is_string($command->proof))fixtureError(400,'Invalid review proof.');
    fixtureReply(200,$service->submit($id,$r,$expected,$command->key,$command->proof,json_encode($command->selections,JSON_THROW_ON_ERROR)));
}catch(NewConnectionDraftInvalid $e){fixtureError(422,'Please correct the highlighted fields.',['errors'=>$e->errors]);}
catch(NewConnectionConflict $e){fixtureError(409,'Saved data or review changed. Resume and review again.');}
catch(NewConnectionDraftLimited $e){fixtureError(429,'Too many saved changes. Try later.');}
catch(NewConnectionDenied $e){fixtureError(403,'Requested operation unavailable for this identity or application.');}
catch(InvalidArgumentException $e){fixtureError(400,'Invalid command.');}
catch(Throwable $e){fixtureError(503,'Service temporarily unavailable. No details are exposed.');}
