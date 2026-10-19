<?php

/** Persist only within the association transaction; never send before commit. */
function queueAccountNotification(PDO $pdo, int $userId, string $acctNo, string $event): string
{
    if (!$pdo->inTransaction() || !in_array($event, ['linked','unlinked'], true)) throw new LogicException('Invalid notification transaction.');
    $find=$pdo->prepare('SELECT u.email, u.name, pa.account_nickname, m.AcctCode, m.Name
        FROM mco_portal.paleco_accounts pa
        INNER JOIN mco_portal.users u ON u.id = pa.user_id
        LEFT JOIN palecxzp_pal_db.master m ON m.AcctNo = pa.AcctNo
        WHERE pa.user_id = ? AND pa.AcctNo = ?');
    $find->execute([$userId,$acctNo]);
    $row=$find->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new LogicException('Notification association unavailable.');
    $label=trim((string)($row['account_nickname']??''));
    $name=trim((string)($row['Name']??''));
    $display=$label!==''?$label:($name!==''?mb_strtoupper($name,'UTF-8'):'Consumer name unavailable');
    $email=(string)$row['email'];
    // An unusable registered address is a notification failure, never a link failure.
    if (strlen($email)>254 || !filter_var($email,FILTER_VALIDATE_EMAIL)) $email='';
    $id=bin2hex(random_bytes(16));
    $insert=$pdo->prepare('INSERT INTO mco_portal.account_notifications
        (event_id,user_id,event_type,recipient_email,recipient_name,account_code,display_name,occurred_at)
        VALUES (?,?,?,?,?,?,?,?)');
    $insert->execute([$id,$userId,$event,$email,(string)$row['name'],trim((string)($row['AcctCode']??'')),$display,time()]);
    return $id;
}

function accountNotificationMessage(array $event): array
{
    require_once __DIR__ . '/../../config/app.php';
    require_once __DIR__ . '/../helpers/account-email-footer.php';
    // app_url validates the configured base URL; never use Host or a reset token.
    $resetUrl = app_url('forgot-password.php');
    $linked = $event['event_type'] === 'linked';
    $subject = $linked ? 'PALECO Account Successfully Linked' : 'PALECO Account Unlinked';
    $heading = $linked ? 'Account Linked Successfully' : 'Account Unlinked Successfully';
    $activity = $linked ? 'Account Linked' : 'Account Unlinked';
    $intro = $linked
        ? 'A PALECO account has been successfully linked to your PALECO MCO Portal profile.'
        : 'A PALECO account has been removed from your PALECO MCO Portal profile.';
    $explanation = $linked
        ? 'You can now access the linked account’s available services through PALECO MCO Portal.'
        : 'This account will no longer be accessible through your PALECO MCO Portal profile unless you link it again.';
    $code = $event['account_code'] !== '' ? $event['account_code'] : 'Account code unavailable';
    $date = (new DateTimeImmutable('@' . $event['occurred_at']))
        ->setTimezone(new DateTimeZone('Asia/Manila'))->format('F j, Y, g:i A') . ' (Philippine Time, Asia/Manila)';
    $security = 'If you did not authorize this action, please reset your PALECO MCO Portal password immediately using the Forgot Password feature on the login page. We also recommend reviewing your linked PALECO accounts for any unauthorized changes.';
    $escape = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $securityHtml = str_replace('Forgot Password', '<a href="' . $escape($resetUrl) . '" style="color:#166534">Forgot Password</a>', $escape($security));
    $footer = accountEmailFooter();
    $html = '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
        . '<body style="margin:0;background:#f1f5f9;padding:16px;font-family:Arial,sans-serif;color:#0f172a"><div style="max-width:600px;margin:auto;background:#fff;border-radius:12px;overflow:hidden">'
        . '<div style="background:#166534;color:#fff;padding:20px;font-size:20px;font-weight:bold">PALECO MCO Portal</div><div style="padding:24px;line-height:1.6;overflow-wrap:anywhere">'
        . '<h2>' . $heading . '</h2><p>Hello,</p><p>' . $escape($intro) . '</p>'
        . '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:16px">'
        . '<p style="margin:0"><strong>PALECO Account:</strong> ' . $escape($code) . '<br>' . $escape($event['display_name'])
        . '<br><strong>Date and Time:</strong> ' . $escape($date) . '<br><strong>Activity:</strong> ' . $activity . '</p></div>'
        . '<p>' . $escape($explanation) . '</p><div style="border-left:4px solid #166534;padding-left:16px;margin-top:24px"><h3>Security Notice</h3><p>' . $securityHtml . '</p></div>'
        . $footer['html'] . '</div></div></body></html>';
    $plain = $heading . "\n\nHello,\n\n" . $intro . "\n\nPALECO Account: " . $code . "\n" . $event['display_name']
        . "\nDate and Time: " . $date . "\nActivity: " . $activity . "\n\n" . $explanation
        . "\n\nSecurity Notice\n" . $security . "\nForgot Password: " . $resetUrl . "\n\n" . $footer['plain'];
    return ['subject' => $subject, 'html' => $html, 'plain' => $plain];
}

/** At-most-once attempt: ambiguous SMTP outcomes are never automatically retried. */
function dispatchAccountNotification(PDO $pdo, string $eventId, ?callable $transport=null): void
{
    if ($pdo->inTransaction()) throw new LogicException('Notification delivery requires commit.');
    try {
        $claim=$pdo->prepare("UPDATE mco_portal.account_notifications SET delivery_state='sending',attempted_at=? WHERE event_id=? AND delivery_state='pending'");
        $claim->execute([time(),$eventId]);
        if ($claim->rowCount()!==1) return;
        $find=$pdo->prepare('SELECT * FROM mco_portal.account_notifications WHERE event_id=?');
        $find->execute([$eventId]);$event=$find->fetch(PDO::FETCH_ASSOC);
        $accepted=false;
        try {
            if (filter_var($event['recipient_email'],FILTER_VALIDATE_EMAIL)) {
                $message=accountNotificationMessage($event);
                if ($transport===null) {
                    require_once __DIR__ . '/mailer.php';
                    $transport='sendEmail';
                }
                $accepted=(bool)$transport($event['recipient_email'],$event['recipient_name'],$message['subject'],$message['html'],$message['plain'],$eventId);
            }
        } catch(Throwable $e) {
            // Never log exception text, addresses, account codes or message bodies.
            $accepted=false;
        }
        $state=$accepted?'accepted':'failed';
        $done=$pdo->prepare('UPDATE mco_portal.account_notifications SET delivery_state=? WHERE event_id=?');
        $done->execute([$state,$eventId]);
        error_log('Account notification: '.$state.' event='.$eventId);
    } catch(Throwable $e) {
        error_log('Account notification: delivery_state_error event='.$eventId);
    }
}
