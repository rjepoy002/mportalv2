<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__,2).'/app/services/new-connection/applicant-contract-dispatch.php';
$ctx=require __DIR__.'/stage1-v2-data.php';$checks=0;
function v2check(bool $ok,string $label):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($label);}
function v2validate(array $input,bool $complete=false,?array $context=null):array{global $ctx;return newConnectionValidateContract(NewConnectionApplicantContractV2::VERSION,json_encode($input,JSON_THROW_ON_ERROR),$context??$ctx,$complete);}
$input=NewConnectionApplicantContractV2::emptyInput();v2check(v2validate($input)['valid']&&!v2validate($input)['ready'],'Valid incomplete draft');
$input['personal']=['date_of_birth'=>'2000-10-23','gender'=>'prefer_not_to_say','civil_status'=>'single'];$input['service_location']=array_replace($input['service_location'],['city_municipality_psgc'=>'TEST-V2-C1','barangay_psgc'=>'TEST-V2-B1','street_purok_sitio'=>'Synthetic Sitio','pin'=>['latitude'=>0.25,'longitude'=>0.25,'confirmed'=>true]]);$input['confirmations']=['profile_fingerprint'=>NewConnectionApplicantContractV2::profileFingerprint($ctx),'profile_confirmed'=>true,'information_confirmed'=>true];
$r=v2validate($input,true);v2check($r['ready'],'Complete input');v2check($r['snapshot']['personal']['age_years']===25,'Birthday not reached');
$d=$input;$d['personal']['date_of_birth']='2000-10-22';v2check(v2validate($d)['snapshot']['personal']['age_years']===26,'Birthday reached');
foreach(['2026-10-23','2023-02-29','2000-13-01','0000-01-01','2000-1-1'] as $date){$d=$input;$d['personal']['date_of_birth']=$date;v2check(!v2validate($d)['valid'],'Invalid date');}
$d=$input;$d['personal']['date_of_birth']='2026-10-22';v2check(v2validate($d,true)['ready'],'No invented minimum age');
$d=$input;$d['personal']['date_of_birth']='2000-02-29';$clock=$ctx;$clock['as_of']='2025-02-28';v2check(v2validate($d,false,$clock)['snapshot']['personal']['age_years']===24,'Leap-day age before March 1');$clock['as_of']='2025-03-01';v2check(v2validate($d,false,$clock)['snapshot']['personal']['age_years']===25,'Leap-day age on March 1');
$status=$ctx;$status['profile']['status']='inactive';v2check(!v2validate($input,false,$status)['valid'],'Inactive profile denied');$status=$ctx;$status['profile']['email_verified_at']=null;v2check(!v2validate($input,false,$status)['valid'],'Unverified profile denied');
foreach(['identity','applicant','age_years','province_context','catalog_version','residential_address','service_request','staff_permissions'] as $key){$d=$input;$d[$key]='spoof';v2check(!v2validate($d)['valid'],'Identity/server/legacy override denied');}
foreach(['gender','civil_status'] as $key){$d=$input;$d['personal'][$key]='invalid';v2check(!v2validate($d)['valid'],'Enum rejection');}
$d=$input;$d['personal']['civil_status']='married';v2check(!v2validate($d,true)['ready'],'Spouse required');$d['spouse']=['first_name'=>'Ana','middle_name'=>null,'last_name'=>'Test'];v2check(v2validate($d,true)['ready'],'Spouse middle not required');$d['personal']['civil_status']='widowed';v2check(v2validate($d)['input']['spouse']['first_name']===null,'Clear spouse');
foreach(array_keys(NewConnectionApplicantContractV2::EDUCATION) as $code){$d=$input;$d['additional']['education_code']=$code;v2check(v2validate($d,true)['ready'],'Education valid');}
$d=$input;$d['additional']['education_code']='college_graduate';$d['additional']['education_other']='discard';v2check(v2validate($d)['input']['additional']['education_other']===null,'Clear education Other');
$d=$input;$d['additional']['education_code']='invented';v2check(!v2validate($d)['valid'],'Education invalid');
foreach(['-1','1e3','10.999'] as $amount){$d=$input;$d['additional']['annual_income_php']=$amount;v2check(!v2validate($d)['valid'],'Decimal rejection');}
$d=$input;$d['additional']['annual_income_php']='0';v2check(v2validate($d)['input']['additional']['annual_income_php']==='0.00','Decimal normalization');
$d=$input;$d['additional']['dependent_count']=true;v2check(!v2validate($d)['valid'],'Boolean is not integer');
$d=$input;$d['additional']['religion']='Synthetic';$private=$ctx;$private['sensitive_allowed']=false;v2check(!v2validate($d,false,$private)['valid'],'Privacy gate');
$changed=$ctx;$changed['profile']['first_name']='Changed';v2check(!v2validate($input,true,$changed)['valid'],'Stale profile binding');
$d=$input;$d['service_location']['barangay_psgc']='TEST-V2-B2';v2check(!v2validate($d)['valid'],'Hierarchy mismatch');
$d=$input;$d['service_location']['pin']['latitude']=91;v2check(!v2validate($d)['valid'],'Coordinate range');$d['service_location']['pin']['latitude']=2;v2check(!v2validate($d)['valid'],'Synthetic box mismatch');
$d=$input;$d['service_location']['pin']=['latitude'=>null,'longitude'=>null,'confirmed'=>false];v2check(v2validate($d)['valid']&&!v2validate($d,true)['ready'],'Missing pin draft only');
$d=$input;$d['service_location']['geometry_version']='spoof';v2check(!v2validate($d)['valid'],'Geometry version override');
$json=str_repeat('x',65537);v2check(!NewConnectionApplicantContractV2::validate($json,$ctx)['valid'],'Oversize');
foreach(['[]','{"personal":[]}','{"personal":{"gender":false}}','{"service_location":{"pin":{"latitude":"0.25"}}}'] as $json)v2check(!NewConnectionApplicantContractV2::validate($json,$ctx)['valid'],'Wrong types');
$old=require __DIR__.'/prototype-data.php';v2check(newConnectionValidateContract(NewConnectionApplicantContract::VERSION,'{}',$old)['valid'],'Explicit v1 dispatch');
try{newConnectionValidateContract('unknown','{}',$ctx);throw new RuntimeException('Unknown version accepted');}catch(InvalidArgumentException $e){$checks++;}
echo "Stage 1 v2 contract passed ($checks assertions; pure synthetic data, no database).\n";
