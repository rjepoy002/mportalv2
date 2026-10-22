<?php
// CLI-only rehearsal: copied controllers, SELECT-only synthetic PDO, private temporary Apache.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$harness = file_get_contents(__DIR__.'/localhost-preview-test.php');
$hook = "eval('?>'.\$source);";
if (substr_count($harness, $hook) !== 1) throw new RuntimeException('Preview seam changed');
$adapt = <<<'PHP'
$old = <<<'OLD'
    $server = proc_open([PHP_BINARY, '-S', $address, '-t', $temporary],
        [0 => ['pipe', 'r'], 1 => ['file', $temporary . '/server.log', 'a'], 2 => ['file', $temporary . '/server.log', 'a']], $pipes);
OLD;
$new = <<<'NEW'
    $port = (int)substr(strrchr($address, ':'), 1);
    $path = str_replace('\\', '/', $temporary);
    $conf = <<<'CONF'
ServerRoot "C:/xampp/apache"
ServerName localhost
LoadModule authz_core_module modules/mod_authz_core.so
LoadModule authz_host_module modules/mod_authz_host.so
LoadModule dir_module modules/mod_dir.so
LoadModule mime_module modules/mod_mime.so
LoadFile "C:/xampp/php/php8ts.dll"
LoadFile "C:/xampp/php/libpq.dll"
LoadFile "C:/xampp/php/libsqlite3.dll"
LoadModule php_module "C:/xampp/php/php8apache2_4.dll"
PHPIniDir "C:/xampp/php"
TypesConfig "C:/xampp/apache/conf/mime.types"
<Directory />
Require all denied
</Directory>
CONF;
    $conf .= "\nListen 127.0.0.1:$port\nPidFile \"$path/httpd.pid\"\nErrorLog \"$path/server.log\"\nDocumentRoot \"$path\"\n<Directory \"$path\">\nRequire local\nAllowOverride None\nOptions -Indexes\n<FilesMatch \\\.php$>\nSetHandler application/x-httpd-php\n</FilesMatch>\n</Directory>\n";
    file_put_contents($temporary.'/httpd.conf', $conf);
    $server = proc_open(['C:/xampp/apache/bin/httpd.exe', '-X', '-f', $temporary.'/httpd.conf'],
        [0=>['pipe','r'],1=>['file','NUL','a'],2=>['file','NUL','a']],$pipes,'C:/xampp/apache');
NEW;
if (substr_count($source,$old)!==1) throw new RuntimeException('Server seam changed');
$source=str_replace($old,$new,$source);
$extra = <<<'TEST'
    foreach ([['portal_last_seen'=>time()-1801], ['portal_created_at'=>time()-43201]] as $expired) {
        $sid=ncSeed(1); session_id($sid); session_start();
        $_SESSION=array_merge($_SESSION,$expired); session_write_close();
        [$body,$headers]=ncRequest('new-connection-preview.php',$sid);
        ncCheck(str_contains($headers[0],'302')&&!str_contains($body,'Applicant Information'),'Expired session exposes preview');
    }
TEST;
$source=str_replace('    // Export synthetic rendering only', $extra."\n    // Export synthetic rendering only",$source);
$source=str_replace('Localhost preview security passed','Separate Apache preview acceptance passed',$source);
eval('?>'.$source);
PHP;
$harness=str_replace($hook,$adapt,$harness);
eval(substr($harness,5));
