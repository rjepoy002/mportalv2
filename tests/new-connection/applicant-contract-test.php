<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once dirname(__DIR__,2).'/app/services/new-connection/applicant-contract.php';
$context=require __DIR__.'/prototype-data.php';$checks=0;
function protoCheck(bool $ok,string $message):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
function protoJSON(array $data):string{return json_encode($data,JSON_THROW_ON_ERROR);}
$empty=NewConnectionApplicantContract::emptyInput();
$r=NewConnectionApplicantContract::validate('{}',$context);protoCheck($r['valid']&&!$r['ready'],'Empty draft semantics');
foreach(['[]','null','"text"','{bad'] as $json)protoCheck(!NewConnectionApplicantContract::validate($json,$context)['valid'],'Reject invalid JSON object');
$full=$empty;$full['applicant']=array_replace($full['applicant'],$context['profile'],['contact_number'=>'+63 900-000-0000','profile_confirmed'=>true]);
$full['residential_address']=array_replace($full['residential_address'],['city_municipality_code'=>'TEST-C1','barangay_code'=>'TEST-B1','street_purok_sitio'=>'Synthetic Sitio','postal_code'=>'0123']);
$full['service_location']['same_as_residential']=true;$full['service_request']['premises_classification_code']='home';$full['service_request']['occupancy_code']='owner';$full['review']['information_confirmed']=true;$full['acknowledgments']=array_fill_keys(NewConnectionApplicantContract::ACKS,true);
$r=NewConnectionApplicantContract::validate(protoJSON($full),$context,true);protoCheck($r['valid']&&$r['ready'],'Complete synthetic input');
protoCheck($r['snapshot']['applicant']['contact_number']==='09000000000','Mobile canonical form');
protoCheck($r['snapshot']['service_location']['postal_code']==='0123','Address copy/postal leading zeros');
protoCheck($r['snapshot']['residential_address']['city_municipality_name']===$context['catalog']['cities']['TEST-C1']['name'],'Server locality labels');
protoCheck(!isset($r['snapshot']['acknowledgments']),'Checkbox selections not in durable snapshot');
$reordered=array_reverse($full,true);protoCheck(NewConnectionApplicantContract::validate(protoJSON($reordered),$context,true)['canonical']===$r['canonical'],'Deterministic canonicalization');
foreach(['09000000000','+639000000000','639000000000','9000000000'] as $mobile){$x=$full;$x['applicant']['contact_number']=$mobile;protoCheck(NewConnectionApplicantContract::validate(protoJSON($x),$context,true)['ready'],'Supported mobile syntax');}
$cases=[
 ['applicant','email','attacker@example.invalid'],['applicant','first_name',['bad']],['applicant','last_name',42],['applicant','first_name',"Bad\nName"],
 ['applicant','first_name',str_repeat('é',26)],['applicant','contact_number','08123456789'],['applicant','profile_confirmed','true'],
 ['residential_address','postal_code','123'],['residential_address','postal_code',1234],['residential_address','barangay_code','TEST-B2'],
 ['residential_address','province_code','TEST-P'],['residential_address','province_name','forged'],['service_request','request_kind','transfer'],
 ['service_request','premises_classification_code','official_fake'],['service_request','connection_type_code','ordinary'],['service_request','requested_service_details',str_repeat('x',501)],
 ['service_request','occupancy_other','unnecessary'],['review','information_confirmed',1]
];
foreach($cases as [$s,$k,$v]){$x=$full;$x[$s][$k]=$v;protoCheck(!NewConnectionApplicantContract::validate(protoJSON($x),$context,true)['valid'],'Reject '.$s.'.'.$k);}
$x=$full;$x['unexpected']=new stdClass;protoCheck(!NewConnectionApplicantContract::validate(protoJSON($x),$context)['valid'],'Unknown top-level key');
protoCheck(!NewConnectionApplicantContract::validate(str_repeat(' ',65537),$context)['valid'],'Oversized payload');
$x=$full;$x['applicant']['first_name']=str_repeat('é',25);protoCheck(NewConnectionApplicantContract::validate(protoJSON($x),$context,true)['ready'],'Valid Unicode boundary');
$x=$full;$x['service_request']['premises_classification_code']='shop';protoCheck(!NewConnectionApplicantContract::validate(protoJSON($x),$context,true)['ready'],'Conditional category required');
$x['service_request']['connection_type_code']='ordinary';protoCheck(NewConnectionApplicantContract::validate(protoJSON($x),$context,true)['ready'],'Conditional example category');
$x['service_request']['occupancy_code']='other';protoCheck(!NewConnectionApplicantContract::validate(protoJSON($x),$context,true)['ready'],'Other occupancy clarification required');
$x['service_request']['occupancy_other']='Synthetic example';protoCheck(NewConnectionApplicantContract::validate(protoJSON($x),$context,true)['ready'],'Conditional occupancy provided');
$x=$full;$x['acknowledgments']['privacy_notice']=false;protoCheck(!NewConnectionApplicantContract::validate(protoJSON($x),$context,true)['ready'],'Acknowledgment separate required demo');
$legacy=NewConnectionApplicantContract::emptyInput();$legacyContext=$context;$legacyContext['profile']=['name'=>'Do not split this legacy name'];protoCheck(NewConnectionApplicantContract::validate(protoJSON($legacy),$legacyContext)['input']['applicant']['first_name']===null,'No legacy name splitting');
echo "Applicant contract passed ($checks assertions; pure synthetic input, no database or email).\n";
