<?php
// Only copied into the private temporary server root, whose router enforces loopback/host.
if(PHP_SAPI!=='cli-server'||!is_file(__DIR__.'/private/rehearsal.json')){http_response_code(404);exit;}
require_once __DIR__.'/isolated-identity-claim.php';
function fixtureSqlDB(): PDO
{
    global $cfg;return draftTestConnection($cfg['schema']);
}
function fixtureAtomicClaim(string $digest,int $expires,int $now): bool
{
    global $control;
    // Shared lock spans SQL commit; operator's exclusive gate change drains in-flight consumers.
    $gate=fopen(__DIR__.'/private/rehearsal.json','r');if(!$gate||!flock($gate,LOCK_SH))throw new RuntimeException('Claim unavailable');
    try {
    $external=json_decode(stream_get_contents($gate),true,16,JSON_THROW_ON_ERROR);
    if(($external['ready']??null)!==true || time()<($external['recover_after']??PHP_INT_MAX))throw new RuntimeException('Claim unavailable');
    $db=fixtureSqlDB();
    $hook=static function($point)use($control){
        if(!empty($control['pause_claim'])&&$point==='before_commit'){
            file_put_contents(__DIR__.'/private/claim-checkpoint','ready');$until=microtime(true)+12;
            while(!is_file(__DIR__.'/private/claim-release')){if(microtime(true)>$until)throw new RuntimeException('Claim checkpoint timeout');usleep(10000);}
        }
        if(!empty($control['claim_unknown'])&&$point==='after_commit')throw new RuntimeException('Uncertain result');
    };
    $adapter=new IsolatedSqlReturnClaims($db,$external['generation'],10000,2,8,$hook);
    if(!empty($control['claim_failure'])){
        $connection=(int)$db->query('SELECT CONNECTION_ID()')->fetchColumn();fixtureSqlDB()->exec('KILL CONNECTION '.$connection);
    }
    return $adapter->claim($digest,$expires,$now);
    } finally {flock($gate,LOCK_UN);fclose($gate);}
}
function fixtureRevalidate(bool $google): void
{
    global $user,$control;
    if(!empty($control['race'])||!empty($control['pause_before_claim'])){
        file_put_contents(__DIR__.'/private/checkpoint','ready');$until=microtime(true)+12;
        while(!is_file(__DIR__.'/private/release')){if(microtime(true)>$until)throw new RuntimeException('Checkpoint timeout');usleep(10000);}
    }
    $q=fixtureSqlDB()->prepare('SELECT status,email_verified_at FROM users WHERE id=?');$q->execute([$user['id']]);$current=$q->fetch();
    if(!$current||$current['status']!=='active'||(($control['google_policy']??'current')==='experimental'||!$google)&&$current['email_verified_at']===null)
        throw new RuntimeException('Authentication unavailable');
}
function fixtureProposedGoogle(PDO $db,object $info): array
{
    global $fixtureOAuthLink;
    $provider=new SyntheticProviderEvidence($info->id,strtolower(trim($info->email)),$info->verified_email===true);
    $link=is_array($fixtureOAuthLink)?$fixtureOAuthLink:null;
    $result=(new IsolatedGooglePolicy($db))->decide($provider,$link?'link':'login',$link['actor']??null,$link['proof']??null,session_id());
    if(!in_array($result['decision'],['authenticate','linked','already_bound'],true))fixtureAuthReply(403,['message'=>'Experimental identity policy denied sign-in.','decision'=>$result['decision']]);
    $q=$db->prepare('SELECT * FROM users WHERE google_id=?');$q->execute([$provider->subject]);$row=$q->fetch();
    if(!$row)throw new RuntimeException('Identity unavailable');return $row;
}
function fixtureExplicitLink(): never
{
    if($_SERVER['REQUEST_METHOD']!=='POST')fixtureAuthReply(405,[]);
    if(!portalAuthCsrfValid($_POST['csrf_token']??null))fixtureAuthReply(419,[]);
    $db=fixtureSqlDB();$q=$db->prepare('SELECT * FROM users WHERE id=?');$q->execute([$_SESSION['user_id']??0]);$u=$q->fetch();
    $password=$_POST['password']??null;
    if(!$u||$u['status']!=='active'||$u['email_verified_at']===null||!is_string($password)||!password_verify($password,$u['password_hash']))fixtureAuthReply(403,[]);
    // Local provider double supplies synthetic validated identity evidence, not browser identity fields.
    $info=json_decode(file_get_contents(__DIR__.'/private/provider.json'),true,16,JSON_THROW_ON_ERROR);
    $provider=new SyntheticProviderEvidence($info['id'],$info['email'],$info['verified_email']===true);
    $proof=(new IsolatedGooglePolicy($db))->issueLinkProof((int)$u['id'],$provider,session_id(),true,true);
    $_SESSION['rehearsal_link']=['actor'=>(int)$u['id'],'proof'=>$proof];
    fixtureAuthReply(200,['message'=>'Synthetic link authorization prepared; continue OAuth.']);
}
