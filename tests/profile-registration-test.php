<?php
require_once __DIR__ . '/../app/services/ebills-enrollment.php';
$checks=0;
function profileCheck(bool $ok,string $message):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
$valid=['first_name'=>' Ana Maria ','last_name'=>'De la Cruz','middle_name'=>'','extension_name'=>'Jr.','contact_number'=>''];
$p=portalProfileValues($valid);
profileCheck($p['first_name']==='Ana Maria'&&$p['last_name']==='De la Cruz','Structured names were split or not preserved.');
profileCheck($p['middle_name']===null&&$p['contact_number']===null,'Optional fields not nullable.');
foreach(['first_name','last_name','middle_name','extension_name'] as $field){
    $v=$valid;$v[$field]=str_repeat('é',25);profileCheck(mb_strlen(portalProfileValues($v)[$field])===25,'Valid multibyte name rejected.');
    $v[$field]=str_repeat('é',26);$rejected=false;try{portalProfileValues($v);}catch(DomainException $e){$rejected=true;}profileCheck($rejected,'Overlength name accepted.');
}
foreach([['first_name'=>''],['last_name'=>''],['first_name'=>['tampered']],['first_name'=>"Bad\nName"],['contact_number'=>'091234567890'],['contact_number'=>'+639123456789']] as $bad){
    $rejected=false;try{portalProfileValues(array_replace($valid,$bad));}catch(DomainException $e){$rejected=true;}profileCheck($rejected,'Invalid profile accepted.');
}
session_save_path(sys_get_temp_dir());$_SERVER['REQUEST_METHOD']='GET';
ob_start();require __DIR__ . '/../register.php';$html=ob_get_clean();
foreach(['first_name','last_name','middle_name','extension_name','contact_number'] as $key)profileCheck(str_contains($html,'name="'.$key.'"'),'Registration field missing.');
profileCheck(str_contains($html,'id="nameError"'),'Existing registration error target removed.');
profileCheck(!str_contains($html,'name="name"'),'Old full-name input remains authoritative.');
profileCheck(!str_contains($html,'Warning:')&&!str_contains($html,'Fatal error:'),'Registration render failed.');
if(session_status()===PHP_SESSION_ACTIVE)session_destroy();
echo "Profile and registration tests passed ($checks assertions; GET only, no real registration/email).\n";
