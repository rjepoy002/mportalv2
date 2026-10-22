<?php
if(PHP_SAPI!=='apache2handler'||!is_file(__DIR__.'/private/config.json')){http_response_code(404);exit;}
function akClaimPDO(): PDO
{
    $c=json_decode(file_get_contents(__DIR__.'/private/claim-user.json'),true,16,JSON_THROW_ON_ERROR);
    if(!preg_match('/^mco_nc_test_[a-f0-9]{16}$/D',$c['schema']))throw new RuntimeException('Claim unavailable');
    $port=getenv('MCO_NC_TEST_PORT')?:'3306';if(!ctype_digit($port))throw new RuntimeException('Claim unavailable');
    $pdo=new PDO('mysql:host=127.0.0.1;port='.$port.';dbname='.$c['schema'].';charset=utf8mb4',$c['user'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $pdo->exec("SET time_zone='+00:00'");$pdo->exec("SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,NO_ENGINE_SUBSTITUTION'");
    return new DraftTestPDO($pdo,$c['schema']);
}
function akHtmlLink(string $route): string
{
    global $intent;$flow=fixtureAuthFlow();$selected=$intent->match($_SESSION,$flow,fixtureAuthNow())['status']==='module';
    return htmlspecialchars($route.($selected?'?auth_flow='.rawurlencode($flow):''),ENT_QUOTES,'UTF-8');
}
function akBeforeCapture(): void
{
    global $control;if(empty($control['apache_race']))return;
    file_put_contents(__DIR__.'/private/race-ready','ready');$until=microtime(true)+12;
    while(!is_file(__DIR__.'/private/race-release')){if(microtime(true)>$until)throw new RuntimeException('Race timeout');usleep(10000);}
}
function akLanding(array $user): never
{
    global $fixtureNonce;header('Content-Type: text/html; charset=utf-8');
    $portalActivePage='new-connection';$portalPageTitle='New Connection';$portalUserDisplayName='Synthetic Applicant';$portalUserEmail='';
    $stages=[['Applicant Information and Submission','Prepare and review information online.'],['Document Submission and Verification','Future supporting-document review.'],['Billing Clearance','Internal clearance.'],['Online PMES','Learning and assessment.'],['Technical Inspection','Staff-managed inspection.'],['Payment and Handwritten Form Signing','Offline payment and physical signatures.'],['Installation Scheduling','Final scheduling stage.']];
    require __DIR__.'/app/views/pages/new-connection.php';exit;
}
