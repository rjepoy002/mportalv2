<?php
// Structural regression checks; no DB, session or email side effects.
$root = dirname(__DIR__);
$map = json_decode(file_get_contents($root . '/reports/reorganization/relocation-map.json'), true, 512, JSON_THROW_ON_ERROR);
$checks = 0;
foreach ($map as $old => $new) {
    if (!is_file($root . '/' . $new)) throw new RuntimeException('Missing relocated file: ' . $new);
    $checks++;
    if (str_ends_with($old, '.php')) {
        $shim = file_get_contents($root . '/' . $old);
        if (!str_contains($shim, 'require') || strlen($shim) > 300) throw new RuntimeException('Invalid compatibility entry: ' . $old);
        $checks++;
    }
}
foreach (['app', 'config', 'includes', 'database', 'storage', 'tests', 'reports', 'vendor', 'jobs', 'migrations'] as $directory) {
    if (!str_contains(file_get_contents($root . '/' . $directory . '/.htaccess'), 'Require all denied')) {
        throw new RuntimeException('Missing Apache protection: ' . $directory);
    }
    $checks++;
}
foreach (['app', 'api', 'config', 'includes', 'jobs', 'database'] as $directory) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS));
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') continue;
        $source = file_get_contents($file->getPathname());
        preg_match_all('/__DIR__\s*\.\s*([\'"])([^\'"\r\n]+)\1/', $source, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (!file_exists($file->getPath() . $match[2])) throw new RuntimeException('Broken filesystem reference in ' . $file->getPathname());
            $checks++;
        }
    }
}
echo "Structure and dependency checks passed ($checks assertions).\n";
