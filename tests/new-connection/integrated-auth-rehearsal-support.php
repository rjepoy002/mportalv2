<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
function rehearsalReplace(string $source,string $needle,string $replacement,string $label): string
{
    if(substr_count($source,$needle)!==1)throw new RuntimeException('Reviewed fixture transformation changed: '.$label);
    return str_replace($needle,$replacement,$source);
}
function rehearsalPrepare(string $temp,PDO $db): void
{
    rehearsalTempGuard($temp);
    require_once __DIR__.'/isolated-identity-claim.php';
    require_once dirname(__DIR__,2).'/app/helpers/auth-return-intent.php';
    identityClaimFixtureSchema($db);
    // One authoritative identity store: an updatable view maps 06H's contract to copied auth users.
    $db->exec('ALTER TABLE link_proofs DROP FOREIGN KEY link_proofs_ibfk_1');
    $db->exec('DROP TABLE synthetic_users');
    $db->exec("ALTER TABLE users MODIFY google_id VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NULL, ADD password_available BOOLEAN NOT NULL DEFAULT 0, ADD binding_origin VARCHAR(24) NOT NULL DEFAULT 'unknown', ADD version INT NOT NULL DEFAULT 0");
    $db->exec('CREATE VIEW synthetic_users AS SELECT id,email,google_id AS google_subject,status,email_verified_at,password_available,binding_origin,version FROM users');
    $db->exec('ALTER TABLE link_proofs ADD FOREIGN KEY(actor) REFERENCES users(id)');
    $generation=bin2hex(random_bytes(16));$q=$db->prepare('INSERT INTO claim_control VALUES(1,?,1,0,0,0)');$q->execute([$generation]);
    returnWrite($temp.'/private/rehearsal.json',json_encode(['ready'=>true,'generation'=>$generation,'recover_after'=>0]));
    $component=file_get_contents(__DIR__.'/isolated-identity-claim.php');
    $component=rehearsalReplace($component,"PHP_SAPI !== 'cli'","PHP_SAPI !== 'cli' && PHP_SAPI !== 'cli-server'",'private server component guard');
    returnWrite($temp.'/isolated-identity-claim.php',$component);
    returnWrite($temp.'/integrated-auth-rehearsal-http.php',file_get_contents(__DIR__.'/integrated-auth-rehearsal-http.php'));
    $router=file_get_contents($temp.'/auth-return-fixture.php');
    $start=strpos($router,'function fixtureAtomicClaim(');$end=strpos($router,'$intent=new PortalAuthReturnIntent', $start);
    if($start===false||$end===false)throw new RuntimeException('Claim replacement markers changed');
    $router=substr($router,0,$start)."require __DIR__.'/integrated-auth-rehearsal-http.php';\n".substr($router,$end);
    $router=rehearsalReplace($router,"session_save_path(__DIR__.'/private/sessions');", "if (!empty(\$control['race']) && parse_url(\$_SERVER['REQUEST_URI'],PHP_URL_PATH)==='/login' && \$_SERVER['REQUEST_METHOD']==='POST') file_put_contents(__DIR__.'/private/arrivals','1',FILE_APPEND|LOCK_EX); session_save_path(__DIR__.'/private/sessions');",'pre-session race arrival');
    $router=rehearsalReplace($router,'return $intent->take($_SESSION,$google?($fixtureOAuthFlow??null):fixtureAuthFlow(),fixtureAuthNow());',
        'fixtureRevalidate($google); return $intent->take($_SESSION,$google?($fixtureOAuthFlow??null):fixtureAuthFlow(),fixtureAuthNow());','current identity check');
    $router=rehearsalReplace($router,'global $fixtureOAuthFlow;$pending=', 'global $fixtureOAuthFlow,$fixtureOAuthLink;$pending=','OAuth link context local');
    $router=rehearsalReplace($router,'$ok=portalOAuthStateValid($state);', '$ok=portalOAuthStateValid($state);$fixtureOAuthLink=$ok?($pending[\'link_context\']??null):null;','state-bound link context');
    $router=rehearsalReplace($router,"if(\$path==='/session')", "if(\$path==='/link')fixtureExplicitLink(); if(\$path==='/session')",'private explicit link route');
    returnWrite($temp.'/auth-return-fixture.php',$router);
}
function rehearsalControllers(string $temp): void
{
    rehearsalTempGuard($temp);
    $file=$temp.'/app/controllers/google-callback.php';$source=file_get_contents($file);
    $branch= <<<'PHP'
    if (($control['google_policy']??'current')==='experimental') {
        try {
        $user=fixtureProposedGoogle($pdo,$userInfo);
        $returnOutcome=fixtureAuthCapture(true);
        } catch (Throwable) { fixtureAuthReply(503,['message'=>'Experimental authentication unavailable.']); }
        portalAuthenticated($user);
        $_SESSION['logged_in']=true;
        header('Location: '.fixtureAuthTarget($returnOutcome));exit;
    }
PHP;
    $source=rehearsalReplace($source,'    $googleId = $userInfo->id;', $branch."\n    \$googleId = \$userInfo->id;",'experimental policy after actual state/exchange checks');
    returnWrite($file,$source);
    $file=$temp.'/app/controllers/google-login.php';$source=file_get_contents($file);
    $source=rehearsalReplace($source,'$client->setState($state);',"\$_SESSION['oauth_pending']['link_context']=\$_SESSION['rehearsal_link']??null;unset(\$_SESSION['rehearsal_link']);\n\$client->setState(\$state);",'server-pending link context');
    returnWrite($file,$source);
}

