<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
function akReplace(string $s,string $needle,string $replacement): string
{if(substr_count($s,$needle)!==1)throw new RuntimeException('Reviewed Apache hook changed');return str_replace($needle,$replacement,$s);}
function apacheNavigationRun(string $source): void
{
    global $root,$schema,$temp,$created,$process,$processes,$checks,$exit,$server,$db,$host,$hosts,$env;
    $source=akReplace($source,'localhostNavigationPrepare($temp,$db);','localhostNavigationPrepare($temp,$db);akPrepare($temp,$db);');
    $source=akReplace($source,'$host=$hosts[0];returnWrite',"\$hosts=array_map(static fn(\$h)=>str_replace('127.0.0.1','localhost',\$h),\$hosts);\$host=\$hosts[0];returnWrite");
    $start=strpos($source,'foreach($hosts as $workerHost){');$end=strpos($source,'// Password/session/auth intent;',$start);
    if($start===false||$end===false)throw new RuntimeException('Apache launcher seam changed');
    $source=substr($source,0,$start).'akStart($temp,$hosts,$processes,$env);'.substr($source,$end);
    $source=str_replace("'http://'.\$host","'http://'.\$host.'/mportalv2'",$source);
    $source=str_replace("'http://'.\$c['host'].'/login'","'http://'.\$c['host'].'/mportalv2/login'",$source);
    $source=str_replace("['status']===419","['status']===403",$source);
    $source=str_replace("'/login?","'/mportalv2/index.php?",$source);
    $source=str_replace("__DIR__.'/localhost-auth-navigation-browser.js'","__DIR__.'/apache-navigation-browser.js'",$source);
    $source=str_replace("__DIR__.'/localhost-auth-navigation-results.json'","__DIR__.'/apache-navigation-results.json'",$source);
    $source=akReplace($source,"returnWrite(\$temp.'/private/browser.json',", "akFinalize(\$temp,\$db);akExtra(\$db);returnWrite(\$temp.'/private/browser.json',");
    $source=akReplace($source,'if($created){if(!preg_match', 'akPrincipalCleanup($server??null);if($created){if(!preg_match');
    $source=str_replace('Current-policy SQL navigation passed','Apache current-policy navigation passed',$source);
    $source=str_replace('Visual confirmation dialogs not re-exercised by this JSON-only harness; existing dialog regression evidence separate.','Rendered logout dialog and complete HTML journeys exercised; real Google/SMTP, existing port-80 authentication and HTTPS deployment remain untested.',$source);
    $source=str_replace('Current Google unbound Gmail/hosted verification-write branches are not exercised in this navigation suite; their behavior must remain unchanged. Separate security review is not a prerequisite for Change Set A.','Current hosted auto-link and new Google registration verification writes exercised on synthetic rows only; identity branch body copied byte-for-byte. Experimental policy remains excluded.',$source);
    // Error responses use the standard status in the private router too.
    $source=str_replace("'date'=>'2026-10-21'","'date'=>'2026-10-21','http_runtime'=>'separate Apache apache2handler'",$source);
    eval('?>'.$source);
}
function akPrepare(string $temp,PDO $db): void
{
    global $akPrincipal;
    require_once __DIR__.'/draft-test-support.php';
    $schema=$db->query('SELECT DATABASE()')->fetchColumn();
    $akPrincipal='mco_ak_'.bin2hex(random_bytes(8));$password=bin2hex(random_bytes(24));$admin=draftTestPDO();
    $admin->exec("CREATE USER '".$akPrincipal."'@'localhost' IDENTIFIED BY '".$password."'");
    $admin->exec("GRANT SELECT,INSERT,DELETE ON `".$schema."`.return_claims TO '".$akPrincipal."'@'localhost'");
    $admin->exec("GRANT SELECT,UPDATE ON `".$schema."`.claim_control TO '".$akPrincipal."'@'localhost'");
    returnWrite($temp.'/private/claim-user.json',json_encode(['user'=>$akPrincipal,'password'=>$password,'schema'=>$schema]));
    // Private helper only: add explicitly authorized localhost scope, production bytes unchanged.
    $helper=file_get_contents($temp.'/app/helpers/auth-return-intent.php');
    $helper=akReplace($helper,"(\$gates['environment']??null)==='isolated-auth-test'&&\$local",
        "(\$gates['environment']??null)==='localhost-development' && (\$gates['development_authorized']??false)===true && in_array(\$gates['server_addr']??null,['127.0.0.1','::1'],true) && in_array(\$gates['remote_addr']??null,['127.0.0.1','::1'],true) && strtolower(\$url['host'])==='localhost' && rtrim(\$url['path']??'','/')==='/mportalv2'");
    returnWrite($temp.'/app/helpers/auth-return-intent.php',$helper);
    // Parent isolated class for exact canonical URL/negative scope tests, no production helper import.
    eval(substr(str_replace('PortalAuthReturnIntent','ApacheFixtureReturnIntent',$helper),5));
    $router=file_get_contents($temp.'/auth-return-fixture.php');
    $router=str_replace("PHP_SAPI!=='cli-server'","PHP_SAPI!=='apache2handler'",$router);
    $router=str_replace("'isolated-auth-test'","'localhost-development','development_authorized'=>true,'server_addr'=>\$_SERVER['SERVER_ADDR']??null,'remote_addr'=>\$_SERVER['REMOTE_ADDR']??null",$router);
    $router=str_replace("'http://'.\$cfg['host']","'http://'.\$cfg['host'].'/mportalv2'",$router);
    $router=str_replace("'APP_URL=http://'.\$cfg['host']","'APP_URL=http://'.\$cfg['host'].'/mportalv2'",$router);
    $router=akReplace($router,"session_save_path(__DIR__.'/private/sessions');", "if(!empty(\$control['apache_race'])&&parse_url(\$_SERVER['REQUEST_URI'],PHP_URL_PATH)==='/mportalv2/login'&&\$_SERVER['REQUEST_METHOD']==='POST')file_put_contents(__DIR__.'/private/race-arrivals','1',FILE_APPEND|LOCK_EX);session_save_path(__DIR__.'/private/sessions');");
    $router=akReplace($router,'return $intent->take($_SESSION,','akBeforeCapture();return $intent->take($_SESSION,');
    $router=str_replace('http_response_code($status);','http_response_code($status===419?403:$status);',$router);
    $router=akReplace($router,"header(\"Content-Security-Policy: default-src 'none'; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'\");",
        "\$fixtureHtml=str_contains(\$_SERVER['HTTP_ACCEPT']??'','text/html');\$fixtureNonce=base64_encode(random_bytes(18)); header(\"Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-\$fixtureNonce'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'\");");
    // This CSP is only for the standalone rendered fixture; no actual CSP is changed.
    $router=akReplace($router,'$path=parse_url($_SERVER[\'REQUEST_URI\'],PHP_URL_PATH);', <<<'PHP'
$path=substr(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),strlen('/mportalv2'));
$path=['/index.php'=>'/login','/register.php'=>'/register','/google-login.php'=>'/oauth','/google-callback.php'=>'/callback','/forgot-password.php'=>'/forgot','/reset-password.php'=>'/reset','/logout.php'=>'/logout','/new-connection.php'=>'/module'][$path]??$path;
require __DIR__.'/apache-private.php';
if($path==='/provider-double.php'){header('Location: google-callback.php?state='.rawurlencode($_GET['state']??'').'&code=synthetic');exit;}
if($fixtureHtml&&$path==='/module'){
    if(($control['feature']??true)!==true){http_response_code(404);exit('Development module unavailable');}
    if(!isset($_SESSION['user_id'])){$f=$intent->begin($_SESSION,PortalAuthReturnIntent::DESTINATION);header('Location: index.php?auth_flow='.rawurlencode($f));exit;}
}
if($fixtureHtml&&$path==='/dashboard.php'&&isset($_SESSION['user_id'])){header('Content-Type: text/html');echo '<h1>Synthetic dashboard</h1><a href="new-connection.php">New Connection</a>';exit;}
PHP);
    $router=str_replace("\$db=draftTestConnection(\$cfg['schema']);\$adapter=new IsolatedSqlReturnClaims(\$db,\$readiness['generation'],10000);",
        "\$db=akClaimPDO();\$adapter=new IsolatedSqlReturnClaims(\$db,\$readiness['generation'],(int)(\$control['claim_capacity']??10000));",$router);
    $router=akReplace($router,"\$readiness=json_decode(file_get_contents(__DIR__.'/private/claim-readiness.json'),true,16,JSON_THROW_ON_ERROR);",
        "\$gate=fopen(__DIR__.'/private/claim-readiness.json','r');if(!\$gate||!flock(\$gate,LOCK_SH))throw new RuntimeException('Claim unavailable');try{\$readiness=json_decode(stream_get_contents(\$gate),true,16,JSON_THROW_ON_ERROR);");
    $router=akReplace($router,'return $adapter->claim($digest,$expires,$now);','return $adapter->claim($digest,$expires,$now);}finally{flock($gate,LOCK_UN);fclose($gate);}');
    $router=str_replace("fixtureAuthReply(200,['user_id'=>\$user['id'],'verified'=>true]);","if(\$fixtureHtml)akLanding(\$user);fixtureAuthReply(200,['user_id'=>\$user['id'],'verified'=>true]);",$router);
    $router=str_replace("'/login?","'/mportalv2/index.php?",$router);
    returnWrite($temp.'/auth-return-fixture.php',$router);
    foreach(['draft-test-support.php','isolated-identity-claim.php'] as $file){$s=file_get_contents($temp.'/'.$file);$s=str_replace("PHP_SAPI!=='cli'&&PHP_SAPI!=='cli-server'","PHP_SAPI!=='cli'&&PHP_SAPI!=='apache2handler'",$s);$s=str_replace("PHP_SAPI !== 'cli' && PHP_SAPI !== 'cli-server'","PHP_SAPI !== 'cli' && PHP_SAPI !== 'apache2handler'",$s);returnWrite($temp.'/'.$file,$s);}
    returnWrite($temp.'/apache-private.php',file_get_contents(__DIR__.'/apache-navigation-private.php'));
}
function akReadyWrite(array $state): void
{
    global $temp;$f=fopen($temp.'/private/claim-readiness.json','c+');if(!$f||!flock($f,LOCK_EX))throw new RuntimeException('Recovery gate unavailable');
    try{$json=json_encode($state,JSON_THROW_ON_ERROR);rewind($f);ftruncate($f,0);if(fwrite($f,$json)!==strlen($json)||!fflush($f))throw new RuntimeException('Recovery gate unavailable');}finally{flock($f,LOCK_UN);fclose($f);}
}
function akExtra(PDO $db): void
{
    global $temp,$host;
    $g=['environment'=>'localhost-development','development_authorized'=>true,'server_addr'=>'127.0.0.1','remote_addr'=>'::1','environment_permitted'=>true,'feature_enabled'=>true];
    $h=new ApacheFixtureReturnIntent('http://localhost/mportalv2/',$g);$s=[];$f=$h->begin($s);
    returnAssert($h->match($s,$f)['status']==='module','Isolated helper accepts exact localhost/mportalv2 canonical development base');
    foreach([['development_authorized'=>false],['server_addr'=>'192.0.2.1'],['remote_addr'=>'192.0.2.2'],['environment'=>'production']] as $bad){try{new ApacheFixtureReturnIntent('http://localhost/mportalv2/',array_replace($g,$bad));$denied=false;}catch(InvalidArgumentException){$denied=true;}returnAssert($denied,'HTTP helper denies absent development authorization or nonloopback/production scope');}
    foreach(['http://foreign.invalid/mportalv2','http://127.0.0.1/mportalv2','http://localhost/other','http://localhost/mportalv2?return=evil'] as $bad){try{new ApacheFixtureReturnIntent($bad,$g);$denied=false;}catch(InvalidArgumentException){$denied=true;}returnAssert($denied,'HTTP helper denies alternate canonical destination');}
    returnAssert((new ApacheFixtureReturnIntent('https://portal.example.invalid/mportalv2',['environment_permitted'=>true,'feature_enabled'=>true]))->target(['status'=>'module','destination'=>'new_connection.home'])==='https://portal.example.invalid/mportalv2/new-connection.php','HTTPS closed destination unchanged');
    $c=json_decode(file_get_contents($temp.'/private/claim-user.json'),true);$port=getenv('MCO_NC_TEST_PORT')?:3306;
    $restricted=new PDO('mysql:host=127.0.0.1;port='.$port.';dbname='.$c['schema'].';charset=utf8mb4',$c['user'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    foreach(['SELECT * FROM users','CREATE TABLE forbidden(id INT)','ALTER TABLE return_claims ADD forbidden INT'] as $sql){try{$restricted->exec($sql);$denied=false;}catch(PDOException){$denied=true;}returnAssert($denied,'Restricted claim account cannot read identities or perform DDL');}
    require_once __DIR__.'/isolated-identity-claim.php';$ready=json_decode(file_get_contents($temp.'/private/claim-readiness.json'),true);$store=new IsolatedSqlReturnClaims($restricted,$ready['generation'],10000);
    returnAssert(!$store->claim(hash('sha256','backend-expired'),time(),time()),'Least-privilege backend independently rejects expired claim');
    $q=$db->prepare('INSERT INTO users(email,name,password_hash,status,email_verified_at) VALUES(?,?,?,?,?)');$q->execute(['apache-extra@example.invalid','Synthetic Apache',password_hash('Apache123',PASSWORD_DEFAULT),'active','2026-10-21']);
    $q->execute(['browser-login@example.invalid','Synthetic Browser',password_hash('Browser123',PASSWORD_DEFAULT),'active','2026-10-21']);
    $cookie='';$flow=returnBegin($cookie);$count=(int)$db->query('SELECT COUNT(*) FROM return_claims')->fetchColumn();returnControl(['claim_capacity'=>$count]);
    returnAssert(returnLogin($cookie,$flow,'apache-extra@example.invalid','Apache123')['status']===503,'Apache claim capacity exhaustion fails closed');
    $ordinary='';returnAssert(returnLogin($ordinary,null,'apache-extra@example.invalid','Apache123')['location']==='dashboard.php','Ordinary Apache login independent of full claim store');returnControl([]);
    foreach(['/login','/register','/forgot','/reset'] as $route){$c='';$data=['csrf_token'=>'invalid','action'=>'register','email'=>'none@example.invalid','password'=>'invalid'];if($route==='/reset'){
        $token=bin2hex(random_bytes(32));$q=$db->prepare('INSERT INTO password_resets(user_id,token_hash,expires_at) VALUES(?,?,UTC_TIMESTAMP()+INTERVAL 1 HOUR)');$q->execute([1,hash('sha256',$token)]);$data['token']=$token;
    }$r=returnRequest($route,$c,$data,'POST');returnAssert($r['status']===403,'Copied Apache CSRF denial uses HTTP 403: '.$route);}
    returnAssert(str_contains(file_get_contents($temp.'/private/errors.log'),'"category":"csrf_invalid"'),'Copied recovery CSRF rejection retains safe security logging');
    // Real SQL backup/restore with a retained old Apache session; no actual user data.
    $cookie='';$flow=returnBegin($cookie);$csrf=returnSession($cookie)['csrf'];$raw=file_get_contents($temp.'/private/sessions/sess_'.$cookie);
    $claims=$db->query('SELECT * FROM return_claims')->fetchAll();$control=$db->query('SELECT * FROM claim_control')->fetch();
    returnAssert(str_ends_with(returnLogin($cookie,$flow,'apache-extra@example.invalid','Apache123')['location'],'/new-connection.php'),'Apache recovery fixture consumes original return flow');
    $clone=bin2hex(random_bytes(16));file_put_contents($temp.'/private/sessions/sess_'.$clone,$raw);$ready['ready']=false;akReadyWrite($ready);
    $db->beginTransaction();$db->exec('DELETE FROM return_claims');$q=$db->prepare('INSERT INTO return_claims VALUES(?,?)');foreach($claims as $row)$q->execute([$row['digest'],$row['expires']]);$q=$db->prepare('UPDATE claim_control SET used=?,last_clock=? WHERE id=1');$q->execute([$control['used'],$control['last_clock']]);$db->commit();
    $r=returnRequest('/login',$clone,['csrf_token'=>$csrf,'auth_flow'=>$flow,'email'=>'apache-extra@example.invalid','password'=>'Apache123'],'POST');
    returnAssert($r['status']===503&&returnSession($clone)['id']===null,'Restored old SQL snapshot cannot replay retained Apache session during external quarantine');
    $dir=realpath($temp.'/private/sessions');if(!$dir||dirname($dir)!==realpath($temp.'/private'))throw new RuntimeException('Unsafe hint invalidation');
    foreach(new DirectoryIterator($dir) as $file)if($file->isFile()&&preg_match('/^sess_[A-Za-z0-9,-]+$/D',$file->getFilename()))unlink($file->getPathname());
    $ready['generation']=bin2hex(random_bytes(16));$q=$db->prepare('UPDATE claim_control SET generation=? WHERE id=1');$q->execute([$ready['generation']]);$ready['ready']=true;akReadyWrite($ready);
    returnAssert(returnRequest('/login',$clone,['csrf_token'=>$csrf,'auth_flow'=>$flow,'email'=>'apache-extra@example.invalid','password'=>'Apache123'],'POST')['status']===403,'Invalidated old Apache session cannot replay after restoration');
    $fresh='';$f=returnBegin($fresh);returnAssert(str_ends_with(returnLogin($fresh,$f,'apache-extra@example.invalid','Apache123')['location'],'/new-connection.php'),'Explicit fresh flow works after claim recovery and global fixture hint invalidation');
    akApacheRace($db);
    akCurrentGoogle($db);
}
function akCurrentGoogle(PDO $db): void
{
    global $temp,$root;
    $actual=file_get_contents($root.'/app/controllers/google-callback.php');$copy=file_get_contents($temp.'/app/controllers/google-callback.php');
    $a=strpos($actual,'$googleId = $userInfo->id;');$b=strpos($copy,'$googleId = $userInfo->id;');$ae=strpos($actual,'portalAuthenticated($user);',$a);$be=strpos($copy,'$returnOutcome=fixtureAuthCapture(',$b);
    returnAssert($a!==false&&$b!==false&&$ae!==false&&$be!==false&&rtrim(substr($actual,$a,$ae-$a))===rtrim(substr($copy,$b,$be-$b)),'Actual Google identity SQL and branch body copied byte-for-byte; navigation hook outside identity block');
    $db->prepare('INSERT INTO users(email,name,password_hash,status,email_verified_at) VALUES(?,?,?,?,NULL)')->execute(['apache-hosted@example.invalid','Synthetic Hosted',password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT),'active']);
    foreach([['id'=>'apache-hosted','email'=>'apache-hosted@example.invalid','hd'=>'synthetic.example.invalid'],['id'=>'apache-new','email'=>'apache-new@example.invalid','hd'=>null]] as $scenario){
        returnWrite($temp.'/private/provider.json',json_encode($scenario+['name'=>'Synthetic Google','verified_email'=>true]));$cookie='';$f=returnBegin($cookie);$o=returnRequest('/oauth?auth_flow='.$f,$cookie);parse_str(parse_url($o['location'],PHP_URL_QUERY),$params);
        $r=returnRequest('/callback?state='.$params['state'].'&code=synthetic',$cookie);returnAssert(str_ends_with($r['location'],'/new-connection.php')&&returnRequest('/module',$cookie)['status']===200,'Current copied Google auto-link/new-registration behavior remains compatible with module eligibility');
        $q=$db->prepare('SELECT google_id,email_verified_at FROM users WHERE email=?');$q->execute([$scenario['email']]);$row=$q->fetch();returnAssert($row['google_id']===$scenario['id']&&$row['email_verified_at']!==null,'Current Google verification writes preserved only on synthetic rows');
    }
    returnWrite($temp.'/private/provider.json',json_encode(['id'=>'google-5','email'=>'synthetic5@example.invalid','name'=>'Synthetic','verified_email'=>true,'hd'=>null]));
}
function akApacheRace(PDO $db): void
{
    global $temp,$hosts,$env,$processes;
    $db->prepare('INSERT INTO users(email,name,password_hash,status,email_verified_at) VALUES(?,?,?,?,?)')->execute(['apache-race@example.invalid','Synthetic Race',password_hash('Race123',PASSWORD_DEFAULT),'active','2026-10-21']);
    $cookie='';$flow=returnBegin($cookie);$csrf=returnSession($cookie)['csrf'];returnControl(['apache_race'=>true]);$clients=[];
    $code= <<<'PHP'
$c=json_decode(file_get_contents($argv[1]),true);file_get_contents('http://'.$c['host'].'/mportalv2/login',false,stream_context_create(['http'=>['method'=>'POST','timeout'=>15,'ignore_errors'=>true,'follow_location'=>0,'header'=>'Cookie: PHPSESSID='.$c['cookie']."\r\nContent-Type: application/x-www-form-urlencoded\r\n",'content'=>http_build_query($c['body'])]]));$out=['location'=>'','regenerated'=>false];foreach($http_response_header as $h){if(str_starts_with($h,'Location: '))$out['location']=substr($h,10);if(preg_match('/^Set-Cookie: PHPSESSID=([^;]+)/i',$h,$m)&&$m[1]!==$c['cookie'])$out['regenerated']=true;}echo json_encode($out);
PHP;
    foreach($hosts as $i=>$h){$file=$temp.'/private/apache-race-'.$i.'.json';returnWrite($file,json_encode(['host'=>$h,'cookie'=>$cookie,'body'=>['csrf_token'=>$csrf,'auth_flow'=>$flow,'email'=>'apache-race@example.invalid','password'=>'Race123']]));$p=proc_open([PHP_BINARY,'-r',$code,$file],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,null,$env);if(!is_resource($p))throw new RuntimeException('Race client unavailable');$processes[]=$p;fclose($pipes[0]);$clients[]=[$p,$pipes];if($i===0)akWait(fn()=>is_file($temp.'/private/race-ready'));}
    akWait(fn()=>is_file($temp.'/private/race-arrivals')&&strlen(file_get_contents($temp.'/private/race-arrivals'))===2);usleep(200000);file_put_contents($temp.'/private/race-release','go');
    $results=[];foreach($clients as [$p,$pipes]){$raw=stream_get_contents($pipes[1]);$error=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);returnAssert(proc_close($p)===0&&$error==='','Independent Apache race client exits cleanly');$results[]=json_decode($raw,true,16,JSON_THROW_ON_ERROR);}
    returnControl([]);$locations=array_column($results,'location');returnAssert(count(array_filter($locations,fn($v)=>str_ends_with($v,'/new-connection.php')))===1&&count(array_filter($locations,fn($v)=>str_ends_with($v,'auth_context=expired')))===1,'Forced independent Apache worker race yields exactly one module return and one expired context');
    returnAssert($results[0]['regenerated']&&$results[1]['regenerated'],'Both Apache workers reach actual session regeneration on old-session race');
    $q=$db->prepare('SELECT COUNT(*) FROM return_claims WHERE digest=?');$q->execute([hash('sha256','portal.auth.return.v1:'.$flow)]);returnAssert((int)$q->fetchColumn()===1,'Forced Apache race leaves one durable SQL claim');
}
function akWait(callable $ready): void
{$until=microtime(true)+8;while(!$ready()){if(microtime(true)>$until)throw new RuntimeException('Apache race checkpoint timeout');usleep(10000);}}
function akPrincipalCleanup(?PDO $admin): void
{
    global $akPrincipal;if($admin&&isset($akPrincipal)&&preg_match('/^mco_ak_[a-f0-9]{16}$/D',$akPrincipal))$admin->exec("DROP USER IF EXISTS '".$akPrincipal."'@'localhost'");
}
function akStart(string $temp,array $hosts,array &$processes,array $env): void
{
    akFinalize($temp,null);
    returnWrite($temp.'/config/google.php',"<?php define('GOOGLE_CLIENT_ID','synthetic');define('GOOGLE_CLIENT_SECRET','synthetic');define('GOOGLE_REDIRECT_URI','http://".$hosts[0]."/mportalv2/google-callback.php');");
    foreach($hosts as $i=>$workerHost){
        $port=(int)substr(strrchr($workerHost,':'),1);$path=str_replace('\\','/',$temp);
        $conf="ServerRoot \"C:/xampp/apache\"\nServerName localhost\nLoadModule authz_core_module modules/mod_authz_core.so\nLoadModule authz_host_module modules/mod_authz_host.so\nLoadModule dir_module modules/mod_dir.so\nLoadModule mime_module modules/mod_mime.so\nLoadModule alias_module modules/mod_alias.so\nLoadFile \"C:/xampp/php/php8ts.dll\"\nLoadFile \"C:/xampp/php/libpq.dll\"\nLoadFile \"C:/xampp/php/libsqlite3.dll\"\nLoadModule php_module \"C:/xampp/php/php8apache2_4.dll\"\nPHPIniDir \"C:/xampp/php\"\nTypesConfig \"C:/xampp/apache/conf/mime.types\"\nListen 127.0.0.1:$port\nListen [::1]:$port\nPidFile \"$path/private/apache-$i.pid\"\nErrorLog \"$path/private/apache-$i.log\"\nDocumentRoot \"$path/empty\"\nAlias /mportalv2/ \"$path/public/\"\n<Directory />\nRequire all denied\n</Directory>\n<Directory \"$path/public\">\nRequire local\nOptions -Indexes\nAllowOverride None\nDirectoryIndex index.php\n<FilesMatch \\.php$>\nSetHandler application/x-httpd-php\n</FilesMatch>\n</Directory>\n";
        $conf.="Alias /favicon.ico \"$path/public/assets/images/logo.png\"\nLoadModule headers_module modules/mod_headers.so\nHeader always set Cache-Control \"private, no-store\"\n<LocationMatch \"^/mportalv2/(session|begin|login|register|oauth|callback|forgot|reset|logout|module|owned)$\">\nSetHandler application/x-httpd-php\n</LocationMatch>\n";
        returnWrite($temp.'/private/apache-'.$i.'.conf',$conf);
        $p=proc_open(['C:/xampp/apache/bin/httpd.exe','-X','-f',$temp.'/private/apache-'.$i.'.conf'],[0=>['pipe','r'],1=>['file','NUL','a'],2=>['file','NUL','a']],$pipes,'C:/xampp/apache',$env);
        if(!is_resource($p))throw new RuntimeException('Apache unavailable');$processes[]=$p;fclose($pipes[0]);
        $until=microtime(true)+10;do{$ready=@stream_socket_client('tcp://'.$workerHost,$errno,$error,.2);if($ready){fclose($ready);break;}usleep(20000);}while(microtime(true)<$until);returnAssert((bool)$ready,'Independent loopback Apache starts');
    }
}
function akFinalize(string $temp,?PDO $db): void
{
    global $root;
    if(is_file($temp.'/private/render-ready'))return;
    foreach(['index','register','google-login','google-callback','forgot-password','reset-password','logout'] as $name){$file=$temp.'/app/controllers/'.$name.'.php';$before=file_get_contents($file);$s=$before;$s=str_replace('http_response_code(419);','http_response_code(403);',$s);$s=str_replace('logoutFailure(419,','logoutFailure(403,',$s);
        if($name==='forgot-password')$s=akReplace($s,"recovery_log(\$requestId, 'request_validation_failed', 'csrf_invalid');","http_response_code(403); recovery_log(\$requestId, 'request_validation_failed', 'csrf_invalid');");
        $normalized=str_replace("http_response_code(403); recovery_log(\$requestId, 'request_validation_failed', 'csrf_invalid');","recovery_log(\$requestId, 'request_validation_failed', 'csrf_invalid');",$s);$normalized=str_replace(['http_response_code(403);','logoutFailure(403,'],['http_response_code(419);','logoutFailure(419,'],$normalized);
        returnAssert($normalized===$before,'Status-only Apache controller patch preserves token checks, messages and logging: '.$name);
        returnWrite($file,$s);
    }
    foreach(['index','register','forgot-password','reset-password'] as $view){
        $actual=file_get_contents($root.'/app/views/pages/'.$view.'.php');
        $actual=str_replace('<script','<script nonce="<?= htmlspecialchars($fixtureNonce,ENT_QUOTES) ?>"',$actual);
        // Navigation metadata is escaped and generated from a same-session matched hint.
        $hidden='<?php if($fixtureHtml && $intent->match($_SESSION,fixtureAuthFlow(),fixtureAuthNow())["status"]==="module"): ?><input type="hidden" name="auth_flow" value="<?= htmlspecialchars(fixtureAuthFlow(),ENT_QUOTES,"UTF-8") ?>"><?php endif; ?>';
        $actual=str_replace('</form>',$hidden.'</form>',$actual);
        $actual=preg_replace_callback('/href="(register\.php|forgot-password\.php|index\.php)"/',static fn($m)=>'href="<?= akHtmlLink("'.$m[1].'") ?>"',$actual);
        returnWrite($temp.'/render/'.$view.'.php',$actual);
        $json=file_get_contents($temp.'/app/views/pages/'.$view.'.php');
        $json=str_replace('<?php','<?php if($fixtureHtml){header("Content-Type: text/html; charset=utf-8");require dirname(__DIR__,3)."/render/'.$view.'.php";return;} ',$json);
        returnWrite($temp.'/app/views/pages/'.$view.'.php',$json);
    }
    foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/assets',FilesystemIterator::SKIP_DOTS)) as $file)if($file->isFile())returnWrite($temp.'/public/assets/'.substr($file->getPathname(),strlen($root.'/assets/')),file_get_contents($file->getPathname()));
    $js=file_get_contents($temp.'/public/assets/js/login.js');$js=str_replace('"google-login.php"','document.getElementById("fixtureGoogleTarget").value',$js);returnWrite($temp.'/public/assets/js/login.js',$js);
    $view=file_get_contents($temp.'/render/index.php');$view=str_replace('<!-- Login JavaScript -->','<input id="fixtureGoogleTarget" type="hidden" value="<?= akHtmlLink("google-login.php") ?>"><!-- Login JavaScript -->',$view);returnWrite($temp.'/render/index.php',$view);
    foreach(['app/helpers/portal-navigation.php','app/views/partials/portal-sidebar.php','app/views/partials/portal-header.php','app/views/partials/session-action-dialogs.php','app/views/pages/new-connection.php'] as $file)returnWrite($temp.'/'.$file,file_get_contents($root.'/'.$file));
    $nav=file_get_contents($temp.'/app/helpers/portal-navigation.php');$nav=str_replace("'dashboard' => ['label'", "'new-connection' => ['label'=>'New Connection','href'=>'new-connection.php','icon'=>'connection'],\n        'dashboard' => ['label'",$nav);$nav=str_replace("'home' =>", "'connection'=>'<path d=\"M9 3v5m6-5v5M7 8h10v3a5 5 0 0 1-5 5v5m-5-10a5 5 0 0 0 5 5\"/>',\n        'home' =>",$nav);returnWrite($temp.'/app/helpers/portal-navigation.php',$nav);
    $view=file_get_contents($temp.'/app/views/pages/new-connection.php');$view=str_replace('<strong>Applications are not open on this page yet.</strong>','<strong>Development preview — applications are unavailable.</strong>',$view);$view=str_replace('<script','<script nonce="<?= htmlspecialchars($fixtureNonce,ENT_QUOTES) ?>"',$view);returnWrite($temp.'/app/views/pages/new-connection.php',$view);
    $double=file_get_contents($temp.'/vendor/autoload.php');$double=str_replace("return 'https://provider.example.invalid/oauth?state='.\$this->state;","return 'http://'.json_decode(file_get_contents(dirname(__DIR__).'/private/config.json'),true)['host'].'/mportalv2/provider-double.php?state='.\$this->state;",$double);returnWrite($temp.'/vendor/autoload.php',$double);
    foreach(['index','register','google-login','google-callback','provider-double','forgot-password','reset-password','logout','new-connection','dashboard','session','begin','login','oauth','callback','forgot','reset','module','owned'] as $page)returnWrite($temp.'/public/'.$page.(str_contains($page,'.')?'':'.php'),"<?php require dirname(__DIR__).'/auth-return-fixture.php';");
    // Extensionless coordinator URLs are PHP wrappers too; Apache FilesMatch enables these exact names.
    foreach(['session','begin','login','register','oauth','callback','forgot','reset','logout','module','owned'] as $page)returnWrite($temp.'/public/'.$page,"<?php require dirname(__DIR__).'/auth-return-fixture.php';");
    mkdir($temp.'/empty',0700,true);returnWrite($temp.'/private/render-ready','ready');
}
