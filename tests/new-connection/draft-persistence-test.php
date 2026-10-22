<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/draft-test-support.php';
require_once dirname(__DIR__,2).'/app/services/new-connection/drafts.php';
require_once dirname(__DIR__,2).'/database/migrations/20261020_new_connection_draft_receipts.php';
$context=require __DIR__.'/prototype-data.php';
function draftKey(): string {return bin2hex(random_bytes(32));}
function draftExpected(array $receipt): array {return array_intersect_key($receipt,array_flip(['application_version','revision_id','revision_no','revision_sha256']));}
function draftJSON(array $value): string {return json_encode($value,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);}
if(($argv[1]??'')==='worker'){
    try{
        $command=json_decode(file_get_contents($argv[3]),true,32,JSON_THROW_ON_ERROR);
        $service=new NewConnectionDrafts(draftTestConnection($argv[2]),$argv[2],$context['catalog']);
        while(microtime(true)<$command['start'])usleep(2000);
        $receipt=$service->save(1,$command['reference'],$command['expected'],$command['key'],$command['json']);
        echo json_encode(['result'=>'committed','receipt'=>$receipt],JSON_THROW_ON_ERROR);
    }catch(NewConnectionConflict $e){echo '{"result":"conflict"}';}
    catch(Throwable $e){fwrite(STDERR,'Fixture worker failed: '.get_class($e));exit(1);}
    exit;
}
$checks=0;
function draftAssert(bool $ok,string $label): void {global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
function draftReject(callable $call,string $class,string $label): void
{
    try{$call();}catch(Throwable $e){draftAssert($e instanceof $class,$label.' wrong exception: '.get_class($e));return;}
    throw new RuntimeException($label.' accepted');
}
function draftCount(PDO $db,string $table): int {return (int)$db->query('SELECT COUNT(*) FROM new_connection_'.$table)->fetchColumn();}
function draftDatabaseState(PDO $db): string
{
    $all=[];foreach(['applications','application_revisions','stages','audit_events','draft_requests'] as $table)$all[$table]=$db->query('SELECT * FROM new_connection_'.$table.' ORDER BY 1')->fetchAll();
    return hash('sha256',json_encode($all,JSON_THROW_ON_ERROR));
}
function draftRace(string $schema,array $commands,string $temp): array
{
    $workers=[];$start=microtime(true)+1;
    try{
        foreach($commands as $n=>$command){
            $file=$temp.'/command-'.$n.'.json';file_put_contents($file,draftJSON($command+['start'=>$start]));
            $proc=proc_open([PHP_BINARY,__FILE__,'worker',$schema,$file],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            if(!is_resource($proc))throw new RuntimeException('Worker unavailable.');fclose($pipes[0]);$workers[]=[$proc,$pipes];
        }
        $results=[];
        foreach($workers as [$proc,$pipes]){$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($proc)!==0)throw new RuntimeException($err);$results[]=json_decode($out,true,32,JSON_THROW_ON_ERROR);}
        $workers=[];return $results;
    }finally{foreach($workers as [$proc,$pipes])if(is_resource($proc)){proc_terminate($proc);proc_close($proc);}}
}
$schema='mco_nc_test_'.bin2hex(random_bytes(8));$created=false;$exitCode=0;$temp=sys_get_temp_dir().'/mco-draft-'.bin2hex(random_bytes(8));mkdir($temp,0700);
try{
    try{$server=draftTestPDO();}catch(Throwable $e){throw new RuntimeException('BLOCKED: local disposable MariaDB connection unavailable; no live fallback.',2);}
    draftAssert($server->query('SELECT DATABASE()')->fetchColumn()===null,'Bootstrap must select no portal database');
    $before=draftTestOtherSchemaSignature($server);$version=$server->query('SELECT VERSION()')->fetchColumn();
    try{$server->exec('CREATE DATABASE `'.$schema.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$created=true;}catch(Throwable $e){throw new RuntimeException('BLOCKED: disposable CREATE DATABASE privilege unavailable.',2);}
    $db=draftTestConnection($schema);
    $db->exec('CREATE TABLE users(id BIGINT UNSIGNED PRIMARY KEY,email VARCHAR(254) NOT NULL,status VARCHAR(20) NOT NULL,email_verified_at DATETIME NULL) ENGINE=InnoDB');
    $db->exec("INSERT INTO users VALUES(1,'prototype@example.invalid','active','2026-10-20'),(2,'other@example.invalid','active','2026-10-20'),(3,'inactive@example.invalid','inactive','2026-10-20'),(4,'unverified@example.invalid','active',NULL),(5,'limited@example.invalid','active','2026-10-20')");
    newConnectionMigrate($db);$original=newConnectionSchemaSignature($db);
    newConnectionDraftMigrate($db);$signature=newConnectionDraftSignature($db);newConnectionDraftMigrate($db);
    draftAssert($signature===newConnectionDraftSignature($db),'Additive migration rerun');
    draftAssert($original===newConnectionSchemaSignature($db),'Task 04 definitions unchanged');
    newConnectionDraftRollback($db);draftAssert($original===newConnectionSchemaSignature($db),'Empty additive rollback preserves foundation');newConnectionDraftMigrate($db);
    draftReject(fn()=>newConnectionDraftMigrate($server),RuntimeException::class,'Unselected schema migration denied');
    $service=new NewConnectionDrafts($db,$schema,$context['catalog']);
    foreach([0,3,4,999] as $user)draftReject(fn()=>$service->create($user,draftKey()),NewConnectionDenied::class,'Inactive/unverified/missing create');
    $creation=draftKey();$receipt=$service->create(1,$creation);$ref=$receipt['reference'];
    draftAssert($service->create(1,$creation)===$receipt,'Idempotent empty create');
    draftAssert(draftCount($db,'applications')===1&&draftCount($db,'stages')===7,'One application exactly seven stages');
    draftAssert(draftCount($db,'audit_events')===1,'Create retry no duplicate audit');
    $get=$service->retrieve(1,$ref);
    draftAssert($get['snapshot']['applicant']['email']==='prototype@example.invalid','Server email authoritative');
    draftAssert(draftCount($db,'audit_events')===1,'Read/resume no writes');
    foreach([2,3,4,999] as $user){
        draftReject(fn()=>$service->retrieve($user,$ref),NewConnectionDenied::class,'Cross-user or inactive read');
        draftReject(fn()=>$service->save($user,$ref,draftExpected($receipt),draftKey(),'{}'),NewConnectionDenied::class,'Cross-user or inactive save');
    }
    draftReject(fn()=>$service->retrieve(1,str_repeat('f',32)),NewConnectionDenied::class,'Unknown reference same denial');
    $input=NewConnectionApplicantContract::emptyInput();$input['applicant']['first_name']='Ana María';$input['applicant']['contact_number']='+63 900-000-0000';
    $input['review']['information_confirmed']=true;$input['acknowledgments']=array_fill_keys(NewConnectionApplicantContract::ACKS,true);
    $json=draftJSON($input);$expected=draftExpected($receipt);$saveKey=draftKey();$saved=$service->save(1,$ref,$expected,$saveKey,$json);
    draftAssert($saved['application_version']===1&&$saved['revision_id']===$receipt['revision_id'],'Incomplete draft saved with version increment');
    $get=$service->retrieve(1,$ref);draftAssert($get['snapshot']['applicant']['contact_number']==='09000000000','Persist canonical mobile');
    draftAssert(NewConnectionApplicantContract::validate(draftJSON($get['input']),$context)['valid'],'Resumed closed input can be validated without resending server fields');
    draftAssert(!isset($get['snapshot']['acknowledgments'])&&!$get['snapshot']['review']['information_confirmed'],'No durable draft acknowledgments/review consent');
    draftAssert(hash('sha256',NewConnectionApplicantContract::canonical($get['snapshot']))===$saved['revision_sha256'],'Persist exact canonical hash');
    $state=draftDatabaseState($db);
    draftAssert($service->create(1,$creation)===$receipt,'Create retry preserves original receipt after later save');
    draftAssert($service->save(1,$ref,$expected,$saveKey,$json)===$saved,'Exact retry returns original receipt');
    draftAssert(draftDatabaseState($db)===$state,'Exact retry no duplicate writes/audit');
    draftReject(fn()=>$service->save(2,$ref,$expected,$saveKey,$json),NewConnectionDenied::class,'Known retry key does not authorize another owner');
    draftReject(fn()=>$service->save(1,$ref,$expected,$saveKey,$json.' '),NewConnectionConflict::class,'Same key changed raw content');
    draftReject(fn()=>$service->save(1,$ref,draftExpected($saved),$saveKey,$json),NewConnectionConflict::class,'Same key changed expected versions');
    draftReject(fn()=>$service->save(1,$ref,$expected,draftKey(),$json),NewConnectionConflict::class,'Stale application token');
    foreach(['revision_id','revision_no','revision_sha256'] as $key){$bad=draftExpected($saved);$bad[$key]=$key==='revision_sha256'?str_repeat('f',64):$bad[$key]+1;draftReject(fn()=>$service->save(1,$ref,$bad,draftKey(),$json),NewConnectionConflict::class,'Stale revision '.$key);}
    $invalid=$input;$invalid['applicant']['email']='forged@example.invalid';
    foreach([draftJSON($invalid),'[]','{"applicant":{"first_name":42}}',str_repeat(' ',65537)] as $bad)draftReject(fn()=>$service->save(1,$ref,draftExpected($saved),draftKey(),$bad),NewConnectionDraftInvalid::class,'Malformed/authoritative/oversize draft');
    $bad=draftExpected($saved);$bad['applicant_user_id']=2;draftReject(fn()=>$service->save(1,$ref,$bad,draftKey(),$json),InvalidArgumentException::class,'Unknown concurrency token key');
    $bad=draftExpected($saved);$bad['application_version']='1';draftReject(fn()=>$service->save(1,$ref,$bad,draftKey(),$json),InvalidArgumentException::class,'Noninteger concurrency version');
    draftReject(fn()=>$service->save(1,$ref,draftExpected($saved),'short',$json),InvalidArgumentException::class,'Weak idempotency key');
    draftAssert(draftDatabaseState($db)===$state,'All rejected writes leave state intact');
    // Real independent PHP/PDO processes race with identical expected versions.
    $command=['reference'=>$ref,'expected'=>draftExpected($saved),'json'=>$json];
    $race=draftRace($schema,[$command+['key'=>draftKey()],$command+['key'=>draftKey()]],$temp);
    $outcomes=array_column($race,'result');sort($outcomes);draftAssert($outcomes===['committed','conflict'],'Concurrent stale-save conflict, one commit');
    $latest=$service->retrieve(1,$ref);draftAssert($latest['application_version']===2,'No lost concurrency version');
    draftAssert($service->save(1,$ref,$expected,$saveKey,$json)===$saved,'Old exact retry returns original result after later save');
    $command=['reference'=>$ref,'expected'=>draftExpected($latest),'json'=>$json,'key'=>draftKey()];$audit=draftCount($db,'audit_events');
    $race=draftRace($schema,[$command,$command],$temp);
    draftAssert($race[0]['result']==='committed'&&$race[0]===$race[1],'Concurrent identical retry same receipt');
    draftAssert(draftCount($db,'audit_events')===$audit+1,'Concurrent identical retry one audit');
    $latest=$service->retrieve(1,$ref);$state=draftDatabaseState($db);
    // Fail the final receipt insert, AFTER snapshot, application and audit writes.
    $db->exec("CREATE TRIGGER nc_test_receipt_failure BEFORE INSERT ON new_connection_draft_requests FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic receipt failure'");
    draftReject(fn()=>$service->save(1,$ref,draftExpected($latest),draftKey(),'{}'),PDOException::class,'Injected final write failure');
    draftAssert(!$db->inTransaction()&&draftDatabaseState($db)===$state,'Atomic rollback includes snapshot/version/audit/receipt');$db->exec('DROP TRIGGER nc_test_receipt_failure');
    $db->exec("CREATE TRIGGER nc_test_create_failure BEFORE INSERT ON new_connection_stages FOR EACH ROW BEGIN IF NEW.stage_code=4 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic stage failure'; END IF; END");
    draftReject(fn()=>$service->create(2,draftKey()),PDOException::class,'Injected initialization failure');draftAssert(draftDatabaseState($db)===$state,'Initialization rolls back every row');$db->exec('DROP TRIGGER nc_test_create_failure');
    // No PHP session belongs to the service: simulate lost auth, then a fresh service after reauthentication.
    draftReject(fn()=>$service->retrieve(0,$ref),NewConnectionDenied::class,'Expired/missing session identity');
    $resumed=(new NewConnectionDrafts(draftTestConnection($schema),$schema,$context['catalog']))->retrieve(1,$ref);
    draftAssert($resumed===$latest,'Reauthentication resumes last committed DB draft');
    $db->exec("UPDATE users SET status='inactive' WHERE id=1");draftReject(fn()=>$service->save(1,$ref,$expected,$saveKey,$json),NewConnectionDenied::class,'Retry revalidates active user');
    $db->exec("UPDATE users SET status='active',email_verified_at=NULL WHERE id=1");draftReject(fn()=>$service->retrieve(1,$ref),NewConnectionDenied::class,'Previously valid now unverified');
    $db->exec("UPDATE users SET email_verified_at='2026-10-20' WHERE id=1");
    $db->exec("UPDATE new_connection_applications SET lifecycle_status='withdrawn'");draftReject(fn()=>$service->save(1,$ref,draftExpected($latest),draftKey(),$json),NewConnectionDenied::class,'Noneditable lifecycle');$db->exec("UPDATE new_connection_applications SET lifecycle_status='draft'");
    $db->exec("UPDATE new_connection_stages SET status='under_review' WHERE stage_code=1");draftReject(fn()=>$service->save(1,$ref,draftExpected($latest),draftKey(),$json),NewConnectionDenied::class,'Stage not editable');$db->exec("UPDATE new_connection_stages SET status='in_progress' WHERE stage_code=1");
    $db->exec('UPDATE new_connection_application_revisions SET schema_version=2');draftReject(fn()=>$service->retrieve(1,$ref),NewConnectionDenied::class,'Unsupported snapshot version fails closed');$db->exec('UPDATE new_connection_application_revisions SET schema_version=1');
    $db->exec("UPDATE new_connection_application_revisions SET submission_state='submitted',submitted_at=UTC_TIMESTAMP(6)");
    $frozen=draftDatabaseState($db);draftReject(fn()=>$service->save(1,$ref,draftExpected($latest),draftKey(),$json),NewConnectionDenied::class,'Submitted revision cannot save');
    draftReject(fn()=>$service->save(1,$ref,$expected,$saveKey,$json),NewConnectionDenied::class,'Submitted retry still requires edit authorization');
    draftReject(fn()=>$db->exec("UPDATE new_connection_application_revisions SET snapshot_json='{}'"),PDOException::class,'DB submitted immutability trigger');
    draftAssert(draftDatabaseState($db)===$frozen,'Submitted evidence untouched');
    draftReject(fn()=>$db->exec("UPDATE new_connection_draft_requests SET receipt_json='{}'"),PDOException::class,'Receipt update immutable');
    draftReject(fn()=>$db->exec('DELETE FROM new_connection_draft_requests'),PDOException::class,'Receipt delete immutable');
    draftReject(fn()=>$db->exec('INSERT INTO new_connection_draft_requests SELECT * FROM new_connection_draft_requests LIMIT 1'),PDOException::class,'Duplicate receipt key constraint');
    $appId=(int)$db->query('SELECT id FROM new_connection_applications LIMIT 1')->fetchColumn();
    $insert=$db->prepare('INSERT INTO new_connection_draft_requests(application_id,actor_user_id,idempotency_key,request_sha256,receipt_json,occurred_at) VALUES(?,?,?,?,?,UTC_TIMESTAMP(6))');
    draftReject(fn()=>$insert->execute([$appId,2,draftKey(),str_repeat('a',64),'{}']),PDOException::class,'Receipt owner composite foreign key');
    draftReject(fn()=>$insert->execute([$appId,1,draftKey(),str_repeat('a',64),'not JSON']),PDOException::class,'Receipt JSON validity check');
    draftReject(fn()=>newConnectionDraftRollback($db),RuntimeException::class,'Populated receipt rollback refused');
    // Bound successful create/save traffic across all drafts for one applicant.
    $limited=$service->create(5,draftKey());for($n=0;$n<29;$n++)$limited=$service->save(5,$limited['reference'],draftExpected($limited),draftKey(),'{}');
    draftReject(fn()=>$service->save(5,$limited['reference'],draftExpected($limited),draftKey(),'{}'),NewConnectionDraftLimited::class,'Thirty writes per minute enforced');
    draftReject(fn()=>$service->create(5,draftKey()),NewConnectionDraftLimited::class,'Create shares applicant write budget');
    $limitedGet=$service->retrieve(5,$limited['reference']);draftAssert($limitedGet['application_version']===29,'Read allowed during write throttle');
    $metadata=$db->query('SELECT safe_metadata_json FROM new_connection_audit_events')->fetchAll(PDO::FETCH_COLUMN);
    foreach($metadata as $safe)draftAssert(array_keys(json_decode($safe,true))===['application_version','revision_no'],'Audit allowlist no personal data');
    draftAssert(!str_contains(implode('',$metadata),'Ana')&&!str_contains(implode('',$metadata),'@')&&!str_contains(implode('',$metadata),'09000000000'),'No personal values in audit metadata');
    $receipts=$db->query('SELECT receipt_json FROM new_connection_draft_requests')->fetchAll(PDO::FETCH_COLUMN);foreach($receipts as $safe)draftAssert(array_keys(json_decode($safe,true))===['reference','application_version','revision_id','revision_no','revision_sha256'],'Receipt metadata only');
    draftAssert(!str_contains(implode('',$db->statements),'Ana María')&&!str_contains(implode('',$db->statements),'+63 900'),'Service values use bound parameters, no applicant SQL literals');
    newConnectionDraftMigrate($db);draftAssert($original===newConnectionSchemaSignature($db),'Foundation compatible after populated additive rerun');
    $db->exec('ALTER TABLE new_connection_draft_requests ADD COLUMN synthetic_drift INT NULL');
    draftReject(fn()=>newConnectionDraftMigrate($db),RuntimeException::class,'Additive drift detection fails closed');
    draftAssert(draftTestOtherSchemaSignature($server)===$before,'All nonfixture schema metadata unchanged');
    draftAssert($server->query('SELECT DATABASE()')->fetchColumn()===null,'Admin connection never selected actual portal');
    echo "Draft persistence passed ($checks assertions; $version; disposable schema; two-process races; no portal connection, emails or operational queries).\n";
}catch(Throwable $e){fwrite(STDERR,'FAIL: '.get_class($e).' '.$e->getMessage()."\n");$exitCode=$e->getCode()===2?2:1;}
finally{
    if($created){if(!preg_match('/^mco_nc_test_[a-f0-9]{16}$/D',$schema))throw new LogicException('Unsafe cleanup.');$server->exec('DROP DATABASE `'.$schema.'`');echo "Disposable schema removed.\n";}
    foreach(glob($temp.'/*')?:[] as $file)unlink($file);rmdir($temp);
}
exit($exitCode);
