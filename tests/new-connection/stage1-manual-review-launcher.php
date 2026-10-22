<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(($argv[1]??'')!=='--start'){fwrite(STDERR,"Use --start to explicitly launch synthetic read-only review.\n");exit(2);}
require __DIR__.'/draft-test-support.php';
$root=dirname(__DIR__,2);$temp=sys_get_temp_dir().'/mco-readonly-'.bin2hex(random_bytes(8));$schema='mco_nc_test_'.bin2hex(random_bytes(8));$created=false;$apache=null;$exit=0;
function reviewWrite(string $path,string $text):void{if(!is_dir(dirname($path)))mkdir(dirname($path),0700,true);file_put_contents($path,$text);}
function reviewRemove(string $dir):void{foreach(new FilesystemIterator($dir) as $f){if($f->isDir())reviewRemove($f->getPathname());else unlink($f->getPathname());}rmdir($dir);}
try {
 mkdir($temp,0700);mkdir($temp.'/public',0700);mkdir($temp.'/private/sessions',0700,true);
 $db=draftTestPDO();$db->exec('CREATE DATABASE `'.$schema.'` CHARACTER SET utf8mb4');$created=true;$identity=draftTestConnection($schema);
 $identity->exec('CREATE TABLE users(id BIGINT UNSIGNED PRIMARY KEY,name VARCHAR(100),email VARCHAR(254),status VARCHAR(20),email_verified_at DATETIME NULL) ENGINE=InnoDB');
 $q=$identity->prepare('INSERT INTO users VALUES(?,?,?,?,?)');foreach([[1,'active','2026-10-21'],[2,'inactive','2026-10-21'],[3,'active',null]] as [$id,$status,$verified])$q->execute([$id,'Synthetic Reviewer','review@example.invalid',$status,$verified]);
 foreach(['app/controllers/new-connection-stage1-display.php','app/helpers/new-connection-stage1-display.php','config/new-connection-stage1-display.php','app/helpers/session-security.php','app/middleware/new-connection-auth.php','app/services/new-connection/applicant-contract.php','app/helpers/philippine-mobile.php','app/helpers/portal-navigation.php','app/helpers/new-connection-preview.php','config/new-connection-preview.php','app/views/pages/new-connection-prototype.php','app/views/partials/portal-sidebar.php','app/views/partials/portal-header.php','app/views/partials/session-action-dialogs.php'] as $file)reviewWrite($temp.'/private/'.$file,file_get_contents($root.'/'.$file));
 reviewWrite($temp.'/private/draft-test-support.php',str_replace("PHP_SAPI!=='cli'","PHP_SAPI!=='cli'&&PHP_SAPI!=='apache2handler'",file_get_contents(__DIR__.'/draft-test-support.php')));
 foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/assets',FilesystemIterator::SKIP_DOTS)) as $f)if($f->isFile()&&in_array($f->getExtension(),['css','js','png','svg','woff','woff2'],true))reviewWrite($temp.'/public/assets'.substr($f->getPathname(),strlen($root.'/assets')),file_get_contents($f->getPathname()));
 session_name('NC_READONLY_REVIEW');session_save_path($temp.'/private/sessions');$map=[];
 foreach(['active'=>1,'inactive'=>2,'unverified'=>3,'expired'=>1,'anonymous'=>0] as $case=>$id){$session=bin2hex(random_bytes(16));session_id($session);session_start();$_SESSION=$id?['user_id'=>$id,'portal_created_at'=>time(),'portal_last_seen'=>time()-($case==='expired'?1801:0)]:[];session_write_close();$name=$case.'-'.bin2hex(random_bytes(12)).'.php';$map[$name]=$session;reviewWrite($temp.'/public/'.$name,"<?php require dirname(__DIR__).'/private/bootstrap.php';");$urls[$case]=$name;}
 $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);if(!$socket)throw new RuntimeException('No loopback port');$host=stream_socket_get_name($socket,false);fclose($socket);
 reviewWrite($temp.'/private/context.php','<?php return '.var_export(['host'=>$host,'schema'=>$schema,'sessions'=>$map],true).';');
 reviewWrite($temp.'/private/bootstrap.php', <<<'BOOT'
<?php
ini_set('display_errors','0');ini_set('log_errors','0');header('Cache-Control: private, no-store');header('Referrer-Policy: no-referrer');
$cfg=require __DIR__.'/context.php';
if(($_SERVER['REMOTE_ADDR']??'')!=='127.0.0.1'||($_SERVER['HTTP_HOST']??'')!==$cfg['host']){http_response_code(404);exit;}
$name=basename($_SERVER['SCRIPT_FILENAME']);if(!isset($cfg['sessions'][$name])){http_response_code(404);exit;}
if(!in_array($_SERVER['REQUEST_METHOD'],['GET','HEAD'],true)){header('Allow: GET, HEAD');http_response_code(405);exit;}
session_name('NC_READONLY_REVIEW');session_save_path(__DIR__.'/sessions');session_id($cfg['sessions'][$name]);
putenv('MCO_STAGE1_DISPLAY_ENABLED=1');putenv('APP_ENV=development');putenv('MCO_NEW_CONNECTION_PREVIEW_ENABLED=0');
require __DIR__.'/draft-test-support.php';
$stage1DisplayIdentityRepository=static function(int $id)use($cfg):array|false{$db=draftTestConnection($cfg['schema']);$q=$db->prepare('SELECT id,name,email,status,email_verified_at FROM users WHERE id=?');$q->execute([$id]);return $q->fetch();};
require __DIR__.'/app/controllers/new-connection-stage1-display.php';
BOOT);
 $path=str_replace('\\','/',$temp);$conf= <<<'CONF'
