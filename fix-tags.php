<?php
$wrongOpen = '<' . 'motion';
$wrongClose = '</' . 'motion>';
$rightOpen = '<' . 'div';
$rightClose = '</' . 'motion>';
$rightClose = '</' . 'div>';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/resources/js'));
foreach ($iterator as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'vue') {
        continue;
    }
    $path = $file->getPathname();
    if (! str_contains($path, 'Admin')) {
        continue;
    }
    $c = file_get_contents($path);
    $n = str_replace([$wrongOpen, $wrongClose], [$rightOpen, $rightClose], $c);
    if ($n !== $c) {
        file_put_contents($path, $n);
        echo $path, PHP_EOL;
    }
}
