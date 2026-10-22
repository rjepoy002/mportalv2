<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
define('NC_GOVERNED_HTTP',true);require __DIR__.'/stage1-http-test.php';
