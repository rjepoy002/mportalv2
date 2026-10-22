<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(!in_array($argv[1]??'', ['--start','--test'],true)){fwrite(STDERR,"Use --start (manual) or --test (automated).\n");exit(2);}
$root=dirname(__DIR__,2);$temp=sys_get_temp_dir().'/mco-v2-'.bin2hex(random_bytes(8));$server=null;$exit=0;
$persistent=in_array('--persistent',$argv,true);$created=false;
if($persistent)require __DIR__.'/stage1-v2-persistence-support.php';
function v2remove(string $dir):void{foreach(new FilesystemIterator($dir) as $f){if($f->isDir())v2remove($f->getPathname());else unlink($f->getPathname());}rmdir($dir);}
try{
 mkdir($temp,0700);mkdir($temp.'/private/sessions',0700,true);
 foreach(['app/helpers/session-security.php','app/helpers/philippine-mobile.php','app/helpers/portal-navigation.php','app/helpers/new-connection-preview.php','config/new-connection-preview.php','app/services/new-connection/applicant-contract.php','app/services/new-connection/applicant-contract-v2.php','app/services/new-connection/applicant-contract-dispatch.php','app/views/pages/new-connection-v2-prototype.php','app/views/partials/portal-sidebar.php','app/views/partials/portal-header.php','app/views/partials/session-action-dialogs.php'] as $file){$to=$temp.'/'.$file;if(!is_dir(dirname($to)))mkdir(dirname($to),0700,true);copy($root.'/'.$file,$to);}
 foreach(['stage1-v2-data.php','stage1-v2-router.php'] as $f)copy(__DIR__.'/'.$f,$temp.'/'.$f);
 if($persistent){
    $schema='mco_nc_test_'.bin2hex(random_bytes(8));$bootstrap=draftTestPDO();$before=draftTestOtherSchemaSignature($bootstrap);$bootstrap->exec('CREATE DATABASE `'.$schema.'` CHARACTER SET utf8mb4');$created=true;$db=draftTestConnection($schema);v2persistentSeed($db);
    $service=v2persistentService($db,$schema);$service->createVersioned(1,NewConnectionApplicantContract::VERSION,bin2hex(random_bytes(32)));
    foreach(['workflow.php','drafts.php','versioned-drafts.php','review-keys.php'] as $f)copy($root.'/app/services/new-connection/'.$f,$temp.'/app/services/new-connection/'.$f);
    copy(__DIR__.'/stage1-v2-persistent-api.php',$temp.'/stage1-v2-persistent-api.php');copy(__DIR__.'/prototype-data.php',$temp.'/prototype-data.php');
    file_put_contents($temp.'/private/db-support.php',str_replace("PHP_SAPI!=='cli'","PHP_SAPI!=='cli'&&PHP_SAPI!=='cli-server'",file_get_contents(__DIR__.'/draft-test-support.php')));
 }
 foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/assets',FilesystemIterator::SKIP_DOTS)) as $f)if($f->isFile()&&in_array($f->getExtension(),['css','js','png','svg','woff','woff2'],true)){$to=$temp.'/assets'.substr($f->getPathname(),strlen($root.'/assets'));if(!is_dir(dirname($to)))mkdir(dirname($to),0700,true);copy($f->getPathname(),$to);}
 foreach(['stage1-v2-ui.js','stage1-v2-login.js','stage1-v2-persistent-ui.js'] as $f)copy(__DIR__.'/'.$f,$temp.'/assets/js/'.$f);
 $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);$host=stream_socket_get_name($socket,false);fclose($socket);file_put_contents($temp.'/private/config.php','<?php return '.var_export(['host'=>$host,'review_ttl'=>$argv[1]==='--test'?1:300,'persistent'=>$persistent]+($persistent?['schema'=>$schema,'proof_key'=>base64_encode(random_bytes(32))]:[]),true).';');
 $server=proc_open([PHP_BINARY,'-S',$host,'-t',$temp,$temp.'/stage1-v2-router.php'],[0=>['pipe','r'],1=>['file','NUL','a'],2=>['file','NUL','a']],$pipes);fclose($pipes[0]);for($i=0;$i<100;$i++){$ready=@stream_socket_client('tcp://'.$host,$errno,$error,.1);if($ready){fclose($ready);break;}usleep(30000);}if(!$ready)throw new RuntimeException('Fixture unavailable');
 echo "Synthetic v2 URL: http://$host/login\n".($persistent?"Disposable database: $schema\nTemporary resources: $temp\n":"Session only; no database.\n")."No submission. q then Enter stops; EOF stops.\n";
 if($argv[1]==='--test'){$config=$temp.'/browser.json';file_put_contents($config,json_encode(['base'=>'http://'.$host,'root'=>$root,'temp'=>$temp]));$p=proc_open(['C:/Program Files/nodejs/node.exe',__DIR__.($persistent?'/stage1-v2-persistent-browser.cjs':'/stage1-v2-browser.cjs'),$config],[0=>['pipe','r'],1=>STDOUT,2=>STDERR],$pipes);fclose($pipes[0]);if(proc_close($p)!==0)$exit=1;}
 else while(($line=fgets(STDIN))!==false)if(trim($line)==='q')break;
}finally{if(is_resource($server)){proc_terminate($server);proc_close($server);}if($created){if(draftTestOtherSchemaSignature($bootstrap)!==$before)$exit=1;$bootstrap->exec('DROP DATABASE `'.$schema.'`');echo "Disposable v2 database removed.\n";}if(realpath(dirname($temp))!==realpath(sys_get_temp_dir())||!preg_match('/^mco-v2-[a-f0-9]{16}$/D',basename($temp)))throw new RuntimeException('Unsafe cleanup');v2remove($temp);echo "Disposable v2 fixture removed.\n";}
exit($exit);
