<?php
// Real copied route/middleware; existing foundation's SELECT-only PDO, no actual DB or customer data.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$source=file_get_contents(__DIR__.'/foundation-test.php');
$needle="'new-connection.php', 'config/new-connection.php', 'app/controllers/new-connection.php',";
if(substr_count($source,$needle)!==1)throw new RuntimeException('Foundation copy hook changed');
$source=str_replace($needle,$needle."\n        'new-connection-preview.php', 'app/controllers/new-connection-preview.php',",$source);
$setup= <<<'PHP'
    $previewConfig=file_get_contents($temporary.'/config/new-connection-preview.php');
    file_put_contents($temporary.'/config/new-connection-preview.php', <<<'BOOT'
<?php
$controls=json_decode(file_get_contents(__DIR__.'/../preview-control.json'),true);
putenv('MCO_NEW_CONNECTION_PREVIEW_ENABLED='.($controls['enabled']??'1'));
putenv('APP_ENV='.($controls['environment']??'development'));
foreach(['SERVER_ADDR','REMOTE_ADDR','HTTP_HOST'] as $key)if(isset($controls[$key]))$_SERVER[$key]=$controls[$key];
?>
BOOT
    .$previewConfig);
    file_put_contents($temporary.'/preview-control.json','{}');
    $entry=file_get_contents($temporary.'/new-connection-preview.php');
    file_put_contents($temporary.'/new-connection-preview.php', <<<'ENTRY'
<?php
$c=json_decode(file_get_contents(__DIR__.'/preview-control.json'),true);
session_save_path(__DIR__.'/sessions');
$_SERVER['SERVER_ADDR']=$c['SERVER_ADDR']??'127.0.0.1';
foreach(['REMOTE_ADDR','HTTP_HOST','HTTP_FORWARDED','HTTP_X_FORWARDED_FOR','HTTP_X_FORWARDED_HOST'] as $key)if(isset($c[$key]))$_SERVER[$key]=$c[$key];
?>
ENTRY
    .$entry);
