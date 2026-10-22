<?php
// Included by disposable HTTP coordinator; never opens a connection or authenticates a password.
if(PHP_SAPI!=='cli'||!isset($db,$schema)){http_response_code(404);exit;}
require_once dirname(__DIR__,2).'/app/helpers/session-security.php';
require_once __DIR__.'/trusted-staging-context.php';
$contextChecks=0;
$assertContext=static function(bool $ok)use(&$contextChecks){$contextChecks++;if(!$ok)throw new RuntimeException('Trusted context assertion failed.');};
$denyContext=static function(callable $fn)use($assertContext){try{$fn();}catch(NewConnectionDenied $e){$assertContext(true);return;}throw new RuntimeException('Trusted context failed open.');};
$settings=['environment'=>'isolated-staging-preparation','enabled'=>true,'synthetic_only'=>true,'schema'=>$schema,'origin'=>'https://synthetic.example.invalid','base_path'=>'/portal'];
$ring=new NewConnectionReviewKeys('context-test',['context-test'=>random_bytes(32)]);
$context=new NewConnectionTrustedStagingContext($db,$settings,$ring);
$assertContext((int)$context->applicant(['user_id'=>1,'email'=>'forged@invalid'])['id']===1);
$assertContext($context->applicant(['user_id'=>1])['email']==='synthetic1@example.invalid');
foreach([[],['user_id'=>5],['user_id'=>6],['user_id'=>999]] as $session)$denyContext(fn()=>$context->applicant($session));
foreach([['enabled'=>false],['enabled'=>'1'],['environment'=>'production'],['synthetic_only'=>false],['schema'=>'mco_portal'],['schema'=>'mco_nc_test_0000000000000000'],['origin'=>'http://synthetic.example.invalid'],['origin'=>'https://user:pass@example.invalid'],['origin'=>'https://example.invalid/path'],['base_path'=>'//foreign.invalid'],['base_path'=>'/../']] as $bad)$denyContext(fn()=>new NewConnectionTrustedStagingContext($db,array_replace($settings,$bad),$ring));
$_SESSION=['auth_csrf'=>str_repeat('a',64)];$context->boundary($settings['origin'],$_SESSION['auth_csrf']);$assertContext(true);
$denyContext(fn()=>$context->boundary('https://foreign.invalid',$_SESSION['auth_csrf']));
$denyContext(fn()=>$context->boundary($settings['origin'],'wrong'));
$assertContext($context->returnPath('//foreign.invalid')==='/portal/new-connection.php');
$assertContext($context->returnPath('/portal/new-connection.php')==='/portal/new-connection.php');
$assertContext($context->service() instanceof NewConnectionStage1Submission);
$assertContext($db->query('SELECT @@SESSION.time_zone')->fetchColumn()==='+00:00');
$assertContext(str_contains($db->query('SELECT @@SESSION.sql_mode')->fetchColumn(),'STRICT_'));
$tables=$db->query('SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchAll(PDO::FETCH_COLUMN);
$assertContext(array_intersect($tables,['master','member','nc_users','billhistory','arledger'])===[]);
echo "Trusted staging context passed ($contextChecks assertions; synthetic identities, configured HTTPS origin/base path, no linked-account dependency).\n";
