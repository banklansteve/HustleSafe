<?php

/** Strip flat backgrounds from 512×512 icon PNGs (favicons). */

$root = dirname(__DIR__);
$targets = [
    'images/logo/v7b_icon_512_light.png',
    'images/logo/v7b_icon_512_dark.png',
];

foreach ($targets as $relative) {
    stripBackground($root.'/public/'.$relative, str_contains($relative, '_dark'));
    echo "Processed: {$relative}\n";
}

function stripBackground(string $path, bool $isDark): void
{
    $src = imagecreatefrompng($path);
    imagesavealpha($src, true);
    $w = imagesx($src);
    $h = imagesy($src);
    $threshold = $isDark ? 48 : 40;

    $visited = str_repeat("\0", $w * $h);
    $queue = [[0, 0], [$w - 1, 0], [0, $h - 1], [$w - 1, $h - 1]];

    while ($queue !== []) {
        [$x, $y] = array_pop($queue);
        if ($x < 0 || $y < 0 || $x >= $w || $y >= $h) {
            continue;
        }

        $idx = $y * $w + $x;
        if ($visited[$idx] !== "\0") {
            continue;
        }

        $rgba = imagecolorat($src, $x, $y);
        $a = ($rgba >> 24) & 0x7F;
        $r = ($rgba >> 16) & 0xFF;
        $g = ($rgba >> 8) & 0xFF;
        $b = $rgba & 0xFF;

        if ($a >= 120 || isBackgroundPixel($r, $g, $b, $isDark, $threshold)) {
            $visited[$idx] = "\1";
            imagesetpixel($src, $x, $y, imagecolorallocatealpha($src, 0, 0, 0, 127));
            $queue[] = [$x + 1, $y];
            $queue[] = [$x - 1, $y];
            $queue[] = [$x, $y + 1];
            $queue[] = [$x, $y - 1];
        }
    }

    imagealphablending($src, false);
    imagesavealpha($src, true);
    imagepng($src, $path, 9);
    imagedestroy($src);
}

function isBackgroundPixel(int $r, int $g, int $b, bool $isDark, int $threshold): bool
{
    if ($isDark) {
        return $r <= 70 && $g <= 70 && $b <= 70;
    }

    $avg = ($r + $g + $b) / 3;
    $spread = max($r, $g, $b) - min($r, $g, $b);

    return $avg >= 255 - $threshold && $spread <= 28;
}
