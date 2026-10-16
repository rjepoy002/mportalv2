<?php
require_once __DIR__.'/../config/mailer.php';
require_once __DIR__.'/../includes/account-notifications.php';
final class AccountEventFakeMailer extends \PHPMailer\PHPMailer\PHPMailer
{
    public string $outcome='accepted';
    public function send(): bool
    {
        if($this->outcome==='exception') {
            $this->ErrorInfo='private-transport-detail';
            throw new RuntimeException('private-recipient@example.test secret-token');
        }
        return $this->outcome==='accepted';
    }
}
$path=__DIR__.'/notification-mailer-'.bin2hex(random_bytes(6)).'.log';
$previous=ini_set('error_log',$path);
try {
    foreach(['linked','unlinked'] as $event) {
        $body=accountNotificationMessage(['event_type'=>$event,'recipient_name'=>'Portal Consumer','display_name'=>'Home','account_code'=>'02-0504-9986','occurred_at'=>0]);
        foreach(['accepted','rejected','exception'] as $outcome) {
            $mailer=new AccountEventFakeMailer(true);$mailer->outcome=$outcome;
            $accepted=sendEmail('registered@example.test','Portal Consumer',$body['subject'],$body['html'],$body['plain'],'synthetic-event',fn()=>$mailer);
            if($accepted!==($outcome==='accepted'))throw new RuntimeException('Transport acceptance was misreported.');
            if($mailer->getToAddresses()!==[['registered@example.test','Portal Consumer']])throw new RuntimeException('Mailer recipient mismatch.');
            if($mailer->Body!==$body['html']||$mailer->AltBody!==$body['plain'])throw new RuntimeException('Multipart notification content mismatch.');
        }
    }
    $log=file_get_contents($path);
    foreach(['private-transport-detail','private-recipient@example.test','secret-token','registered@example.test'] as $private)if(str_contains($log,$private))throw new RuntimeException('Mail failure logging exposed private details.');
    echo "Account notification mailer tests passed (real sendEmail infrastructure; fake transport, no network).\n";
}finally{ini_set('error_log',$previous);if(is_file($path))unlink($path);}
