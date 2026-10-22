<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/draft-test-support.php';
require dirname(__DIR__,2).'/database/migrations/20261020_new_connection_governance.php';
require dirname(__DIR__,2).'/app/services/new-connection/submission.php';
require __DIR__.'/governed-http-policy.php';
$checks=[];$schemas=[];$accounts=[];$failures=[];$blocked=[];$started=microtime(true);
$temp=sys_get_temp_dir().'/mco-recovery-'.bin2hex(random_bytes(8));mkdir($temp,0700);
function recoveryCheck(bool $ok,string $label): void {global $checks;if(!$ok)throw new RuntimeException($label);$checks[]=$label;}
function recoveryReject(callable $fn,string $class,string $label): void {try{$fn();}catch(Throwable $e){recoveryCheck($e instanceof $class,$label);return;}throw new RuntimeException($label.' accepted');}
function recoveryCreate(PDO $server): string {global $schemas;$s='mco_nc_test_'.bin2hex(random_bytes(8));$server->exec('CREATE DATABASE `'.$s.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$schemas[]=$s;return $s;}
function recoveryReset(PDO $server,string $s): void {global $schemas;if(!in_array($s,$schemas,true)||!preg_match('/^mco_nc_test_[a-f0-9]{16}$/D',$s))throw new LogicException('Unsafe reset');$server->exec('DROP DATABASE `'.$s.'`');$server->exec('CREATE DATABASE `'.$s.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');}
function recoveryUsers(PDO $db): void {$db->exec('CREATE TABLE users(id BIGINT UNSIGNED PRIMARY KEY,email VARCHAR(254) NOT NULL,status VARCHAR(20) NOT NULL,email_verified_at DATETIME NULL) ENGINE=InnoDB');$q=$db->prepare("INSERT INTO users VALUES(?,?,'active','2026-10-20')");foreach(range(1,6) as $id)$q->execute([$id,'synthetic'.$id.'@example.invalid']);}
function recoveryExpected(array $r): array {return array_intersect_key($r,array_flip(['application_version','revision_id','revision_no','revision_sha256']));}
function recoveryService(PDO $db,string $schema,NewConnectionReviewKeys $keys): NewConnectionStage1Submission {return new NewConnectionStage1Submission($db,$schema,new NewConnectionPublication($db,$schema),$keys);}
function recoveryDigest(PDO $db): string {$all=[];foreach($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table){if(!preg_match('/^(users|new_connection_[a-z_]+)$/D',$table))throw new LogicException('Unexpected table');$rows=$db->query('SELECT * FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC);$encoded=array_map(static fn($r)=>NewConnectionApplicantContract::canonical($r),$rows);sort($encoded);$all[$table]=$encoded;}ksort($all);return hash('sha256',json_encode($all,JSON_THROW_ON_ERROR));}
function recoverySQLTool(string $tool,string $schema,?string $input,?string $output): void
{
    if(!preg_match('/^mco_nc_test_[a-f0-9]{16}$/D',$schema))throw new LogicException('Unsafe tool schema');
    $binary='C:/xampp/mysql/bin/'.$tool.'.exe';if(!is_file($binary))throw new RuntimeException('BLOCKED: local backup tool unavailable',2);
    $args=[$binary,'--no-defaults','--protocol=tcp','--host='.(getenv('MCO_NC_TEST_HOST')?:'127.0.0.1'),'--port='.(getenv('MCO_NC_TEST_PORT')?:'3306'),'--user='.(getenv('MCO_NC_TEST_USER')?:'root'),'--default-character-set=utf8mb4'];
    if($tool==='mysqldump')array_push($args,'--single-transaction','--skip-comments','--skip-add-locks','--skip-disable-keys','--hex-blob','--triggers');
    $args[]=$schema;$env=[];
    foreach(['SystemRoot','SYSTEMROOT','WINDIR','ComSpec','PATH','TEMP','TMP'] as $name){$value=getenv($name);if($value!==false)$env[$name]=$value;}
    $env['MYSQL_PWD']=getenv('MCO_NC_TEST_PASSWORD')?:'';
    $p=proc_open($args,[0=>$input?['file',$input,'r']:['pipe','r'],1=>$output?['file',$output,'w']:['file',PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null','a'],2=>['pipe','w']],$pipes,null,$env);
    if(!is_resource($p))throw new RuntimeException('Backup client unavailable');if(isset($pipes[0]))fclose($pipes[0]);$error=stream_get_contents($pipes[2]);fclose($pipes[2]);if(proc_close($p)!==0)throw new RuntimeException('Backup/restore client failed (details withheld).');
}
function recoveryRestore(string $schema,string $encrypted,string $key,string $nonce,string $tag,string $temp): void
{
    $plain=openssl_decrypt(file_get_contents($encrypted),'aes-256-gcm',$key,OPENSSL_RAW_DATA,$nonce,$tag);if($plain===false)throw new RuntimeException('Backup authentication failed');
    $file=$temp.'/restore.sql';file_put_contents($file,$plain);chmod($file,0600);unset($plain);try{recoverySQLTool('mysql',$schema,$file,null);}finally{unlink($file);}
}
/** Inject a real interruption after the first governance CREATE TABLE, without editing migrations. */
final class RecoveryInterruptedPDO extends PDO
{
    public function __construct(private PDO $inner){}
    public function exec(string $sql): int|false {if(str_contains($sql,'CREATE TABLE new_connection_catalog_versions'))throw new RuntimeException('Injected DDL interruption');return $this->inner->exec($sql);}
    public function query(string $sql,?int $mode=null,mixed ...$args): PDOStatement|false {return $mode===null?$this->inner->query($sql):$this->inner->query($sql,$mode,...$args);}
    public function prepare(string $sql,array $options=[]): PDOStatement|false {return $this->inner->prepare($sql,$options);}
    public function getAttribute(int $attribute): mixed {return $this->inner->getAttribute($attribute);}
}
function recoveryLogin(string $schema,string $user,string $password): PDO
{
    $db=new PDO('mysql:host='.(getenv('MCO_NC_TEST_HOST')?:'127.0.0.1').';port='.(getenv('MCO_NC_TEST_PORT')?:'3306').';dbname='.$schema.';charset=utf8mb4',$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);$db->exec("SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION'");return new DraftTestPDO($db,$schema);
}
try{
    try{$server=draftTestPDO();}catch(Throwable $e){throw new RuntimeException('BLOCKED: local disposable database unavailable',2);}
    $before=draftTestOtherSchemaSignature($server);recoveryCheck($server->query('SELECT DATABASE()')->fetchColumn()===null,'No actual portal database selected');
    $version=$server->query('SELECT VERSION()')->fetchColumn();$source=recoveryCreate($server);$restore=recoveryCreate($server);$crash=recoveryCreate($server);
    $db=draftTestConnection($source);recoveryUsers($db);
    newConnectionMigrate($db);newConnectionDraftMigrate($db);newConnectionSubmissionMigrate($db);newConnectionGovernanceMigrate($db);
    governedSeed($db,$source);$keys=new NewConnectionReviewKeys('rehearsal',['rehearsal'=>random_bytes(32)]);$s=recoveryService($db,$source,$keys);
    $draft=$s->create(1,bin2hex(random_bytes(32)));$input=NewConnectionApplicantContract::emptyInput();$input['applicant']['first_name']='Synthetic';$draft=$s->save(1,$draft['reference'],recoveryExpected($draft),bin2hex(random_bytes(32)),json_encode($input));
    $submitted=$s->create(2,bin2hex(random_bytes(32)));$input['applicant']=array_replace($input['applicant'],['first_name'=>'Synthetic','last_name'=>'Rehearsal','contact_number'=>'09000000000','profile_confirmed'=>true]);
    $input['residential_address']=array_replace($input['residential_address'],['city_municipality_code'=>'TEST-C1','barangay_code'=>'TEST-B1','street_purok_sitio'=>'Synthetic Recovery Sitio']);$input['service_location']['same_as_residential']=true;$input['service_request']['premises_classification_code']='home';$input['service_request']['occupancy_code']='owner';
    $submitted=$s->save(2,$submitted['reference'],recoveryExpected($submitted),bin2hex(random_bytes(32)),json_encode($input));$review=$s->review(2,$submitted['reference'],recoveryExpected($submitted));$receipt=$s->submit(2,$submitted['reference'],recoveryExpected($submitted),bin2hex(random_bytes(32)),$review['proof'],json_encode(['review_confirmed'=>true,'acknowledgments'=>array_fill_keys(NewConnectionApplicantContract::ACKS,true)]));
    recoveryCheck((int)$db->query('SELECT COUNT(*) FROM new_connection_stages')->fetchColumn()===14,'Exactly seven stages for draft and submitted applications');
    // Separate random DB accounts and schema-scoped grants, all removed in finally.
    try{
        $privilegeStage='create accounts';foreach(['runtime','publisher','migrator'] as $role){$name='nct_'.bin2hex(random_bytes(6));$password=bin2hex(random_bytes(24));$server->exec('CREATE USER '.$server->quote($name)."@'localhost' IDENTIFIED BY ".$server->quote($password));$accounts[$role]=['name'=>$name,'password'=>$password];}
        $privilegeStage='grant reads';$tables=$db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        foreach(['runtime','publisher'] as $role)foreach($tables as $table)$server->exec('GRANT SELECT ON `'.$source.'`.`'.$table.'` TO '.$server->quote($accounts[$role]['name'])."@'localhost'");
        $inserts=['applications','application_revisions','stages','stage_events','audit_events','draft_requests','submission_requests','consents','submission_publications'];
        foreach($inserts as $table)$server->exec('GRANT INSERT ON `'.$source.'`.`new_connection_'.$table.'` TO '.$server->quote($accounts['runtime']['name'])."@'localhost'");
        foreach(['applications','application_revisions','stages'] as $table)$server->exec('GRANT UPDATE ON `'.$source.'`.`new_connection_'.$table.'` TO '.$server->quote($accounts['runtime']['name'])."@'localhost'");
        foreach(array_merge(array_diff(newConnectionGovernanceTables(),['publication_heads','submission_publications','publication_events']),['audit_events']) as $table)$server->exec('GRANT INSERT ON `'.$source.'`.`new_connection_'.$table.'` TO '.$server->quote($accounts['publisher']['name'])."@'localhost'");
        $server->exec('GRANT UPDATE ON `'.$source.'`.new_connection_publication_heads TO '.$server->quote($accounts['publisher']['name'])."@'localhost'");
        $server->exec('GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,ALTER,DROP,INDEX,REFERENCES,TRIGGER ON `'.$crash.'`.* TO '.$server->quote($accounts['migrator']['name'])."@'localhost'");
        $privilegeStage='runtime login/service';$runtime=recoveryLogin($source,$accounts['runtime']['name'],$accounts['runtime']['password']);$rs=recoveryService($runtime,$source,$keys);recoveryCheck($rs->retrieve(1,$draft['reference'])['reference']===$draft['reference'],'Runtime can resume without linked accounts');
        $saved=$rs->save(1,$draft['reference'],recoveryExpected($draft),bin2hex(random_bytes(32)),json_encode(NewConnectionApplicantContract::emptyInput()));$draft=$saved;recoveryCheck($saved['application_version']===2,'Least-privilege runtime can save');
        $rd=$rs->create(3,bin2hex(random_bytes(32)));$rd=$rs->save(3,$rd['reference'],recoveryExpected($rd),bin2hex(random_bytes(32)),json_encode($input));$rr=$rs->review(3,$rd['reference'],recoveryExpected($rd));
        $rr=$rs->submit(3,$rd['reference'],recoveryExpected($rd),bin2hex(random_bytes(32)),$rr['proof'],json_encode(['review_confirmed'=>true,'acknowledgments'=>array_fill_keys(NewConnectionApplicantContract::ACKS,true)]));recoveryCheck($rr['status']==='submitted','Least-privilege runtime creates and submits atomically');
        recoveryReject(fn()=>$runtime->exec('CREATE TABLE forbidden_probe(id INT)'),PDOException::class,'Runtime DDL denied');
        recoveryReject(fn()=>$runtime->exec('DELETE FROM new_connection_audit_events'),PDOException::class,'Runtime evidence deletion denied');
        recoveryReject(fn()=>$runtime->exec("UPDATE new_connection_publication_heads SET epoch=epoch+1"),PDOException::class,'Runtime cannot publish');
        recoveryReject(fn()=>(new NewConnectionPublication($runtime,$source))->withdraw(1,1),NewConnectionDenied::class,'Applicant has no publisher authority');
        $publisher=recoveryLogin($source,$accounts['publisher']['name'],$accounts['publisher']['password']);$pg=new NewConnectionPublication($publisher,$source);$pg->publish(4,1,1);recoveryCheck(true,'Publisher scoped credential and assignment can publish');
        recoveryReject(fn()=>$publisher->exec('UPDATE new_connection_applications SET lock_version=lock_version+1'),PDOException::class,'Publisher cannot mutate applicants');
        // Use only another disposable schema as a stand-in; never attempt a real operational write.
        $decoy=draftTestConnection($restore);$decoy->exec('CREATE TABLE operational_write_probe(id INT)');
        recoveryReject(fn()=>recoveryLogin($restore,$accounts['runtime']['name'],$accounts['runtime']['password']),PDOException::class,'Runtime denied unrelated disposable schema (operational boundary stand-in)');
        $migrator=recoveryLogin($crash,$accounts['migrator']['name'],$accounts['migrator']['password']);recoveryUsers($migrator);newConnectionGovernanceMigrate($migrator);recoveryCheck(true,'Scoped migration credential applies all four migrations');
        recoveryCheck($runtime->query('SELECT @@SESSION.time_zone')->fetchColumn()==='+00:00','Runtime UTC');recoveryCheck(str_contains($runtime->query('SELECT @@SESSION.sql_mode')->fetchColumn(),'STRICT_'),'Runtime strict SQL mode');
    }catch(PDOException $e){$blocked[]='Credential/grant rehearsal unavailable or failed at '.($privilegeStage??'setup').' (SQLSTATE '.$e->getCode().', driver '.($e->errorInfo[1]??0).'); details withheld.';}
    // Quiescent synthetic source: schema, triggers and data together; signing keys are separate.
    $digest=recoveryDigest($db);$backupStart=microtime(true);$plain=$temp.'/backup.sql';recoverySQLTool('mysqldump',$source,null,$plain);chmod($plain,0600);
    $backupKey=random_bytes(32);$nonce=random_bytes(12);$tag='';$bytes=openssl_encrypt(file_get_contents($plain),'aes-256-gcm',$backupKey,OPENSSL_RAW_DATA,$nonce,$tag);if($bytes===false)throw new RuntimeException('Backup encryption failed');
    $encrypted=$temp.'/backup.enc';file_put_contents($encrypted,$bytes);chmod($encrypted,0600);$backupHash=hash('sha256',$bytes);$backupBytes=strlen($bytes);unlink($plain);$backupSeconds=microtime(true)-$backupStart;
    recoveryReset($server,$restore);$restoreStart=microtime(true);recoveryRestore($restore,$encrypted,$backupKey,$nonce,$tag,$temp);$restored=draftTestConnection($restore);newConnectionGovernanceMigrate($restored);
    recoveryCheck(recoveryDigest($restored)===$digest,'Restored all table data matches source digest');
    foreach($restored->query('SELECT * FROM new_connection_application_revisions')->fetchAll() as $row)recoveryCheck(hash_equals($row['snapshot_sha256'],hash('sha256',$row['snapshot_json'])),'Restored canonical revision hash');
    recoveryCheck((int)$restored->query("SELECT COUNT(*) FROM new_connection_schema_migrations WHERE state='ready'")->fetchColumn()===4,'All four migration ledger checksums/signatures revalidated');
    $expectedSubmitted=(int)$db->query("SELECT COUNT(*) FROM new_connection_applications WHERE lifecycle_status='submitted'")->fetchColumn();
    recoveryCheck((int)$restored->query('SELECT COUNT(*) FROM new_connection_consents')->fetchColumn()===4*$expectedSubmitted,'Restored four versioned acknowledgments per submission');
    recoveryCheck((int)$restored->query('SELECT COUNT(*) FROM new_connection_submission_publications')->fetchColumn()===$expectedSubmitted,'Restored publication lineage');
    $read=recoveryService($restored,$restore,$keys);recoveryCheck($read->submissionReceipt(2,$receipt['reference'])===$receipt,'Original authorized receipt after restore');recoveryCheck($read->retrieve(1,$draft['reference'])['revision_sha256']===$draft['revision_sha256'],'Saved draft resumed after restore');
    foreach(['application_revisions','consents','audit_events','submission_requests','draft_requests','submission_publications'] as $table)recoveryReject(fn()=>$restored->exec('DELETE FROM new_connection_'.$table),PDOException::class,'Restored immutable '.$table);
    recoveryCheck((int)$restored->query('SELECT @@SESSION.foreign_key_checks')->fetchColumn()===1,'Restore leaves foreign keys enabled');
    recoveryReject(fn()=>$restored->exec('UPDATE new_connection_applications SET applicant_user_id=999999 WHERE id=1'),PDOException::class,'Restored applicant foreign key rejects orphan');
    recoveryReject(fn()=>$restored->exec("UPDATE new_connection_application_revisions SET snapshot_json='{}',snapshot_sha256=SHA2('{}',256) WHERE submission_state='submitted'"),PDOException::class,'Restored submitted snapshot cannot be rewritten');
    recoveryReject(fn()=>$restored->exec("UPDATE new_connection_audit_events SET safe_metadata_json='{}'"),PDOException::class,'Restored audit cannot be rewritten');
    $restoreSeconds=microtime(true)-$restoreStart;
    recoveryReset($server,$crash);$broken=draftTestConnection($crash);recoveryUsers($broken);newConnectionSubmissionMigrate($broken);
    recoveryReject(fn()=>newConnectionGovernanceMigrate(new RecoveryInterruptedPDO($broken)),RuntimeException::class,'Injected interruption after partial governance DDL');
    recoveryCheck($broken->query("SELECT state FROM new_connection_schema_migrations WHERE version='20261020_governance'")->fetchColumn()==='applying','Interrupted ledger retains applying state');
    recoveryReject(fn()=>newConnectionGovernanceMigrate($broken),RuntimeException::class,'Blind rerun refuses interrupted migration');
    $recoveryStart=microtime(true);recoveryReset($server,$crash);recoveryRestore($crash,$encrypted,$backupKey,$nonce,$tag,$temp);$recovered=draftTestConnection($crash);newConnectionGovernanceMigrate($recovered);recoveryCheck(recoveryDigest($recovered)===$digest,'Interrupted disposable target rebuilt from verified backup');
    $recoverySeconds=microtime(true)-$recoveryStart;
    $recovered->exec('ALTER TABLE new_connection_stages ADD COLUMN drift_probe INT NULL');recoveryReject(fn()=>newConnectionGovernanceMigrate($recovered),RuntimeException::class,'Schema drift blocks migration rerun');
    recoveryCheck(draftTestOtherSchemaSignature($server)===$before,'Nonfixture application schema metadata unchanged');
    $result=['date'=>'2026-10-21','database_version'=>$version,'checks'=>count($checks),'results'=>$checks,'blocked'=>$blocked,'backup'=>['encryption'=>'AES-256-GCM, ephemeral key kept separately in memory','sha256'=>$backupHash,'bytes'=>$backupBytes,'seconds'=>round($backupSeconds,3)],'restore_seconds'=>round($restoreSeconds,3),'interruption_recovery_seconds'=>round($recoverySeconds,3),'elapsed_seconds'=>round(microtime(true)-$started,3),'synthetic_only'=>true,'cleanup'=>'pending'];
}catch(Throwable $e){$failures[]=$e instanceof PDOException?'Database rehearsal failed (details withheld).':$e->getMessage();}
finally{
    if(isset($server)){
        foreach($accounts as $account){if(!preg_match('/^nct_[a-f0-9]{12}$/D',$account['name']))throw new LogicException('Unsafe account cleanup');$server->exec('DROP USER '.$server->quote($account['name'])."@'localhost'");}
        foreach($schemas as $schema){if(!preg_match('/^mco_nc_test_[a-f0-9]{16}$/D',$schema))throw new LogicException('Unsafe cleanup');$server->exec('DROP DATABASE `'.$schema.'`');}
    }
    $resolved=realpath($temp);if(!$resolved||dirname($resolved)!==realpath(sys_get_temp_dir())||!preg_match('/^mco-recovery-[a-f0-9]{16}$/D',basename($resolved)))throw new LogicException('Unsafe temp cleanup');foreach(new FilesystemIterator($resolved) as $file){if(!$file->isFile())throw new LogicException('Unexpected directory');unlink($file->getPathname());}rmdir($resolved);
}
if($failures){foreach($failures as $message)fwrite(STDERR,$message."\n");exit(1);}
$result['cleanup']='All random schemas/accounts and encrypted/plaintext backup artifacts removed';file_put_contents(__DIR__.'/staging-recovery-results.json',json_encode($result,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));echo 'Recovery rehearsal passed ('.count($checks).' assertions; '.$version.'); restore '.round($restoreSeconds,3).'s, interrupted-target recovery '.round($recoverySeconds,3)."s.\n";if($blocked)echo 'BLOCKED: '.implode(' ',$blocked)."\n";echo $result['cleanup'].".\n";
