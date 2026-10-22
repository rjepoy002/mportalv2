<?php
// Disposable-only additive lineage guards. No production override.
require_once __DIR__.'/20261020_new_connection_draft_receipts.php';
function newConnectionV2DraftSignature(PDO $pdo):string {
    $ddl=$pdo->query('SHOW CREATE TABLE new_connection_draft_conversions')->fetch(PDO::FETCH_NUM)[1];
    $triggers=$pdo->query("SELECT TRIGGER_NAME,ACTION_STATEMENT FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() AND TRIGGER_NAME LIKE 'nc7b_%' ORDER BY TRIGGER_NAME")->fetchAll(PDO::FETCH_ASSOC);
    return hash('sha256',json_encode([$ddl,$triggers],JSON_THROW_ON_ERROR));
}
function newConnectionV2DraftMigrate(PDO $pdo):void {
    $schema=newConnectionMigrationSchema($pdo);newConnectionDraftMigrate($pdo);
    $q=$pdo->prepare('SELECT GET_LOCK(?,5)');$q->execute(['nc7b:'.$schema]);if((int)$q->fetchColumn()!==1)throw new RuntimeException('Migration busy');
    try{
        $checksum=hash_file('sha256',__FILE__);$row=$pdo->query("SELECT * FROM new_connection_schema_migrations WHERE version='20261022_v2_drafts'")->fetch(PDO::FETCH_ASSOC);
        if($row){if($row['state']!=='ready'||!hash_equals($row['checksum'],$checksum)||!hash_equals($row['schema_signature'],newConnectionV2DraftSignature($pdo)))throw new RuntimeException('V2 migration requires recovery');return;}
        if((int)$pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='new_connection_draft_conversions'")->fetchColumn())throw new RuntimeException('Unowned conversion table');
        $q=$pdo->prepare("INSERT INTO new_connection_schema_migrations(version,checksum,state) VALUES('20261022_v2_drafts',?,'applying')");$q->execute([$checksum]);
        $pdo->exec("CREATE TABLE new_connection_draft_conversions(application_id BIGINT UNSIGNED NOT NULL,source_revision_id BIGINT UNSIGNED NOT NULL,target_revision_id BIGINT UNSIGNED NOT NULL,actor_user_id BIGINT UNSIGNED NOT NULL,occurred_at DATETIME(6) NOT NULL,PRIMARY KEY(source_revision_id),UNIQUE KEY nc7b_target(target_revision_id),CONSTRAINT nc7b_source FOREIGN KEY(application_id,source_revision_id) REFERENCES new_connection_application_revisions(application_id,id) ON DELETE RESTRICT,CONSTRAINT nc7b_target_fk FOREIGN KEY(application_id,target_revision_id) REFERENCES new_connection_application_revisions(application_id,id) ON DELETE RESTRICT,CONSTRAINT nc7b_owner FOREIGN KEY(application_id,actor_user_id) REFERENCES new_connection_applications(id,applicant_user_id) ON DELETE RESTRICT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        foreach(['UPDATE','DELETE'] as $event){$suffix=strtolower($event);
            $pdo->exec("CREATE TRIGGER nc7b_lineage_$suffix BEFORE $event ON new_connection_draft_conversions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conversion lineage is immutable'");
            $pdo->exec("CREATE TRIGGER nc7b_revision_$suffix BEFORE $event ON new_connection_application_revisions FOR EACH ROW BEGIN IF EXISTS(SELECT 1 FROM new_connection_draft_conversions WHERE source_revision_id=OLD.id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Converted source revision is immutable'; END IF; END");
        }
        $q=$pdo->prepare("UPDATE new_connection_schema_migrations SET state='ready',schema_signature=?,applied_at=UTC_TIMESTAMP(6) WHERE version='20261022_v2_drafts'");$q->execute([newConnectionV2DraftSignature($pdo)]);
    }finally{$q=$pdo->prepare('SELECT RELEASE_LOCK(?)');$q->execute(['nc7b:'.$schema]);}
}
function newConnectionV2DraftRollback(PDO $pdo):void {
    newConnectionMigrationSchema($pdo);$row=$pdo->query("SELECT * FROM new_connection_schema_migrations WHERE version='20261022_v2_drafts'")->fetch(PDO::FETCH_ASSOC);
    if(!$row||$row['state']!=='ready'||!hash_equals($row['schema_signature'],newConnectionV2DraftSignature($pdo)))throw new RuntimeException('Matching ready migration required');
    if((int)$pdo->query('SELECT COUNT(*) FROM new_connection_draft_conversions')->fetchColumn()||(int)$pdo->query('SELECT COUNT(*) FROM new_connection_application_revisions WHERE schema_version=2')->fetchColumn())throw new RuntimeException('Preserve populated v2 history');
    foreach(['lineage','revision'] as $type)foreach(['update','delete'] as $event)$pdo->exec("DROP TRIGGER nc7b_{$type}_{$event}");
    $pdo->exec('DROP TABLE new_connection_draft_conversions');$pdo->exec("DELETE FROM new_connection_schema_migrations WHERE version='20261022_v2_drafts'");
}
