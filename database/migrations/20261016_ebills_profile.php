<?php
// Explicit, reviewed additive migration. Never run automatically from the portal.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../../config/database.php';
$backup=$argv[1]??'';
if (!is_file($backup)||filesize($backup)<100) throw new RuntimeException('A completed portal database backup is required.');
$backupText=file_get_contents($backup);
if(!str_contains($backupText,'Dump completed')||!preg_match('/Database:\s+mco_portal\b/',$backupText)) throw new RuntimeException('A completed mco_portal backup is required.');
$fields=['first_name'=>25,'last_name'=>25,'middle_name'=>25,'extension_name'=>25,'contact_number'=>12];
$existing=$pdo->query('SHOW COLUMNS FROM mco_portal.users')->fetchAll(PDO::FETCH_UNIQUE);
$original=array_keys($existing);
$quoted=implode(',',array_map(fn($c)=>'`'.$c.'`',$original));
$before=hash('sha256',json_encode($pdo->query('SELECT '.$quoted.' FROM mco_portal.users ORDER BY id')->fetchAll()));
$ebillsSchema=$pdo->query('SHOW CREATE TABLE palecxzp_pal_db.ebills')->fetch(PDO::FETCH_NUM)[1];
// Validate every existing field before executing any ALTER.
foreach($fields as $field=>$size){
    if(isset($existing[$field])){
        if(strtolower($existing[$field]['Type'])!=="varchar($size)"||$existing[$field]['Null']!=='YES') throw new RuntimeException('Existing column differs from reviewed schema: '.$field);
    }
}
foreach(array_diff_key($fields,$existing) as $field=>$size){
    $pdo->exec('ALTER TABLE mco_portal.users ADD COLUMN `'.$field.'` VARCHAR('.$size.') NULL DEFAULT NULL');
}
$after=hash('sha256',json_encode($pdo->query('SELECT '.$quoted.' FROM mco_portal.users ORDER BY id')->fetchAll()));
if($before!==$after||$ebillsSchema!==$pdo->query('SHOW CREATE TABLE palecxzp_pal_db.ebills')->fetch(PDO::FETCH_NUM)[1]) throw new RuntimeException('Migration verification failed.');
echo "Profile migration verified: approved nullable fields present; original user data and eBills schema unchanged.\n";
