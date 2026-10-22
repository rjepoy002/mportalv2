<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/apache-navigation-support.php';
// Reuse current-policy SQL coordinator; all modifications occur in memory or its private tree.
$entry=file_get_contents(__DIR__.'/localhost-auth-navigation-test.php');
if(substr_count($entry,"eval('?>'.\$source);")!==1)throw new RuntimeException('Coordinator changed');
$entry=str_replace("eval('?>'.\$source);","apacheNavigationRun(\$source);",$entry);
eval('?>'.$entry);
