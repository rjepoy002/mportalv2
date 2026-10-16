<?php

require_once __DIR__ . '/../includes/account-linking.php';
require_once __DIR__ . '/../includes/account-unlinking.php';
require_once __DIR__ . '/../includes/billing-service.php';
require_once __DIR__ . '/../includes/soa-service.php';

// Opt-in, isolated fixtures only. Never point this suite at application databases.
$dsn = getenv('ACCOUNT_LINK_TEST_DSN');
if (!$dsn) {
    echo "SKIP MySQL linking tests: set ACCOUNT_LINK_TEST_DSN for mportal_account_linking_test.\n";
    exit;
}
if (!str_starts_with($dsn, 'mysql:') || !preg_match('/(?:^|;)dbname=mportal_account_linking_test(?:;|$)/', substr($dsn, 6))) {
    throw new RuntimeException('Only the dedicated mportal_account_linking_test database is permitted.');
}

final class AccountLinkFixturePdo extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        // Both operational SELECTs and portal mutations resolve to synthetic fixtures.
        $query = str_replace(['mco_portal.', 'palecxzp_pal_db.'], 'mportal_account_linking_test.', $query);
        return parent::prepare($query, $options);
    }
}

function linkTestPdo(): PDO
{
    return new AccountLinkFixturePdo(getenv('ACCOUNT_LINK_TEST_DSN'), getenv('ACCOUNT_LINK_TEST_USER') ?: 'root', getenv('ACCOUNT_LINK_TEST_PASSWORD') ?: '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

function linkTestConfig(): array
{
    $config = require __DIR__ . '/../config/account-linking.php';
    $config['enabled'] = true; // Synthetic tests only; does not change deployment flags.
    return $config;
}

$notificationMessages = [];
$notificationResult = true;
function linkTestTransport(...$message): bool
{
    global $pdo, $notificationMessages, $notificationResult;
    if ($pdo->inTransaction()) throw new RuntimeException('Transport invoked before commit.');
    $notificationMessages[] = $message;
    if ($notificationResult === 'throw') throw new RuntimeException('private-recipient-and-secret');
    return (bool)$notificationResult;
}
function fixtureUnlink(PDO $pdo, int $userId, string $acctNo, string $token, string $csrf): bool
{
    return unlinkPalecoAccount($pdo,$userId,$acctNo,$token,$csrf,'linkTestTransport');
}

function dashboardEscape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$pdo = linkTestPdo();
if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'mportal_account_linking_test') {
    throw new RuntimeException('Connection resolved outside the dedicated fixture database.');
}
$service = new PalecoAccountLinker($pdo, linkTestConfig(), 'linkTestTransport');
$csrf = str_repeat('c', 64);

if (($argv[1] ?? '') === '--worker') {
    $id = $argv[2];
    $directory = $argv[3];
    $worker = $argv[4];
    file_put_contents($directory . '/ready-' . $worker, '1');
    $deadline = microtime(true) + 15;
    while (!is_file($directory . '/go')) {
        if (microtime(true) > $deadline) exit(2);
        usleep(1000);
    }
    $result = $service->handle(1, ['csrf_token' => $csrf, 'action' => 'verify_link', 'challenge_id' => $id, 'bill_month' => '202609', 'bill_amount' => '1250.00'], $csrf);
    echo json_encode($result);
    exit;
}

if ($pdo->query('SHOW TABLES')->fetchColumn() !== false) throw new RuntimeException('Dedicated test database must be empty.');
$created = [];
$checks = 0;
$mailHash = hash_file('sha256', __DIR__ . '/../config/mail.php');
$previousErrorLog = ini_get('error_log');
$auditFile = __DIR__ . '/link-test-audit-' . bin2hex(random_bytes(6)) . '.log';
ini_set('error_log', $auditFile);
function linkCheck(bool $condition, string $message): void
{
    global $checks;
    $checks++;
    if (!$condition) throw new RuntimeException($message);
}
function linkPost(array $input, int $user = 1): array
{
    global $service, $csrf;
    return $service->handle($user, $input + ['csrf_token' => $csrf], $csrf);
}
function linkReset(): void
{
    global $pdo;
    foreach (['account_notifications', 'account_link_challenges', 'account_link_limits', 'paleco_accounts', 'billhistory', 'master'] as $table) $pdo->exec('DELETE FROM ' . $table);
    $pdo->exec("INSERT INTO master (AcctNo,AcctCode,MeterSerial,Name,Status) VALUES ('FIXTURE-A','12-3456-7890','00123A','Synthetic account','A'), ('FIXTURE-B','98-7654-3210','OTHER','Synthetic account','A')");
    $pdo->exec("INSERT INTO billhistory VALUES
        ('one','FIXTURE-A','202609',1250.00),
        ('duplicate','FIXTURE-A','202609',1250.00),
        ('revision','FIXTURE-A','202609',1450.00),
        ('older','FIXTURE-A','202607',72.10),
        ('oldest','FIXTURE-A','202604',0.00),
        ('excluded','FIXTURE-A','202603',1.00),
        ('badmonth','FIXTURE-A','202613',77.00),
        ('shortmonth','FIXTURE-A','20261',77.00),
        ('negative','FIXTURE-A','202610',-1.00),
        ('nulltotal','FIXTURE-A','202611',NULL),
        ('other','FIXTURE-B','202609',9999.00)");
}
function linkStart(int $user = 1, array $override = []): array
{
    return linkPost($override + ['action' => 'add', 'account_code' => '12-3456-7890', 'meter_number' => '00123A', 'label' => 'My Home'], $user);
}
function linkFinish(string $amount = '1250.00', string $month = '202609', int $user = 1, array $override = []): array
{
    global $service;
    $pending = $service->pending($user);
    return linkPost($override + ['action' => 'verify_link', 'challenge_id' => $pending['id'] ?? '', 'bill_month' => $month, 'bill_amount' => $amount], $user);
}

try {
    $pdo->exec('CREATE TABLE users (id BIGINT UNSIGNED PRIMARY KEY, email VARCHAR(254), name VARCHAR(255)) ENGINE=InnoDB');
    $created[] = 'users';
    $pdo->exec("INSERT INTO users VALUES (1,'portal-one@example.test','Portal One'),(2,'portal-two@example.test','Portal Two'),(3,'portal-three@example.test','Portal Three')");
    $pdo->exec("CREATE TABLE master (AcctNo VARCHAR(15) PRIMARY KEY, AcctCode VARCHAR(12), MeterSerial VARCHAR(20), Name VARCHAR(50), Status CHAR(1), Address VARCHAR(255) DEFAULT 'Synthetic location') ENGINE=InnoDB");
    $created[] = 'master';
    // Reduced fixtures reproduce the exact types used by the verification queries.
    $pdo->exec('CREATE TABLE billhistory (TransID VARCHAR(255) PRIMARY KEY, AcctNo VARCHAR(15), BillMonth VARCHAR(6), TotalBill DECIMAL(19,2), KEY (AcctNo,BillMonth)) ENGINE=InnoDB');
    $created[] = 'billhistory';
    $pdo->exec("CREATE TABLE paleco_accounts (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED, AcctNo VARCHAR(15), account_nickname VARCHAR(100), is_primary TINYINT DEFAULT 0, status ENUM('active','archived'), verified_at DATETIME, UNIQUE KEY uq_user_acctno(user_id,AcctNo)) ENGINE=InnoDB");
    $created[] = 'paleco_accounts';
    $migration = file_get_contents(__DIR__ . '/../migrations/20261016_account_link_verification.sql');
    $pdo->exec(str_replace('mco_portal.', 'mportal_account_linking_test.', $migration));
    $created[] = 'account_link_challenges';
    $created[] = 'account_link_limits';
    $notificationMigration = file_get_contents(__DIR__.'/../migrations/20261016_account_notifications.sql');
    $pdo->exec(str_replace('mco_portal.', 'mportal_account_linking_test.', $notificationMigration));
    $created[] = 'account_notifications';

    linkReset();
    $disabled = new PalecoAccountLinker($pdo, ['enabled' => false]);
    linkCheck(!$disabled->handle(1, ['action' => 'add', 'csrf_token' => $csrf], $csrf)['ok'], 'Disabled feature permitted linking.');
    linkCheck(!$disabled->handle(1, ['action' => 'verify_link', 'csrf_token' => $csrf], $csrf)['ok'], 'Disabled feature permitted verification.');
    linkCheck(!linkPost(['action' => 'add'], 0)['ok'], 'Unauthenticated request accepted.');
    linkCheck(!linkPost(['action' => 'add'], 999)['ok'], 'Missing user accepted.');
    linkCheck(!linkPost(['action' => 'add', 'csrf_token' => 'bad'])['ok'], 'Invalid CSRF accepted.');
    foreach ([['account_code' => 'bad'], ['meter_number' => 'WRONG'], ['meter_number' => 'OTHER'], ['account_code' => '98-7654-3210']] as $input) {
        linkCheck(!linkStart(1, $input)['ok'], 'Incorrect identity accepted.');
        linkCheck($service->pending(1) === null, 'Failed identity created a challenge.');
    }
    linkReset();
    linkCheck(linkStart()['ok'], 'Correct identity rejected.');
    linkCheck((int) $pdo->query('SELECT COUNT(*) FROM paleco_accounts')->fetchColumn() === 0, 'Step 1 created a permanent link.');
    $pending = $service->pending(1);
    linkCheck($pending['months'] === ['202609','202607','202604'], 'Latest distinct valid months incorrect.');
    linkCheck(array_keys($pending) === ['id','months','expires_at','summary'], 'Pending response contains private fields.');
    linkCheck($pending['summary'] === ['account_number'=>'12-3456-7890','description'=>'My Home','consumer_name'=>'Synthetic account','address'=>'Synthetic location'], 'Verified account summary mismatch.');
    $wire = json_encode($pending);
    foreach (['1250.00', '1450.00', 'FIXTURE-A', '00123A', 'TotalBill', 'TransID', 'revision'] as $secret) linkCheck(!str_contains($wire, $secret), 'Sensitive data leaked in pending response.');
    $accountLinkPending=$pending;
    $accountLinker=$service;
    $accountLinkFormError=null;
    $accountLinkPending['summary']['consumer_name']='<img src=x onerror=alert(1)>';
    ob_start();require __DIR__.'/../includes/account-linking-form.php';$view=ob_get_clean();
    linkCheck(str_contains($view,'id="account-linking-panel"'),'Panel is missing its navigation target.');
    linkCheck(str_contains($view,'12-3456-7890') && str_contains($view,'Synthetic location'),'Verified summary absent from Step 2.');
    linkCheck(str_contains($view,'&lt;img') && !str_contains($view,'<img src=x'),'Summary was not HTML escaped.');
    linkCheck(!str_contains($view,'FIXTURE-A') && !str_contains($view,'1250.00') && !str_contains($view,'1450.00'),'Internal identifier or expected bill amount leaked in view.');
    linkCheck(str_contains($view,'Account found. Please complete billing verification to link this account.') && !str_contains($view,'linked successfully'),'Step 1 displayed premature linking success.');
    linkStart(2,['account_code'=>'98-7654-3210','meter_number'=>'OTHER','label'=>'Office']);
    linkCheck($service->pending(2)['summary']['account_number']==='98-7654-3210' && $service->pending(1)['summary']['account_number']==='12-3456-7890','Summary substituted another authenticated user account.');

    // Every duplicate amount and all three periods are accepted independently.
    foreach ([['1250.00','202609'],['1450.00','202609'],['₱1,250.00','202609'],['72.10','202607'],['0','202604']] as [$amount,$month]) {
        linkReset(); linkStart();
        linkCheck(linkFinish($amount,$month)['ok'], 'Matching duplicate or selected month rejected.');
        linkCheck($service->linkedAccountForEnrollment()==='FIXTURE-A','Optional enrollment account was not bound to the committed link.');
        linkCheck($service->pending(1) === null, 'Successful challenge not consumed.');
        linkCheck($pdo->query('SELECT account_nickname FROM paleco_accounts')->fetchColumn() === 'My Home', 'Friendly label lost.');
    }
    linkReset();
    $pdo->exec("DELETE FROM billhistory WHERE TransID IN ('duplicate','revision')");
    linkStart(); linkCheck(linkFinish()['ok'], 'Single matching bill rejected.');
    linkReset();
    $pdo->exec("INSERT INTO billhistory VALUES ('max-decimal','FIXTURE-A','202609',99999999999999999.99)");
    linkStart();linkCheck(linkFinish('99,999,999,999,999,999.99')['ok'],'Maximum DECIMAL amount lost precision.');

    foreach (['1300.00','9999.00','-1','1e3','1250.001','1,25.00','100000000000000000', '', '0.001'] as $amount) {
        linkReset(); linkStart();
        $result=linkFinish($amount);
        linkCheck(!$result['ok'], 'Wrong or malformed amount accepted.');
        linkCheck(!str_contains(json_encode($result), '1250') && !str_contains(json_encode($result), '1450'), 'Expected amount leaked.');
    }
    foreach (['202603','202610','202613','20261',"202609\n"] as $month) {
        linkReset(); linkStart(); linkCheck(!linkFinish('1.00',$month)['ok'], 'Tampered month accepted.');
    }
    foreach ([1,2,0] as $count) {
        linkReset();
        $pdo->exec("DELETE FROM billhistory WHERE AcctNo='FIXTURE-A'");
        for($i=1;$i<=$count;$i++) $pdo->exec("INSERT INTO billhistory VALUES ('limited$i','FIXTURE-A','20260$i',10.00)");
        $r=linkStart();
        linkCheck($count ? $r['ok'] : !$r['ok'], 'Missing/limited history behavior incorrect.');
        linkCheck($count ? count($service->pending(1)['months'])===$count : $service->pending(1)===null, 'Incorrect available month count.');
    }
    linkReset();linkStart();
    $old=$service->pending(1)['id'];linkStart();
    linkCheck(!linkFinish('1250.00','202609',1,['challenge_id'=>$old])['ok'], 'Replaced challenge accepted.');
    linkCheck($service->pending(1)!==null, 'Old replay erased the new challenge.');
    linkCheck(!linkPost(['action'=>'verify_link','challenge_id'=>$service->pending(1)['id'],'bill_month'=>'202609','bill_amount'=>'1250.00'],2)['ok'], 'Cross-user challenge accepted.');
    $expired=$service->pending(1)['id'];
    $pdo->exec('UPDATE account_link_challenges SET expires_at=1');
    linkCheck(!linkFinish('1250.00','202609',1,['challenge_id'=>$expired])['ok'] && $service->pending(1)===null,'Expired challenge accepted.');
    linkCheck((int)$pdo->query('SELECT COUNT(*) FROM account_link_challenges')->fetchColumn()===0,'Expired challenge not removed.');
    linkReset();linkStart();
    linkPost(['action'=>'restart_link']);
    linkCheck($service->pending(1)===null,'Back did not invalidate the challenge.');
    linkReset();linkStart();
    $pdo->exec("UPDATE master SET MeterSerial='REPLACED' WHERE AcctNo='FIXTURE-A'");
    linkCheck(!linkFinish()['ok'],'Changed master mapping accepted.');
    linkReset();linkStart();
    linkCheck(!linkPost(['action'=>'verify_link','challenge_id'=>$service->pending(1)['id'],'csrf_token'=>'bad','bill_month'=>'202609','bill_amount'=>'1250.00'])['ok'],'Step 2 invalid CSRF accepted.');
    linkCheck(linkFinish('1250.00','202609',1,['AcctNo'=>'FIXTURE-B','account_code'=>'98-7654-3210','meter_number'=>'OTHER'])['ok'],'Client account substitution changed trusted challenge.');
    linkCheck($pdo->query('SELECT AcctNo FROM paleco_accounts')->fetchColumn()==='FIXTURE-A','Client account was linked instead of verified account.');

    linkReset();linkStart();
    $replay=$service->pending(1)['id'];
    linkCheck(linkFinish()['ok'],'Initial link failed.');
    linkCheck(!linkFinish('1250.00','202609',1,['challenge_id'=>$replay])['ok'],'Replayed challenge accepted.');
    linkStart();linkCheck(!linkFinish()['ok'],'Same-user duplicate link accepted.');
    linkStart(2);linkCheck(linkFinish('1450.00','202609',2)['ok'],'Different portal user could not independently link.');
    linkCheck((int)$pdo->query('SELECT COUNT(*) FROM paleco_accounts')->fetchColumn()===2,'Existing user link was removed.');
    $pdo->exec("UPDATE paleco_accounts SET status='archived', is_primary=0 WHERE user_id=1");
    linkStart();linkCheck(linkFinish()['ok'],'Archived link could not be restored after verification.');
    linkCheck((int)$pdo->query('SELECT COUNT(*) FROM paleco_accounts')->fetchColumn()===2,'Archived link duplicated.');
    linkCheck($pdo->query('SELECT account_nickname FROM paleco_accounts WHERE user_id=2')->fetchColumn()==='My Home','Other user label changed.');

    // Failures survive challenge replacement, new service instances and session resets.
    linkReset();linkStart();
    for($i=0;$i<5;$i++) linkCheck(!linkFinish('1300.00')['ok'],'Incorrect amount accepted during attempts.');
    linkCheck($service->pending(1)===null,'Five failures did not invalidate challenge.');
    $service=new PalecoAccountLinker(linkTestPdo(),linkTestConfig(), 'linkTestTransport');
    $_SESSION=[];
    linkStart();linkCheck(!linkFinish()['ok'],'Restart/session reset bypassed user cooldown.');
    linkStart(2);linkCheck(!linkFinish('1250.00','202609',2)['ok'],'Different user bypassed account cooldown.');
    $pdo->exec('UPDATE account_link_limits SET blocked_until=0,window_started=1');
    linkStart();linkCheck(linkFinish()['ok'],'Cooldown expiration did not restore verification.');
    linkReset();
    for($i=0;$i<20;$i++) linkStart(1,['meter_number'=>'WRONG']);
    linkCheck(!linkStart()['ok'],'Step 1 user throttle bypassed.');
    linkCheck(!linkStart(2)['ok'],'Step 1 account throttle bypassed.');

    // Unlink archives this association only; archived links relink through verification.
    linkReset();linkStart();linkFinish();
    $pdo->exec("INSERT INTO paleco_accounts (user_id,AcctNo,account_nickname,is_primary,status) VALUES (1,'FIXTURE-B','Other',0,'active'),(2,'FIXTURE-A','Shared',1,'active')");
    $billHash=hash('sha256',json_encode($pdo->query('SELECT * FROM billhistory ORDER BY TransID')->fetchAll()));
    foreach ([[0,'FIXTURE-A',$csrf],[3,'FIXTURE-A',$csrf],[1,'FIXTURE-A','bad']] as [$user,$account,$token]) {
        $rejected=false;
        try { fixtureUnlink($pdo,$user,$account,$token,$csrf); } catch(DomainException $e) { $rejected=true; }
        linkCheck($rejected,'Unauthenticated, foreign or CSRF-invalid unlink accepted.');
    }
    linkStart();
    linkCheck(fixtureUnlink($pdo,1,'FIXTURE-A',$csrf,$csrf),'Unlink failed.');
    linkCheck($service->pending(1)===null,'Pre-unlink verification challenge survived.');
    linkCheck($pdo->query("SELECT status FROM paleco_accounts WHERE user_id=1 AND AcctNo='FIXTURE-A'")->fetchColumn()==='archived','Unlinked association remained active.');
    linkCheck($pdo->query("SELECT status FROM paleco_accounts WHERE user_id=2 AND AcctNo='FIXTURE-A'")->fetchColumn()==='active','Other portal user association changed.');
    linkCheck((int)$pdo->query("SELECT is_primary FROM paleco_accounts WHERE user_id=1 AND AcctNo='FIXTURE-B'")->fetchColumn()===1,'Remaining account not promoted.');
    linkCheck(!fixtureUnlink($pdo,1,'FIXTURE-A',$csrf,$csrf),'Repeated unlink was not idempotent.');
    foreach ([fn()=>getUnpaidBills($pdo,1,'FIXTURE-A'),fn()=>soaAuthorizedSelectedAccount($pdo,1,'FIXTURE-A'),fn()=>soaLatestBillMonth($pdo,1,'FIXTURE-A')] as $read) {
        $rejected=false;try{$read();}catch(DomainException $e){$rejected=true;}
        linkCheck($rejected,'Unlinked account retained billing/SOA access.');
    }
    linkCheck(fixtureUnlink($pdo,1,'FIXTURE-B',$csrf,$csrf),'Final account unlink failed.');
    linkCheck((int)$pdo->query("SELECT COUNT(*) FROM paleco_accounts WHERE user_id=1 AND status='active'")->fetchColumn()===0,'Final link remained active.');
    linkCheck((int)$pdo->query('SELECT COUNT(*) FROM users WHERE id=1')->fetchColumn()===1,'Portal profile removed.');
    linkStart();linkCheck(linkFinish()['ok'],'Unlinked account could not relink with verification.');
    linkCheck(hash('sha256',json_encode($pdo->query('SELECT * FROM billhistory ORDER BY TransID')->fetchAll()))===$billHash,'Official billing fixture changed during unlink.');
    // A database failure must roll back the archive and primary-account changes.
    $pdo->exec('RENAME TABLE account_link_challenges TO temporarily_unavailable_challenges');
    try {
        $failed=false;
        try{fixtureUnlink($pdo,1,'FIXTURE-A',$csrf,$csrf);}catch(PDOException $e){$failed=true;}
        linkCheck($failed,'Database failure was not surfaced safely.');
        linkCheck($pdo->query("SELECT status FROM paleco_accounts WHERE user_id=1 AND AcctNo='FIXTURE-A'")->fetchColumn()==='active','Failed unlink did not roll back.');
    } finally {$pdo->exec('RENAME TABLE temporarily_unavailable_challenges TO account_link_challenges');}
    linkCheck(maskPalecoAccountNumber('12-3456-7890')==='********7890','Confirmation account number not masked.');

    // Two independent PHP processes submit the same live challenge together.
    linkReset();linkStart();
    $token=$service->pending(1)['id'];
    $directory=__DIR__.'/link-concurrency-'.bin2hex(random_bytes(6));
    mkdir($directory,0700);
    $workers=[];
    try {
        for($i=0;$i<2;$i++) {
            $process=proc_open([PHP_BINARY,__FILE__,'--worker',$token,$directory,(string)$i],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
            if(!is_resource($process))throw new RuntimeException('Worker failed to start.');
            fclose($pipes[0]);$workers[]=[$process,$pipes];
        }
        $deadline=microtime(true)+10;
        while(count(glob($directory.'/ready-*'))!==2){if(microtime(true)>$deadline)throw new RuntimeException('Worker startup timeout.');usleep(1000);}
        file_put_contents($directory.'/go','1');
        $wins=0;
        foreach($workers as [$process,$pipes]) {
            $output=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);
            fclose($pipes[1]);fclose($pipes[2]);
            linkCheck(proc_close($process)===0,'Concurrent worker failed.');
            $r=json_decode($output,true,512,JSON_THROW_ON_ERROR);$wins+=(int)$r['ok'];
            linkCheck(!str_contains($errors,'1250')&&!str_contains($errors,'00123A'),'Sensitive input leaked to audit logs.');
        }
        linkCheck($wins===1,'Concurrent challenge accepted more or fewer than once.');
        linkCheck((int)$pdo->query('SELECT COUNT(*) FROM paleco_accounts')->fetchColumn()===1,'Concurrent requests created duplicates.');
    } finally {
        foreach(glob($directory.'/*') as $file)unlink($file);
        rmdir($directory);
    }
    linkCheck((int)$pdo->query('SELECT COUNT(*) FROM account_notifications')->fetchColumn()===1,'Concurrent linking queued duplicate notifications.');
    linkReset();$notificationMessages=[];$notificationResult=true;
    linkStart();linkCheck(count($notificationMessages)===0,'Step 1 sent a notification.');
    linkPost(['action'=>'restart_link']);linkStart(1,['meter_number'=>'WRONG']);
    linkStart();linkFinish('1300.00');
    linkCheck(count($notificationMessages)===0 && (int)$pdo->query('SELECT COUNT(*) FROM account_notifications')->fetchColumn()===0,'Cancelled or failed verification queued a notification.');
    linkFinish();
    linkCheck(count($notificationMessages)===1,'Successful link did not send once.');
    $mail=$notificationMessages[0];
    linkCheck($mail[0]==='portal-one@example.test' && $mail[1]==='Portal One','Notification used a non-portal recipient.');
    linkCheck($mail[2]==='PALECO Account Successfully Linked' && str_contains($mail[3],'12-3456-7890') && str_contains($mail[4],'My Home'),'Link notification UI identifier/name mismatch.');
    linkCheck(str_contains($mail[4],'Asia/Manila') && str_contains($mail[4],'please reset your PALECO MCO Portal password immediately'),'Missing event timezone or security advisory.');
    linkCheck(!str_contains(implode(' ',$mail),'FIXTURE-A') && !str_contains(implode(' ',$mail),'00123A'),'Email exposed internal identifier or meter.');
    $event=$pdo->query('SELECT event_id FROM account_notifications')->fetchColumn();
    dispatchAccountNotification($pdo,$event,'linkTestTransport');
    linkStart();linkFinish();
    linkCheck(count($notificationMessages)===1,'Repeated delivery/link request sent duplicate notification.');
    fixtureUnlink($pdo,1,'FIXTURE-A',$csrf,$csrf);
    linkCheck(count($notificationMessages)===2 && $notificationMessages[1][2]==='PALECO Account Unlinked','Successful unlink notification missing.');
    fixtureUnlink($pdo,1,'FIXTURE-A',$csrf,$csrf);
    linkCheck(count($notificationMessages)===2,'Repeated unlink sent duplicate notification.');
    $notificationResult=false;linkStart();
    linkCheck(linkFinish()['ok'],'Mail rejection misrepresented committed link.');
    linkCheck($pdo->query("SELECT status FROM paleco_accounts WHERE user_id=1")->fetchColumn()==='active','Mail rejection reversed link.');
    linkCheck((int)$pdo->query("SELECT COUNT(*) FROM account_notifications WHERE delivery_state='failed'")->fetchColumn()===1,'Mail rejection not recorded.');
    $notificationResult='throw';
    linkCheck(fixtureUnlink($pdo,1,'FIXTURE-A',$csrf,$csrf),'Transport exception misrepresented committed unlink.');
    linkCheck($pdo->query("SELECT status FROM paleco_accounts WHERE user_id=1")->fetchColumn()==='archived','Transport exception reversed unlink.');
    $count=count($notificationMessages);
    foreach($pdo->query("SELECT event_id FROM account_notifications WHERE delivery_state='failed'")->fetchAll(PDO::FETCH_COLUMN) as $failed) dispatchAccountNotification($pdo,$failed,'linkTestTransport');
    linkCheck(count($notificationMessages)===$count,'Failed/ambiguous transport was retried automatically.');
    $pdo->beginTransaction();$rolled=queueAccountNotification($pdo,1,'FIXTURE-A','linked');$pdo->rollBack();
    dispatchAccountNotification($pdo,$rolled,'linkTestTransport');
    linkCheck(count($notificationMessages)===$count,'Rolled-back event sent an email.');
    $notificationResult=true;
    linkStart(2);linkFinish('1250.00','202609',2);
    linkCheck(end($notificationMessages)[0]==='portal-two@example.test','Another user linking shared account used the wrong recipient.');
    $sample=['event_type'=>'unlinked','account_code'=>'02-0504-9986','display_name'=>'<script>alert(1)</script>','recipient_name'=>'<b>Consumer</b>','occurred_at'=>0];
    $template=accountNotificationMessage($sample);
    linkCheck(str_contains($template['html'],'&lt;script&gt;') && !str_contains($template['html'],'<script>'),'Notification HTML did not escape account text.');
    linkCheck(str_contains($template['plain'],'January 1, 1970, 8:00 AM'),'Event date was not converted to Asia/Manila.');
    linkReset();$pdo->exec("UPDATE users SET email='invalid-address' WHERE id=1");
    linkStart();linkCheck(linkFinish()['ok'],'Invalid notification address prevented successful linking.');
    linkCheck($pdo->query('SELECT delivery_state FROM account_notifications')->fetchColumn()==='failed','Invalid registered email failure was not recorded.');
    $pdo->exec("UPDATE users SET email='portal-one@example.test' WHERE id=1");
    linkCheck(hash_file('sha256',__DIR__.'/../config/mail.php')===$mailHash,'Mail configuration changed.');
    $audit = preg_replace('/event=[a-f0-9]{32}/','event=[opaque]',file_get_contents($auditFile));
    linkCheck(!str_contains($audit,'private-recipient-and-secret') && !str_contains($audit,'portal-one@example.test'),'Notification logs exposed transport details.');
    linkCheck(!str_contains($audit, 'service_error'), 'Unexpected database or service errors were hidden by negative assertions.');
    foreach (['1250','1450','00123A','FIXTURE-A','1234567890','99999999999999999'] as $secret) linkCheck(!str_contains($audit,$secret),'Sensitive input leaked to audit records.');
    linkCheck(str_contains($audit,'linked') && str_contains($audit,'billing_rejected'),'Success/failure audit records are missing.');
    echo "MySQL account linking tests passed ($checks assertions, including concurrent PHP workers).\n";
} finally {
    if($pdo->inTransaction())$pdo->rollBack();
    foreach(array_reverse($created) as $table)$pdo->exec('DROP TABLE IF EXISTS '.$table);
    ini_set('error_log', $previousErrorLog);
    if (is_file($auditFile)) unlink($auditFile);
}
