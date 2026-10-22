<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/isolated-identity-claim.php';
require dirname(__DIR__,2).'/app/helpers/auth-return-intent.php';
$checks=[];
function hcheck(bool $ok,string $label): void {global $checks;if(!$ok)throw new LogicException($label);$checks[]=$label;}
function hfail(callable $f,string $label): void {try{$f();}catch(RuntimeException){hcheck(true,$label);return;}throw new LogicException($label);}
function hsql(PDO $db,string $sql,array $args=[]): PDOStatement {$q=$db->prepare($sql);$q->execute($args);return $q;}
function huser(PDO $db,int $id,string $email,?string $subject=null,string $status='active',?string $verified='2026-01-01 00:00:00'): void
{hsql($db,'INSERT INTO synthetic_users(id,email,google_subject,status,email_verified_at) VALUES (?,?,?,?,?)',[$id,$email,$subject,$status,$verified]);}
function hworkers(string $schema,string $op,string $generation,array $actors=[0,0],array $proofs=['',''],?Closure $whileBlocked=null): array
{
    $workers=[];$dir=sys_get_temp_dir().DIRECTORY_SEPARATOR.'mco-nc-06h-'.bin2hex(random_bytes(8));
    if(!mkdir($dir,0700))throw new RuntimeException('Fixture directory unavailable');
    try {
        foreach([0,1] as $i){
            $base=$dir.DIRECTORY_SEPARATOR.'worker'.$i;
            $p=proc_open([PHP_BINARY,__DIR__.'/google-policy-claim-worker.php',$schema,$op,$base,$generation,(string)$actors[$i],$proofs[$i]],
                [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,__DIR__);
            if(!is_resource($p))throw new RuntimeException('Worker unavailable');
            fclose($pipes[0]);$workers[]=[$p,$pipes,$base];
        }
        $end=microtime(true)+15;
        foreach($workers as $w)while(!is_file($w[2].'.ready')){if(microtime(true)>$end)throw new RuntimeException('Worker timeout');usleep(10000);}
        foreach($workers as $w)file_put_contents($w[2].'.go','go');
        if($whileBlocked)$whileBlocked();
        $out=[];
        foreach($workers as [$p,$pipes]){$raw=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($p);hcheck($code===0 && $err==='','Independent worker exits cleanly');$out[]=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}
        return $out;
    } finally {
        foreach($workers as [$p,$pipes])if(is_resource($p)){proc_terminate($p);foreach($pipes as $pipe)if(is_resource($pipe))fclose($pipe);proc_close($p);}
        // Only exact freshly-created directory, fixed filenames; no recursive deletion.
        foreach(['worker0.ready','worker0.go','worker1.ready','worker1.go'] as $file)if(is_file($dir.DIRECTORY_SEPARATOR.$file))unlink($dir.DIRECTORY_SEPARATOR.$file);
        rmdir($dir);
    }
}
$schema='mco_nc_test_'.bin2hex(random_bytes(8));$server=draftTestPDO();$created=false;$generation=bin2hex(random_bytes(16));
try {
    $version=$server->query('SELECT VERSION()')->fetchColumn();
    hcheck(str_contains($version,'MariaDB') && version_compare($version,'10.4','>='),'Disposable MariaDB compatibility checked');
    $server->exec('CREATE DATABASE `'.$schema.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$created=true;
    $db=draftTestConnection($schema);identityClaimFixtureSchema($db);
    hsql($db,'INSERT INTO claim_control VALUES (1,?,1,0,0,0)',[$generation]);
    huser($db,1,'bound@example.invalid','bound');huser($db,2,'unverified@example.invalid','unverified','active',null);
    huser($db,3,'inactive@example.invalid','inactive','inactive');huser($db,4,'link@example.invalid');
    huser($db,5,'race@example.invalid');huser($db,6,'other@example.invalid');huser($db,7,'cross@example.invalid');
    huser($db,8,'expired-proof@example.invalid');huser($db,9,'unbound-unverified@example.invalid',null,'active',null);
    huser($db,10,'suspended@example.invalid','suspended','suspended');
    $db->exec("UPDATE synthetic_users SET binding_origin='auto' WHERE id=1");
    $policy=new IsolatedGooglePolicy($db);$provider=new SyntheticProviderEvidence('bound','bound@example.invalid');
    $verifiedBefore=$db->query('SELECT id,email_verified_at FROM synthetic_users ORDER BY id')->fetchAll();
    hcheck($policy->decide($provider)['decision']==='authenticate','Bound active verified identity authenticates');
    hcheck($policy->decide(new SyntheticProviderEvidence('unverified','unverified@example.invalid'))['decision']==='portal_verification_required','Bound unverified identity cannot verify itself');
    hcheck($policy->decide(new SyntheticProviderEvidence('inactive','inactive@example.invalid'))['decision']==='deny_status','Inactive subject rejected');
    hcheck($policy->decide(new SyntheticProviderEvidence('suspended','suspended@example.invalid'))['decision']==='deny_status','Suspended subject rejected');
    hcheck($policy->decide(new SyntheticProviderEvidence('unbound-unverified','unbound-unverified@example.invalid'))['decision']==='portal_verification_required','Unbound unverified account remains unverified');
    hcheck($policy->decide(new SyntheticProviderEvidence('bound','changed@example.invalid'))['decision']==='portal_email_review_required','Stable subject resolves changed email without creating user');
    hcheck($policy->decide(new SyntheticProviderEvidence('bound','other@example.invalid'))['decision']==='binding_conflict','Subject and email different owners rejected');
    foreach(['fresh@gmail.com','fresh@hosted.example','fresh@example.invalid'] as $email)
        hcheck($policy->decide(new SyntheticProviderEvidence('new-subject',$email))['decision']==='portal_enrollment_required','No automatic enrollment or verification for provider domain');
    foreach([new SyntheticProviderEvidence('bound','bound@example.invalid',false),new SyntheticProviderEvidence('bound','bound@example.invalid',true,false),new SyntheticProviderEvidence('bound','bound@example.invalid',true,true,false)] as $p)
        hcheck($policy->decide($p)['decision']==='deny_provider','Invalid provider evidence rejected independently');
    $link=new SyntheticProviderEvidence('link-subject','link@example.invalid');$session=str_repeat('s',32);
    hcheck($policy->decide($link)['decision']==='explicit_portal_link_required','Email match alone never links');
    hfail(fn()=>$policy->issueLinkProof(4,$link,$session,false,true),'Fresh independent portal proof required');
    hfail(fn()=>$policy->issueLinkProof(4,$link,$session,true,false),'Synthetic CSRF evidence required for proof issuance');
    $proof=$policy->issueLinkProof(4,$link,$session,true,true);
    hcheck($policy->decide($link,'link',6,$proof,$session)['decision']!=='linked','Proof cannot authorize another actor');
    hcheck($policy->decide($link,'link',4,$proof,'another-session-value')['decision']==='explicit_portal_link_required','Proof session binding enforced');
    hcheck($policy->decide(new SyntheticProviderEvidence('different','link@example.invalid'),'link',4,$proof,$session)['decision']==='explicit_portal_link_required','Proof subject binding enforced');
    $failPolicy=new IsolatedGooglePolicy($db,static function($point){if($point==='linked')throw new RuntimeException('Synthetic failure');});
    hfail(fn()=>$failPolicy->decide($link,'link',4,$proof,$session),'Injected link failure rolls back transaction');
    hcheck($db->query('SELECT google_subject FROM synthetic_users WHERE id=4')->fetchColumn()===null && (int)$db->query('SELECT COUNT(*) FROM identity_events')->fetchColumn()===0,'Failed link leaves no binding or audit event');
    hcheck($policy->decide($link,'link',4,$proof,$session)['decision']==='linked','Explicit proof binds subject conditionally');
    hcheck($policy->decide($link,'link',4,$proof,$session)['decision']==='explicit_portal_link_required','Consumed link proof cannot be replayed');
    hcheck($policy->decide($link)['new_connection_eligible']===true,'Verified applicant eligible without electric account tables');
    hfail(fn()=>hsql($db,"UPDATE synthetic_users SET google_subject='link-subject' WHERE id=6"),'Database unique subject prevents cross-account binding');
    $race=new SyntheticProviderEvidence('race-subject','race@example.invalid');$p1=$policy->issueLinkProof(5,$race,$session,true,true);$p2=$policy->issueLinkProof(5,$race,$session,true,true);
    $out=hworkers($schema,'link',$generation,[5,5],[$p1,$p2]);$decisions=array_column(array_column($out,'result'),'decision');sort($decisions);
    hcheck($decisions===['already_bound','linked'],'Concurrent independent linking commits exactly one binding');
    hcheck((int)$db->query('SELECT COUNT(*) FROM identity_events WHERE actor=5')->fetchColumn()===1,'Concurrent linking creates one audit record');
    $cross1=$policy->issueLinkProof(6,new SyntheticProviderEvidence('cross-subject','other@example.invalid'),$session,true,true);
    $cross2=$policy->issueLinkProof(7,new SyntheticProviderEvidence('cross-subject','cross@example.invalid'),$session,true,true);
    $out=hworkers($schema,'cross',$generation,[6,7],[$cross1,$cross2]);$crossDecisions=array_column(array_column($out,'result'),'decision');sort($crossDecisions);
    hcheck($crossDecisions===['binding_conflict','linked'],'Concurrent different-account links cannot steal a unique subject');
    $expired=$policy->issueLinkProof(8,new SyntheticProviderEvidence('expired-subject','expired-proof@example.invalid'),$session,true,true);
    hsql($db,'UPDATE link_proofs SET expires=UNIX_TIMESTAMP()-1 WHERE digest=?',[hash('sha256',$expired)]);
    hcheck($policy->decide(new SyntheticProviderEvidence('expired-subject','expired-proof@example.invalid'),'link',8,$expired,$session)['decision']==='explicit_portal_link_required','Expired explicit link proof rejected');
    // Both independent workers begin after proof issue; a locked status update commits first.
    $db->beginTransaction();$db->query('SELECT id FROM identity_mutex WHERE id=1 FOR UPDATE')->fetch();
    hsql($db,"UPDATE synthetic_users SET status='inactive' WHERE id=5");
    $out=hworkers($schema,'link',$generation,[5,5],[$p1,$p2],function()use($db){usleep(200000);$db->commit();});
    hcheck(array_column(array_column($out,'result'),'decision')===['deny_status','deny_status'],'Status change during authentication checked after row lock');
    hcheck($db->query('SELECT id,email_verified_at FROM synthetic_users ORDER BY id')->fetchAll()===$verifiedBefore,'All policy decisions preserve portal verification timestamps');
    $before=hash('sha256',json_encode($db->query('SELECT * FROM synthetic_users ORDER BY id')->fetchAll()));$inventory=$policy->compatibility();
    hcheck($inventory['google_only']>0 && $inventory['unverified']===2 && $inventory['previous_auto_link']===1,'Read-only synthetic compatibility inventory categorizes access risks');
    hcheck($before===hash('sha256',json_encode($db->query('SELECT * FROM synthetic_users ORDER BY id')->fetchAll())),'Compatibility assessment performs no account writes');
    $claims=new IsolatedSqlReturnClaims($db,$generation);$now=time();$digest=hash('sha256','first');
    hcheck($claims->claim($digest,$now+300,$now),'First claim durably commits');
    hcheck(!(new IsolatedSqlReturnClaims(draftTestConnection($schema),$generation))->claim($digest,$now+400,time()),'Independent connection cannot replay or extend claim');
    hcheck((int)hsql($db,'SELECT expires FROM return_claims WHERE digest=?',[$digest])->fetchColumn()===$now+300,'Absolute expiration unchanged by retry');
    $out=hworkers($schema,'claim',$generation);
    hcheck(count(array_filter(array_column($out,'result'),fn($r)=>$r===true))===1 && count(array_filter(array_column($out,'result'),fn($r)=>$r===false))===1,'Two independent claim workers produce exactly one winner');
    $gates=['environment_permitted'=>true,'feature_enabled'=>true];$helper=new PortalAuthReturnIntent('https://synthetic.example.invalid',$gates,[$claims,'claim']);
    $s=[];$flow=$helper->begin($s,PortalAuthReturnIntent::DESTINATION);$fork=$s;
    hcheck($helper->take($s,$flow)['status']==='module' && $helper->take($fork,$flow)['status']==='expired','Copied pre-regeneration session cannot replay durable claim');
    hcheck(!$claims->claim(hash('sha256','expired'),time(),time()),'Backend rejects expired claim');
    hfail(fn()=>$claims->claim('raw-flow',time()+300,time()),'Raw flow rejected in digest contract');
    hfail(fn()=>$claims->claim(hash('sha256','skew'),time()+300,time()-60),'Clock skew rejected');
    hfail(fn()=>$claims->claim(hash('sha256','ttl'),time()+900,time()),'Excessive lifetime rejected');
    $db->exec('UPDATE claim_control SET last_clock=UNIX_TIMESTAMP()+60 WHERE id=1');
    hfail(fn()=>$claims->claim(hash('sha256','clockrollback'),time()+300,time()),'Database clock regression fails closed');$db->exec('UPDATE claim_control SET last_clock=0 WHERE id=1');
    $lost=new IsolatedSqlReturnClaims($db,$generation,32,2,8,static function($p){if($p==='after_commit')throw new RuntimeException('Lost ack');});
    $unknown=hash('sha256','unknown');hfail(fn()=>$lost->claim($unknown,time()+300,time()),'Unknown commit acknowledgment never returns success');
    hcheck(!$claims->claim($unknown,time()+300,time()),'Committed unknown result cannot be replayed');
    $rollback=new IsolatedSqlReturnClaims($db,$generation,32,2,8,static function($p){if($p==='before_commit')throw new RuntimeException('Failure before commit');});
    $uncommitted=hash('sha256','uncommitted');hfail(fn()=>$rollback->claim($uncommitted,time()+300,time()),'Precommit failure rolls back');
    hcheck($claims->claim($uncommitted,time()+300,time()),'Rollback leaves no false consumed claim');
    $count=(int)$db->query('SELECT COUNT(*) FROM return_claims')->fetchColumn();$tiny=new IsolatedSqlReturnClaims($db,$generation,$count);
    hcheck(!$tiny->claim($digest,$now+300,time()),'Duplicate denial precedes capacity exhaustion');
    hfail(fn()=>$tiny->claim(hash('sha256','full'),time()+300,time()),'Capacity exhaustion never evicts live tombstones');
    // Seed expired synthetic tombstones only; cleanup remains bounded and transactional.
    for($i=0;$i<5;$i++)hsql($db,'INSERT INTO return_claims VALUES (?,?)',[hash('sha256','old'.$i),time()-10]);
    $db->exec('UPDATE claim_control SET used=used+5 WHERE id=1');
    $interrupted=new IsolatedSqlReturnClaims($db,$generation,32,2,2,static function($p){if($p==='cleanup')throw new RuntimeException('Interrupted cleanup');});
    hfail(fn()=>$interrupted->claim(hash('sha256','cleanup'),time()+300,time()),'Interrupted cleanup rolls back');
    hcheck((int)$db->query('SELECT COUNT(*) FROM return_claims WHERE expires<=UNIX_TIMESTAMP()')->fetchColumn()===5,'Interrupted cleanup retains rows and counter consistently');
    (new IsolatedSqlReturnClaims($db,$generation,32,2,2))->claim(hash('sha256','cleanup'),time()+300,time());
    hcheck((int)$db->query('SELECT COUNT(*) FROM return_claims WHERE expires<=UNIX_TIMESTAMP()')->fetchColumn()===3,'One cleanup removes at most configured batch');
    // Actual disconnect of THIS fixture connection only; bootstrap never selects an operational DB.
    $raw=draftTestPDO($schema);$connectionId=(int)$raw->query('SELECT CONNECTION_ID()')->fetchColumn();$offline=new IsolatedSqlReturnClaims($raw,$generation);
    $server->exec('KILL CONNECTION '.$connectionId);
    hfail(fn()=>$offline->claim(hash('sha256','offline'),time()+300,time()),'Actual killed PDO connection fails closed');
    $ordinary=new PortalAuthReturnIntent('https://synthetic.example.invalid',$gates,[$offline,'claim']);$empty=[];
    hcheck($ordinary->take($empty,null)['status']==='ordinary','Ordinary authentication does not call unavailable claim storage');
    // Missing individual history detected via counter; coherent restored history requires external quarantine.
    hsql($db,'DELETE FROM return_claims WHERE digest=?',[$digest]);
    hfail(fn()=>$claims->claim($digest,time()+300,time()),'Missing claim history with counter mismatch fails closed');
    $db->exec('UPDATE claim_control SET ready=0,used=(SELECT COUNT(*) FROM return_claims) WHERE id=1');
    hfail(fn()=>$claims->claim($digest,time()+300,time()),'Restore quarantine blocks claims even with consistent row count');
    $db->exec('UPDATE claim_control SET ready=1,not_before=UNIX_TIMESTAMP()+602 WHERE id=1');
    hfail(fn()=>$claims->claim($digest,time()+300,time()),'Recovery drain blocks both old and new flows before deadline');
    // No fake wait: verify expired old context with helper's injectable clock, and separately open a NEW generation.
    $old=[];$oldFlow=$helper->begin($old,PortalAuthReturnIntent::DESTINATION,time()-601);
    hcheck($helper->take($old,$oldFlow)['status']==='expired','Old helper flow expires across required recovery drain');
    $newGeneration=bin2hex(random_bytes(16));hsql($db,'UPDATE claim_control SET generation=?,not_before=0 WHERE id=1',[$newGeneration]);
    hfail(fn()=>$claims->claim(hash('sha256','old-generation'),time()+300,time()),'Stale adapter generation fails closed');
    $freshClaims=new IsolatedSqlReturnClaims($db,$newGeneration);$freshHelper=new PortalAuthReturnIntent('https://synthetic.example.invalid',$gates,[$freshClaims,'claim']);
    $fresh=[];$freshFlow=$freshHelper->begin($fresh,PortalAuthReturnIntent::DESTINATION);
    hcheck($freshHelper->take($fresh,$freshFlow)['status']==='module','Explicit new authentication flow works after simulated attested recovery');
    $columns=array_column($db->query('SHOW COLUMNS FROM return_claims')->fetchAll(),'Field');
    hcheck($columns===['digest','expires'],'Claim table contains no identity, raw flow, session or provider material');
    $events=$db->query('SELECT actor,action FROM identity_events')->fetchAll();
    hcheck(count($events)===3 && array_unique(array_column($events,'action'))===['explicit_google_link'],'Safe audit events contain IDs and fixed action only');
    foreach($db->statements as $sql)hcheck(!preg_match('/\b(?:mco_portal|nc_users|master|member|billhistory|arledger)\b/i',$sql),'Fixture SQL stays isolated');
    $durability=(int)$db->query('SELECT @@innodb_flush_log_at_trx_commit')->fetchColumn();
    hcheck($durability===1,'MariaDB flush-on-commit enabled; physical power-loss recovery not simulated');
    $result=['task'=>'06H','policy_approved'=>false,'database_version'=>$version,'innodb_flush_log_at_trx_commit'=>$durability,'checks'=>count($checks),'results'=>$checks,'external_requests'=>false,'actual_portal_access'=>false,'restore_drain'=>'Boundary simulation, not real elapsed 602 seconds','unknown_commit'=>'Post-commit acknowledgment fault injection, not a real network partition'];
    file_put_contents(__DIR__.'/google-policy-claim-results.json',json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));
    echo 'Google policy and SQL claims passed ('.count($checks)." assertions).\n";
} finally {
    if(isset($db) && $db->inTransaction())$db->rollBack();
    if($created && preg_match('/^mco_nc_test_[a-f0-9]{16}$/D',$schema))$server->exec('DROP DATABASE `'.$schema.'`');
}
