<?php
// CLI-only negative-test control, copied into the private disposable harness.
if(PHP_SAPI!=='cli'||!is_file(__DIR__.'/private/fixture.json')){http_response_code(404);exit;}
require __DIR__.'/draft-test-support.php';$cfg=json_decode(file_get_contents(__DIR__.'/private/fixture.json'),true,32,JSON_THROW_ON_ERROR);
$db=draftTestConnection($cfg['schema']);
if(!in_array($argv[1]??'', ['inactive','unverified','restore'],true))throw new RuntimeException('Invalid synthetic control.');
$q=$db->prepare('UPDATE users SET status=?,email_verified_at=? WHERE id=4');
$q->execute([$argv[1]==='inactive'?'inactive':'active',$argv[1]==='unverified'?null:'2026-10-20']);
