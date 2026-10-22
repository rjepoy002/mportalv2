<?php
// Library for explicitly invoked CLI tests. No automatic execution or live switch.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function newConnectionMigrationSchema(PDO $pdo): string
{
    $schema=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if(!preg_match('/^mco_nc_test_[a-f0-9]{16}$/D',$schema)) throw new RuntimeException('Only a disposable New Connection test schema is permitted.');
    $version=(string)$pdo->query('SELECT VERSION()')->fetchColumn();
    $minimum=stripos($version,'MariaDB')!==false?'10.4.0':'8.0.16';
    if(version_compare(preg_replace('/[^0-9.].*/','',$version),$minimum,'<')) throw new RuntimeException('Database version not supported.');
    $q=$pdo->query("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='id'");
    if(!preg_match('/^bigint(?:\(20\))? unsigned$/i',(string)$q->fetchColumn()))throw new RuntimeException('users.id must be BIGINT UNSIGNED.');
    return $schema;
}

function newConnectionMigrationTables(): array
{
    return ['applications','application_revisions','stages','stage_events','audit_events','consents','roles','permissions','role_permissions','staff_assignments','staff_assignment_events'];
}

function newConnectionSchemaSignature(PDO $pdo): string
{
    $parts=[];
    foreach(array_merge(['schema_migrations'],newConnectionMigrationTables()) as $suffix){
        $row=$pdo->query('SHOW CREATE TABLE `new_connection_'.$suffix.'`')->fetch(PDO::FETCH_NUM);
        $parts[]=preg_replace('/ AUTO_INCREMENT=\d+/','',$row[1]);
    }
    $parts[]=$pdo->query("SELECT TRIGGER_NAME,ACTION_TIMING,EVENT_MANIPULATION,ACTION_STATEMENT FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME LIKE 'nc4_%' ORDER BY TRIGGER_NAME")->fetchAll(PDO::FETCH_ASSOC);
    return hash('sha256',json_encode($parts,JSON_THROW_ON_ERROR));
}

