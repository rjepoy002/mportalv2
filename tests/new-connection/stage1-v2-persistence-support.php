<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/draft-test-support.php';require_once dirname(__DIR__,2).'/database/migrations/20261022_new_connection_v2_drafts.php';require_once dirname(__DIR__,2).'/app/services/new-connection/versioned-drafts.php';
function v2persistentContext():array {$context=require __DIR__.'/stage1-v2-data.php';$v1=require __DIR__.'/prototype-data.php';$context['v1_catalog']=$v1['catalog'];return $context;}
function v2persistentSeed(PDO $db):void {
    $db->exec('CREATE TABLE users(id BIGINT UNSIGNED PRIMARY KEY,first_name VARCHAR(25),middle_name VARCHAR(25),last_name VARCHAR(25),extension_name VARCHAR(25),contact_number VARCHAR(64),email VARCHAR(254) NOT NULL,status VARCHAR(20) NOT NULL,email_verified_at DATETIME NULL) ENGINE=InnoDB');
    $q=$db->prepare('INSERT INTO users VALUES(?,?,?,?,?,?,?,?,?)');foreach(range(1,4) as $id)$q->execute([$id,'Synthetic',null,'Owner'.$id,null,'09000000000','owner'.$id.'@example.invalid',$id===3?'inactive':'active',$id===4?null:'2026-10-22']);newConnectionV2DraftMigrate($db);
}
function v2persistentService(PDO $db,string $schema,?callable $clock=null):NewConnectionVersionedDrafts {return new NewConnectionVersionedDrafts($db,$schema,v2persistentContext(),new NewConnectionReviewKeys('test',['test'=>str_repeat('x',32)]),$clock);}
function v2expected(array $r):array {return array_intersect_key($r,array_flip(['application_version','revision_id','revision_no','revision_sha256']));}
function v2completeInput(NewConnectionVersionedDrafts $s,int $id=1):array {$input=NewConnectionApplicantContractV2::emptyInput();$input['personal']=['date_of_birth'=>'2000-01-01','gender'=>'prefer_not_to_say','civil_status'=>'single'];$input['service_location']=array_replace($input['service_location'],['city_municipality_psgc'=>'TEST-V2-C1','barangay_psgc'=>'TEST-V2-B1','street_purok_sitio'=>'Synthetic road','pin'=>['latitude'=>0.25,'longitude'=>0.25,'confirmed'=>true]]);$input['confirmations']=['profile_fingerprint'=>$s->profile($id)['profile_fingerprint'],'profile_confirmed'=>true,'information_confirmed'=>false];return $input;}
