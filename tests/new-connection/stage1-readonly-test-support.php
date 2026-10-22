<?php
// Loaded inside the existing isolated Apache coordinator before evaluating its fixture source.
$readonlySetup= <<<'SETUP'
    foreach(['new-connection-stage1-display.php','app/controllers/new-connection-stage1-display.php','app/helpers/new-connection-stage1-display.php','config/new-connection-stage1-display.php','app/services/new-connection/applicant-contract.php','app/helpers/philippine-mobile.php','app/views/pages/new-connection-prototype.php'] as $file){$to=$temporary.'/'.$file;if(!is_dir(dirname($to)))mkdir(dirname($to),0700,true);copy($root.'/'.$file,$to);}
    $identitySchema=$GLOBALS['identitySchema'];
    $repository='<?php session_save_path(__DIR__."/sessions"); $_SERVER["SERVER_ADDR"]="127.0.0.1";putenv("MCO_STAGE1_DISPLAY_ENABLED=1");putenv("APP_ENV=development");$c=json_decode(@file_get_contents(__DIR__."/display-control.json"),true)?:[];if(isset($c["enabled"]))putenv("MCO_STAGE1_DISPLAY_ENABLED=".$c["enabled"]);if(isset($c["environment"]))putenv("APP_ENV=".$c["environment"]);foreach(["REMOTE_ADDR","HTTP_HOST","HTTP_X_FORWARDED_FOR"] as $k)if(isset($c[$k]))$_SERVER[$k]=$c[$k];require __DIR__."/draft-test-support.php";$stage1DisplayIdentityRepository=static function(int $id):array|false{$db=draftTestConnection('.var_export($identitySchema,true).');$q=$db->prepare("SELECT id,name,email,status,email_verified_at FROM users WHERE id=?");$q->execute([$id]);return $q->fetch();}; ?>';
    copy($root.'/tests/new-connection/draft-test-support.php',$temporary.'/draft-test-support.php');
    file_put_contents($temporary.'/draft-test-support.php',str_replace("PHP_SAPI!=='cli'","PHP_SAPI!=='cli'&&PHP_SAPI!=='apache2handler'",file_get_contents($temporary.'/draft-test-support.php')));
    file_put_contents($temporary.'/new-connection-stage1-display.php',$repository.file_get_contents($temporary.'/new-connection-stage1-display.php'));
SETUP;
$source=str_replace("    file_put_contents(\$temporary . '/flag', '0');",$readonlySetup."\n    file_put_contents(\$temporary . '/flag', '0');",$source);
$readonlyTests= <<<'TESTS'
    [$body,$headers]=ncRequest('new-connection-stage1-display.php');ncCheck(str_contains($headers[0],'302'),'Read-only anonymous denial');
    foreach([2,3,99] as $id){[$body,$headers]=ncRequest('new-connection-stage1-display.php',ncSeed($id));ncCheck(str_contains($headers[0],'403'),'Read-only inactive/unverified/missing denied');}
    $sid=ncSeed(1);[$body,$headers]=ncRequest('new-connection-stage1-display.php',$sid);
    ncCheck(str_contains($headers[0],'200')&&str_contains($body,'Read-Only Development Display'),'Eligible zero-account display');
    ncCheck(!preg_match('/<(input|select|textarea|form)\b/i',$body),'No editable controls or forms');
    ncCheck(!str_contains($body,'src="assets/js/new-connection-prototype.js"')&&!str_contains($body,'prototype-context'),'No mutation JavaScript/context');
    ncCheck(in_array('Cache-Control: private, no-store',$headers,true),'Read-only no-store');
    ncCheck(substr_count($body,'data-step=')===5,'Five visible sections');
    $displayText=html_entity_decode($body,ENT_QUOTES|ENT_HTML5,'UTF-8');
    ncCheck(!str_contains($body,'Stage 1 prototype')&&str_contains($displayText,'Stage 1 — Applicant Information'),'Read-only page naming');
    ncCheck(!str_contains($body,'Save an incomplete')&&!str_contains($body,'Enter the synthetic applicant')&&!str_contains($body,'I have reviewed')&&!str_contains($body,'I understand that'),'No interactive instructions or first-person declarations');
    ncCheck(str_contains($displayText,'Privacy notice acknowledgment — approval pending')&&str_contains($displayText,' — provisional'),'Provisional fields and acknowledgments labeled');
    foreach(['POST','PUT','DELETE','PATCH'] as $method){[$b,$h]=ncRequest('new-connection-stage1-display.php',$sid,$method);ncCheck(str_contains($h[0],'405'),'Mutation rejected');}
    [$b,$h]=ncRequest('new-connection-stage1-display.php',$sid,'HEAD');ncCheck(str_contains($h[0],'200')&&$b==='','Display HEAD');
    foreach([['enabled'=>'0'],['environment'=>'production'],['REMOTE_ADDR'=>'192.0.2.1'],['HTTP_HOST'=>'public.invalid'],['HTTP_X_FORWARDED_FOR'=>'192.0.2.1']] as $control){file_put_contents($temporary.'/display-control.json',json_encode($control));[$b,$h]=ncRequest('new-connection-stage1-display.php',$sid);ncCheck(str_contains($h[0],'404'),'Display gate fails closed');}
    file_put_contents($temporary.'/display-control.json','{}');
    session_id($sid);session_start();$_SESSION['portal_last_seen']=time()-1801;session_write_close();[$b,$h]=ncRequest('new-connection-stage1-display.php',$sid);ncCheck(str_contains($h[0],'302'),'Read-only expiry denied');
    file_put_contents($temporary.'/preview-body.html',$body);
TESTS;
$source=str_replace('    // Export synthetic rendering only',$readonlyTests."\n    // Export synthetic rendering only",$source);
