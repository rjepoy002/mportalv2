<?php
require_once __DIR__.'/20261020_new_connection_submission_receipts.php';

function newConnectionGovernanceTables(): array
{
    return ['notice_versions','catalog_versions','publication_bundles','bundle_catalogs','bundle_notices','catalog_retirements','publication_heads','publication_events','submission_publications'];
}
function newConnectionGovernanceSignature(PDO $pdo): string
{
    $parts=[];foreach(newConnectionGovernanceTables() as $table){$ddl=$pdo->query('SHOW CREATE TABLE new_connection_'.$table)->fetch(PDO::FETCH_NUM)[1];$parts[]=preg_replace('/ AUTO_INCREMENT=\d+/','',$ddl);}
    $parts[]=$pdo->query("SELECT TRIGGER_NAME,ACTION_TIMING,EVENT_MANIPULATION,ACTION_STATEMENT FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME LIKE 'nc7_%' ORDER BY TRIGGER_NAME")->fetchAll(PDO::FETCH_ASSOC);
    return hash('sha256',json_encode($parts,JSON_THROW_ON_ERROR));
}
function newConnectionGovernanceMigrate(PDO $pdo): void
{
    $schema=newConnectionMigrationSchema($pdo);newConnectionSubmissionMigrate($pdo);
    $q=$pdo->prepare('SELECT GET_LOCK(?,5)');$q->execute(['nc7:'.$schema]);if((int)$q->fetchColumn()!==1)throw new RuntimeException('Governance migration busy.');
    try{
        $sql=file_get_contents(__DIR__.'/20261020_new_connection_governance.sql');$checksum=hash('sha256',$sql.file_get_contents(__FILE__));
        $row=$pdo->query("SELECT * FROM new_connection_schema_migrations WHERE version='20261020_governance'")->fetch(PDO::FETCH_ASSOC);
        if($row){if($row['state']!=='ready'||!hash_equals($row['checksum'],$checksum)||!hash_equals($row['schema_signature'],newConnectionGovernanceSignature($pdo)))throw new RuntimeException('Governance migration incomplete or drifted.');return;}
        foreach(newConnectionGovernanceTables() as $table){$q=$pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$q->execute(['new_connection_'.$table]);if($q->fetchColumn())throw new RuntimeException('Unowned governance table.');}
        $q=$pdo->prepare("INSERT INTO new_connection_schema_migrations(version,checksum,state) VALUES('20261020_governance',?,'applying')");$q->execute([$checksum]);
        foreach(explode('-- statement',$sql) as $ddl)$pdo->exec(trim($ddl));
        foreach(array_diff(newConnectionGovernanceTables(),['publication_heads']) as $table)foreach(['UPDATE','DELETE'] as $event)$pdo->exec("CREATE TRIGGER nc7_{$table}_".strtolower($event)." BEFORE $event ON new_connection_$table FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Immutable governance evidence'");
        $pdo->exec("CREATE TRIGGER nc7_head_update BEFORE UPDATE ON new_connection_publication_heads FOR EACH ROW BEGIN IF NEW.scope<>OLD.scope OR NEW.epoch<>OLD.epoch+1 OR NEW.updated_by IS NULL OR NEW.last_action IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Publication epoch must advance exactly once'; END IF; END");
        $pdo->exec("CREATE TRIGGER nc7_head_delete BEFORE DELETE ON new_connection_publication_heads FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Publication head cannot be deleted'");
        $pdo->exec("CREATE TRIGGER nc7_head_event AFTER UPDATE ON new_connection_publication_heads FOR EACH ROW INSERT INTO new_connection_publication_events(scope,epoch,action,state,bundle_id,catalog_id,actor_user_id,occurred_at) VALUES(NEW.scope,NEW.epoch,NEW.last_action,NEW.state,NEW.bundle_id,NEW.catalog_id,NEW.updated_by,UTC_TIMESTAMP(6))");
        $pdo->exec("INSERT INTO new_connection_publication_heads(scope,epoch,state,updated_at) VALUES('STAGE1',0,'withdrawn',UTC_TIMESTAMP(6))");
        $q=$pdo->prepare("UPDATE new_connection_schema_migrations SET state='ready',schema_signature=?,applied_at=UTC_TIMESTAMP(6) WHERE version='20261020_governance'");$q->execute([newConnectionGovernanceSignature($pdo)]);
    }finally{$q=$pdo->prepare('SELECT RELEASE_LOCK(?)');$q->execute(['nc7:'.$schema]);}
}
function newConnectionGovernanceRollback(PDO $pdo): void
{
    $schema=newConnectionMigrationSchema($pdo);$q=$pdo->prepare('SELECT GET_LOCK(?,5)');$q->execute(['nc7:'.$schema]);if((int)$q->fetchColumn()!==1)throw new RuntimeException('Governance migration busy.');
    try{
        $row=$pdo->query("SELECT * FROM new_connection_schema_migrations WHERE version='20261020_governance'")->fetch(PDO::FETCH_ASSOC);
        if(!$row||$row['state']!=='ready'||!hash_equals($row['schema_signature'],newConnectionGovernanceSignature($pdo)))throw new RuntimeException('Matching governance installation required.');
        foreach(array_diff(newConnectionGovernanceTables(),['publication_heads']) as $table)if((int)$pdo->query('SELECT COUNT(*) FROM new_connection_'.$table)->fetchColumn())throw new RuntimeException('Preserve populated governance history.');
        if((int)$pdo->query("SELECT epoch FROM new_connection_publication_heads WHERE scope='STAGE1'")->fetchColumn()!==0)throw new RuntimeException('Preserve used publication head.');
        foreach(['submission_publications','publication_events','publication_heads','catalog_retirements','bundle_notices','bundle_catalogs','publication_bundles','catalog_versions','notice_versions'] as $table){$name='new_connection_'.$table;$pdo->exec('DROP TABLE `'.$name.'`');}
        $pdo->exec("DELETE FROM new_connection_schema_migrations WHERE version='20261020_governance'");
    }finally{$q=$pdo->prepare('SELECT RELEASE_LOCK(?)');$q->execute(['nc7:'.$schema]);}
}
