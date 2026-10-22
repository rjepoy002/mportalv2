<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__,2).'/app/services/new-connection/workflow.php';
require_once dirname(__DIR__,2).'/database/migrations/20261020_new_connection_foundation.php';

/** Every fixture SQL statement is checked before reaching the connection. */
final class NCIsolatedPDO extends PDO
{
    public function __construct(private PDO $connection,private string $schema) {}
    private function guard(string $sql): void {
        if(preg_match('/\b(?:mco_portal|palecxzp_pal_db)\b/i',$sql))throw new LogicException('Live schema reference refused.');
        if(preg_match('/\b(?:CREATE|DROP)\s+DATABASE\b|\bUSE\s/i',$sql))throw new LogicException('Fixture connection cannot switch databases.');
        if($this->connection->query('SELECT DATABASE()')->fetchColumn()!==$this->schema)throw new LogicException('Isolated schema lost.');
    }
    public function exec(string $statement): int|false {$this->guard($statement);return $this->connection->exec($statement);}
    public function query(string $query,?int $fetchMode=null,mixed ...$fetchModeArgs): PDOStatement|false {$this->guard($query);return $fetchMode===null?$this->connection->query($query):$this->connection->query($query,$fetchMode,...$fetchModeArgs);}
    public function prepare(string $query,array $options=[]): PDOStatement|false {$this->guard($query);return $this->connection->prepare($query,$options);}
    public function beginTransaction(): bool {return $this->connection->beginTransaction();}
    public function commit(): bool {return $this->connection->commit();}
    public function rollBack(): bool {return $this->connection->rollBack();}
    public function inTransaction(): bool {return $this->connection->inTransaction();}
    public function lastInsertId(?string $name=null): string|false {return $this->connection->lastInsertId($name);}
}
function ncConnection(string $schema): NCIsolatedPDO {
    if(!preg_match('/^mco_nc_test_[a-f0-9]{16}$/D',$schema))throw new LogicException('Invalid disposable schema.');
    require dirname(__DIR__,2).'/config/database.php';
    $pdo->exec('USE `'.$schema.'`');
    $pdo->exec("SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION'");
    return new NCIsolatedPDO($pdo,$schema);
}
if(($argv[1]??'')==='worker'){
    $db=ncConnection($argv[2]);$flow=new NewConnectionWorkflow($db,$argv[2]);
    while(microtime(true)<(float)$argv[5])usleep(5000);
    try{$flow->transition((int)$argv[4],$argv[3],1,'approved',2,bin2hex(random_bytes(32)),'Synthetic concurrent decision');echo 'committed';}
    catch(NewConnectionConflict $e){echo 'conflict';}
    exit;
}
$checks=0;
function ncAssert(bool $ok,string $label): void {global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
function ncReject(callable $work,string $label,?string $class=null): void {
    try{$work();}catch(Throwable $e){ncAssert($class===null||$e instanceof $class,$label.' wrong rejection '.get_class($e));return;}
    throw new RuntimeException($label.' was accepted.');
}
function ncKey(): string{return bin2hex(random_bytes(32));}
function ncLiveSignature(PDO $pdo): string {
    $parts=[];
    foreach(['TABLES'=>['TABLE_SCHEMA','TABLE_NAME','TABLE_TYPE','ENGINE','TABLE_COLLATION'],
        'COLUMNS'=>['TABLE_SCHEMA','TABLE_NAME','COLUMN_NAME','COLUMN_TYPE','IS_NULLABLE','COLUMN_DEFAULT','EXTRA'],
        'STATISTICS'=>['TABLE_SCHEMA','TABLE_NAME','INDEX_NAME','NON_UNIQUE','SEQ_IN_INDEX','COLUMN_NAME'],
        'KEY_COLUMN_USAGE'=>['TABLE_SCHEMA','TABLE_NAME','CONSTRAINT_NAME','COLUMN_NAME','REFERENCED_TABLE_NAME','REFERENCED_COLUMN_NAME']] as $source=>$columns){
        $q=$pdo->query('SELECT '.implode(',',$columns).' FROM information_schema.'.$source." WHERE TABLE_SCHEMA IN ('mco_portal','palecxzp_pal_db') ORDER BY ".implode(',',$columns));$parts[]=$q->fetchAll(PDO::FETCH_ASSOC);
    }
    return hash('sha256',json_encode($parts,JSON_THROW_ON_ERROR));
}
require dirname(__DIR__,2).'/config/database.php'; // Metadata/bootstrap only, never business-row inspection.
$server=$pdo;$before=ncLiveSignature($server);
$schema='mco_nc_test_'.bin2hex(random_bytes(8));$created=false;
try{
    try{$server->exec('CREATE DATABASE `'.$schema.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$created=true;}
    catch(Throwable $e){echo "BLOCKED: disposable-schema CREATE DATABASE privilege unavailable; no live fallback.\n";exit(2);}
    $db=ncConnection($schema);
    $db->exec('CREATE TABLE users(id BIGINT UNSIGNED PRIMARY KEY,status VARCHAR(20) NOT NULL,email_verified_at DATETIME NULL) ENGINE=InnoDB');
    $db->exec("INSERT INTO users VALUES(1,'active','2026-10-20'),(2,'active','2026-10-20'),(3,'inactive','2026-10-20'),(4,'active','2026-10-20'),(5,'active','2026-10-20'),(6,'active','2026-10-20'),(7,'active',NULL),(8,'active','2026-10-20')");
    newConnectionMigrate($db);$signature=newConnectionSchemaSignature($db);newConnectionMigrate($db);
    ncAssert($signature===newConnectionSchemaSignature($db),'Migration repeat changed schema');
    ncAssert((int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE 'new_connection%' AND ENGINE='InnoDB' AND TABLE_COLLATION='utf8mb4_unicode_ci'")->fetchColumn()===12,'Storage engine/collation contract');
    ncAssert((int)$db->query("SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME LIKE 'nc4_%'")->fetchColumn()===15,'History triggers missing');
    ncAssert((int)$db->query('SELECT COUNT(*) FROM new_connection_staff_assignments')->fetchColumn()===0&&(int)$db->query('SELECT COUNT(*) FROM new_connection_roles')->fetchColumn()===0,'Migration must not provision staff');
    newConnectionRollback($db);ncAssert((int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE 'new_connection%'")->fetchColumn()===0,'Empty rollback failed');newConnectionMigrate($db);
    ncReject(fn()=>newConnectionMigrate($server),'Live migration guard',RuntimeException::class);
    $flow=new NewConnectionWorkflow($db,$schema);
    ncReject(fn()=>$flow->initialize(3,ncKey()),'Inactive applicant',NewConnectionDenied::class);
    ncReject(fn()=>$flow->initialize(7,ncKey()),'Unverified applicant',NewConnectionDenied::class);
    $key=ncKey();$ref=$flow->initialize(1,$key);ncAssert($flow->initialize(1,$key)===$ref,'Initialization not idempotent');
    $app=$db->query('SELECT * FROM new_connection_applications')->fetch(PDO::FETCH_ASSOC);$id=(int)$app['id'];
    ncAssert((int)$db->query('SELECT COUNT(*) FROM new_connection_stages')->fetchColumn()===7,'Not exactly seven stages');
    ncAssert(preg_match('/^[a-f0-9]{32}$/D',$ref)===1,'Reference not opaque');
    ncReject(fn()=>$db->exec("INSERT INTO new_connection_stages(application_id,stage_code,status,revision_id,updated_at) SELECT application_id,stage_code,status,revision_id,updated_at FROM new_connection_stages LIMIT 1"),'Duplicate stage',PDOException::class);
    ncReject(fn()=>$db->exec("INSERT INTO new_connection_applications(public_reference,applicant_user_id,creation_key,lifecycle_status,workflow_version,created_at,updated_at) SELECT public_reference,applicant_user_id,REPEAT('a',64),'draft',1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6) FROM new_connection_applications LIMIT 1"),'Duplicate reference',PDOException::class);
    ncReject(fn()=>$db->exec("INSERT INTO new_connection_applications(public_reference,applicant_user_id,creation_key,lifecycle_status,workflow_version,created_at,updated_at) VALUES(REPEAT('b',32),999,REPEAT('c',64),'draft',1,UTC_TIMESTAMP(6),UTC_TIMESTAMP(6))"),'Applicant FK',PDOException::class);
    ncReject(fn()=>$db->exec('UPDATE new_connection_stages SET stage_code=8 WHERE stage_code=7'),'Stage range CHECK',PDOException::class);
    ncReject(fn()=>$flow->transition(2,$ref,1,'submitted',0,ncKey(),'Synthetic unauthorized action'),'Other applicant mutation',NewConnectionDenied::class);
    ncReject(fn()=>$flow->transition(1,$ref,1,'approved',0,ncKey(),'Synthetic applicant approval'),'Applicant approval',NewConnectionDenied::class);
    $submit=ncKey();ncAssert($flow->transition(1,$ref,1,'submitted',0,$submit,'Synthetic submission')===1,'Owner submission');
    ncAssert($flow->transition(1,$ref,1,'submitted',0,$submit,'Synthetic submission')===1,'Submission replay not idempotent');
    ncReject(fn()=>$flow->transition(1,$ref,1,'submitted',0,$submit,'Different input'),'Conflicting request key',NewConnectionConflict::class);
    ncReject(fn()=>$db->exec("UPDATE new_connection_application_revisions SET snapshot_json='{}' WHERE id=".$app['current_revision_id']),'Submitted revision update',PDOException::class);
    ncReject(fn()=>$db->exec('DELETE FROM new_connection_application_revisions WHERE id='.$app['current_revision_id']),'Submitted revision delete',PDOException::class);
    $db->exec("INSERT INTO new_connection_roles(code,description) VALUES('test-reviewer','Synthetic role')");
    foreach(array_merge(array_map(fn($n)=>'stage.'.$n.'.manage',range(1,7)),['workflow.reopen']) as $permission){
        $q=$db->prepare('INSERT INTO new_connection_permissions(code,description) VALUES(?,?)');$q->execute([$permission,'Synthetic permission']);
    }
    $db->exec('INSERT INTO new_connection_role_permissions SELECT 1,id FROM new_connection_permissions');
    $db->exec("INSERT INTO new_connection_staff_assignments(user_id,role_id,office_scope,granted_by,granted_at) VALUES(4,1,'UNASSIGNED',6,UTC_TIMESTAMP(6)),(5,1,'UNASSIGNED',6,UTC_TIMESTAMP(6)),(8,1,'OTHER',6,UTC_TIMESTAMP(6))");
    ncReject(fn()=>$flow->transition(8,$ref,1,'under_review',1,ncKey(),'Wrong office'),'Office scope',NewConnectionDenied::class);
    ncReject(fn()=>$flow->transition(4,$ref,2,'in_progress',0,ncKey(),'Missing prerequisite'),'Prerequisite enforcement',DomainException::class);
    ncReject(fn()=>$flow->transition(4,$ref,1,'approved',1,ncKey(),'Invalid matrix'),'Matrix enforcement',DomainException::class);
    $flow->transition(4,$ref,1,'under_review',1,ncKey(),'Synthetic review');
    // Two independent processes race for the same expected version; only one may commit.
    $workers=[];$start=(string)(microtime(true)+1);
    foreach([4,5] as $actor){$p=proc_open([PHP_BINARY,__FILE__,'worker',$schema,$ref,(string)$actor,$start],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($p))throw new RuntimeException('Worker infrastructure unavailable.');fclose($pipes[0]);$workers[]=[$p,$pipes];}
    $out=[];foreach($workers as [$p,$pipes]){$out[]=trim(stream_get_contents($pipes[1]));fclose($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[2]);ncAssert(proc_close($p)===0&&$error==='','Concurrent worker error');}sort($out);ncAssert($out===['committed','conflict'],'Concurrent decision silently overwritten');
    ncAssert((int)$db->query("SELECT COUNT(*) FROM new_connection_stage_events WHERE to_status='approved'")->fetchColumn()===1,'Duplicate concurrent approval event');
    $flow->transition(4,$ref,2,'in_progress',0,ncKey(),'Synthetic stage start');
    $flow->transition(4,$ref,2,'submitted',1,ncKey(),'Synthetic stage submission');
    $flow->transition(4,$ref,2,'under_review',2,ncKey(),'Synthetic stage review');
    $flow->transition(4,$ref,2,'approved',3,ncKey(),'Synthetic stage approval');
    $oldRevision=(int)$db->query('SELECT current_revision_id FROM new_connection_applications WHERE id='.$id)->fetchColumn();
    $flow->transition(4,$ref,1,'in_progress',3,ncKey(),'Synthetic authorized reopening',true);
    ncAssert((int)$db->query("SELECT COUNT(*) FROM new_connection_stages WHERE application_id=$id AND stage_code>1 AND status='not_started' AND decided_at IS NULL")->fetchColumn()===6,'Downstream approvals not invalidated');
    ncAssert((int)$db->query('SELECT COUNT(*) FROM new_connection_application_revisions WHERE application_id='.$id)->fetchColumn()===2,'Reopen did not create a new revision');
    ncAssert((int)$db->query("SELECT COUNT(*) FROM new_connection_stage_events WHERE to_status='approved'")->fetchColumn()===2,'Historical decisions lost');
    foreach(['stage_events','audit_events','staff_assignment_events'] as $suffix){ncReject(fn()=>$db->exec('UPDATE new_connection_'.$suffix.' SET id=id'),'Immutable history '.$suffix,PDOException::class);ncReject(fn()=>$db->exec('DELETE FROM new_connection_'.$suffix),'History deletion '.$suffix,PDOException::class);}
    $q=$db->prepare("INSERT INTO new_connection_consents(application_id,revision_id,applicant_user_id,acknowledgment_code,document_version,document_sha256,acknowledged_at) VALUES(?,?,1,'online-notice','v1',?,UTC_TIMESTAMP(6))");$q->execute([$id,$oldRevision,str_repeat('d',64)]);
    ncReject(fn()=>$db->exec('DELETE FROM new_connection_consents'),'Acknowledgment immutable',PDOException::class);
    ncReject(fn()=>$db->exec("UPDATE new_connection_staff_assignments SET office_scope='*' WHERE user_id=8"),'Assignment cannot silently expand',PDOException::class);
    $db->exec("UPDATE new_connection_staff_assignments SET revoked_at=UTC_TIMESTAMP(6),revoked_by=6,revocation_reason='Synthetic revocation' WHERE user_id=4");
    ncAssert((int)$db->query("SELECT COUNT(*) FROM new_connection_staff_assignment_events WHERE event_type='revoked'")->fetchColumn()===1,'Revocation not recorded');
    ncReject(fn()=>$flow->transition(4,$ref,1,'submitted',4,ncKey(),'Revoked permission'),'Revocation not enforced',NewConnectionDenied::class);
    $db->exec("INSERT INTO new_connection_staff_assignments(user_id,role_id,office_scope,granted_by,granted_at,expires_at) VALUES(8,1,'UNASSIGNED',6,DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 2 DAY),DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 DAY))");
    ncReject(fn()=>$flow->transition(8,$ref,1,'submitted',4,ncKey(),'Expired assignment'),'Assignment expiry',NewConnectionDenied::class);
    $db->exec("INSERT INTO new_connection_staff_assignments(user_id,role_id,office_scope,granted_by,granted_at) VALUES(8,1,'UNASSIGNED',6,DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 1 DAY))");
    ncReject(fn()=>$flow->transition(8,$ref,1,'submitted',4,ncKey(),'Future assignment'),'Future assignment',NewConnectionDenied::class);
    // A normal correction also creates a new revision without changing the frozen one.
    $other=$flow->initialize(2,ncKey());
    $flow->transition(2,$other,1,'submitted',0,ncKey(),'Synthetic correction submission');
    $flow->transition(5,$other,1,'under_review',1,ncKey(),'Synthetic correction review');
    $flow->transition(5,$other,1,'needs_action',2,ncKey(),'Synthetic correction request');
    $correction=ncKey();$flow->transition(2,$other,1,'in_progress',3,$correction,'Synthetic applicant correction');
    ncAssert($flow->transition(2,$other,1,'in_progress',3,$correction,'Synthetic applicant correction')===4,'Owner correction replay failed');
    $otherApp=$db->query("SELECT * FROM new_connection_applications WHERE public_reference='$other'")->fetch(PDO::FETCH_ASSOC);
    ncAssert($otherApp['lifecycle_status']==='draft'&&(int)$otherApp['revision_no']===2,'Correction lifecycle/revision mismatch');
    ncReject(fn()=>$db->exec('UPDATE new_connection_stages SET revision_id='.$otherApp['current_revision_id'].' WHERE application_id='.$id),'Cross-application stage revision',PDOException::class);
    ncReject(fn()=>$db->exec('UPDATE new_connection_applications SET current_revision_id='.$otherApp['current_revision_id'].' WHERE id='.$id),'Cross-application current revision',PDOException::class);
    ncReject(fn()=>$db->exec("INSERT INTO new_connection_application_revisions(application_id,revision_no,schema_version,snapshot_json,snapshot_sha256,submission_state,created_by,created_at) VALUES($id,99,1,'invalid-json',REPEAT('a',64),'draft',1,UTC_TIMESTAMP(6))"),'Invalid snapshot JSON',PDOException::class);
    ncReject(fn()=>$db->exec('DELETE FROM new_connection_stages WHERE application_id='.$id),'Stage deletion',PDOException::class);
    ncReject(fn()=>$db->exec('DELETE FROM users WHERE id=1'),'Applicant deletion must preserve history',PDOException::class);
    ncReject(fn()=>$db->exec('DELETE FROM new_connection_applications WHERE id='.$id),'Application deletion must preserve history',PDOException::class);
    ncReject(fn()=>$db->exec('UPDATE new_connection_consents SET applicant_user_id=2'),'Acknowledgment update immutable',PDOException::class);
    ncReject(fn()=>$db->exec("INSERT INTO new_connection_consents(application_id,revision_id,applicant_user_id,acknowledgment_code,document_version,document_sha256,acknowledged_at) VALUES($id,$oldRevision,2,'other','v1',REPEAT('e',64),UTC_TIMESTAMP(6))"),'Acknowledgment ownership FK',PDOException::class);
    // Full synthetic path: lifecycle becomes scheduled only at the final stage.
    $flow->transition(1,$ref,1,'submitted',4,ncKey(),'Synthetic resubmission');
    $flow->transition(5,$ref,1,'under_review',5,ncKey(),'Synthetic re-review');
    $flow->transition(5,$ref,1,'approved',6,ncKey(),'Synthetic re-approval');
    for($code=2;$code<=7;$code++){
        $version=(int)$db->query("SELECT lock_version FROM new_connection_stages WHERE application_id=$id AND stage_code=$code")->fetchColumn();
        foreach(['in_progress','submitted','under_review','approved'] as $target)$version=$flow->transition(5,$ref,$code,$target,$version,ncKey(),'Synthetic prerequisite-only workflow test');
    }
    ncAssert($db->query('SELECT lifecycle_status FROM new_connection_applications WHERE id='.$id)->fetchColumn()==='scheduled','Final lifecycle not separate/scheduled');
    ncAssert((int)$db->query("SELECT COUNT(*) FROM new_connection_stages WHERE application_id=$id AND status='approved'")->fetchColumn()===7,'Full seven-stage path failed');
    $db->exec("INSERT INTO new_connection_roles(code,description) VALUES('stage-only','Synthetic role without reopen')");
    $db->exec("INSERT INTO new_connection_role_permissions SELECT 2,id FROM new_connection_permissions WHERE code='stage.1.manage'");
    $db->exec("INSERT INTO new_connection_staff_assignments(user_id,role_id,office_scope,granted_by,granted_at) VALUES(8,2,'UNASSIGNED',6,UTC_TIMESTAMP(6))");
    ncReject(fn()=>$flow->transition(8,$ref,1,'in_progress',7,ncKey(),'Synthetic unauthorized reopen',true),'Reopen permission separate from stage permission',NewConnectionDenied::class);
    $db->exec("UPDATE users SET status='inactive' WHERE id=5");
    ncReject(fn()=>$flow->transition(5,$ref,1,'in_progress',7,ncKey(),'Synthetic disabled staff',true),'Disabled staff identity',NewConnectionDenied::class);
    $db->exec("UPDATE users SET status='active' WHERE id=5");
    ncReject(fn()=>$db->exec("INSERT INTO new_connection_consents(application_id,revision_id,applicant_user_id,acknowledgment_code,document_version,document_sha256,acknowledged_at) VALUES($id,$oldRevision,1,'online-notice','v1',REPEAT('d',64),UTC_TIMESTAMP(6))"),'Duplicate acknowledgment version',PDOException::class);
    // Inject failure during initialization and prove every write rolls back.
    $db->exec("CREATE TRIGGER nc_test_fail BEFORE INSERT ON new_connection_stages FOR EACH ROW BEGIN IF NEW.stage_code=4 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Synthetic failure'; END IF; END");
    $count=(int)$db->query('SELECT COUNT(*) FROM new_connection_applications')->fetchColumn();
    $children=[];foreach(['application_revisions','stages','audit_events'] as $suffix)$children[$suffix]=(int)$db->query('SELECT COUNT(*) FROM new_connection_'.$suffix)->fetchColumn();
    ncReject(fn()=>$flow->initialize(2,ncKey()),'Transactional initialization failure',PDOException::class);
    ncAssert((int)$db->query('SELECT COUNT(*) FROM new_connection_applications')->fetchColumn()===$count,'Partial application survived rollback');$db->exec('DROP TRIGGER nc_test_fail');
    foreach($children as $suffix=>$expected)ncAssert((int)$db->query('SELECT COUNT(*) FROM new_connection_'.$suffix)->fetchColumn()===$expected,'Partial child rows survived rollback: '.$suffix);
    ncReject(fn()=>newConnectionRollback($db),'Populated rollback must preserve history',RuntimeException::class);
    newConnectionMigrate($db);ncAssert(true,'Rerun with data preserves schema/history');
    $db->exec('ALTER TABLE new_connection_roles ADD COLUMN synthetic_drift INT NULL');
    ncReject(fn()=>newConnectionMigrate($db),'Schema drift must fail closed',RuntimeException::class);
    ncAssert(ncLiveSignature($server)===$before,'Existing portal/operational schema metadata changed');
    echo "Database foundation passed ($checks assertions; disposable schema; real MariaDB FKs/triggers/transactions and two-process concurrency).\n";
}finally{
    if($created){
        if(!preg_match('/^mco_nc_test_[a-f0-9]{16}$/D',$schema))throw new LogicException('Unsafe cleanup name.');
        $server->exec('DROP DATABASE `'.$schema.'`');
    }
}
