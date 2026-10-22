<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__,2);$temp=sys_get_temp_dir().'/mco-prototype-'.bin2hex(random_bytes(8));mkdir($temp,0700);
$server=null;
function prototypeRemove(string $dir):void{foreach(new FilesystemIterator($dir) as $f){if($f->isDir())prototypeRemove($f->getPathname());else unlink($f->getPathname());}rmdir($dir);}
try{
 $files=['app/services/new-connection/applicant-contract.php','app/helpers/philippine-mobile.php','app/helpers/session-security.php','app/helpers/portal-navigation.php','app/helpers/new-connection-preview.php','config/new-connection-preview.php','app/views/pages/new-connection-prototype.php','app/views/partials/portal-sidebar.php','app/views/partials/portal-header.php','app/views/partials/session-action-dialogs.php'];
 foreach($files as $file){if(!is_dir(dirname($temp.'/'.$file)))mkdir(dirname($temp.'/'.$file),0700,true);copy($root.'/'.$file,$temp.'/'.$file);}
 copy(__DIR__.'/prototype-data.php',$temp.'/prototype-data.php');mkdir($temp.'/sessions',0700);
 foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/assets',FilesystemIterator::SKIP_DOTS)) as $f){if(!$f->isFile())continue;$to=$temp.'/assets'.substr($f->getPathname(),strlen($root.'/assets'));if(!is_dir(dirname($to)))mkdir(dirname($to),0700,true);copy($f->getPathname(),$to);}
 file_put_contents($temp.'/router.php', <<<'PHP'
<?php
if(!in_array($_SERVER['REMOTE_ADDR']??'', ['127.0.0.1','::1'],true)){http_response_code(403);return;}
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(str_starts_with($path,'/assets/')){$f=realpath(__DIR__.$path);if(!$f||!str_starts_with($f,realpath(__DIR__.'/assets').DIRECTORY_SEPARATOR)||!is_file($f)){http_response_code(404);return;}header('Content-Type: '.match(pathinfo($f,PATHINFO_EXTENSION)){'css'=>'text/css','js'=>'text/javascript','png'=>'image/png','woff2'=>'font/woff2','woff'=>'font/woff',default=>'application/octet-stream'});readfile($f);return;}
if(!in_array($path,['/prototype','/fixture-api'],true)){http_response_code(404);return;}
require __DIR__.'/app/helpers/session-security.php';session_name('NC_PROTO_FIXTURE');session_save_path(__DIR__.'/sessions');portalSessionStart();
require __DIR__.'/app/services/new-connection/applicant-contract.php';$context=require __DIR__.'/prototype-data.php';
$_SESSION['fixture_csrf']??=bin2hex(random_bytes(32));$csrf=$_SESSION['fixture_csrf'];
if($path==='/prototype'){
 if($_SERVER['REQUEST_METHOD']!=='GET'){http_response_code(405);return;}
 define('NC_PROTOTYPE_FIXTURE',true);$initial=$_SESSION['fixture_draft']??NewConnectionApplicantContract::emptyInput();
 if(!isset($_SESSION['fixture_draft']))$initial['applicant']=array_replace($initial['applicant'],$context['profile']);
 $expires=time()+(isset($_GET['fast_expiry'])?2:600);$_SESSION['fixture_expires']=$expires;
 require __DIR__.'/app/views/pages/new-connection-prototype.php';return;
}
header('Content-Type: application/json');
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);echo '{}';return;}
if(time()>=($_SESSION['fixture_expires']??0)){http_response_code(401);echo '{}';return;}
$body=file_get_contents('php://input',false,null,0,98305);
if(strlen($body)>98304){http_response_code(413);echo '{}';return;}
try{$command=json_decode($body,true,32,JSON_THROW_ON_ERROR);}catch(Throwable $e){http_response_code(400);echo '{}';return;}
if(!is_array($command)||!is_string($command['csrf']??null)||!hash_equals($csrf,$command['csrf'])){http_response_code(419);echo '{}';return;}
if(!in_array($command['action']??null,['validate','save','check'],true)){http_response_code(405);echo '{}';return;}
// Decode the input independently as objects so empty-object/type distinctions survive.
$raw=json_decode($body);$result=NewConnectionApplicantContract::validate(json_encode($raw->input??null,JSON_THROW_ON_ERROR),$context,$command['action']==='check');
if($command['action']==='save'&&$result['valid']){$draft=$result['input'];$draft['acknowledgments']=array_fill_keys(NewConnectionApplicantContract::ACKS,false);$draft['review']['information_confirmed']=false;$_SESSION['fixture_draft']=$draft;}
if(!$result['valid'])http_response_code(422);echo json_encode($result,JSON_THROW_ON_ERROR);
PHP);
 $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);if(!$socket)throw new RuntimeException('No fixture port.');$address=stream_socket_get_name($socket,false);fclose($socket);
 $server=proc_open([PHP_BINARY,'-S',$address,$temp.'/router.php'],[0=>['pipe','r'],1=>['file',$temp.'/server.log','a'],2=>['file',$temp.'/server.log','a']],$pipes);if(!is_resource($server))throw new RuntimeException('No fixture server.');fclose($pipes[0]);
 $deadline=microtime(true)+10;do{$ready=@stream_socket_client('tcp://'.$address,$errno,$error,.2);if($ready){fclose($ready);break;}usleep(20000);}while(microtime(true)<$deadline);if(!$ready)throw new RuntimeException('Fixture startup failed.');
 file_put_contents($temp.'/browser.json',json_encode(['base'=>'http://'.$address,'root'=>$root,'temp'=>$temp]));
 $process=proc_open(['C:/Program Files/nodejs/node.exe',__DIR__.'/applicant-prototype-browser-test.js',$temp.'/browser.json'],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($process))throw new RuntimeException('No browser runner.');fclose($pipes[0]);echo stream_get_contents($pipes[1]);fclose($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[2]);if(proc_close($process)!==0)throw new RuntimeException('Browser fixture failed: '.$err);
 // Deliberate allowlist: the fixture contains no database configuration, mailer or business controller.
 foreach(['config/database.php','app/services/mailer.php','app/controllers/dashboard.php','app/services/new-connection/workflow.php'] as $forbidden)if(file_exists($temp.'/'.$forbidden))throw new RuntimeException('Unsafe fixture dependency.');
 echo "Prototype fixture isolated: no database, operational queries, email, uploads or real submission routes; disposable state removed.\n";
}finally{if(is_resource($server)){proc_terminate($server);proc_close($server);}prototypeRemove($temp);}
