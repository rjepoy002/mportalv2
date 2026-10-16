<?php

/** Shared by account notifications only; sender is not an inquiry channel. */
function accountEmailFooter(): array
{
    $heading = 'Automated Email Notification';
    $text = 'This is an automated notification from PALECO MCO Portal. Please do not reply to this email, as this mailbox is not monitored.';
    return [
        'html' => '<div style="border-top:1px solid #e2e8f0;margin-top:24px;padding-top:16px;color:#475569;font-size:13px"><h3 style="font-size:14px">' . $heading . '</h3><p>' . $text . '</p></div>',
        'plain' => $heading . "\n" . $text,
    ];
}
