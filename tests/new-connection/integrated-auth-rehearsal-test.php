<?php
// Reuse the full 06F coordinator by applying checked, test-only transformations in memory.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/integrated-auth-rehearsal-support.php';
$source=file_get_contents(__DIR__.'/auth-return-integration-test.php');
$source=rehearsalReplace($source,"returnWrite(\$temp.'/auth-return-fixture.php',file_get_contents(__DIR__.'/auth-return-fixture.php'));",
    "returnWrite(\$temp.'/auth-return-fixture.php',file_get_contents(__DIR__.'/auth-return-fixture.php')); rehearsalPrepare(\$temp,\$db);",'SQL/schema/router preparation');
$source=rehearsalReplace($source,"foreach(['index','register','forgot-password','reset-password'] as \$view)",
    "rehearsalControllers(\$temp); foreach(['index','register','forgot-password','reset-password'] as \$view)",'temporary Google policy branch');
$source=rehearsalReplace($source,"returnWrite(\$temp.'/private/browser.json',", "rehearsalJourneys(); returnWrite(\$temp.'/private/browser.json',",'integrated journeys');
$source=str_replace("__DIR__.'/auth-return-browser-test.js'","__DIR__.'/integrated-auth-rehearsal-browser.js'",$source);
$source=str_replace("__DIR__.'/auth-return-integration-results.json'","__DIR__.'/integrated-auth-rehearsal-results.json'",$source);
$source=str_replace("'date'=>'2026-10-21'","'date'=>'2026-10-21','claim_adapter'=>'06H SQL','experimental_policy'=>true",$source);
$source=str_replace("'Synthetic return integration passed ('","'Integrated SQL/policy authentication rehearsal passed ('",$source);
$source=str_replace("'blocked'=>['Actual staging/controller deployment unapproved; no live changes.','Existing Google unbound Gmail/hosted unverified-account timestamp update needs separate policy/security approval; not executed or changed here.','Visual confirmation dialogs not re-exercised by this JSON-only harness; existing dialog regression evidence separate.']",
    "'blocked'=>['Production policy not approved; actual controllers unchanged.','Real Google/SMTP, Apache HTTPS and visual dialogs not exercised.','Crash/power-loss and distributed restore attestation not exercised.']",$source);
eval('?>'.$source);
