<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/stage1-v2-persistence-support.php';
$file=realpath($argv[1]??'');if(!$file||!str_starts_with($file,realpath(sys_get_temp_dir()).DIRECTORY_SEPARATOR)||!preg_match('/^mco-v2-race-[a-f0-9]{16}$/D',basename(dirname($file))))throw new LogicException('Private race file required');
$c=json_decode(file_get_contents($file),true,32,JSON_THROW_ON_ERROR);$db=draftTestConnection($c['schema']);$s=v2persistentService($db,$c['schema']);
try{
 if($c['action']==='profile'){$db->beginTransaction();$q=$db->prepare('SELECT id FROM users WHERE id=1 FOR UPDATE');$q->execute();file_put_contents(dirname($file).'/locked','1');$db->exec("UPDATE users SET first_name='Race profile' WHERE id=1");usleep(400000);$db->commit();}
 elseif($c['action']==='save')$s->saveVersioned(1,$c['reference'],NewConnectionApplicantContractV2::VERSION,$c['expected'],$c['key'],json_encode($c['input']));
 else $s->abandon(1,$c['reference'],$c['expected'],$c['key'],true);
 echo 'ok';
}catch(NewConnectionConflict|NewConnectionDenied|NewConnectionDraftInvalid $e){echo 'conflict';}
