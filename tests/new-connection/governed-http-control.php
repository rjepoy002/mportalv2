<?php
if(PHP_SAPI!=='cli'||!is_file(__DIR__.'/private/fixture.json')){http_response_code(404);exit;}
require __DIR__.'/draft-test-support.php';
require __DIR__.'/app/services/new-connection/submission.php';
require __DIR__.'/governed-http-policy.php';
$cfg=json_decode(file_get_contents(__DIR__.'/private/fixture.json'),true,32,JSON_THROW_ON_ERROR);
governedChange(draftTestConnection($cfg['schema']),$cfg['schema'],$argv[1]??'');
