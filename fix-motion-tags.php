<?php
$files = array_merge(
    glob(__DIR__.'/resources/js/Pages/Admin/**/*.vue') ?: [],
    glob(__DIR__.'/resources/js/Pages/Admin/*.vue') ?: [],
);

foreach (array_unique($files) as $path) {
    $c = file_get_contents($path);
    $n = str_replace('<motion', '<div', $c);
    $n = str_replace('</motion>', '</div>', $n);
    if ($n !== $c) {
        file_put_contents($path, $n);
        echo $path, PHP_EOL;
    }
}