function rehearsalProvider(array $provider): void
{global $temp;returnWrite($temp.'/private/provider.json',json_encode($provider+['name'=>'Synthetic','verified_email'=>true,'hd'=>null]));}
function rehearsalGoogle(string &$cookie,?string $flow=null): array
{
    $o=returnRequest('/oauth'.($flow===null?'':'?auth_flow='.$flow),$cookie);
    parse_str(parse_url($o['location'],PHP_URL_QUERY),$p);
    return returnRequest('/callback?state='.$p['state'].'&code=synthetic',$cookie);
}
function rehearsalAsync(string $host,string $cookie,array $body,int $number): array
{
    global $temp,$env,$processes;
    $file=$temp.'/private/rehearsal-client-'.$number.'.json';returnWrite($file,json_encode(compact('host','cookie','body')));
    $p=proc_open([PHP_BINARY,__DIR__.'/integrated-auth-rehearsal-client.php',$file],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env);
    if(!is_resource($p))throw new RuntimeException('Client unavailable');$processes[]=$p;fclose($pipes[0]);return [$p,$pipes];
}
function rehearsalTempGuard(string $temp): void
{
    $resolved=realpath($temp);
    if(!$resolved||dirname($resolved)!==realpath(sys_get_temp_dir())||!preg_match('/^mco-return-[a-f0-9]{16}$/D',basename($resolved)))throw new RuntimeException('Temporary rehearsal root required');
}
function rehearsalCollect(array $client): array
{
    [$p,$pipes]=$client;$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    returnAssert(proc_close($p)===0&&$err==='','Integrated independent HTTP client exits cleanly');return json_decode($out,true,16,JSON_THROW_ON_ERROR);
}
function rehearsalWait(callable $ready): void
{ $until=microtime(true)+8;while(!$ready()){if(microtime(true)>$until)throw new RuntimeException('Rehearsal checkpoint timeout');usleep(10000);} }
function rehearsalJourneys(): void
{
    global $temp,$db,$host,$hosts;
    // The inherited 06F cases above intentionally exercise CURRENT Google behavior.
    returnControl(['google_policy'=>'experimental']);
    rehearsalProvider(['id'=>'google-5','email'=>'synthetic5@example.invalid']);$c='';$f=returnBegin($c);$before=$c;
    $r=rehearsalGoogle($c,$f);
    returnAssert(str_ends_with($r['location'],'/new-connection.php')&&$before!==$c,'Experimental Google verified account uses SQL claim and session regeneration');
    returnAssert(returnRequest('/module',$c)['status']===200,'Experimental Google and module share authoritative identity without electric accounts');
    foreach([['id'=>'google-3','email'=>'synthetic3@example.invalid','decision'=>'portal_verification_required'],['id'=>'google-2','email'=>'synthetic2@example.invalid','decision'=>'deny_status'],['id'=>'google-5','email'=>'changed@example.invalid','decision'=>'portal_email_review_required'],['id'=>'wrong-binding','email'=>'synthetic5@example.invalid','decision'=>'binding_conflict']] as $case){
        rehearsalProvider($case);$cc='';$ff=returnBegin($cc);$denied=rehearsalGoogle($cc,$ff);
        returnAssert($denied['status']===403&&$denied['json']['decision']===$case['decision']&&returnSession($cc)['id']===null,'Experimental policy denies unsafe identity before session issuance: '.$case['decision']);
    }
    // Explicit link: independent password reauthentication, CSRF and state-bound synthetic proof.
    $db->prepare('INSERT INTO users(email,name,password_hash,status,email_verified_at,password_available) VALUES(?,?,?,?,?,1)')->execute(['explicit-link@example.invalid','Synthetic Link',password_hash('LinkPassword123',PASSWORD_DEFAULT),'active','2026-10-21']);
    $linkId=(int)$db->lastInsertId();$linkCookie='';returnAssert(returnLogin($linkCookie,null,'explicit-link@example.invalid','LinkPassword123')['location']==='dashboard.php','Explicit link starts from independent portal authentication');
    rehearsalProvider(['id'=>'explicit-subject','email'=>'explicit-link@example.invalid']);$csrf=returnSession($linkCookie)['csrf'];
    returnAssert(returnRequest('/link',$linkCookie,['csrf_token'=>'invalid','password'=>'LinkPassword123'],'POST')['status']===419,'Explicit link cannot bypass CSRF');
    returnAssert(returnRequest('/link',$linkCookie,['csrf_token'=>$csrf,'password'=>'wrong'],'POST')['status']===403,'Explicit link requires fresh independent password proof');
    returnAssert(returnRequest('/link',$linkCookie,['csrf_token'=>$csrf,'password'=>'LinkPassword123'],'POST')['status']===200,'Synthetic server proof is prepared without browser identity or actor selection');
    $lf=returnBegin($linkCookie);$linked=rehearsalGoogle($linkCookie,$lf);
    returnAssert(str_ends_with($linked['location'],'/new-connection.php'),'Explicit transactional Google link completes module return');
    $row=$db->query('SELECT google_id,email_verified_at FROM users WHERE id='.$linkId)->fetch();
    returnAssert($row['google_id']==='explicit-subject'&&$row['email_verified_at']==='2026-10-21 00:00:00','Link updates authoritative auth users without changing portal verification');
    returnAssert((int)$db->query('SELECT COUNT(*) FROM identity_events WHERE actor='.$linkId)->fetchColumn()===1,'Integrated link commits one safe audit event');
    // Same legacy account, separate current/experimental branches, no mutation of verification.
    rehearsalProvider(['id'=>'google-3','email'=>'synthetic3@example.invalid']);returnControl([]);$legacy='';rehearsalGoogle($legacy);
    returnAssert(returnSession($legacy)['id']==3&&returnRequest('/module',$legacy)['status']===403,'Current policy permits bound unverified ordinary session but module denies');
    returnControl(['google_policy'=>'experimental']);$strict='';$strictResult=rehearsalGoogle($strict);
    returnAssert($strictResult['status']===403&&returnSession($strict)['id']===null,'Proposed policy rejects same legacy account before session creation');
    returnAssert($db->query('SELECT email_verified_at FROM users WHERE id=3')->fetchColumn()===null,'Neither comparison branch changes existing verification timestamp');
    // Real expiry then explicit reauthentication. Existing commands are never retained/replayed.
    $expired=$linkCookie;returnControl(['session_offset'=>1801]);returnAssert(returnRequest('/module',$expired)['status']===401,'Expired authenticated session rejected');returnControl([]);
    $fresh=returnBegin($expired);returnAssert(str_ends_with(returnLogin($expired,$fresh,'explicit-link@example.invalid','LinkPassword123')['location'],'/new-connection.php'),'Explicit login after expiry establishes fresh return flow');
    // Claim-store failure must not affect ordinary password or Google login.
    returnControl(['claim_failure'=>true,'google_policy'=>'experimental']);$ordinary='';$n=(int)$db->query('SELECT COUNT(*) FROM return_claims')->fetchColumn();
    returnAssert(returnLogin($ordinary,null,'explicit-link@example.invalid','LinkPassword123')['location']==='dashboard.php','Ordinary password login avoids disconnected SQL claim dependency');
    rehearsalProvider(['id'=>'explicit-subject','email'=>'explicit-link@example.invalid']);$ordinaryGoogle='';
    returnAssert(rehearsalGoogle($ordinaryGoogle)['location']==='dashboard.php','Ordinary proposed Google login avoids claim-store outage');
    $outageGoogle='';$outageFlow=returnBegin($outageGoogle);$outage=rehearsalGoogle($outageGoogle,$outageFlow);
    returnAssert($outage['status']===503&&$outage['location']===''&&returnSession($outageGoogle)['id']===null,'Proposed module Google return fails generically during actual claim-connection outage');
    returnAssert((int)$db->query('SELECT COUNT(*) FROM return_claims')->fetchColumn()===$n,'Ordinary authentication creates no claim rows');returnControl([]);
    // Commit acknowledgment loss: actual DB commit, injected lost return signal.
    $unknown='';$uf=returnBegin($unknown);$digest=hash('sha256','portal.auth.return.v1:'.$uf);
    returnControl(['claim_unknown'=>true]);$r=returnLogin($unknown,$uf,'explicit-link@example.invalid','LinkPassword123');
    returnAssert($r['status']===503&&returnSession($unknown)['id']===null,'Unknown commit result fails before session authentication');
    $q=$db->prepare('SELECT COUNT(*) FROM return_claims WHERE digest=?');$q->execute([$digest]);returnAssert((int)$q->fetchColumn()===1,'Unknown result has one durably committed claim');
    returnControl([]);$r=returnLogin($unknown,$uf,'explicit-link@example.invalid','LinkPassword123');
    returnAssert(str_ends_with($r['location'],'auth_context=expired'),'Retry after lost commit acknowledgment never repeats module return');
    $newFlow=returnBegin($unknown);returnAssert(str_ends_with(returnLogin($unknown,$newFlow,'explicit-link@example.invalid','LinkPassword123')['location'],'/new-connection.php'),'Explicit new authentication flow recovers after uncertain result');
    // Force real two-worker old-session race: first holds session lock; second opens same session.
    foreach(['checkpoint','release','arrivals'] as $file)if(is_file($temp.'/private/'.$file))unlink($temp.'/private/'.$file);
    $race='';$rf=returnBegin($race);$token=returnSession($race)['csrf'];returnControl(['race'=>true]);
    $body=['csrf_token'=>$token,'auth_flow'=>$rf,'email'=>'explicit-link@example.invalid','password'=>'LinkPassword123'];
    $a=rehearsalAsync($hosts[0],$race,$body,10);rehearsalWait(fn()=>is_file($temp.'/private/checkpoint'));
    $b=rehearsalAsync($hosts[1],$race,$body,11);rehearsalWait(fn()=>is_file($temp.'/private/arrivals')&&strlen(file_get_contents($temp.'/private/arrivals'))===2);
    usleep(250000);file_put_contents($temp.'/private/release','go');$ra=rehearsalCollect($a);$rb=rehearsalCollect($b);returnControl([]);
    $destinations=[$ra['location'],$rb['location']];
    returnAssert(count(array_filter($destinations,fn($v)=>str_ends_with($v,'/new-connection.php')))===1,'Forced simultaneous login race permits exactly one SQL return claim');
    returnAssert($ra['regenerated']&&$rb['regenerated']&&$ra['status']===302&&$rb['status']===302,'Both old-session concurrent requests reach actual session regeneration');
    returnAssert(count(array_filter($destinations,fn($v)=>str_ends_with($v,'auth_context=expired')))===1,'Losing regenerated session receives expired context, no dashboard fallback');
    $q=$db->prepare('SELECT COUNT(*) FROM return_claims WHERE digest=?');$q->execute([hash('sha256','portal.auth.return.v1:'.$rf)]);returnAssert((int)$q->fetchColumn()===1,'Concurrent regenerated sessions share exactly one committed digest');
    // Password user changes after password validation and before fresh authorization check.
    foreach(['checkpoint','release'] as $file)if(is_file($temp.'/private/'.$file))unlink($temp.'/private/'.$file);
    $statusCookie='';$sf=returnBegin($statusCookie);$st=returnSession($statusCookie)['csrf'];returnControl(['pause_before_claim'=>true]);
    $worker=rehearsalAsync($host,$statusCookie,['csrf_token'=>$st,'auth_flow'=>$sf,'email'=>'explicit-link@example.invalid','password'=>'LinkPassword123'],12);
    rehearsalWait(fn()=>is_file($temp.'/private/checkpoint'));$db->exec("UPDATE users SET status='inactive' WHERE id=".$linkId);file_put_contents($temp.'/private/release','go');$denied=rehearsalCollect($worker);returnControl([]);
    returnAssert($denied['status']===503&&returnSession($statusCookie)['id']===null,'Status revoked after password lookup is rechecked before authentication');
    $db->exec("UPDATE users SET status='active' WHERE id=".$linkId);
    rehearsalRecovery();
    rehearsalGateDrain();
    rehearsalPolicyCompatibility();
    returnControl([]);rehearsalProvider(['id'=>'google-5','email'=>'synthetic5@example.invalid']);
}

