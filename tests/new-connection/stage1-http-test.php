<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/draft-test-support.php';require dirname(__DIR__,2).'/database/migrations/20261020_new_connection_submission_receipts.php';
$governed=defined('NC_GOVERNED_HTTP');
if($governed){require dirname(__DIR__,2).'/database/migrations/20261020_new_connection_governance.php';require dirname(__DIR__,2).'/app/services/new-connection/submission.php';require __DIR__.'/governed-http-policy.php';}
$root=dirname(__DIR__,2);$schema='mco_nc_test_'.bin2hex(random_bytes(8));$temp=sys_get_temp_dir().'/mco-http-'.bin2hex(random_bytes(8));mkdir($temp,0700);$servers=[];$created=false;$exitCode=0;
function httpFixtureRemove(string $dir): void {foreach(new FilesystemIterator($dir) as $entry){if($entry->isDir())httpFixtureRemove($entry->getPathname());else unlink($entry->getPathname());}rmdir($dir);}
try{
    try{$server=draftTestPDO();}catch(Throwable $e){throw new RuntimeException('BLOCKED: disposable MariaDB unavailable.',2);}
    $before=draftTestOtherSchemaSignature($server);if($server->query('SELECT DATABASE()')->fetchColumn()!==null)throw new RuntimeException('Bootstrap selected a database.');
    try{$server->exec('CREATE DATABASE `'.$schema.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$created=true;}catch(Throwable $e){throw new RuntimeException('BLOCKED: disposable database privileges unavailable.',2);}
    $db=draftTestConnection($schema);$db->exec('CREATE TABLE users(id BIGINT UNSIGNED PRIMARY KEY,email VARCHAR(254) NOT NULL,status VARCHAR(20) NOT NULL,email_verified_at DATETIME NULL) ENGINE=InnoDB');
    $insert=$db->prepare('INSERT INTO users VALUES(?,?,?,?)');foreach(range(1,6) as $id)$insert->execute([$id,'synthetic'.$id.'@example.invalid',$id===5?'inactive':'active',$id===6?null:'2026-10-20']);newConnectionSubmissionMigrate($db);if($governed)governedSeed($db,$schema);
    $files=['app/helpers/session-security.php','app/helpers/philippine-mobile.php','app/helpers/portal-navigation.php','app/helpers/new-connection-preview.php','config/new-connection-preview.php','app/services/new-connection/workflow.php','app/services/new-connection/applicant-contract.php','app/services/new-connection/drafts.php','app/services/new-connection/submission.php','app/services/new-connection/publication.php','app/services/new-connection/review-keys.php','app/views/pages/new-connection-prototype.php','app/views/partials/portal-sidebar.php','app/views/partials/portal-header.php','app/views/partials/session-action-dialogs.php'];
    foreach($files as $file){$to=$temp.'/'.$file;if(!is_dir(dirname($to)))mkdir(dirname($to),0700,true);copy($root.'/'.$file,$to);}
    foreach(['prototype-data.php','submission-test-policy.php','stage1-http-page.php','stage1-http-router.php','stage1-http-test-control.php'] as $file)copy(__DIR__.'/'.$file,$temp.'/'.$file);
    if($governed)foreach(['governed-http-policy.php','governed-http-control.php','governance-fixtures.php','trusted-staging-context.php'] as $file)copy(__DIR__.'/'.$file,$temp.'/'.$file);
    // Only this private copy permits the fixture web-server SAPI. No production config.
    file_put_contents($temp.'/draft-test-support.php',str_replace("PHP_SAPI!=='cli'","PHP_SAPI!=='cli'&&PHP_SAPI!=='cli-server'",file_get_contents(__DIR__.'/draft-test-support.php')));
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/assets',FilesystemIterator::SKIP_DOTS)) as $entry){if(!$entry->isFile())continue;$to=$temp.'/assets'.substr($entry->getPathname(),strlen($root.'/assets'));if(!is_dir(dirname($to)))mkdir(dirname($to),0700,true);copy($entry->getPathname(),$to);}
    foreach(['stage1-http-ui.js','stage1-http-login.js'] as $file)copy(__DIR__.'/'.$file,$temp.'/assets/js/'.$file);
    copy(__DIR__.'/stage1-http.css',$temp.'/assets/css/stage1-http.css');
    mkdir($temp.'/private/sessions',0700,true);file_put_contents($temp.'/private/control.json','{}');
    $hosts=[];for($n=0;$n<2;$n++){$socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);if(!$socket)throw new RuntimeException('No fixture port.');$hosts[]=stream_socket_get_name($socket,false);fclose($socket);}
    file_put_contents($temp.'/private/fixture.json',json_encode(['hosts'=>$hosts,'schema'=>$schema,'review_key'=>base64_encode(random_bytes(32)),'review_key2'=>base64_encode(random_bytes(32)),'governed'=>$governed],JSON_THROW_ON_ERROR));
    foreach($hosts as $host){$process=proc_open([PHP_BINARY,'-d','display_errors=0','-d','log_errors=0','-S',$host,'-t',$temp,$temp.'/stage1-http-router.php'],[0=>['pipe','r'],1=>['file',PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null','a'],2=>['file',PHP_OS_FAMILY==='Windows'?'NUL':'/dev/null','a']],$pipes,$temp);if(!is_resource($process))throw new RuntimeException('No HTTP server.');fclose($pipes[0]);$servers[]=$process;
        $deadline=microtime(true)+8;do{$ready=@stream_socket_client('tcp://'.$host,$errno,$error,.2);if($ready){fclose($ready);break;}usleep(20000);}while(microtime(true)<$deadline);if(!$ready)throw new RuntimeException('HTTP fixture unavailable.');}
    file_put_contents($temp.'/browser.json',json_encode(['base'=>'http://'.$hosts[0],'second'=>'http://'.$hosts[1],'root'=>$root,'temp'=>$temp],JSON_THROW_ON_ERROR));
    if (defined('NC_MANUAL_DEVELOPMENT')) {
        echo "Development Test Only — Do not enter real personal information.\nPreview URL: http://{$hosts[0]}/login\nDisposable schema: $schema\nTemporary resources: $temp\nSynthetic login: demo-a / fixture-only (other / fixture-only for ownership checks).\nType q then Enter to stop and remove the disposable schema and temporary files. EOF also stops. Do not close/kill this console; forced termination may prevent cleanup.\n";
        while (($line=fgets(STDIN))!==false) { if (trim($line)==='q') break; }
    } else {
    $process=proc_open(['C:/Program Files/nodejs/node.exe',__DIR__.(defined('NC_STAGING_RECOVERY_HTTP')?'/staging-recovery-browser-test.js':($governed?'/governed-http-browser-test.js':'/stage1-http-browser-test.js')),$temp.'/browser.json'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($process))throw new RuntimeException('No Chrome test runner.');fclose($pipes[0]);$output=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);if(proc_close($process)!==0)throw new RuntimeException('Browser acceptance failed: '.$error);echo $output;
    }
    $applications=$db->query('SELECT id,lifecycle_status,current_revision_id FROM new_connection_applications')->fetchAll();$submitted=0;
    foreach($applications as $app){$q=$db->prepare('SELECT COUNT(*) FROM new_connection_stages WHERE application_id=?');$q->execute([$app['id']]);if((int)$q->fetchColumn()!==7)throw new RuntimeException('Seven stages invariant.');
        if($app['lifecycle_status']!=='submitted')continue;$submitted++;foreach(['consents'=>4,'stage_events'=>1,'submission_requests'=>1] as $table=>$count){$q=$db->prepare('SELECT COUNT(*) FROM new_connection_'.$table.' WHERE application_id=?');$q->execute([$app['id']]);if((int)$q->fetchColumn()!==$count)throw new RuntimeException('Duplicate/missing submitted evidence.');}}
    if($governed){
        $count=(int)$db->query('SELECT COUNT(*) FROM new_connection_submission_publications')->fetchColumn();if($count!==$submitted)throw new RuntimeException('Missing publication lineage.');
        $secrets=json_decode(file_get_contents($temp.'/private/fixture.json'),true,32,JSON_THROW_ON_ERROR);
        $retained='';foreach(['application_revisions','audit_events','draft_requests','submission_requests','submission_publications'] as $table)$retained.=json_encode($db->query('SELECT * FROM new_connection_'.$table)->fetchAll(),JSON_THROW_ON_ERROR);
        foreach(['review_key','review_key2'] as $name)if(str_contains($retained,$secrets[$name]))throw new RuntimeException('Private key leaked into retained records.');
        foreach($db->query('SELECT safe_metadata_json FROM new_connection_audit_events')->fetchAll(PDO::FETCH_COLUMN) as $metadata)if(preg_match('/@|Synthetic Saved|09000000000|first_name|proof|selections/',$metadata))throw new RuntimeException('Unsafe audit metadata.');
        require __DIR__.'/trusted-staging-context-test.php';
    }
    if(!defined('NC_MANUAL_DEVELOPMENT')&&$submitted<3)throw new RuntimeException('Browser did not complete all viewport submissions.');
    if($server->query('SELECT DATABASE()')->fetchColumn()!==null||draftTestOtherSchemaSignature($server)!==$before)throw new RuntimeException('Nonfixture schema changed.');
    foreach(['config/database.php','config/mail.php','app/controllers/dashboard.php'] as $forbidden)if(file_exists($temp.'/'.$forbidden))throw new RuntimeException('Forbidden fixture file.');
    echo "Database HTTP checks passed: ".count($applications)." synthetic applications, $submitted submitted; seven stages each, four consents/one event/one receipt per submission; nonfixture schemas unchanged.\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage()."\n");$exitCode=$e->getCode()===2?2:1;}
finally{
    foreach($servers as $process)if(is_resource($process)){proc_terminate($process);proc_close($process);}
    if($created){if(!preg_match('/^mco_nc_test_[a-f0-9]{16}$/D',$schema))throw new RuntimeException('Unsafe schema cleanup.');$server->exec('DROP DATABASE `'.$schema.'`');echo "Disposable HTTP schema removed.\n";}
    $resolved=realpath($temp);$parent=realpath(sys_get_temp_dir());if(!$resolved||dirname($resolved)!==$parent||!preg_match('/^mco-http-[a-f0-9]{16}$/D',basename($resolved)))throw new RuntimeException('Unsafe temp cleanup.');
    for($attempt=0;$attempt<4;$attempt++){try{httpFixtureRemove($resolved);break;}catch(Throwable $e){if($attempt===3)throw $e;usleep(200000);}}
}
exit($exitCode);
