<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/draft-test-support.php';
require_once dirname(__DIR__,2).'/app/services/new-connection/submission.php';
require_once dirname(__DIR__,2).'/database/migrations/20261020_new_connection_submission_receipts.php';
$policy=require __DIR__.'/submission-test-policy.php';
function submitKey(): string {return bin2hex(random_bytes(32));}
function submitExpected(array $r): array {return array_intersect_key($r,array_flip(['application_version','revision_id','revision_no','revision_sha256']));}
function submitJSON(array $v): string {return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}
function submitSelections(): string {return submitJSON(['review_confirmed'=>true,'acknowledgments'=>array_fill_keys(NewConnectionApplicantContract::ACKS,true)]);}
if(($argv[1]??'')==='worker'){
    try{
        $c=json_decode(file_get_contents($argv[3]),true,32,JSON_THROW_ON_ERROR);$db=draftTestConnection($argv[2]);
        $s=new NewConnectionStage1Submission($db,$argv[2],static fn()=>$policy,base64_decode($c['secret']));
        while(microtime(true)<$c['start'])usleep(2000);
        if($c['operation']==='save')$r=$s->save($c['actor'],$c['reference'],$c['expected'],$c['key'],$c['input']);
        else $r=$s->submit($c['actor'],$c['reference'],$c['expected'],$c['key'],$c['proof'],$c['selections']);
        echo json_encode(['outcome'=>'committed','result'=>$r],JSON_THROW_ON_ERROR);
    }catch(NewConnectionConflict|NewConnectionDenied $e){echo '{"outcome":"conflict"}';}
    catch(Throwable $e){fwrite(STDERR,'Worker failure: '.get_class($e));exit(1);}exit;
}
$checks=0;
function submitAssert(bool $ok,string $label): void {global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
function submitReject(callable $call,string $class,string $label): void
{
    try{$call();}catch(Throwable $e){submitAssert($e instanceof $class,$label.' wrong exception '.get_class($e));return;}throw new RuntimeException($label.' accepted');
}
function submitState(PDO $db): string
{
    $rows=[];foreach(['applications','application_revisions','stages','stage_events','audit_events','consents','draft_requests','submission_requests'] as $name)$rows[$name]=$db->query('SELECT * FROM new_connection_'.$name.' ORDER BY 1')->fetchAll();
    return hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR));
}
function submitCount(PDO $db,string $table): int {return (int)$db->query('SELECT COUNT(*) FROM new_connection_'.$table)->fetchColumn();}
function submitFixture(NewConnectionStage1Submission $service): array
{
    static $id=0;$actor=++$id;$r=$service->create($actor,submitKey());
    $input=NewConnectionApplicantContract::emptyInput();$input['applicant']=array_replace($input['applicant'],['first_name'=>'Synthetic','last_name'=>'Applicant','contact_number'=>'09000000000','profile_confirmed'=>true]);
    $input['residential_address']=array_replace($input['residential_address'],['city_municipality_code'=>'TEST-C1','barangay_code'=>'TEST-B1','street_purok_sitio'=>'Synthetic Sitio']);
    $input['service_location']['same_as_residential']=true;$input['service_request']['premises_classification_code']='home';$input['service_request']['occupancy_code']='owner';
    $r=$service->save($actor,$r['reference'],submitExpected($r),submitKey(),submitJSON($input));
    $review=$service->review($actor,$r['reference'],submitExpected($r));
    return ['actor'=>$actor,'reference'=>$r['reference'],'expected'=>submitExpected($r),'proof'=>$review['proof'],'selections'=>submitSelections(),'input'=>submitJSON($input),'key'=>submitKey(),'operation'=>'submit'];
}
function submitCommand(NewConnectionStage1Submission $s,array $c): array {return $s->submit($c['actor'],$c['reference'],$c['expected'],$c['key'],$c['proof'],$c['selections']);}
function submitRace(string $schema,array $commands,string $secret,string $temp): array
{
    $workers=[];$results=[];$start=microtime(true)+1;
    try{
        foreach($commands as $n=>$c){$file=$temp.'/worker-'.$n.'.json';file_put_contents($file,submitJSON($c+['start'=>$start,'secret'=>base64_encode($secret)]));$proc=proc_open([PHP_BINARY,__FILE__,'worker',$schema,$file],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($proc))throw new RuntimeException('No worker.');fclose($pipes[0]);$workers[]=[$proc,$pipes];}
        foreach($workers as [$proc,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($proc)!==0)throw new RuntimeException($err);$results[]=json_decode($out,true,32,JSON_THROW_ON_ERROR);}$workers=[];return $results;
    }finally{foreach($workers as [$proc,$pipes])if(is_resource($proc)){proc_terminate($proc);proc_close($proc);}}
}
$schema='mco_nc_test_'.bin2hex(random_bytes(8));$created=false;$exitCode=0;$temp=sys_get_temp_dir().'/mco-submission-'.bin2hex(random_bytes(8));mkdir($temp,0700);
try{
    try{$server=draftTestPDO();}catch(Throwable $e){throw new RuntimeException('BLOCKED: local disposable MariaDB unavailable; no live fallback.',2);}
    submitAssert($server->query('SELECT DATABASE()')->fetchColumn()===null,'No selected portal database');$before=draftTestOtherSchemaSignature($server);$version=$server->query('SELECT VERSION()')->fetchColumn();
    try{$server->exec('CREATE DATABASE `'.$schema.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$created=true;}catch(Throwable $e){throw new RuntimeException('BLOCKED: disposable CREATE DATABASE unavailable.',2);}
    $db=draftTestConnection($schema);$db->exec('CREATE TABLE users(id BIGINT UNSIGNED PRIMARY KEY,email VARCHAR(254) NOT NULL,status VARCHAR(20) NOT NULL,email_verified_at DATETIME NULL) ENGINE=InnoDB');
    $insert=$db->prepare("INSERT INTO users VALUES(?,?,'active','2026-10-20')");for($n=1;$n<=100;$n++)$insert->execute([$n,'synthetic'.$n.'@example.invalid']);$db->exec("UPDATE users SET status='inactive' WHERE id=98");$db->exec('UPDATE users SET email_verified_at=NULL WHERE id=99');
    newConnectionDraftMigrate($db);$foundation=newConnectionSchemaSignature($db);$draftSignature=newConnectionDraftSignature($db);
    newConnectionSubmissionMigrate($db);$sig=newConnectionSubmissionSignature($db);newConnectionSubmissionMigrate($db);submitAssert($sig===newConnectionSubmissionSignature($db),'Migration repeat stable');
    newConnectionSubmissionRollback($db);submitAssert($foundation===newConnectionSchemaSignature($db)&&$draftSignature===newConnectionDraftSignature($db),'Empty additive rollback preserves foundations');newConnectionSubmissionMigrate($db);
    submitReject(fn()=>newConnectionSubmissionMigrate($server),RuntimeException::class,'Migration rejects unselected database');
    $secret=random_bytes(32);$now=time();$s=new NewConnectionStage1Submission($db,$schema,static function()use(&$policy){return $policy;},$secret,static function()use(&$now){return $now;});
    submitReject(fn()=>new NewConnectionStage1Submission($db,'mco_portal',static fn()=>$policy,$secret),NewConnectionDenied::class,'Production schema denied before use');
    $c=submitFixture($s);$baseline=submitState($db);
    $review=$s->review($c['actor'],$c['reference'],$c['expected']);submitAssert($review['expires_in']===300&&count($review['notices'])===4&&submitState($db)===$baseline,'Review issues bounded proof without persistent changes');
    foreach([98,99,100,0] as $actor){$bad=$c;$bad['actor']=$actor;submitReject(fn()=>submitCommand($s,$bad),NewConnectionDenied::class,'Cross-applicant or inactive/unverified submit');submitReject(fn()=>$s->review($actor,$c['reference'],$c['expected']),NewConnectionDenied::class,'Unauthorized review');}
    $bad=$c;$bad['selections']='{}';submitReject(fn()=>submitCommand($s,$bad),NewConnectionDraftInvalid::class,'Missing acknowledgments');
    $selection=json_decode(submitSelections(),true);$selection['acknowledgments']['privacy_notice']=false;$bad['selections']=submitJSON($selection);submitReject(fn()=>submitCommand($s,$bad),NewConnectionDraftInvalid::class,'Unchecked acknowledgment');
    $selection=json_decode(submitSelections(),true);$selection['acknowledgments']['privacy_notice']='true';$bad['selections']=submitJSON($selection);submitReject(fn()=>submitCommand($s,$bad),NewConnectionDraftInvalid::class,'Wrong acknowledgment JSON type');
    $selection=json_decode(submitSelections(),true);$selection['actor_user_id']=100;$bad['selections']=submitJSON($selection);submitReject(fn()=>submitCommand($s,$bad),NewConnectionDraftInvalid::class,'Client identity override');
    $selection=json_decode(submitSelections(),true);$selection['acknowledgments']['document_sha256']=str_repeat('a',64);$bad['selections']=submitJSON($selection);submitReject(fn()=>submitCommand($s,$bad),NewConnectionDraftInvalid::class,'Client notice hash override');
    foreach(['[]','null','{"review_confirmed":true,"acknowledgments":[]}',str_repeat(' ',8193)] as $malformed){$bad=$c;$bad['selections']=$malformed;submitReject(fn()=>submitCommand($s,$bad),NewConnectionDraftInvalid::class,'Malformed/oversize selection payload');}
    $bad=$c;$bad['proof']=str_repeat('x',8193);submitReject(fn()=>submitCommand($s,$bad),NewConnectionDraftInvalid::class,'Oversized proof');
    $bad=$c;$bad['proof']=substr($c['proof'],0,-1).($c['proof'][-1]==='a'?'b':'a');submitReject(fn()=>submitCommand($s,$bad),NewConnectionDenied::class,'Forged proof signature');
    $now+=301;submitReject(fn()=>submitCommand($s,$c),NewConnectionConflict::class,'Expired proof');$now-=301;
    foreach(['application_version','revision_id','revision_no','revision_sha256'] as $field){$bad=$c;$bad['expected'][$field]=$field==='revision_sha256'?str_repeat('f',64):$bad['expected'][$field]+1;submitReject(fn()=>submitCommand($s,$bad),NewConnectionConflict::class,'Incorrect snapshot hash or stale '.$field);}
    $originalPolicy=$policy;
    $policy['notices']['privacy_notice']['published']=false;submitReject(fn()=>submitCommand($s,$c),NewConnectionDenied::class,'Unpublished notice');$policy=$originalPolicy;
    $policy['notices']['privacy_notice']['version']='TEST-replaced-2';submitReject(fn()=>submitCommand($s,$c),NewConnectionConflict::class,'Replaced notice version invalidates proof');$policy=$originalPolicy;
    $policy['notices']['privacy_notice']['content'].=' Changed bytes.';submitReject(fn()=>submitCommand($s,$c),NewConnectionConflict::class,'Changed notice hash invalidates proof');$policy=$originalPolicy;
    $policy['catalog_approved_for_test']=false;submitReject(fn()=>submitCommand($s,$c),NewConnectionDenied::class,'Unapproved test catalog');$policy=$originalPolicy;
    $policy['catalog']['version']='SYNTHETIC-ONLY-02';submitReject(fn()=>submitCommand($s,$c),NewConnectionConflict::class,'Catalog replacement invalidates review');$policy=$originalPolicy;
    $policy['catalog']['connections']['ordinary']='Changed synthetic classification';submitReject(fn()=>submitCommand($s,$c),NewConnectionConflict::class,'Catalog bytes change with same version invalidates proof');$policy=$originalPolicy;
    $q=$db->prepare('UPDATE new_connection_application_revisions SET snapshot_sha256=? WHERE id=?');$q->execute([str_repeat('f',64),$c['expected']['revision_id']]);
    submitReject(fn()=>submitCommand($s,$c),RuntimeException::class,'Stored snapshot integrity failure');$q->execute([$c['expected']['revision_sha256'],$c['expected']['revision_id']]);
    $db->exec("UPDATE new_connection_stages SET status='in_progress' WHERE stage_code=2");$changed=submitState($db);submitReject(fn()=>submitCommand($s,$c),NewConnectionDenied::class,'Downstream prerequisite integrity');submitAssert(submitState($db)===$changed,'Inconsistent downstream rejection no partial writes');$db->exec("UPDATE new_connection_stages SET status='not_started' WHERE stage_code=2");
    submitAssert(submitState($db)===$baseline,'Every rejected command leaves no partial rows');
    // Failure points occur after writes, not mocked transaction behavior.
    foreach([
        'after_consents'=>"BEFORE UPDATE ON new_connection_application_revisions",
        'after_freeze'=>"BEFORE UPDATE ON new_connection_stages",
        'before_receipt'=>"BEFORE INSERT ON new_connection_submission_requests"
    ] as $point=>$trigger){
        $db->exec("CREATE TRIGGER nc_test_submission_failure $trigger FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic injected submission failure'");
        submitReject(fn()=>submitCommand($s,$c),PDOException::class,'Failure injection '.$point);
        submitAssert(!$db->inTransaction()&&submitState($db)===$baseline,'Full rollback '.$point);$db->exec('DROP TRIGGER nc_test_submission_failure');
    }
    $result=submitCommand($s,$c);submitAssert($result['status']==='submitted'&&$result['application_version']===2&&$result['stage_version']===1,'Valid atomic submission');
    submitAssert(submitCount($db,'consents')===4&&submitCount($db,'stage_events')===1&&submitCount($db,'submission_requests')===1,'Exactly four consents and one event/receipt');
    $app=$db->query('SELECT * FROM new_connection_applications LIMIT 1')->fetch();$rev=$db->query('SELECT * FROM new_connection_application_revisions LIMIT 1')->fetch();
    submitAssert($app['lifecycle_status']==='submitted'&&$rev['submission_state']==='submitted'&&$app['submitted_at']!==null&&$rev['submitted_at']!==null,'Lifecycle/revision freeze committed');
    submitAssert(hash('sha256',$rev['snapshot_json'])===$rev['snapshot_sha256']&&!str_contains($rev['snapshot_json'],$c['proof']),'Final hash correct and no raw proof');
    $evidence=$db->query('SELECT * FROM new_connection_consents ORDER BY acknowledgment_code')->fetchAll();
    foreach($evidence as $row){$notice=$policy['notices'][$row['acknowledgment_code']];submitAssert((int)$row['applicant_user_id']===$c['actor']&&$row['document_version']===$notice['version']&&$row['document_sha256']===hash('sha256',$notice['content'])&&$row['acknowledged_at']!==null,'Server-bound published test acknowledgment');}
    $state=submitState($db);$now+=301;submitAssert(submitCommand($s,$c)===$result,'Exact authorized retry survives proof expiry');$now-=301;
    submitAssert($s->submissionReceipt($c['actor'],$c['reference'])===$result,'Private receipt read returns original result');
    submitReject(fn()=>$s->submissionReceipt(100,$c['reference']),NewConnectionDenied::class,'Foreign actor cannot read receipt');
    submitReject(fn()=>$s->submissionReceipt(98,$c['reference']),NewConnectionDenied::class,'Inactive actor cannot read receipt');
    submitAssert(submitState($db)===$state,'Exact retry no duplicate history');
    $policy['notices']['privacy_notice']['published']=false;
    $retryService=new NewConnectionStage1Submission(draftTestConnection($schema),$schema,static function()use(&$policy){return $policy;},$secret);
    submitAssert(submitCommand($retryService,$c)===$result,'Fresh service replays authorized receipt despite later notice withdrawal');$policy=$originalPolicy;
    $bad=$c;$bad['selections'].=' ';submitReject(fn()=>submitCommand($s,$bad),NewConnectionConflict::class,'Changed payload same key');
    $bad=$c;$bad['key']=submitKey();submitReject(fn()=>submitCommand($s,$bad),NewConnectionDenied::class,'Different-key duplicate submission');
    $db->exec("UPDATE users SET status='inactive' WHERE id=".$c['actor']);submitReject(fn()=>submitCommand($s,$c),NewConnectionDenied::class,'Inactive user cannot replay receipt');$db->exec("UPDATE users SET status='active',email_verified_at=NULL WHERE id=".$c['actor']);submitReject(fn()=>submitCommand($s,$c),NewConnectionDenied::class,'Unverified user cannot replay receipt');$db->exec("UPDATE users SET email_verified_at='2026-10-20' WHERE id=".$c['actor']);
    submitReject(fn()=>$db->exec("UPDATE new_connection_application_revisions SET snapshot_json='{}' WHERE id=".$rev['id']),PDOException::class,'Submitted revision immutable');
    foreach(['consents','stage_events','audit_events','submission_requests'] as $table){submitReject(fn()=>$db->exec('DELETE FROM new_connection_'.$table),PDOException::class,'Immutable '.$table);}
    submitReject(fn()=>$db->exec("UPDATE new_connection_submission_requests SET receipt_json='{}'"),PDOException::class,'Submission receipt update immutable');
    submitReject(fn()=>$db->exec('INSERT INTO new_connection_submission_requests SELECT * FROM new_connection_submission_requests LIMIT 1'),PDOException::class,'Duplicate submission receipt constraint');
    // Incomplete/invalid catalog content is introduced ONLY through explicit fixture SQL.
    $incomplete=$s->create(90,submitKey());submitReject(fn()=>$s->review(90,$incomplete['reference'],submitExpected($incomplete)),NewConnectionDraftInvalid::class,'Missing required applicant fields');
    $bad=$incomplete;$bad=['actor'=>90,'reference'=>$incomplete['reference'],'expected'=>submitExpected($incomplete),'key'=>submitKey(),'proof'=>$c['proof'],'selections'=>submitSelections()];submitReject(fn()=>submitCommand($s,$bad),NewConnectionDraftInvalid::class,'Incomplete submission cannot bypass review');
    $invalid=submitFixture($s);$draft=$s->retrieve($invalid['actor'],$invalid['reference']);$snapshot=$draft['snapshot'];$snapshot['residential_address']['barangay_code']='TEST-B2';$canonical=NewConnectionApplicantContract::canonical($snapshot);$q=$db->prepare('UPDATE new_connection_application_revisions SET snapshot_json=?,snapshot_sha256=? WHERE id=?');$q->execute([$canonical,hash('sha256',$canonical),$invalid['expected']['revision_id']]);$expected=$invalid['expected'];$expected['revision_sha256']=hash('sha256',$canonical);
    submitReject(fn()=>$s->review($invalid['actor'],$invalid['reference'],$expected),NewConnectionDraftInvalid::class,'Invalid catalog relationship rejected');
    $invalid['expected']=$expected;submitReject(fn()=>submitCommand($s,$invalid),NewConnectionDraftInvalid::class,'Invalid catalog cannot submit');
    $stale=submitFixture($s);$new=$s->save($stale['actor'],$stale['reference'],$stale['expected'],submitKey(),$stale['input']);$stale['expected']=submitExpected($new);submitReject(fn()=>submitCommand($s,$stale),NewConnectionConflict::class,'Changed draft invalidates proof even if content hash unchanged');
    $foreign=submitFixture($s);$foreign['proof']=$stale['proof'];submitReject(fn()=>submitCommand($s,$foreign),NewConnectionConflict::class,'Valid proof cannot move between applications or actors');
    // Real processes race; parent waits with no transaction/row locks held.
    $race=submitFixture($s);$consents=submitCount($db,'consents');$events=submitCount($db,'stage_events');$other=$race;$other['key']=submitKey();
    $results=submitRace($schema,[$race,$other],$secret,$temp);$outcomes=array_column($results,'outcome');sort($outcomes);submitAssert($outcomes===['committed','conflict'],'Two submission attempts one outcome');
    submitAssert(submitCount($db,'consents')===$consents+4&&submitCount($db,'stage_events')===$events+1,'Concurrent submit no duplicate evidence');
    $race=submitFixture($s);$consents=submitCount($db,'consents');$results=submitRace($schema,[$race,$race],$secret,$temp);submitAssert($results[0]===$results[1]&&$results[0]['outcome']==='committed','Concurrent exact retries same authorized result');submitAssert(submitCount($db,'consents')===$consents+4,'Concurrent retry four consents only');
    $race=submitFixture($s);$save=$race;$save['operation']='save';$save['key']=submitKey();$results=submitRace($schema,[$save,$race],$secret,$temp);$outcomes=array_column($results,'outcome');sort($outcomes);submitAssert($outcomes===['committed','conflict'],'Save versus submit one serialized winner');
    $q=$db->prepare('SELECT a.lifecycle_status,a.lock_version,r.submission_state FROM new_connection_applications a JOIN new_connection_application_revisions r ON r.id=a.current_revision_id WHERE a.public_reference=?');$q->execute([$race['reference']]);$row=$q->fetch();
    $winner=$results[0]['outcome']==='committed'?'save':'submit';submitAssert((int)$row['lock_version']===2&&$row['lifecycle_status']===($winner==='save'?'draft':'submitted')&&$row['submission_state']===($winner==='save'?'draft':'submitted'),'Race persisted one consistent revision/lifecycle');
    // Force both orderings as well, independent of scheduler choice in the race.
    $ordered=submitFixture($s);$saved=$s->save($ordered['actor'],$ordered['reference'],$ordered['expected'],submitKey(),$ordered['input']);submitReject(fn()=>submitCommand($s,$ordered),NewConnectionConflict::class,'Save first makes submit stale');
    $ordered=submitFixture($s);submitCommand($s,$ordered);submitReject(fn()=>$s->save($ordered['actor'],$ordered['reference'],$ordered['expected'],submitKey(),$ordered['input']),NewConnectionDenied::class,'Submit first freezes out save');
    $safe=$db->query("SELECT safe_metadata_json FROM new_connection_audit_events WHERE action_code='stage1.submitted'")->fetchAll(PDO::FETCH_COLUMN);foreach($safe as $json)submitAssert(array_keys(json_decode($json,true))===['application_version','revision_no'],'Audit metadata strict allowlist');
    $receipts=$db->query('SELECT receipt_json FROM new_connection_submission_requests')->fetchAll(PDO::FETCH_COLUMN);foreach($receipts as $json)submitAssert(array_keys(json_decode($json,true))===['reference','application_version','revision_id','revision_no','revision_sha256','stage_version','status'],'Receipt contains no applicant data or proof');
    submitReject(fn()=>newConnectionSubmissionRollback($db),RuntimeException::class,'Populated rollback refused');newConnectionSubmissionMigrate($db);
    submitAssert($foundation===newConnectionSchemaSignature($db)&&$draftSignature===newConnectionDraftSignature($db),'Prior table definitions unchanged');
    $db->exec('ALTER TABLE new_connection_submission_requests ADD COLUMN synthetic_drift INT NULL');submitReject(fn()=>newConnectionSubmissionMigrate($db),RuntimeException::class,'Additive migration drift rejected');
    submitAssert(draftTestOtherSchemaSignature($server)===$before&&$server->query('SELECT DATABASE()')->fetchColumn()===null,'No nonfixture schema modification or portal connection');
    echo "Stage 1 submission passed ($checks assertions; $version; synthetic policy; disposable schema; real transactions and two-process concurrency).\n";
}catch(Throwable $e){fwrite(STDERR,'FAIL: '.get_class($e).' '.$e->getMessage()."\n");$exitCode=$e->getCode()===2?2:1;}
finally{
    if($created){if(!preg_match('/^mco_nc_test_[a-f0-9]{16}$/D',$schema))throw new LogicException('Unsafe cleanup.');$server->exec('DROP DATABASE `'.$schema.'`');echo "Disposable submission schema removed.\n";}
    foreach(glob($temp.'/*')?:[] as $file)unlink($file);rmdir($temp);
}
exit($exitCode);