function rehearsalExternalWrite(array $state): void
{
    global $temp;rehearsalTempGuard($temp);$file=fopen($temp.'/private/rehearsal.json','c+');if(!$file||!flock($file,LOCK_EX))throw new RuntimeException('External gate unavailable');
    try{$json=json_encode($state,JSON_THROW_ON_ERROR);rewind($file);if(!ftruncate($file,0)||fwrite($file,$json)!==strlen($json)||!fflush($file))throw new RuntimeException('External gate write failed');}
    finally{flock($file,LOCK_UN);fclose($file);}
}
function rehearsalGateDrain(): void
{
    global $temp,$db,$host,$env,$processes;
    $db->prepare('INSERT INTO users(email,name,password_hash,status,email_verified_at) VALUES(?,?,?,?,?)')->execute(['gate-rehearsal@example.invalid','Synthetic Gate',password_hash('Gate123',PASSWORD_DEFAULT),'active','2026-10-21']);
    $c='';$flow=returnBegin($c);$csrf=returnSession($c)['csrf'];returnControl(['pause_claim'=>true]);
    $client=rehearsalAsync($host,$c,['csrf_token'=>$csrf,'auth_flow'=>$flow,'email'=>'gate-rehearsal@example.invalid','password'=>'Gate123'],20);
    rehearsalWait(fn()=>is_file($temp.'/private/claim-checkpoint'));
    $probe=fopen($temp.'/private/rehearsal.json','r');$locked=flock($probe,LOCK_EX|LOCK_NB);if($locked)flock($probe,LOCK_UN);fclose($probe);
    returnAssert(!$locked,'External recovery writer cannot pass active shared claim lock');
    $external=json_decode(file_get_contents($temp.'/private/rehearsal.json'),true);$external['ready']=false;returnWrite($temp.'/private/next-gate.json',json_encode($external));
    $p=proc_open([PHP_BINARY,__DIR__.'/integrated-auth-rehearsal-gate-worker.php',$temp],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env);
    if(!is_resource($p))throw new RuntimeException('Gate writer unavailable');$processes[]=$p;fclose($pipes[0]);
    rehearsalWait(fn()=>is_file($temp.'/private/gate-writer-ready'));usleep(150000);
    returnAssert(proc_get_status($p)['running'],'Independent recovery writer waits for in-flight claim to drain');
    file_put_contents($temp.'/private/claim-release','go');$r=rehearsalCollect($client);
    $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
    returnAssert(proc_close($p)===0&&$out==='closed'&&$err==='','External recovery gate closes after SQL commit finishes');
    returnAssert(str_ends_with($r['location'],'/new-connection.php')&&json_decode(file_get_contents($temp.'/private/rehearsal.json'),true)['ready']===false,'Already in-flight claim commits before acknowledged quarantine closure');
    returnControl([]);$blocked='';$blockedFlow=returnBegin($blocked);
    returnAssert(returnLogin($blocked,$blockedFlow,'gate-rehearsal@example.invalid','Gate123')['status']===503,'No subsequent return claim passes acknowledged quarantine closure');
    // No data restore occurred in this subtest, so reopening preserves all existing tombstones.
    $external['ready']=true;rehearsalExternalWrite($external);
}
function rehearsalRecovery(): void
{
    global $temp,$db;
    $db->prepare('INSERT INTO users(email,name,password_hash,status,email_verified_at,password_available) VALUES(?,?,?,?,?,1)')->execute(['recovery-rehearsal@example.invalid','Synthetic Recovery',password_hash('Recovery123',PASSWORD_DEFAULT),'active','2026-10-21']);
    $external=json_decode(file_get_contents($temp.'/private/rehearsal.json'),true);
    $old='';$flow=returnBegin($old);$csrf=returnSession($old)['csrf'];
    session_save_path($temp.'/private/sessions');session_id($old);session_start();$oldState=$_SESSION;session_write_close();
    $savedClaims=$db->query('SELECT * FROM return_claims')->fetchAll();$savedControl=$db->query('SELECT * FROM claim_control')->fetch();
    returnAssert(str_ends_with(returnLogin($old,$flow,'recovery-rehearsal@example.invalid','Recovery123')['location'],'/new-connection.php'),'Pre-restore flow is consumed successfully');
    // Recreate an independently retained pre-regeneration session with the still-live old hint.
    $clone=bin2hex(random_bytes(16));file_put_contents($temp.'/private/sessions/sess_'.$clone,serializeSessionFixture($oldState));
    $external['ready']=false;$external['recover_after']=time()+602;rehearsalExternalWrite($external);
    $db->beginTransaction();$db->exec('DELETE FROM return_claims');$insert=$db->prepare('INSERT INTO return_claims VALUES(?,?)');foreach($savedClaims as $claim)$insert->execute([$claim['digest'],$claim['expires']]);
    $q=$db->prepare('UPDATE claim_control SET used=?,last_clock=? WHERE id=1');$q->execute([$savedControl['used'],$savedControl['last_clock']]);$db->commit();
    returnAssert(time()<$oldState[PortalAuthReturnIntent::SESSION_KEY]['expires_at'],'Restored missing digest is tested while original authentication hint remains valid');
    $denied=returnRequest('/login',$clone,['csrf_token'=>$csrf,'auth_flow'=>$flow,'email'=>'recovery-rehearsal@example.invalid','password'=>'Recovery123'],'POST');
    returnAssert($denied['status']===503&&returnSession($clone)['id']===null,'External restore quarantine prevents replay despite coherent old SQL history');
    $external['ready']=true;rehearsalExternalWrite($external);
    returnAssert(returnLogin($clone,$flow,'recovery-rehearsal@example.invalid','Recovery123')['status']===503,'Premature readiness cannot bypass external recovery deadline');
    // Alternative to a real 602-second drain: invalidate ALL hint-bearing fixture sessions first.
    $external['ready']=false;rehearsalExternalWrite($external);
    $directory=realpath($temp.'/private/sessions');if(!$directory||dirname($directory)!==realpath($temp.'/private'))throw new RuntimeException('Unsafe session invalidation path');
    foreach(new DirectoryIterator($directory) as $entry)if($entry->isFile()&&preg_match('/^sess_[A-Za-z0-9,-]+$/D',$entry->getFilename()))unlink($entry->getPathname());
    $external['generation']=bin2hex(random_bytes(16));$external['recover_after']=0;$external['ready']=true;
    $q=$db->prepare('UPDATE claim_control SET generation=? WHERE id=1');$q->execute([$external['generation']]);rehearsalExternalWrite($external);
    returnAssert(returnRequest('/login',$clone,['csrf_token'=>$csrf,'auth_flow'=>$flow,'email'=>'recovery-rehearsal@example.invalid','password'=>'Recovery123'],'POST')['status']===419,'Invalidated old authentication session cannot replay after reopening');
    $fresh='';$f=returnBegin($fresh);returnAssert(str_ends_with(returnLogin($fresh,$f,'recovery-rehearsal@example.invalid','Recovery123')['location'],'/new-connection.php'),'Recovery requires explicit fresh authentication after global fixture-session invalidation');
    // Logout with a live, new return hint must destroy both authentication and hint.
    $logoutFlow=returnBegin($fresh);session_id($fresh);session_start();$_SESSION['dashboard_csrf']=bin2hex(random_bytes(32));$logoutToken=$_SESSION['dashboard_csrf'];session_write_close();
    returnAssert(returnRequest('/logout',$fresh,['intent'=>'logout','csrf_token'=>$logoutToken],'POST')['status']===302,'Logout succeeds while a new return hint is pending');
    $s=returnSession($fresh);returnAssert($s['id']===null&&!in_array(PortalAuthReturnIntent::SESSION_KEY,$s['keys'],true),'Logout destroys pending intent and authenticated identity');
}
function serializeSessionFixture(array $state): string
{
    // PHP's default session serialize handler, checked against the copied helper environment.
    if(ini_get('session.serialize_handler')!=='php')throw new RuntimeException('Fixture requires PHP session serialization');
    $encoded='';foreach($state as $key=>$value)$encoded.=$key.'|'.serialize($value);return $encoded;
}

