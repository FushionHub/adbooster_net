<?php
$dir = new RecursiveDirectoryIterator('c:/laragon/www/seopp');
$ite = new RecursiveIteratorIterator($dir);
$matches = [];
foreach ($ite as $file) {
    if (!$file->isFile()) continue;
    $path = $file->getPathname();
    if (strpos($path, 'vendor') !== false || strpos($path, '.git') !== false || strpos($path, 'scratch') !== false) continue;
    if (!preg_match('/\.(php|html)$/i', $path)) continue;

    $content = file_get_contents($path);
    if (preg_match_all('/href=[\'"][^\'"]*?\.php[^\'"]*?[\'"]/i', $content, $m)) {
        foreach ($m[0] as $match) {
            $matches[] = str_replace('c:/laragon/www/seopp\\', '', $path) . ' => ' . $match;
        }
    }
}
echo implode("\n", $matches) . "\nTotal found: " . count($matches) . "\n";
