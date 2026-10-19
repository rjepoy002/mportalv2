<?php
/** Read relocated implementation text for existing source-based regression checks. */
function portalPageSource(string $file): string
{
    $root = dirname(__DIR__);
    $controller = $file === 'soa.php' ? $root . '/api/soa.php' : $root . '/app/controllers/' . $file;
    $source = file_get_contents($controller);
    $view = $root . '/app/views/pages/' . $file;
    if (is_file($view)) {
        $source = preg_replace("~require __DIR__ \\. '/\\.\\./views/pages/[^']+';\\s*$~", '', $source);
        $source .= '?>' . file_get_contents($view);
    }
    return $source;
}
