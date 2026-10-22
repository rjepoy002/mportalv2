<?php
if(PHP_SAPI!=='cli-server'||!is_file(__DIR__.'/private/config.php')){http_response_code(404);exit;}
ini_set('display_errors','0');ini_set('log_errors','0');$cfg=require __DIR__.'/private/config.php';
header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: no-referrer');header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self'; frame-ancestors 'none'; base-uri 'none'");
function v2reply(int $code,array $data):never{http_response_code($code);header('Content-Type: application/json');echo json_encode($data,JSON_HEX_TAG|JSON_THROW_ON_ERROR);exit;}
set_exception_handler(static fn(Throwable $e)=>v2reply(503,['message'=>'Fixture unavailable. No details exposed.']));
if(($_SERVER['REMOTE_ADDR']??'')!=='127.0.0.1'||($_SERVER['HTTP_HOST']??'')!==$cfg['host'])v2reply(404,[]);
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);$method=$_SERVER['REQUEST_METHOD'];
if(str_starts_with($path,'/assets/')&&$method==='GET'){$f=realpath(__DIR__.$path);if(!$f||!str_starts_with($f,realpath(__DIR__.'/assets').DIRECTORY_SEPARATOR)||!in_array(pathinfo($f,PATHINFO_EXTENSION),['js','css','png','woff','woff2','svg'],true))v2reply(404,[]);header('Content-Type: '.match(pathinfo($f,PATHINFO_EXTENSION)){'js'=>'text/javascript','css'=>'text/css','png'=>'image/png','svg'=>'image/svg+xml',default=>'font/woff2'});readfile($f);exit;}
if(!in_array($path,['/login','/v2','/api'],true))v2reply(404,[]);
if(($path==='/api'&&$method!=='POST')||($path!=='/api'&&!in_array($method,['GET','HEAD'],true))){header('Allow: '.($path==='/api'?'POST':'GET, HEAD'));v2reply(405,[]);}
require __DIR__.'/app/helpers/session-security.php';require __DIR__.'/app/services/new-connection/applicant-contract-dispatch.php';
session_name('NC_V2_SYNTHETIC');session_save_path(__DIR__.'/private/sessions');portalSessionStart();header('Cache-Control: private, no-store');$csrf=portalAuthCsrf();
if($cfg['persistent']??false)require __DIR__.'/stage1-v2-persistent-api.php';
if($path==='/login'){header('Content-Type: text/html; charset=utf-8');echo '<!doctype html><html lang="en"><title>Synthetic v2 session</title><body><h1>Synthetic only — no real personal information</h1><label for="v2-fixture-login">Synthetic identity</label><select id="v2-fixture-login"><option value="owner-a">Owner A</option><option value="owner-b">Owner B</option><option value="inactive">Inactive</option><option value="unverified">Unverified</option></select><button id="v2-start">Start synthetic profile session</button><script type="application/json" id="login-context">'.json_encode(['csrf'=>$csrf],JSON_HEX_TAG).'</script><script src="/assets/js/stage1-v2-login.js"></script></body></html>';exit;}
if($path==='/api'){
 if(($_SERVER['HTTP_ORIGIN']??'')!=='http://'.$cfg['host']||($_SERVER['HTTP_SEC_FETCH_SITE']??'same-origin')!=='same-origin')v2reply(403,[]);
 if(strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json')v2reply(415,[]);
 $raw=file_get_contents('php://input',false,null,0,98305);if(strlen($raw)>98304)v2reply(413,[]);
 try{$command=json_decode($raw,false,16,JSON_THROW_ON_ERROR);}catch(Throwable $e){v2reply(400,[]);}
 if(!$command instanceof stdClass||array_diff(array_keys(get_object_vars($command)),['csrf','action','version','input','proof','key','expected','reference','confirmed','fixture_login']))v2reply(400,[]);
 if(($cfg['persistent']??false)&&($command->action??null)!=='start'&&!isset($_SESSION['user_id']))v2reply(401,['message'=>'Session expired. Sign in again; saved drafts remain.']);
 if(!portalAuthCsrfValid($command->csrf??null))v2reply(403,[]);
 // Shared per-fixture IP counter, not reset by changing cookies.
 $handle=fopen(__DIR__.'/private/rate','c+');flock($handle,LOCK_EX);$limit=json_decode(stream_get_contents($handle),true)?:['at'=>time(),'n'=>0];if(time()-$limit['at']>=60)$limit=['at'=>time(),'n'=>0];$limit['n']++;ftruncate($handle,0);rewind($handle);fwrite($handle,json_encode($limit));flock($handle,LOCK_UN);fclose($handle);if($limit['n']>240)v2reply(429,['message'=>'Wait before more fixture requests.']);
 if(($command->action??null)==='start'){
    if($cfg['persistent']??false){$names=['owner-a'=>1,'owner-b'=>2,'inactive'=>3,'unverified'=>4];$actor=is_string($command->fixture_login??'owner-a')?($names[$command->fixture_login??'owner-a']??0):0;try{$profile=v2PersistentService($cfg)->profile($actor)['profile'];}catch(NewConnectionDenied $e){v2reply(403,['message'=>'Synthetic sign-in unavailable.']);}portalAuthenticated(['id'=>$actor,'email'=>$profile['email'],'name'=>'Synthetic Reviewer']);v2reply(200,['csrf'=>portalAuthCsrf()]);}
    $context=require __DIR__.'/stage1-v2-data.php';portalAuthenticated(['id'=>1,'email'=>$context['profile']['email'],'name'=>'Synthetic Reviewer']);$_SESSION['v2_context']=$context;$_SESSION['v2_version']=0;v2reply(200,['csrf'=>portalAuthCsrf()]);
 }
}
if($cfg['persistent']??false){
 $actor=$_SESSION['user_id']??null;if(!is_int($actor)){if($path==='/v2'){header('Location: /login');http_response_code(302);exit;}v2reply(401,['message'=>'Session expired. Sign in and list drafts; no automatic replay.']);}
 try{$p=v2PersistentService($cfg)->profile($actor);}catch(NewConnectionDenied $e){v2reply(403,['message'=>'Active verified synthetic owner required.']);}
 if($path==='/v2'){define('NC_V2_FIXTURE',true);$context=require __DIR__.'/stage1-v2-data.php';$context['profile']=$p['profile'];$initial=NewConnectionApplicantContractV2::emptyInput();header('Content-Type: text/html; charset=utf-8');require __DIR__.'/app/views/pages/new-connection-v2-prototype.php';exit;}
 v2PersistentAction($cfg,$command,$actor);
}
if(($_SESSION['user_id']??null)!==1||!isset($_SESSION['v2_context'])){if($path==='/v2'){header('Location: /login');http_response_code(302);exit;}v2reply(401,['message'=>'Session expired. No automatic replay.']);}
$context=$_SESSION['v2_context'];if($context['profile']['status']!=='active'||empty($context['profile']['email_verified_at']))v2reply(403,[]);
if($path==='/v2'){define('NC_V2_FIXTURE',true);$initial=$_SESSION['v2_draft']??NewConnectionApplicantContractV2::emptyInput();$initial['confirmations']['information_confirmed']=false;header('Content-Type: text/html; charset=utf-8');require __DIR__.'/app/views/pages/new-connection-v2-prototype.php';exit;}
$action=$command->action??'';if(!in_array($action,['validate','save','resume','review','check-proof','profile-change'],true))v2reply(404,[]);
if(($command->version??null)!==NewConnectionApplicantContractV2::VERSION)v2reply(409,['message'=>'Explicit v2 version required.']);
if($action==='profile-change'){$_SESSION['v2_context']['profile']['first_name']=$context['profile']['first_name']==='Synthetic'?'Changed':'Synthetic';unset($_SESSION['v2_proof']);if(isset($_SESSION['v2_draft']))$_SESSION['v2_draft']['confirmations']['profile_confirmed']=false;v2reply(200,['profile'=>$_SESSION['v2_context']['profile'],'profile_fingerprint'=>NewConnectionApplicantContractV2::profileFingerprint($_SESSION['v2_context']),'message'=>'Synthetic profile changed. Confirm again.']);}
$input=$action==='resume'?($_SESSION['v2_draft']??NewConnectionApplicantContractV2::emptyInput()):($command->input??null);
// Ignore no fields: contract rejects identity and server metadata overrides.
$json=json_encode($input,JSON_THROW_ON_ERROR);$result=newConnectionValidateContract($command->version,$json,$context,false);
if(!$result['valid'])v2reply(422,$result);
if($action==='save'){
 if(!is_string($command->key??null)||!preg_match('/^[a-f0-9]{64}$/D',$command->key)||!is_int($command->expected??null))v2reply(400,[]);
 $fingerprint=hash('sha256',$json.'|'.$command->expected);$old=$_SESSION['v2_requests'][$command->key]??null;
 if($old){if(!hash_equals($old['hash'],$fingerprint))v2reply(409,['message'=>'Key reused with different content.']);v2reply(200,$old['result']);}
 if($command->expected!==$_SESSION['v2_version'])v2reply(409,['message'=>'Stale session draft version. Resume before saving.']);
 if(count($_SESSION['v2_requests']??[])>=100)v2reply(429,['message'=>'Session receipt capacity reached.']);
 $result['input']['confirmations']['information_confirmed']=false;$_SESSION['v2_draft']=$result['input'];$_SESSION['v2_version']++;unset($_SESSION['v2_proof']);$result['message']='Session-only draft saved; no database persistence.';$result['draft_version']=$_SESSION['v2_version'];$_SESSION['v2_requests'][$command->key]=['hash'=>$fingerprint,'result'=>$result];v2reply(200,$result);
}
if($action==='review'||$action==='check-proof'){
 $candidate=$result['input'];$candidate['confirmations']['information_confirmed']=true;$complete=newConnectionValidateContract($command->version,json_encode($candidate),$context,true);if(!$complete['ready'])v2reply(422,$complete);
 $candidate['confirmations']['information_confirmed']=false;$hash=hash('sha256',NewConnectionApplicantContractV2::canonical($candidate).'|'.NewConnectionApplicantContractV2::profileFingerprint($context).'|'.$context['as_of']);
 if($action==='review'){$proof=bin2hex(random_bytes(32));$_SESSION['v2_proof']=['token'=>$proof,'hash'=>$hash,'expires'=>time()+$cfg['review_ttl']];$result['proof']=$proof;$result['message']='Private synthetic review created. No submission endpoint.';}
 else{$saved=$_SESSION['v2_proof']??null;if(!$result['input']['confirmations']['information_confirmed']||!$saved||$saved['expires']<=time()||!is_string($command->proof??null)||!hash_equals($saved['token'],$command->proof)||!hash_equals($saved['hash'],$hash))v2reply(409,['message'=>'Stale or expired review. Review again.']);$result['message']='Review proof valid. Nothing was submitted.';}
}
$result['draft_version']=$_SESSION['v2_version'];v2reply(200,$result);
