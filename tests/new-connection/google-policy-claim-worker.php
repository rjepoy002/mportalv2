<?php
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
require __DIR__.'/isolated-identity-claim.php';
// Arguments contain only disposable schema, public fixture IDs, barrier paths and synthetic values.
[$script,$schema,$operation,$barrier,$generation,$actor,$proof]=$argv;
$db=draftTestConnection($schema);
file_put_contents($barrier.'.ready','ready');
$until=microtime(true)+15;
while (!is_file($barrier.'.go')) {if(microtime(true)>$until)exit(3);usleep(10000);}
try {
    if ($operation==='claim') $result=(new IsolatedSqlReturnClaims($db,$generation))->claim(hash('sha256','shared-flow'),time()+300,time());
    else {
        $provider=$operation==='cross' ? new SyntheticProviderEvidence('cross-subject',$actor==='6'?'other@example.invalid':'cross@example.invalid') : new SyntheticProviderEvidence('race-subject','race@example.invalid');
        $result=(new IsolatedGooglePolicy($db))->decide($provider,'link',(int)$actor,$proof,str_repeat('s',32));
    }
    echo json_encode(['result'=>$result],JSON_THROW_ON_ERROR);
} catch (Throwable) {echo json_encode(['failed'=>true]);}
