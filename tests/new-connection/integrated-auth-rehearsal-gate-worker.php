<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/integrated-auth-rehearsal-support.php';
$temp=$argv[1];rehearsalTempGuard($temp);
$state=json_decode(file_get_contents($temp.'/private/next-gate.json'),true,16,JSON_THROW_ON_ERROR);
file_put_contents($temp.'/private/gate-writer-ready','ready');
rehearsalExternalWrite($state);echo 'closed';
