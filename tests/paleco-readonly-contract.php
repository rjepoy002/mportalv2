<?php

/**
 * Static guard for application SQL. Database privileges must enforce the same
 * boundary in deployed environments.
 */
$root = dirname(__DIR__);
$writableTables = ['users', 'email_verifications', 'password_resets', 'paleco_accounts', 'account_link_challenges', 'account_link_limits', 'account_notifications', 'activity_logs'];
$writableTables = array_merge($writableTables, array_map(static fn($name) => 'new_connection_'.$name,
    ['schema_migrations','applications','application_revisions','stages','stage_events','audit_events','consents','roles','permissions','role_permissions','staff_assignments','staff_assignment_events','draft_requests','submission_requests',
     'notice_versions','catalog_versions','publication_bundles','bundle_catalogs','bundle_notices','catalog_retirements','publication_heads','publication_events','submission_publications']));
$files = [];
$directory = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
$filter = new RecursiveCallbackFilterIterator(
    $directory,
    static fn (SplFileInfo $entry) => !$entry->isDir()
        || !in_array($entry->getFilename(), ['vendor', 'node_modules', '.tmp', 'tests', '.git'], true)
);
$iterator = new RecursiveIteratorIterator($filter);
foreach ($iterator as $entry) {
    if (!$entry->isFile() || !in_array(strtolower($entry->getExtension()), ['php', 'sql'], true)) {
        continue;
    }
    $files[] = $entry->getPathname();
}
$violations = [];

foreach ($files as $file) {
    $source = file_get_contents($file);
    $sqlFragments = str_ends_with(strtolower($file), '.sql')
        ? [[$source, 1]]
        : array_filter(token_get_all($source), static fn ($token) => is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING);
    foreach ($sqlFragments as $token) {
        $sql = str_ends_with(strtolower($file), '.sql') ? $token[0] : $token[1];
        $line = str_ends_with(strtolower($file), '.sql') ? 1 : $token[2];
        // Trigger declarations describe events; UPDATE ON is not an UPDATE target.
        $sql = preg_replace('/\b(?:BEFORE|AFTER)\s+(?:INSERT|UPDATE|DELETE)\s+ON\b/i', 'TRIGGER_EVENT ON', $sql);
        if (preg_match('/^[\'"]?SHOW\s+CREATE\s+TABLE\b/i', trim($sql))) continue;
        preg_match_all(
            '/\b(?<operation>INSERT\s+INTO|REPLACE\s+INTO|(?<!KEY )UPDATE|DELETE\s+FROM|ALTER\s+TABLE|DROP\s+TABLE|TRUNCATE\s+TABLE|CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?)\s+(?:`?(?<schema>[a-z_][a-z_0-9]*)`?\s*\.\s*)?`?(?<table>[a-z_][a-z_0-9]*)`?/i',
            $sql,
            $matches,
            PREG_SET_ORDER
        );
        foreach ($matches as $match) {
            $schema = strtolower($match['schema'] ?? '');
            $table = strtolower($match['table']);
            // Task 07B: only the guarded disposable migration owns conversion lineage DDL.
            if ($schema === '' && $table === 'new_connection_draft_conversions'
                && basename($file) === '20261022_new_connection_v2_drafts.php'
                && str_contains($source, 'newConnectionMigrationSchema($pdo)')) continue;
            if ($schema==='palecxzp_pal_db' && $table==='ebills'
                && strtoupper($match['operation'])==='INSERT INTO'
                && basename($file)==='ebills-enrollment.php'
                && preg_match('/INSERT INTO palecxzp_pal_db\.ebills\s*\(([^)]+)\)/i',$sql,$columns)
                && preg_replace('/\s+/','',strtolower($columns[1]))==='acccode,datecreated,timestamp,firstname,lastname,middlename,extensionname,email,contactnumber') continue;
            if (($schema !== '' && $schema !== 'mco_portal') || !in_array($table, $writableTables, true)) {
                $violations[] = basename($file) . ':' . $line . ' targets ' . ($schema !== '' ? $schema . '.' : '') . $table;
            }
        }
    }
}

if ($violations) {
    fwrite(STDERR, "Non-portal table write detected:\n" . implode("\n", $violations) . "\n");
    exit(1);
}

echo "PALECO read-only contract check passed.\n";
