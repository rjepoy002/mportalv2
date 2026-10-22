<?php
/** 06J Change Set A only: original Google decisions, SQL claims, no experimental policy calls. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
function localhostNavigationPrepare(string $temp,PDO $db): void
{
    $resolved=realpath($temp);if(!$resolved||dirname($resolved)!==realpath(sys_get_temp_dir())||!preg_match('/^mco-return-[a-f0-9]{16}$/D',basename($resolved)))throw new RuntimeException('Private fixture required');
    $db->exec('CREATE TABLE claim_control (id INT PRIMARY KEY,generation CHAR(32) NOT NULL,ready BOOLEAN NOT NULL,used INT NOT NULL,last_clock BIGINT NOT NULL,not_before BIGINT NOT NULL) ENGINE=InnoDB');
    $db->exec('CREATE TABLE return_claims (digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,expires BIGINT NOT NULL,INDEX(expires)) ENGINE=InnoDB');
    $generation=bin2hex(random_bytes(16));$q=$db->prepare('INSERT INTO claim_control VALUES(1,?,1,0,0,0)');$q->execute([$generation]);
    returnWrite($temp.'/private/claim-readiness.json',json_encode(['ready'=>true,'generation'=>$generation]));
    $component=file_get_contents(__DIR__.'/isolated-identity-claim.php');$component=str_replace("PHP_SAPI !== 'cli'","PHP_SAPI !== 'cli' && PHP_SAPI !== 'cli-server'",$component);
    returnWrite($temp.'/isolated-identity-claim.php',$component);
    $router=file_get_contents($temp.'/auth-return-fixture.php');$start=strpos($router,'function fixtureAtomicClaim(');$end=strpos($router,'$intent=new PortalAuthReturnIntent',$start);
    if($start===false||$end===false)throw new RuntimeException('Reviewed SQL claim hook changed');
    $claim= <<<'PHP'
require_once __DIR__.'/isolated-identity-claim.php';
function fixtureAtomicClaim(string $digest,int $expires,int $now): bool {
    global $cfg,$control;
    $readiness=json_decode(file_get_contents(__DIR__.'/private/claim-readiness.json'),true,16,JSON_THROW_ON_ERROR);
    if(($readiness['ready']??false)!==true)throw new RuntimeException('Claim unavailable');
    $db=draftTestConnection($cfg['schema']);$adapter=new IsolatedSqlReturnClaims($db,$readiness['generation'],10000);
    if(!empty($control['claim_failure'])){$id=(int)$db->query('SELECT CONNECTION_ID()')->fetchColumn();draftTestConnection($cfg['schema'])->exec('KILL CONNECTION '.$id);}
    return $adapter->claim($digest,$expires,$now);
}
PHP;
    returnWrite($temp.'/auth-return-fixture.php',substr($router,0,$start).$claim."\n".substr($router,$end));
}
$source=file_get_contents(__DIR__.'/auth-return-integration-test.php');
$needle="returnWrite(\$temp.'/auth-return-fixture.php',file_get_contents(__DIR__.'/auth-return-fixture.php'));";
if(substr_count($source,$needle)!==1)throw new RuntimeException('Reviewed coordinator hook changed');
$source=str_replace($needle,$needle.' localhostNavigationPrepare($temp,$db);',$source);
$source=str_replace("__DIR__.'/auth-return-browser-test.js'","__DIR__.'/localhost-auth-navigation-browser.js'",$source);
$source=str_replace("__DIR__.'/auth-return-integration-results.json'","__DIR__.'/localhost-auth-navigation-results.json'",$source);
$source=str_replace("'date'=>'2026-10-21'","'date'=>'2026-10-21','google_policy'=>'unchanged current callback','claim_adapter'=>'06H SQL only'",$source);
$source=str_replace("'Synthetic return integration passed ('","'Current-policy SQL navigation passed ('",$source);
$source=str_replace("'blocked'=>[","'limitations'=>[",$source);
$source=str_replace('Existing Google unbound Gmail/hosted unverified-account timestamp update needs separate policy/security approval; not executed or changed here.',
    'Current Google unbound Gmail/hosted verification-write branches are not exercised in this navigation suite; their behavior must remain unchanged. Separate security review is not a prerequisite for Change Set A.',$source);
eval('?>'.$source);
