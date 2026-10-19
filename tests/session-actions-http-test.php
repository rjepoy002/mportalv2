<?php
// Isolated PHP server + synthetic sessions. No database or email actions.
$directory=__DIR__ . '/session-actions-'.bin2hex(random_bytes(6));
mkdir($directory,0700);
$socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
if(!$socket)throw new RuntimeException('Cannot allocate test port.');
$address=stream_socket_get_name($socket,false);fclose($socket);
$server=proc_open([PHP_BINARY,'-d','session.save_path='.$directory,'-S',$address,'-t',dirname(__DIR__)],[0=>['pipe','r'],1=>['file',$directory.'/server.log','a'],2=>['file',$directory.'/server.log','a']],$pipes);
if(!is_resource($server))throw new RuntimeException('Cannot start test server.');
fclose($pipes[0]);
session_save_path($directory);
$checks=0;
function sessionCheck(bool $ok,string $message):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
function requestLogout(string $method,string $id,array $data=[],bool $json=true):array{
    global $address;
    $context=stream_context_create(['http'=>['method'=>$method,'ignore_errors'=>true,'follow_location'=>0,'timeout'=>5,'header'=>"Cookie: PHPSESSID=$id\r\nContent-Type: application/x-www-form-urlencoded\r\n".($json?"Accept: application/json\r\n":''),'content'=>http_build_query($data)]]);
    $body=file_get_contents('http://'.$address.'/logout.php',false,$context);
    return [$body,$http_response_header];
}
function seedSession():string{
    $id=bin2hex(random_bytes(16));session_id($id);session_start();$_SESSION=['user_id'=>123456,'dashboard_csrf'=>str_repeat('c',64),'sentinel'=>'unchanged'];session_write_close();return $id;
}
try{
    $deadline=microtime(true)+10;
    do{$connection=@stream_socket_client('tcp://'.$address,$errno,$error,0.2);if($connection){fclose($connection);break;}usleep(20000);}while(microtime(true)<$deadline);
    if(!$connection)throw new RuntimeException('Test server did not start.');
    $id=seedSession();$path=$directory.'/sess_'.$id;$before=file_get_contents($path);
    [$body,$headers]=requestLogout('GET',$id);
    sessionCheck(str_contains($headers[0],'405'),'GET logout was accepted.');
    sessionCheck(file_get_contents($path)===$before,'GET changed the session.');
    [$body,$headers]=requestLogout('POST',$id,['csrf_token'=>'bad','intent'=>'logout']);
    sessionCheck(str_contains($headers[0],'419'),'Invalid CSRF accepted.');
    sessionCheck(file_get_contents($path)===$before,'Invalid CSRF changed the session.');
    [$body,$headers]=requestLogout('POST',$id,['csrf_token'=>str_repeat('c',64),'intent'=>'invalid']);
    sessionCheck(str_contains($headers[0],'400'),'Unknown action accepted.');
    sessionCheck(file_get_contents($path)===$before,'Invalid action changed the session.');
    foreach(['logout'=>'index.php','reset'=>'forgot-password.php'] as $intent=>$target){
        foreach([true,false] as $json){
            $id=seedSession();
            [$body,$headers]=requestLogout('POST',$id,['csrf_token'=>str_repeat('c',64),'intent'=>$intent],$json);
            sessionCheck(!is_file($directory.'/sess_'.$id),'Confirmed action retained authenticated session data.');
            sessionCheck((bool)array_filter($headers,fn($v)=>str_starts_with(strtolower($v),'set-cookie: phpsessid=')&&str_contains(strtolower($v),'expires=')),'Session cookie was not expired.');
            if($json){$result=json_decode($body,true,512,JSON_THROW_ON_ERROR);sessionCheck($result['ok']&&$result['redirect']===$target,'Wrong JSON redirect.');}
            else{sessionCheck(str_contains($headers[0],'302')&&in_array('Location: '.$target,$headers,true),'Wrong standard POST redirect.');}
            [$body,$headers]=requestLogout('POST',$id,['csrf_token'=>str_repeat('c',64),'intent'=>$intent]);
            sessionCheck(str_contains($headers[0],'401'),'Replayed session retained authentication.');
        }
    }
    echo "Session action HTTP tests passed ($checks assertions; synthetic sessions only).\n";
}finally{
    proc_terminate($server);proc_close($server);
    foreach(glob($directory.'/*') as $file)unlink($file);
    rmdir($directory);
}
