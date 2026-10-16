<?php
require_once __DIR__.'/../includes/account-notifications.php';
$originalUrl=getenv('APP_URL');$originalEnvironment=getenv('APP_ENV');
$checks=0;
function templateCheck(bool $ok,string $message):void{global $checks;$checks++;if(!$ok)throw new RuntimeException($message);}
try {
    putenv('APP_ENV=test');putenv('APP_URL=https://portal.example.test/mportalv2');
    $notice='If you did not authorize this action, please reset your PALECO MCO Portal password immediately using the Forgot Password feature on the login page. We also recommend reviewing your linked PALECO accounts for any unauthorized changes.';
    $footer='This is an automated notification from PALECO MCO Portal. Please do not reply to this email, as this mailbox is not monitored.';
    foreach(['linked'=>['PALECO Account Successfully Linked','Account Linked Successfully','Account Linked'], 'unlinked'=>['PALECO Account Unlinked','Account Unlinked Successfully','Account Unlinked']] as $type=>[$subject,$heading,$activity]) {
        $message=accountNotificationMessage(['event_type'=>$type,'account_code'=>'02-0504-9986','display_name'=>'<b>Home</b>','occurred_at'=>0,'AcctNo'=>'PRIVATE-INTERNAL-ID','token'=>'PRIVATE-TOKEN']);
        templateCheck($message['subject']===$subject,'Subject mismatch.');
        templateCheck(str_contains($message['html'],'<h2>'.$heading.'</h2>') && str_contains($message['plain'],$heading),'Heading mismatch.');
        templateCheck(str_contains($message['plain'],'Activity: '.$activity),'Activity mismatch.');
        templateCheck(str_contains($message['html'],'<p>Hello,</p>') && str_contains($message['plain'],"Hello,\n"),'Greeting mismatch.');
        templateCheck(str_contains($message['html'],'02-0504-9986') && str_contains($message['plain'],'PALECO Account: 02-0504-9986'),'UI account identifier mismatch.');
        templateCheck(str_contains($message['plain'],'January 1, 1970, 8:00 AM (Philippine Time, Asia/Manila)'),'Philippine timestamp mismatch.');
        templateCheck(str_contains(html_entity_decode(strip_tags($message['html']),ENT_QUOTES,'UTF-8'),$notice) && str_contains($message['plain'],$notice),'Security notice mismatch.');
        templateCheck(str_contains($message['html'],'href="https://portal.example.test/mportalv2/forgot-password.php"') && str_contains($message['plain'],'Forgot Password: https://portal.example.test/mportalv2/forgot-password.php'),'Recovery link mismatch.');
        templateCheck(str_contains($message['html'],$footer) && str_contains($message['plain'],$footer),'Shared automated footer mismatch.');
        templateCheck(str_contains($message['html'],'Automated Email Notification') && str_contains($message['plain'],'Automated Email Notification'),'Footer heading missing.');
        templateCheck(!str_contains($message['html'],'MPortalV2') && !str_contains($message['plain'],'MPortalV2') && !str_contains($message['subject'],'MPortalV2'),'Old visible email branding remains.');
        $expectedBody = $type === 'linked' ? 'A PALECO account has been successfully linked to your PALECO MCO Portal profile.' : 'A PALECO account has been removed from your PALECO MCO Portal profile.';
        templateCheck(str_contains($message['html'],$expectedBody) && str_contains($message['plain'],$expectedBody),'Profile branding mismatch.');
        if ($type === 'linked') {
            $expectedAccess = 'You can now access the linked account’s available services through PALECO MCO Portal.';
            templateCheck(str_contains($message['html'],$expectedAccess) && str_contains($message['plain'],$expectedAccess),'Linked service branding mismatch.');
        }
        templateCheck(str_contains($message['html'],'&lt;b&gt;Home&lt;/b&gt;') && !str_contains($message['html'],'<b>Home</b>'),'Dynamic HTML not escaped.');
        templateCheck(!str_contains(implode(' ',$message),'PRIVATE-') && !str_contains(implode(' ',$message),'?token='),'Secret or internal identifier leaked.');
    }
    foreach(['javascript:alert(1)','/relative','http://remote.example.test','https://name:password@portal.example.test','https://portal.example.test/?token=secret','https://portal.example.test/#fragment',"https://portal.example.test/\n"] as $url) {
        putenv('APP_URL='.$url);$rejected=false;
        try{accountNotificationMessage(['event_type'=>'linked','account_code'=>'test','display_name'=>'test','occurred_at'=>0]);}catch(InvalidArgumentException $e){$rejected=true;}
        templateCheck($rejected,'Invalid configured recovery URL accepted.');
    }
    templateCheck(is_file(__DIR__.'/../forgot-password.php'),'Linked recovery route does not exist.');
    echo "Account notification template tests passed ($checks assertions).\n";
} finally {
    putenv($originalUrl===false?'APP_URL':'APP_URL='.$originalUrl);
    putenv($originalEnvironment===false?'APP_ENV':'APP_ENV='.$originalEnvironment);
}
