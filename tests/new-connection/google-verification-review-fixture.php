<?php
// Temporary loopback router; no operational configuration, DB connection, mail or Google SDK.
if(PHP_SAPI!=='cli-server'||!is_file(__DIR__.'/private/plan.json')){http_response_code(404);exit;}
ini_set('display_errors','0');ini_set('log_errors','0');ini_set('error_log',__DIR__.'/private/errors.log');
header('Cache-Control: private, no-store');header('Content-Type: text/plain; charset=utf-8');
$plan=json_decode(file_get_contents(__DIR__.'/private/plan.json'),true,32,JSON_THROW_ON_ERROR);
if(($_SERVER['HTTP_HOST']??'')!==$plan['host']||($_SERVER['REMOTE_ADDR']??'')!=='127.0.0.1'){http_response_code(403);exit;}
session_save_path(__DIR__.'/private/sessions');
$spy=['provider_exchanges'=>0,'selects'=>0,'would_link'=>false,'would_fill_portal_verification'=>false,'would_insert_verified_user'=>false,'write_failed'=>false];
register_shutdown_function(static function()use(&$spy,$plan){
    $spy['session_authenticated']=isset($_SESSION['user_id']);$spy['session_has_oauth_pending']=isset($_SESSION['oauth_pending']);
    $spy['fixed_input_unchanged']=($GLOBALS['plan']??null)===$plan;
    file_put_contents(__DIR__.'/private/summary.json',json_encode($spy,JSON_THROW_ON_ERROR));
});
function fixtureRejected(): never {http_response_code(503);exit('Synthetic review unavailable.');}
set_exception_handler(static fn(Throwable $e)=>fixtureRejected());
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if($path==='/login'){require __DIR__.'/app/controllers/google-login.php';exit;}
if($path==='/callback'){require __DIR__.'/app/controllers/google-callback.php';exit;}
http_response_code(404);
