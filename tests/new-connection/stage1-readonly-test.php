<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/draft-test-support.php';
$identitySchema='mco_nc_test_'.bin2hex(random_bytes(8));$identityDB=draftTestPDO();
$identityDB->exec('CREATE DATABASE `'.$identitySchema.'` CHARACTER SET utf8mb4');
try {
 $identityConnection=draftTestConnection($identitySchema);
 $identityConnection->exec('CREATE TABLE users(id BIGINT UNSIGNED PRIMARY KEY,name VARCHAR(100),email VARCHAR(254),status VARCHAR(20),email_verified_at DATETIME NULL) ENGINE=InnoDB');
 $q=$identityConnection->prepare('INSERT INTO users VALUES(?,?,?,?,?)');
 foreach([[1,'active','2026-10-21'],[2,'inactive','2026-10-21'],[3,'active',null]] as [$id,$status,$verified])$q->execute([$id,'Synthetic Display','display@example.invalid',$status,$verified]);
 // Attestation is established by this coordinator: newly created random schema, three synthetic rows,
 // no customer configuration, no linked-account/application tables, fixed repository SQL.
 $outer=file_get_contents(__DIR__.'/localhost-preview-apache-test.php');
 $injection= <<<'INJECT'
require __DIR__.'/stage1-readonly-test-support.php';
INJECT;
 $outer=str_replace("eval('?>'.\$source);",$injection."\neval('?>'.\$source);",$outer);
 eval(substr($outer,5));
 if((int)$identityConnection->query('SELECT COUNT(*) FROM users')->fetchColumn()!==3)throw new RuntimeException('Identity rows changed');
 if(count($identityConnection->query('SHOW TABLES')->fetchAll())!==1)throw new RuntimeException('Unexpected application tables');
 echo "Disposable identity attestation: three synthetic users; no application/linked-account tables; removed after acceptance.\n";
} finally {$identityDB->exec('DROP DATABASE `'.$identitySchema.'`');}
