<?php
require_once __DIR__ . '/../app/services/ebills-enrollment.php';
$dsn=getenv('EBILLS_TEST_DSN');
if(!$dsn){echo "SKIP: set EBILLS_TEST_DSN for mportal_ebills_test.\n";exit;}
if(!str_starts_with($dsn,'mysql:')||!preg_match('/(?:^|;)dbname=mportal_ebills_test(?:;|$)/',substr($dsn,6)))throw new RuntimeException('Dedicated eBills test database required.');
final class EBillsFixturePDO extends PDO{
    public function prepare(string $query,array $options=[]):PDOStatement|false{return parent::prepare(str_replace(['mco_portal.','palecxzp_pal_db.'],'mportal_ebills_test.',$query),$options);}
}
function testEBillsPDO():PDO{return new EBillsFixturePDO(getenv('EBILLS_TEST_DSN'),getenv('EBILLS_TEST_USER')?:'root',getenv('EBILLS_TEST_PASSWORD')?:'',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);}
$pdo=testEBillsPDO();if($pdo->query('SELECT DATABASE()')->fetchColumn()!=='mportal_ebills_test')throw new RuntimeException('Wrong fixture database.');
$service=new EBillsEnrollment($pdo);$csrf=str_repeat('c',64);$input=['csrf_token'=>$csrf,'consent'=>'1','first_name'=>'Reviewed','last_name'=>'Consumer','middle_name'=>'','extension_name'=>'Jr.','contact_number'=>'09123456789'];
function acceptedInput(EBillsEnrollment $service,int $user,string $account,array $input,string $csrf):array{
    $details=$service->details($user,$account);$input['terms_token']=$details['terms_token'];$input['terms_version']=$details['terms_version'];$service->acceptTerms($user,$account,$input,$csrf);return $input;
}
if(($argv[1]??'')==='--worker'){
    $directory=$argv[2];$id=(int)$argv[3];$input=acceptedInput($service,$id,'A',$input,$csrf);file_put_contents($directory.'/ready-'.$id,'1');$deadline=microtime(true)+10;
    while(!is_file($directory.'/go')){if(microtime(true)>$deadline)exit(2);usleep(1000);}
    echo $service->enroll($id,'A',$input,$csrf)?'1':'0';exit;
}
if($pdo->query('SHOW TABLES')->fetchColumn()!==false)throw new RuntimeException('Fixture database must be empty.');
$created=[];$checks=0;
function ebCheck(bool $ok,string $message):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
function rejectEnrollment(callable $call):void{$rejected=false;try{$call();}catch(DomainException $e){$rejected=true;}ebCheck($rejected,'Invalid request was accepted.');}
try{
    foreach(['users','paleco_accounts','activity_logs'] as $table){$pdo->exec('CREATE TABLE '.$table.' LIKE mco_portal.'.$table);$created[]=$table;}
    $pdo->exec('CREATE TABLE ebills LIKE palecxzp_pal_db.ebills');$created[]='ebills';
    $pdo->exec('CREATE TABLE master (AcctNo VARCHAR(15) PRIMARY KEY,AcctCode VARCHAR(12),Name VARCHAR(255),Address VARCHAR(255)) ENGINE=InnoDB');$created[]='master';
    $pdo->exec("INSERT INTO users (id,email,name,password_hash) VALUES (1,'portal-one@example.test','Existing Display Name','hash'),(2,'portal-two@example.test','Another Display','hash'),(3,'third@example.test','Third Display','hash')");
    $pdo->exec("INSERT INTO master VALUES ('A','02-0504-9986','DIFFERENT ACCOUNT HOLDER','Address A'),('B','03-0123-4567','ANOTHER HOLDER','Address B'),('C','04-0123-4567','THIRD HOLDER','Address C')");
    $pdo->exec("INSERT INTO paleco_accounts (user_id,AcctNo,status,is_primary) VALUES (1,'A','active',1),(1,'B','active',0),(2,'A','active',1)");
    $profile=['csrf_token'=>$csrf,'first_name'=>'Portal','last_name'=>'Consumer','middle_name'=>'','extension_name'=>'Jr.','contact_number'=>'09123456789'];
    ebCheck($service->profile(1)['first_name']===null,'Migration inferred existing first name.');
    ebCheck($service->details(1,'A')['problem']==='','Missing names must be editable during enrollment.');
    $optional=prepareEBillsStep3($pdo,1,'A');
    ebCheck($optional['code']==='02-0504-9986'&&$optional['enrolled']===false,'Optional Step 3 did not identify linked, unenrolled account.');
    ebCheck($_SESSION['dashboard_account_selection_tokens'][$optional['token']]==='A','Step 3 selection token not server-bound.');
    ebCheck(prepareEBillsStep3($pdo,2,'B')===null,'Step 3 exposed another user association.');
    rejectEnrollment(fn()=>$service->enroll(1,'A',$input,$csrf));
    rejectEnrollment(fn()=>$service->saveProfile(1,array_replace($profile,['first_name'=>str_repeat('a',26)]),$csrf));
    rejectEnrollment(fn()=>$service->saveProfile(1,array_replace($profile,['last_name'=>'']),$csrf));
    $service->saveProfile(1,$profile,$csrf);
    ebCheck($pdo->query('SELECT name FROM users WHERE id=1')->fetchColumn()==='Existing Display Name','Profile changed legacy display name.');
    ebCheck($service->profile(1)['middle_name']===null,'Optional empty name not normalized.');
    $details=$service->details(1,'A');
    ebCheck($details['profile']['first_name']==='Portal'&&$details['account']['AcctCode']==='02-0504-9986','Details used wrong identity/account.');
    ebCheck(!isset($details['account']['AcctNo'])&&!isset($details['profile']['id']),'Details leaked internal identifier.');
    $unaccepted=$input;$issued=$service->details(1,'A');$unaccepted['terms_token']=$issued['terms_token'];$unaccepted['terms_version']=$issued['terms_version'];
    rejectEnrollment(fn()=>$service->enroll(1,'A',$unaccepted,$csrf));
    rejectEnrollment(fn()=>$service->acceptTerms(1,'A',array_replace($unaccepted,['consent'=>'0']),$csrf));
    rejectEnrollment(fn()=>$service->acceptTerms(1,'A',array_replace($unaccepted,['csrf_token'=>'bad']),$csrf));
    $input=acceptedInput($service,1,'A',$input,$csrf);
    $inputB=acceptedInput($service,1,'B',$input,$csrf);
    $inputOther=acceptedInput($service,2,'A',$input,$csrf);
    rejectEnrollment(fn()=>$service->enroll(1,'B',$input,$csrf));
    rejectEnrollment(fn()=>$service->enroll(2,'A',$input,$csrf));
    rejectEnrollment(fn()=>$service->enroll(1,'A',array_replace($input,['terms_version'=>'old-version']),$csrf));
    $state=$_SESSION['ebills_terms'][$input['terms_token']];$_SESSION['ebills_terms'][$input['terms_token']]['expires']=time()-1;
    rejectEnrollment(fn()=>$service->enroll(1,'A',$input,$csrf));$_SESSION['ebills_terms'][$input['terms_token']]=$state;
    $_SESSION['ebills_terms'][$input['terms_token']]['version']='superseded';
    rejectEnrollment(fn()=>$service->enroll(1,'A',$input,$csrf));$_SESSION['ebills_terms'][$input['terms_token']]=$state;
    rejectEnrollment(fn()=>$service->enroll(1,'A',array_replace($input,['first_name'=>'']),$csrf));
    rejectEnrollment(fn()=>$service->enroll(1,'A',array_replace($input,['last_name'=>str_repeat('x',26)]),$csrf));
    ebCheck((int)$pdo->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn()===0,'Acceptance before enrollment created a misleading consent audit.');
    ebCheck($details['account']['Address']==='Address A','Service address missing.');
    foreach(['','123','+639123456789','09123a56789','091234567890'] as $bad)rejectEnrollment(fn()=>$service->enroll(1,'A',array_replace($input,['contact_number'=>$bad]),$csrf));
    rejectEnrollment(fn()=>$service->enroll(0,'A',$input,$csrf));
    rejectEnrollment(fn()=>$service->enroll(1,'C',$input,$csrf));
    rejectEnrollment(fn()=>$service->enroll(1,'A',array_replace($input,['csrf_token'=>'bad']),$csrf));
    rejectEnrollment(fn()=>$service->enroll(1,'A',array_replace($input,['consent'=>'0']),$csrf));
    ebCheck((int)$pdo->query('SELECT COUNT(*) FROM ebills')->fetchColumn()===0,'Failed requests inserted enrollment.');
    ebCheck($service->enroll(1,'A',$input+['FirstName'=>'FORGED','Email'=>'forged@example.test','email'=>'forged@example.test','AccCode'=>'OTHER'],$csrf),'Valid enrollment failed.');
    $row=$pdo->query('SELECT * FROM ebills')->fetch(); // Synthetic fixture only, including sentinel checks.
    ebCheck($row['FirstName']==='Reviewed'&&$row['LastName']==='Consumer'&&$row['Email']==='portal-one@example.test','Enrollment ignored reviewed identity or trusted a forged email/account-holder name.');
    ebCheck($row['AccCode']==='02-0504-9986'&&$row['ContactNumber']==='09123456789','Enrollment mapping/contact incorrect.');
    ebCheck($row['DateCreated']!==null&&$row['TimeStamp']!==null,'Enrollment dates missing.');
    ebCheck($row['Attachment']===null&&$row['otp']===null&&$row['otp_TimeStamp']===null,'Excluded columns were populated.');
    ebCheck(prepareEBillsStep3($pdo,1,'A')['enrolled']===true,'Step 3 failed to recognize existing enrollment.');
    $audit=$pdo->query('SELECT * FROM activity_logs')->fetch();$consent=json_decode($audit['description'],true);
    ebCheck((int)$audit['user_id']===1&&$audit['action']==='ebills_enrolled'&&$consent['account_code']===$row['AccCode']&&$consent['consent']===EBillsEnrollment::CONSENT&&$consent['consent_version']===EBillsEnrollment::termsVersion()&&isset($consent['accepted_at'])&&$consent['terms_source']===EBillsEnrollment::TERMS_SOURCE,'Consent audit incomplete.');
    ebCheck(isset($consent['timestamp'],$consent['consent_version'])&&!str_contains($audit['description'],'09123456789')&&!str_contains($audit['description'],'portal-one@example.test'),'Audit missing version/time or logging excess personal data.');
    ebCheck($service->profile(1)['first_name']==='Reviewed','Approved edited names were not saved to the authenticated profile.');
    ebCheck($pdo->query('SELECT name FROM users WHERE id=1')->fetchColumn()==='Existing Display Name','Enrollment changed legacy name.');
    $before=hash('sha256',json_encode($row));
    $beforeProfile=$service->profile(1);
    $duplicateInput=array_replace($input,['first_name'=>'Duplicate must not save','contact_number'=>'09999999999']);
    ebCheck(!$service->enroll(1,'A',$duplicateInput,$csrf),'Duplicate enrollment accepted.');
    ebCheck(hash('sha256',json_encode($pdo->query('SELECT * FROM ebills')->fetch()))===$before,'Duplicate altered existing enrollment.');
    ebCheck($service->profile(1)===$beforeProfile,'Duplicate enrollment changed the profile.');
    $profile['contact_number']='09987654321';$service->saveProfile(1,$profile,$csrf);
    ebCheck($pdo->query('SELECT ContactNumber FROM ebills')->fetchColumn()==='09123456789','Profile update changed existing enrollment.');
    $map=$service->statuses([['AcctCode'=>'02-0504-9986'],['AcctCode'=>'03-0123-4567']]);
    ebCheck(isset($map['02-0504-9986'])&&!isset($map['03-0123-4567']),'Mixed enrollment statuses incorrect.');
    $service->saveProfile(2,$profile,$csrf);
    ebCheck($service->details(2,'A')['enrolled']&&!str_contains(json_encode($service->details(2,'A')),'portal-one@example.test'),'Existing enrollment exposed another recipient.');
    ebCheck(!$service->enroll(2,'A',$inputOther,$csrf),'Different user overwrote existing enrollment.');
    $pdo->exec("UPDATE users SET email=CONCAT(REPEAT('a',45),'@example.test') WHERE id=1");
    rejectEnrollment(fn()=>$service->enroll(1,'B',$inputB,$csrf));
    $pdo->exec("UPDATE users SET email='portal-one@example.test' WHERE id=1");
    $pdo->exec("UPDATE paleco_accounts SET status='archived' WHERE user_id=1 AND AcctNo='B'");
    rejectEnrollment(fn()=>$service->enroll(1,'B',$inputB,$csrf));
    $pdo->exec("UPDATE paleco_accounts SET status='active' WHERE user_id=1 AND AcctNo='B'");
    // A failed required audit write rolls back BOTH enrollment and profile updates.
    $pdo->exec('RENAME TABLE activity_logs TO unavailable_activity_logs');
    try{$failed=false;try{$service->enroll(1,'B',$inputB,$csrf);}catch(PDOException $e){$failed=true;}ebCheck($failed,'Audit failure not surfaced.');
        ebCheck((int)$pdo->query("SELECT COUNT(*) FROM ebills WHERE AccCode='03-0123-4567'")->fetchColumn()===0,'Partial enrollment survived rollback.');
        ebCheck($service->profile(1)['contact_number']==='09987654321'&&$service->profile(1)['first_name']==='Portal','Partial profile update survived rollback.');
        ebCheck($pdo->query("SELECT status FROM paleco_accounts WHERE user_id=1 AND AcctNo='B'")->fetchColumn()==='active','Enrollment failure undid account linking.');
    }finally{$pdo->exec('RENAME TABLE unavailable_activity_logs TO activity_logs');}
    // Two different users compete for one AccCode: one record and one consent.
    $pdo->exec('DELETE FROM ebills');$pdo->exec('DELETE FROM activity_logs');
    $directory=__DIR__ . '/ebills-concurrent-'.bin2hex(random_bytes(6));mkdir($directory,0700);$workers=[];
    try{
        foreach([1,2] as $id){$process=proc_open([PHP_BINARY,__FILE__,'--worker',$directory,(string)$id],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($process))throw new RuntimeException('Worker startup failed.');fclose($pipes[0]);$workers[]=[$process,$pipes];}
        $deadline=microtime(true)+10;while(count(glob($directory.'/ready-*'))!==2){if(microtime(true)>$deadline)throw new RuntimeException('Worker timeout.');usleep(1000);}file_put_contents($directory.'/go','1');
        $wins=0;foreach($workers as [$process,$pipes]){$output=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);ebCheck(proc_close($process)===0&&$errors==='','Concurrent enrollment worker failed.');$wins+=(int)$output;}
        ebCheck($wins===1,'Concurrent enrollment did not produce exactly one winner.');
        ebCheck((int)$pdo->query('SELECT COUNT(*) FROM ebills')->fetchColumn()===1&&(int)$pdo->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn()===1,'Concurrent enrollment duplicated record/audit.');
    }finally{foreach(glob($directory.'/*') as $file)unlink($file);rmdir($directory);}
    echo "eBills integration tests passed ($checks assertions; synthetic data and concurrent workers).\n";
}finally{if($pdo->inTransaction())$pdo->rollBack();foreach(array_reverse($created) as $table)$pdo->exec('DROP TABLE IF EXISTS '.$table);}