PHP;
$source=str_replace("    file_put_contents(\$temporary . '/flag', '0');",$setup."\n    file_put_contents(\$temporary . '/flag', '0');",$source);
$tests= <<<'PHP'
    $database=file_get_contents($temporary.'/config/database.php');
    file_put_contents($temporary.'/config/database.php', '<?php $failureControl=json_decode(file_get_contents(__DIR__."/../preview-control.json"),true);if(!empty($failureControl["bootstrap_fail"]))exit("synthetic-connection-secret"); ?>'.$database);
    file_put_contents($temporary.'/flag','0');
    [$body,$headers]=ncRequest('new-connection-preview.php');
    ncCheck(str_contains($headers[0],'302')&&!str_contains($body,'Applicant Information'),'Anonymous preview exposes no private content.');
    $queriesBefore=file_get_contents($temporary.'/queries');
    foreach([2,3,4,99] as $identity){[$body,$headers]=ncRequest('new-connection-preview.php',ncSeed($identity));ncCheck(str_contains($headers[0],'403'),'Ineligible preview identity accepted.');}
    [$body,$headers]=ncRequest('new-connection-preview.php',ncSeed(98));ncCheck(str_contains($headers[0],'503')&&!str_contains($body,'Synthetic database'),'Preview infrastructure error exposed.');
    $previewSession=ncSeed(1);[$body,$headers]=ncRequest('new-connection-preview.php',$previewSession);
    ncCheck(str_contains($headers[0],'200'),'Active verified zero-account preview denied.');
    ncCheck(str_contains($body,'Development Preview — New Connection applications are not yet available.')&&str_contains($body,'does not accept applications'),'Preview notice missing.');
    ncCheck(substr_count($body,'class="nc-number"')===7&&str_contains($body,'Applicant Information and Submission')&&str_contains($body,'Payment and Handwritten Form Signing'),'Seven preview stages incorrect.');
    ncCheck(str_contains($body,'sign them by hand and make payment offline')&&str_contains($body,'does not mean the service has already been energized'),'Offline/signing/scheduling explanation missing.');
    ncCheck(str_contains($body,'href="new-connection-preview.php"')&&str_contains($body,'aria-current="page"')&&str_contains($body,'>Preview</span>'),'Preview navigation/active state missing.');
    ncCheck(!str_contains($body,'type="file"')&&!str_contains($body,'name="first_name"')&&!str_contains($body,'action="new-connection'),'Preview exposes applicant controls.');
    ncCheck(in_array('Cache-Control: private, no-store',$headers,true),'Preview private caching missing.');
    file_put_contents($temporary.'/preview-body.html',$body);
    [$head,$headers]=ncRequest('new-connection-preview.php',$previewSession,'HEAD');ncCheck(str_contains($headers[0],'200')&&$head==='','Preview HEAD invalid.');
    $queryCount=file_get_contents($temporary.'/queries');
    [$body,$headers]=ncRequest('new-connection-preview.php',$previewSession,'POST');ncCheck(str_contains($headers[0],'405')&&file_get_contents($temporary.'/queries')===$queryCount,'Preview POST touches identity or accepts writes.');
    foreach([['enabled'=>'0'],['enabled'=>'true'],['environment'=>'production'],['SERVER_ADDR'=>'192.0.2.1'],['REMOTE_ADDR'=>'192.0.2.2'],['HTTP_HOST'=>'public.example.invalid'],['HTTP_HOST'=>'localhost.evil.invalid'],['HTTP_FORWARDED'=>'for=192.0.2.1'],['HTTP_X_FORWARDED_FOR'=>'192.0.2.1'],['HTTP_X_FORWARDED_HOST'=>'public.invalid']] as $controls){file_put_contents($temporary.'/preview-control.json',json_encode($controls));[$body,$headers]=ncRequest('new-connection-preview.php',$previewSession);ncCheck(str_contains($headers[0],'404')&&file_get_contents($temporary.'/queries')===$queryCount,'Unavailable/nonlocal preview reaches identity/bootstrap.');}
    file_put_contents($temporary.'/preview-control.json','{}');
    file_put_contents($temporary.'/preview-control.json','{"bootstrap_fail":true}');
    [$body,$headers]=ncRequest('new-connection-preview.php',$previewSession);ncCheck(str_contains($headers[0],'503')&&!str_contains($body,'synthetic-connection-secret'),'Legacy bootstrap termination exposes connection details or succeeds.');
    file_put_contents($temporary.'/preview-control.json','{}');
    [$body,$headers]=ncRequest('new-connection-preview.php?MCO_NEW_CONNECTION_PREVIEW_ENABLED=0&environment=production',$previewSession);ncCheck(str_contains($headers[0],'200'),'Browser query controls preview environment.');
    [$body,$headers]=ncRequest('new-connection.php',$previewSession);ncCheck(str_contains($headers[0],'404'),'Preview enabled real application.');
    // Export synthetic rendering only for independent browser layout checks; no test route installed.
    $export=getenv('MCO_PREVIEW_TEST_RENDER');
    if($export!==false){
        $parent=realpath(dirname($export));$systemTemp=realpath(sys_get_temp_dir());
        if(!$parent||dirname($parent)!==$systemTemp||!preg_match('/^mco-preview-render-[a-zA-Z0-9_-]+$/D',basename($parent))||basename($export)!=='index.html')throw new LogicException('Unsafe synthetic export path');
        file_put_contents($export,file_get_contents($temporary.'/preview-body.html'));
    }
PHP;
$hook='    // Isolated tree has no mailer/account services: successful rendering cannot load them.';
if(substr_count($source,$hook)!==1)throw new RuntimeException('Foundation test seam changed');
$source=str_replace($hook,$tests."\n".$hook,$source);
$source=str_replace('New Connection foundation passed','Localhost preview security passed',$source);
eval('?>'.$source);