function newConnectionMigrate(PDO $pdo): void
{
    $schema=newConnectionMigrationSchema($pdo);
    $sql=file_get_contents(__DIR__.'/20261020_new_connection_foundation.sql');
    $checksum=hash('sha256',$sql.file_get_contents(__FILE__));
    $lock=$pdo->prepare('SELECT GET_LOCK(?,5)');$lock->execute(['nc4:'.$schema]);
    if((int)$lock->fetchColumn()!==1)throw new RuntimeException('Migration is already running.');
    try{
        $hasLedger=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='new_connection_schema_migrations'")->fetchColumn();
        if(!$hasLedger){
            if((int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE 'new_connection\\_%'")->fetchColumn())throw new RuntimeException('Unowned module tables already exist.');
        }
        $pdo->exec("CREATE TABLE IF NOT EXISTS new_connection_schema_migrations(version VARCHAR(64) PRIMARY KEY, checksum CHAR(64) NOT NULL, state ENUM('applying','ready') NOT NULL, schema_signature CHAR(64) NULL, applied_at DATETIME(6) NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $record=$pdo->query("SELECT * FROM new_connection_schema_migrations WHERE version='20261020_foundation'")->fetch(PDO::FETCH_ASSOC);
        if($record){
            if($record['state']!=='ready'||!hash_equals($record['checksum'],$checksum)||!hash_equals($record['schema_signature'],newConnectionSchemaSignature($pdo)))throw new RuntimeException('Partial migration, checksum mismatch or schema drift: manual recovery required.');
            return;
        }
        $q=$pdo->prepare("INSERT INTO new_connection_schema_migrations(version,checksum,state) VALUES('20261020_foundation',?,'applying')");$q->execute([$checksum]);
        foreach(explode('-- statement',$sql) as $statement)$pdo->exec(trim($statement));
        $pdo->exec('ALTER TABLE new_connection_applications ADD CONSTRAINT nc_app_current_revision FOREIGN KEY(id,current_revision_id) REFERENCES new_connection_application_revisions(application_id,id) ON DELETE RESTRICT');
        foreach(['stage_events','audit_events','consents','staff_assignment_events'] as $suffix){
            foreach(['UPDATE','DELETE'] as $event)$pdo->exec("CREATE TRIGGER nc4_{$suffix}_".strtolower($event)." BEFORE $event ON new_connection_$suffix FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Append-only module history'");
        }
        $pdo->exec("CREATE TRIGGER nc4_revision_update BEFORE UPDATE ON new_connection_application_revisions FOR EACH ROW BEGIN IF OLD.submission_state='submitted' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Submitted revision is immutable'; END IF; END");
        $pdo->exec("CREATE TRIGGER nc4_revision_delete BEFORE DELETE ON new_connection_application_revisions FOR EACH ROW BEGIN IF OLD.submission_state='submitted' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Submitted revision is immutable'; END IF; END");
        $pdo->exec("CREATE TRIGGER nc4_stage_delete BEFORE DELETE ON new_connection_stages FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Stage records cannot be deleted'");
        $pdo->exec("CREATE TRIGGER nc4_assignment_insert AFTER INSERT ON new_connection_staff_assignments FOR EACH ROW INSERT INTO new_connection_staff_assignment_events(assignment_id,actor_user_id,event_type,reason,occurred_at) VALUES(NEW.id,NEW.granted_by,'granted','Explicit provisioning',NEW.granted_at)");
        $pdo->exec("CREATE TRIGGER nc4_assignment_update BEFORE UPDATE ON new_connection_staff_assignments FOR EACH ROW BEGIN IF OLD.revoked_at IS NOT NULL OR NOT(NEW.user_id<=>OLD.user_id) OR NOT(NEW.role_id<=>OLD.role_id) OR NOT(NEW.office_scope<=>OLD.office_scope) OR NOT(NEW.granted_by<=>OLD.granted_by) OR NOT(NEW.granted_at<=>OLD.granted_at) OR NOT(NEW.expires_at<=>OLD.expires_at) OR NEW.revoked_at IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Assignments may only be revoked'; END IF; END");
        $pdo->exec("CREATE TRIGGER nc4_assignment_revoked AFTER UPDATE ON new_connection_staff_assignments FOR EACH ROW INSERT INTO new_connection_staff_assignment_events(assignment_id,actor_user_id,event_type,reason,occurred_at) VALUES(NEW.id,NEW.revoked_by,'revoked',NEW.revocation_reason,NEW.revoked_at)");
        $pdo->exec("CREATE TRIGGER nc4_assignment_delete BEFORE DELETE ON new_connection_staff_assignments FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Assignment history cannot be deleted'");
        $q=$pdo->prepare("UPDATE new_connection_schema_migrations SET state='ready',schema_signature=?,applied_at=UTC_TIMESTAMP(6) WHERE version='20261020_foundation'");$q->execute([newConnectionSchemaSignature($pdo)]);
    }finally{$q=$pdo->prepare('SELECT RELEASE_LOCK(?)');$q->execute(['nc4:'.$schema]);}
}

/** Empty disposable installations only. Populated history requires backup/restore review. */
function newConnectionRollback(PDO $pdo): void
{
    newConnectionMigrationSchema($pdo);
    foreach(newConnectionMigrationTables() as $suffix)if((int)$pdo->query('SELECT COUNT(*) FROM `new_connection_'.$suffix.'`')->fetchColumn())throw new RuntimeException('Refusing to drop populated module tables.');
    $pdo->exec('ALTER TABLE new_connection_applications DROP FOREIGN KEY nc_app_current_revision');
    foreach(['consents','stage_events','audit_events','staff_assignment_events','staff_assignments','role_permissions','permissions','roles','stages','application_revisions','applications','schema_migrations'] as $suffix){
        $table='new_connection_'.$suffix;
        $pdo->exec('DROP TABLE `'.$table.'`');
    }
}