function rehearsalPolicyCompatibility(): void
{
    global $db;
    $insert=$db->prepare('INSERT INTO users(email,name,password_hash,status,email_verified_at) VALUES(?,?,?,?,NULL)');
    foreach(['auto-current@example.invalid','auto-experimental@example.invalid'] as $email)$insert->execute([$email,'Synthetic Legacy',password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT),'active']);
    returnControl([]);rehearsalProvider(['id'=>'auto-current','email'=>'auto-current@example.invalid','hd'=>'synthetic.example.invalid']);$current='';
    returnAssert(rehearsalGoogle($current)['location']==='dashboard.php','Current hosted-domain branch implicitly links and authenticates synthetic legacy account');
    $legacy=$db->query("SELECT google_id,email_verified_at FROM users WHERE email='auto-current@example.invalid'")->fetch();
    returnAssert($legacy['google_id']==='auto-current'&&$legacy['email_verified_at']!==null,'Current copied callback fills synthetic legacy verification timestamp');
    returnControl(['google_policy'=>'experimental']);rehearsalProvider(['id'=>'auto-experimental','email'=>'auto-experimental@example.invalid','hd'=>'synthetic.example.invalid']);$experimental='';$r=rehearsalGoogle($experimental);
    returnAssert($r['status']===403&&$r['json']['decision']==='portal_verification_required','Proposed hosted-domain behavior requires independent portal verification');
    $legacy=$db->query("SELECT google_id,email_verified_at FROM users WHERE email='auto-experimental@example.invalid'")->fetch();
    returnAssert($legacy['google_id']===null&&$legacy['email_verified_at']===null,'Proposed callback preserves unverified unbound synthetic account');
    returnControl([]);rehearsalProvider(['id'=>'new-current','email'=>'new-current@example.invalid']);$new='';
    returnAssert(rehearsalGoogle($new)['location']==='dashboard.php','Current copied callback creates verified Google-only synthetic identity');
    $created=$db->query("SELECT google_id,email_verified_at,password_hash FROM users WHERE email='new-current@example.invalid'")->fetch();
    returnAssert($created['google_id']==='new-current'&&$created['email_verified_at']!==null&&$created['password_hash']!=='','Current Google-only hash exists without evidence of a user-known password');
    $count=(int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn();returnControl(['google_policy'=>'experimental']);rehearsalProvider(['id'=>'new-experimental','email'=>'new-experimental@example.invalid']);$denied='';$r=rehearsalGoogle($denied);
    returnAssert($r['status']===403&&$r['json']['decision']==='portal_enrollment_required'&&(int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()===$count,'Proposed callback requires enrollment without creating or verifying an identity');
}
