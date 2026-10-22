<?php
// Negative-test control: CLI only, existing private fixture session only; no HTTP endpoint.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$dir=realpath($argv[1]??'');$id=$argv[2]??'';
if(!$dir||dirname($dir)!==realpath(sys_get_temp_dir())||!preg_match('/^mco-v2-[a-f0-9]{16}$/D',basename($dir))||!preg_match('/^[A-Za-z0-9,-]{16,128}$/D',$id)||!is_file($dir.'/private/sessions/sess_'.$id))throw new LogicException('Private fixture session required');
session_name('NC_V2_SYNTHETIC');session_save_path($dir.'/private/sessions');session_id($id);session_start();$_SESSION['portal_last_seen']=time()-1801;session_write_close();
