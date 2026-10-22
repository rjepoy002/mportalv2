<?php
// Synthetic publication fixture, deliberately unrelated to placeholder legal wording.
$fixture=require __DIR__.'/prototype-data.php';$notices=[];
foreach(['privacy_notice','information_accuracy','application_verification','physical_signing'] as $code)$notices[$code]=[
    'version'=>'TEST-'.$code.'-1','published'=>true,'test_only'=>true,
    'content'=>'Synthetic automated-test acknowledgment: '.$code.'. No legal notice or physical signature is represented.'
];
return ['environment'=>'synthetic_test','catalog_approved_for_test'=>true,'catalog'=>$fixture['catalog'],'notices'=>$notices];
