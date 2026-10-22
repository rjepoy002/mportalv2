<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$c=json_decode(file_get_contents($argv[1]),true,16,JSON_THROW_ON_ERROR);
if(!preg_match('/^127\.0\.0\.1:[0-9]+$/D',$c['host']))throw new RuntimeException('Local client required');
file_get_contents('http://'.$c['host'].'/login',false,stream_context_create(['http'=>['method'=>'POST','timeout'=>18,'ignore_errors'=>true,'follow_location'=>0,'header'=>'Cookie: PHPSESSID='.$c['cookie']."\r\nContent-Type: application/x-www-form-urlencoded\r\n",'content'=>http_build_query($c['body'])]]));
$out=['status'=>0,'location'=>'','regenerated'=>false];
foreach($http_response_header as $h){
    if(preg_match('/^HTTP\/\S+ (\d+)/',$h,$m))$out['status']=(int)$m[1];
    if(str_starts_with($h,'Location: '))$out['location']=substr($h,10);
    if(preg_match('/^Set-Cookie: PHPSESSID=([^;]+)/i',$h,$m)&&$m[1]!==$c['cookie'])$out['regenerated']=true;
}
echo json_encode($out,JSON_THROW_ON_ERROR);
