<?php
// Recovery for pending events if a request ends after commit but before dispatch.
// Never automatically retry sending/failed events: SMTP acceptance may be ambiguous.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../config/database.php';
require __DIR__ . '/../app/services/account-notifications.php';
$events=$pdo->query("SELECT event_id FROM mco_portal.account_notifications WHERE delivery_state='pending' ORDER BY occurred_at LIMIT 100")->fetchAll(PDO::FETCH_COLUMN);
foreach($events as $event) dispatchAccountNotification($pdo,$event);
echo 'Processed pending notification events: '.count($events).PHP_EOL;