ServerRoot "C:/xampp/apache"
ServerName localhost
LoadModule authz_core_module modules/mod_authz_core.so
LoadModule authz_host_module modules/mod_authz_host.so
LoadModule mime_module modules/mod_mime.so
LoadFile "C:/xampp/php/php8ts.dll"
LoadFile "C:/xampp/php/libpq.dll"
LoadFile "C:/xampp/php/libsqlite3.dll"
LoadModule php_module "C:/xampp/php/php8apache2_4.dll"
PHPIniDir "C:/xampp/php"
TypesConfig "C:/xampp/apache/conf/mime.types"
<Directory />
Require all denied
</Directory>
CONF;
 $conf.="\nListen $host\nPidFile \"$path/private/httpd.pid\"\nErrorLog \"$path/private/error.log\"\nDocumentRoot \"$path/public\"\n<Directory \"$path/public\">\nRequire local\nAllowOverride None\nOptions -Indexes\n<FilesMatch \\\.php$>\nSetHandler application/x-httpd-php\n</FilesMatch>\n</Directory>\n";
 reviewWrite($temp.'/private/httpd.conf',$conf);$apache=proc_open(['C:/xampp/apache/bin/httpd.exe','-X','-f',$temp.'/private/httpd.conf'],[0=>['pipe','r'],1=>['file','NUL','a'],2=>['file','NUL','a']],$pipes,'C:/xampp/apache');if(!is_resource($apache))throw new RuntimeException('Apache unavailable');fclose($pipes[0]);
 $ready=false;for($n=0;$n<100;$n++){$connection=@stream_socket_client('tcp://'.$host,$errno,$error,.1);if($connection){fclose($connection);$ready=true;break;}usleep(50000);}if(!$ready)throw new RuntimeException('Apache startup failed');
 echo "Read-only synthetic review. No real personal information.\nSchema: $schema\nTemporary resources: $temp\n";foreach($urls as $case=>$file)echo "$case: http://$host/$file\n";
 echo "Type q then Enter to stop and clean up (EOF also stops). Do not force-close this console.\n";
 while(($line=fgets(STDIN))!==false)if(trim($line)==='q')break;
 if(count($identity->query('SHOW TABLES')->fetchAll())!==1||(int)$identity->query('SELECT COUNT(*) FROM users')->fetchColumn()!==3)throw new RuntimeException('Identity isolation failed');
}catch(Throwable $e){fwrite(STDERR,"Review launcher failed; no infrastructure details exposed.\n");$exit=1;}
finally {
 if(is_resource($apache)){proc_terminate($apache);proc_close($apache);}
 if($created){$db->exec('DROP DATABASE `'.$schema.'`');echo "Disposable review schema removed.\n";}
 $resolved=realpath($temp);if($resolved&&dirname($resolved)===realpath(sys_get_temp_dir())&&preg_match('/^mco-readonly-[a-f0-9]{16}$/D',basename($resolved)))reviewRemove($resolved);
}
exit($exit);
