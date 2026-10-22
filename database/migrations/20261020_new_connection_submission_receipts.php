<?php
// Disposable schemas only; no route includes this migration library.
require_once __DIR__.'/20261020_new_connection_draft_receipts.php';

function newConnectionSubmissionSignature(PDO $pdo): string
{
    $ddl=$pdo->query('SHOW CREATE TABLE new_connection_submission_requests')->fetch(PDO::FETCH_NUM)[1];
    $triggers=$pdo->query("SELECT TRIGGER_NAME,ACTION_TIMING,EVENT_MANIPULATION,ACTION_STATEMENT FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME LIKE 'nc6_%' ORDER BY TRIGGER_NAME")->fetchAll(PDO::FETCH_ASSOC);
    return hash('sha256',json_encode([$ddl,$triggers],JSON_THROW_ON_ERROR));
}
function newConnectionSubmissionMigrate(PDO $pdo): void
{
    $schema=newConnectionMigrationSchema($pdo);newConnectionDraftMigrate($pdo);
    $lock=$pdo->prepare('SELECT GET_LOCK(?,5)');$lock->execute(['nc6:'.$schema]);
    if((int)$lock->fetchColumn()!==1)throw new RuntimeException('Submission migration is busy.');
    try{
        $checksum=hash_file('sha256',__FILE__);
        $row=$pdo->query("SELECT * FROM new_connection_schema_migrations WHERE version='20261020_submission_receipts'")->fetch(PDO::FETCH_ASSOC);
        if($row){if($row['state']!=='ready'||!hash_equals($row['checksum'],$checksum)||!hash_equals($row['schema_signature'],newConnectionSubmissionSignature($pdo)))throw new RuntimeException('Submission migration requires manual recovery.');return;}
        if((int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='new_connection_submission_requests'")->fetchColumn())throw new RuntimeException('Unowned submission receipt table.');
        $q=$pdo->prepare("INSERT INTO new_connection_schema_migrations(version,checksum,state) VALUES('20261020_submission_receipts',?,'applying')");$q->execute([$checksum]);
        $pdo->exec("CREATE TABLE new_connection_submission_requests (
            application_id BIGINT UNSIGNED NOT NULL, actor_user_id BIGINT UNSIGNED NOT NULL, revision_id BIGINT UNSIGNED NOT NULL,
            idempotency_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            request_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            receipt_json LONGTEXT NOT NULL, occurred_at DATETIME(6) NOT NULL,
            PRIMARY KEY(application_id,idempotency_key), UNIQUE KEY nc_submit_revision(application_id,revision_id),
            CONSTRAINT nc_submit_owner FOREIGN KEY(application_id,actor_user_id) REFERENCES new_connection_applications(id,applicant_user_id) ON DELETE RESTRICT,
            CONSTRAINT nc_submit_revision FOREIGN KEY(application_id,revision_id) REFERENCES new_connection_application_revisions(application_id,id) ON DELETE RESTRICT,
            CHECK(JSON_VALID(receipt_json))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        foreach(['UPDATE','DELETE'] as $event)$pdo->exec("CREATE TRIGGER nc6_receipt_".strtolower($event)." BEFORE $event ON new_connection_submission_requests FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Submission receipts are immutable'");
        $q=$pdo->prepare("UPDATE new_connection_schema_migrations SET state='ready',schema_signature=?,applied_at=UTC_TIMESTAMP(6) WHERE version='20261020_submission_receipts'");$q->execute([newConnectionSubmissionSignature($pdo)]);
    }finally{$q=$pdo->prepare('SELECT RELEASE_LOCK(?)');$q->execute(['nc6:'.$schema]);}
}
function newConnectionSubmissionRollback(PDO $pdo): void
{
    $schema=newConnectionMigrationSchema($pdo);
    $lock=$pdo->prepare('SELECT GET_LOCK(?,5)');$lock->execute(['nc6:'.$schema]);
    if((int)$lock->fetchColumn()!==1)throw new RuntimeException('Submission migration is busy.');
    try{
        $row=$pdo->query("SELECT state,schema_signature FROM new_connection_schema_migrations WHERE version='20261020_submission_receipts'")->fetch(PDO::FETCH_ASSOC);
        if(!$row||$row['state']!=='ready'||!hash_equals($row['schema_signature'],newConnectionSubmissionSignature($pdo)))throw new RuntimeException('Submission rollback requires a matching installation.');
        if((int)$pdo->query('SELECT COUNT(*) FROM new_connection_submission_requests')->fetchColumn())throw new RuntimeException('Preserve populated submission receipts.');
        $pdo->exec('DROP TABLE new_connection_submission_requests');
        $pdo->exec("DELETE FROM new_connection_schema_migrations WHERE version='20261020_submission_receipts'");
    }finally{$q=$pdo->prepare('SELECT RELEASE_LOCK(?)');$q->execute(['nc6:'.$schema]);}
}
