<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/google-policy-proposal.php';
$root=dirname(__DIR__,2);$temp=sys_get_temp_dir().'/mco-google-review-'.bin2hex(random_bytes(8));mkdir($temp,0700);$process=null;$checks=[];$cases=[];$exit=0;
function googleCheck(bool $ok,string $label): void {global $checks;if(!$ok)throw new RuntimeException($label);$checks[]=$label;}
function googleWrite(string $path,string $text): void {if(!is_dir(dirname($path)))mkdir(dirname($path),0700,true);file_put_contents($path,$text);}
function googleRequest(string $path,string &$cookie): array {
    global $host;$body=file_get_contents('http://'.$host.$path,false,stream_context_create(['http'=>['ignore_errors'=>true,'follow_location'=>0,'timeout'=>5,'header'=>'Cookie: PHPSESSID='.$cookie]]));$headers=$http_response_header;$status=0;$location='';
    foreach($headers as $h){if(preg_match('/^HTTP\/\S+ (\d+)/',$h,$m))$status=(int)$m[1];if(preg_match('/^Set-Cookie: PHPSESSID=([^;]+)/i',$h,$m))$cookie=$m[1];if(preg_match('/^Location: (.*)$/i',$h,$m))$location=$m[1];}
    return ['status'=>$status,'location'=>$location,'body'=>$body];
}
function googleRemove(string $path): void {foreach(new FilesystemIterator($path) as $f){if($f->isDir())googleRemove($f->getPathname());elseif(!@unlink($f->getPathname()))throw new RuntimeException('Review artifact busy');}if(!@rmdir($path))throw new RuntimeException('Review directory busy');}
try{
    foreach(['app/helpers/session-security.php','app/controllers/google-login.php','app/controllers/google-callback.php'] as $file)googleWrite($temp.'/'.$file,file_get_contents($root.'/'.$file));
    googleWrite($temp.'/router.php',file_get_contents(__DIR__.'/google-verification-review-fixture.php'));
    googleWrite($temp.'/config/google.php',"<?php define('GOOGLE_CLIENT_ID','synthetic-only');define('GOOGLE_CLIENT_SECRET','synthetic-only');define('GOOGLE_REDIRECT_URI','https://callback.example.invalid');");
    googleWrite($temp.'/config/database.php', <<<'PHP'
<?php
// SQL spy only. Constructor never calls PDO::__construct or opens any connection.
final class GoogleReviewPDO extends PDO {
    public function __construct(){}
    public function prepare(string $query,array $options=[]): PDOStatement|false {return new GoogleReviewStatement($query);}
    public function lastInsertId(?string $name=null): string|false {return '9';}
}
final class GoogleReviewStatement extends PDOStatement {
    public function __construct(private string $query){}
    public function execute(?array $params=null): bool {
        global $spy,$plan;
        if(str_contains($this->query,'SELECT')){$spy['selects']++;return true;}
        if(str_contains($this->query,'UPDATE users')){
            $spy['would_link']=true;$spy['would_fill_portal_verification']=empty($plan['portal']['email_verified_at']);
        }elseif(str_contains($this->query,'INSERT INTO users')){$spy['would_insert_verified_user']=true;}
        else throw new LogicException('Unexpected SQL outside review scope');
        if(!empty($plan['write_error'])){$spy['write_failed']=true;throw new PDOException('Synthetic constraint conflict');}
        return true;
    }
    public function fetch(int $mode=PDO::FETCH_DEFAULT,int $cursorOrientation=PDO::FETCH_ORI_NEXT,int $cursorOffset=0): mixed {
        global $plan;if(str_contains($this->query,'WHERE id ='))return $plan['new_result'];return $plan['portal']??false;
    }
}
$pdo=new GoogleReviewPDO();
PHP);
    googleWrite($temp.'/vendor/autoload.php', <<<'PHP'
<?php
namespace Google;
class Client {
    private string $state='';
    public function setClientId($v){}public function setClientSecret($v){}public function setRedirectUri($v){}public function addScope($v){}public function setPrompt($v){}public function setAccessToken($v){}
    public function setState($v){$this->state=$v;}
    public function createAuthUrl(){return 'https://provider.example.invalid/oauth?state='.$this->state;}
    public function fetchAccessTokenWithAuthCode($v){$GLOBALS['spy']['provider_exchanges']++;return !empty($GLOBALS['plan']['token_error'])?['error'=>'synthetic']:['access_token'=>'synthetic'];}
}
namespace Google\Service;
class Oauth2 {public object $userinfo;public function __construct($client){$this->userinfo=new class{public function get(){return (object)$GLOBALS['plan']['provider'];}};}}
PHP);
    mkdir($temp.'/private/sessions',0700,true);$socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);$host=stream_socket_get_name($socket,false);fclose($socket);
    $provider=['id'=>'subject-a','email'=>'synthetic@example.invalid','name'=>'Synthetic','verified_email'=>true,'hd'=>null];
    $portal=['id'=>1,'name'=>'Synthetic','email'=>$provider['email'],'google_id'=>'subject-a','status'=>'active','email_verified_at'=>'2026-10-21 00:00:00'];
    $base=['host'=>$host,'provider'=>$provider,'portal'=>$portal,'new_result'=>array_replace($portal,['id'=>9]),'write_error'=>false];
    googleWrite($temp.'/private/plan.json',json_encode($base));
    $env=[];foreach(['SystemRoot','SYSTEMROOT','WINDIR','ComSpec','PATH','TEMP','TMP'] as $key){$v=getenv($key);if($v!==false)$env[$key]=$v;}
    $process=proc_open([PHP_BINARY,'-d','display_errors=0','-d','log_errors=0','-S',$host,$temp.'/router.php'],[0=>['pipe','r'],1=>['file','NUL','a'],2=>['file','NUL','a']],$pipes,$temp,$env);if(!is_resource($process))throw new RuntimeException('Review HTTP unavailable');fclose($pipes[0]);$until=microtime(true)+8;do{$ready=@stream_socket_client('tcp://'.$host,$errno,$error,.2);if($ready){fclose($ready);break;}usleep(20000);}while(microtime(true)<$until);googleCheck((bool)$ready,'Isolated memory-only fixture starts');
    // id, fixed portal row, provider overrides, current [session,link,fill,insert], proposed decision, controls
    $matrix=[
        ['bound_verified',$portal,[],[true,false,false,false],'authenticate',[]],
        ['bound_unverified',array_replace($portal,['email_verified_at'=>null]),[],[true,false,false,false],'portal_verification_required',[]],
        ['bound_gmail_unverified',array_replace($portal,['email_verified_at'=>null]),['email'=>'synthetic-specimen@gmail.com'],[true,false,false,false],'portal_verification_required',[]],
        ['unbound_gmail_verified',array_replace($portal,['google_id'=>null]),['email'=>'synthetic-specimen@gmail.com'],[true,true,false,false],'explicit_portal_link_required',[]],
        ['unbound_gmail_unverified',array_replace($portal,['google_id'=>null,'email_verified_at'=>null]),['email'=>'synthetic-specimen@gmail.com'],[true,true,true,false],'portal_verification_required',[]],
        ['unbound_hosted_verified',array_replace($portal,['google_id'=>null]),['hd'=>'tenant.example.invalid'],[true,true,false,false],'explicit_portal_link_required',[]],
        ['unbound_hosted_unverified',array_replace($portal,['google_id'=>null,'email_verified_at'=>null]),['hd'=>'tenant.example.invalid'],[true,true,true,false],'portal_verification_required',[]],
        ['unbound_third_party_verified',array_replace($portal,['google_id'=>null]),[],[false,false,false,false],'explicit_portal_link_required',[]],
        ['unbound_third_party_unverified',array_replace($portal,['google_id'=>null,'email_verified_at'=>null]),[],[false,false,false,false],'portal_verification_required',[]],
        ['hd_not_domain_matched',array_replace($portal,['google_id'=>null]),['hd'=>'unrelated.example.invalid'],[true,true,false,false],'explicit_portal_link_required',[]],
        ['hd_whitespace_truthy',array_replace($portal,['google_id'=>null]),['hd'=>' '],[true,true,false,false],'explicit_portal_link_required',[]],
        ['hd_empty',array_replace($portal,['google_id'=>null]),['hd'=>''],[false,false,false,false],'explicit_portal_link_required',[]],
        ['binding_conflict',array_replace($portal,['google_id'=>'different-subject']),[],[false,false,false,false],'binding_conflict',[]],
        ['bound_suspended',array_replace($portal,['status'=>'suspended']),[],[false,false,false,false],'deny_status',[]],
        ['bound_inactive',array_replace($portal,['status'=>'inactive']),[],[false,false,false,false],'deny_status',[]],
        ['unbound_inactive_gmail',array_replace($portal,['status'=>'inactive','google_id'=>null]),['email'=>'synthetic-specimen@gmail.com'],[false,false,false,false],'deny_status',[]],
        ['google_email_false',$portal,['verified_email'=>false],[false,false,false,false],'deny_provider',[]],
        ['google_email_string_true',$portal,['verified_email'=>'true'],[false,false,false,false],'deny_provider',[]],
        ['google_email_integer_true',$portal,['verified_email'=>1],[false,false,false,false],'deny_provider',[]],
        ['google_subject_empty',$portal,['id'=>''],[false,false,false,false],'deny_provider',[]],
        ['google_email_malformed',$portal,['email'=>'not-an-email'],[false,false,false,false],'deny_provider',[]],
        ['new_gmail',null,['email'=>'synthetic-specimen@gmail.com'],[true,false,false,true],'portal_enrollment_required',[]],
        ['new_hosted',null,['hd'=>'tenant.example.invalid'],[true,false,false,true],'portal_enrollment_required',[]],
        ['new_third_party',null,[],[true,false,false,true],'portal_enrollment_required',[]],
        ['new_user_rechecked_inactive',null,[],[false,false,false,true],'portal_enrollment_required',['new_result_status'=>'inactive']],
        ['new_google_email_false',null,['verified_email'=>false],[false,false,false,false],'deny_provider',[]],
        ['new_unique_constraint_failure',null,[],[false,false,false,true],'portal_enrollment_required',['write_error'=>true]],
        ['bound_subject_changed_email_unique_failure',null,['email'=>'changed@example.invalid'],[false,false,false,true],'portal_enrollment_required',['write_error'=>true]],
        ['link_write_failure',array_replace($portal,['google_id'=>null]),['hd'=>'tenant.example.invalid'],[false,true,false,false],'explicit_portal_link_required',['write_error'=>true]],
        ['token_exchange_failure',$portal,[],[false,false,false,false],'authenticate',['token_error'=>true]],
        ['oauth_state_mismatch',$portal,[],[false,false,false,false],'authenticate',['state_mode'=>'mismatch']],
        ['oauth_state_expired',$portal,[],[false,false,false,false],'authenticate',['state_mode'=>'expired']],
        ['callback_code_missing',$portal,[],[false,false,false,false],'authenticate',['state_mode'=>'missing_code']],
        ['callback_provider_cancel',$portal,[],[false,false,false,false],'authenticate',['state_mode'=>'cancel']],
        ['callback_code_array',$portal,[],[false,false,false,false],'authenticate',['state_mode'=>'array_code']],
        ['callback_state_missing',$portal,[],[false,false,false,false],'authenticate',['state_mode'=>'missing_state']],
    ];
    foreach($matrix as [$id,$row,$overrides,$expect,$proposed,$controls]){
        $p=array_replace($provider,$overrides);if($row!==null)$row['email']=$p['email'];$plan=array_replace($base,$controls,['portal'=>$row,'provider'=>$p,'new_result'=>array_replace($base['new_result'],['email'=>$p['email'],'status'=>$controls['new_result_status']??'active'])]);
        googleWrite($temp.'/private/plan.json',json_encode($plan,JSON_THROW_ON_ERROR));$cookie='';$start=googleRequest('/login',$cookie);parse_str(parse_url($start['location'],PHP_URL_QUERY),$args);$state=$args['state'];$mode=$controls['state_mode']??'';
        if($mode==='expired'){session_save_path($temp.'/private/sessions');session_id($cookie);session_start();$_SESSION['oauth_pending']['created']=time()-600;session_write_close();}
        $query='?state='.($mode==='mismatch'?'forged':$state).($mode==='missing_code'?'':($mode==='array_code'?'&code[]=synthetic':'&code=synthetic')).($mode==='cancel'?'&error=access_denied':'');if($mode==='missing_state')$query='?code=synthetic';
        $response=googleRequest('/callback'.$query,$cookie);$current=json_decode(file_get_contents($temp.'/private/summary.json'),true,32,JSON_THROW_ON_ERROR);
        $observed=[$current['session_authenticated'],$current['would_link'],$current['would_fill_portal_verification'],$current['would_insert_verified_user']];googleCheck($observed===$expect,'Current callback branch '.$id);googleCheck($current['fixed_input_unchanged'],'Immutable spy inputs '.$id);
        googleCheck(proposedGoogleDecision($p,$row)===$proposed,'Proposed decision model '.$id);
        $pipeline=proposedGooglePipeline($mode==='',empty($controls['token_error']),$p,$row);googleCheck($pipeline===($mode!==''||!empty($controls['token_error'])?'deny_oauth_evidence':$proposed),'Proposed pipeline evidence gate '.$id);
        if($mode!=='')googleCheck($current['provider_exchanges']===0&&$response['status']===400,'Early state/code/error gate '.$id);
        if($expect[0])googleCheck($response['location']==='dashboard.php','Current ordinary redirect '.$id);
        googleCheck(!$current['session_has_oauth_pending'],'One-use state consumed '.$id);
        $cases[]=['case'=>$id,'current'=>$current,'current_http_status'=>$response['status'],'proposed_identity_decision_if_trusted'=>$proposed,'proposed_pipeline_decision'=>$pipeline];
        if($id==='bound_verified'){$replay=googleRequest('/callback?state='.$state.'&code=synthetic',$cookie);$replayState=json_decode(file_get_contents($temp.'/private/summary.json'),true);googleCheck($replay['status']===400&&$replayState['provider_exchanges']===0,'Actual state replay rejected before provider');}
    }
    $unbound=array_replace($portal,['google_id'=>null]);googleCheck(proposedGoogleDecision($provider,$unbound,null,true,'link')==='link_after_portal_proof','Proposed explicit link only with fresh portal proof');googleCheck(proposedGoogleDecision($provider,array_replace($unbound,['email_verified_at'=>null]),null,true,'link')==='portal_verification_required','Proof does not fill portal verification');
    $owner=array_replace($portal,['id'=>2]);googleCheck(proposedGoogleDecision($provider,$portal,$owner,true,'link')==='binding_conflict','Proposed subject belongs to another owner denies merge');googleCheck(proposedGoogleDecision(array_replace($provider,['email'=>'changed@example.invalid']),null,$owner)==='portal_email_review_required','Proposed bound subject with changed email requires portal review, not merge');googleCheck(proposedGoogleDecision($provider,$unbound,null,true,'unknown')==='deny_purpose','Proposed unknown OAuth purpose denied');
    googleCheck(hash_file('sha256',$temp.'/app/controllers/google-callback.php')===hash_file('sha256',$root.'/app/controllers/google-callback.php'),'Copied current callback is byte-identical');
    $result=['date'=>'2026-10-21','checks'=>count($checks),'results'=>$checks,'current_cases'=>$cases,'source_callback_sha256'=>hash_file('sha256',$root.'/app/controllers/google-callback.php'),'actual_db_connections'=>0,'actual_sql_writes'=>0,'verification_state_changes'=>0,'provider_network_requests'=>0,'current_execution'=>'Unchanged callback with immutable PDO SQL spy and provider double','proposed_execution'=>'Separate unapproved pure decision model; no session or write effects','cleanup'=>'pending'];
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");$exit=1;}
finally{
    if(is_resource($process)){proc_terminate($process);proc_close($process);}
    $resolved=realpath($temp);if(!$resolved||dirname($resolved)!==realpath(sys_get_temp_dir())||!preg_match('/^mco-google-review-[a-f0-9]{16}$/D',basename($resolved)))throw new RuntimeException('Unsafe review cleanup');for($i=0;$i<4;$i++){try{googleRemove($resolved);break;}catch(Throwable $e){if($i===3)throw $e;usleep(200000);}}
}
if(!$exit){$result['cleanup']='Temporary HTTP server, sessions, private immutable fixtures and spies removed';file_put_contents(__DIR__.'/google-verification-review-results.json',json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo 'Google review passed ('.count($checks).' assertions; '.count($cases)." current branches; separate proposed model; no DB/verification changes).\n";}exit($exit);
