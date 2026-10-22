<?php
header('Cache-Control: private, no-store');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Content-Type: text/html; charset=utf-8');
require_once __DIR__.'/../helpers/new-connection-stage1-display.php';
if(!newConnectionStage1DisplayAvailable($_SERVER)){http_response_code(404);exit;}
if(!in_array($_SERVER['REQUEST_METHOD']??'', ['GET','HEAD'],true)){header('Allow: GET, HEAD');http_response_code(405);exit;}
require_once __DIR__.'/../helpers/session-security.php';require_once __DIR__.'/../middleware/new-connection-auth.php';
try {
    portalSessionStart();header('Cache-Control: private, no-store');
    // The production default stays the existing identity lookup. Isolated copies inject a repository.
    newConnectionApplicant($_SESSION,static function():PDO {throw new RuntimeException('Display identity connection unavailable');},$stage1DisplayIdentityRepository??null);
} catch(Throwable $e) {
    if($e->getCode()===401){header('Location: index.php');http_response_code(302);}
    elseif($e->getCode()===403){http_response_code(403);echo 'Access unavailable.';}
    else {http_response_code(503);echo 'Display temporarily unavailable.';}
    return;
}
echo newConnectionStage1ReadOnly();
