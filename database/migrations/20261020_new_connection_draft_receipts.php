<?php
// Isolated-only additive migration. Never included by a portal route.
require_once __DIR__.'/20261020_new_connection_foundation.php';

function newConnectionDraftSignature(PDO $pdo): string
{
    $ddl=$pdo->query('SHOW CREATE TABLE new_connection_draft_requests')->fetch(PDO::FETCH_NUM)[1];
    $triggers=$pdo->query("SELECT TRIGGER_NAME,ACTION_TIMING,EVENT_MANIPULATION,ACTION_STATEMENT FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME LIKE 'nc5_%' ORDER BY TRIGGER_NAME")->fetchAll(PDO::FETCH_ASSOC);
    return hash('sha256',json_encode([$ddl,$triggers],JSON_THROW_ON_ERROR));
}

function newConnectionDraftMigrate(PDO $pdo): void
{
    $schema=newConnectionMigrationSchema($pdo);
    newConnectionMigrate($pdo); // Preserve the Task 04 checksum/drift check.
    $lock=$pdo->prepare('SELECT GET_LOCK(?,5)');$lock->execute(['nc5:'.$schema]);
    if((int)$lock->fetchColumn()!==1)throw new RuntimeException('Draft migration is busy.');
    try {
        $checksum=hash_file('sha256',__FILE__);
        $row=$pdo->query("SELECT * FROM new_connection_schema_migrations WHERE version='20261020_draft_receipts'")->fetch(PDO::FETCH_ASSOC);
        if($row){
            if($row['state']!=='ready'||!hash_equals($row['checksum'],$checksum)||!hash_equals($row['schema_signature'],newConnectionDraftSignature($pdo)))throw new RuntimeException('Draft migration requires manual recovery.');
            return;
        }
        if((int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='new_connection_draft_requests'")->fetchColumn())throw new RuntimeException('Unowned draft receipt table.');
        $q=$pdo->prepare("INSERT INTO new_connection_schema_migrations(version,checksum,state) VALUES('20261020_draft_receipts',?,'applying')");$q->execute([$checksum]);
        $pdo->exec("CREATE TABLE new_connection_draft_requests (
            application_id BIGINT UNSIGNED NOT NULL, actor_user_id BIGINT UNSIGNED NOT NULL,
            idempotency_key CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            request_sha256 CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
            receipt_json LONGTEXT NOT NULL, occurred_at DATETIME(6) NOT NULL,
            PRIMARY KEY(application_id,idempotency_key), KEY nc_draft_actor_time(actor_user_id,occurred_at),
            CONSTRAINT nc_draft_owner FOREIGN KEY(application_id,actor_user_id) REFERENCES new_connection_applications(id,applicant_user_id) ON DELETE RESTRICT,
            CHECK(JSON_VALID(receipt_json))
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        foreach(['UPDATE','DELETE'] as $event)$pdo->exec("CREATE TRIGGER nc5_receipt_".strtolower($event)." BEFORE $event ON new_connection_draft_requests FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Draft receipts are immutable'");
        $q=$pdo->prepare("UPDATE new_connection_schema_migrations SET state='ready',schema_signature=?,applied_at=UTC_TIMESTAMP(6) WHERE version='20261020_draft_receipts'");$q->execute([newConnectionDraftSignature($pdo)]);
    } finally {
        $q=$pdo->prepare('SELECT RELEASE_LOCK(?)');$q->execute(['nc5:'.$schema]);
    }
}

function newConnectionDraftRollback(PDO $pdo): void
{
    newConnectionMigrationSchema($pdo);
    $row=$pdo->query("SELECT state,schema_signature FROM new_connection_schema_migrations WHERE version='20261020_draft_receipts'")->fetch(PDO::FETCH_ASSOC);
    if(!$row||$row['state']!=='ready'||!hash_equals($row['schema_signature'],newConnectionDraftSignature($pdo)))throw new RuntimeException('Draft rollback requires a complete matching installation.');
    if((int)$pdo->query('SELECT COUNT(*) FROM new_connection_draft_requests')->fetchColumn())throw new RuntimeException('Preserve populated draft receipts.');
    $pdo->exec('DROP TABLE new_connection_draft_requests');
    $pdo->exec("DELETE FROM new_connection_schema_migrations WHERE version='20261020_draft_receipts'");
}
