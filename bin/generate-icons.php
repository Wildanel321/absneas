<?php

declare(strict_types=1);

$outDir = dirname(__DIR__) . '/public/icons';
if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

foreach ([192, 512] as $size) {
    $img = imagecreatetruecolor($size, $size);
    imagealphablending($img, true);
    imagesavealpha($img, true);

    $navy = imagecolorallocate($img, 15, 29, 58);
    $white = imagecolorallocate($img, 255, 255, 255);
    $violet = imagecolorallocate($img, 168, 153, 255);

    imagefilledrectangle($img, 0, 0, $size, $size, $navy);

    $s = static fn (float $v): int => (int) round(($v / 192.0) * $size);
    $stroke = max(4, $s(10));

    $drawSquare = static function ($img, int $x, int $y, int $w, int $innerColor) use ($white, $navy, $stroke): void {
        imagefilledrectangle($img, $x, $y, $x + $w, $y + $w, $white);
        imagefilledrectangle($img, $x + $stroke, $y + $stroke, $x + $w - $stroke, $y + $w - $stroke, $navy);
        $pad = (int) round($w * 0.30);
        imagefilledrectangle($img, $x + $pad, $y + $pad, $x + $w - $pad, $y + $w - $pad, $innerColor);
    };

    $drawSquare($img, $s(28), $s(28), $s(52), $violet);
    $drawSquare($img, $s(112), $s(28), $s(52), $white);
    $drawSquare($img, $s(28), $s(112), $s(52), $white);

    // Checkmark di kuadran kanan bawah
    imagesetthickness($img, max(6, $s(12)));
    imageline($img, $s(114), $s(140), $s(130), $s(156), $violet);
    imageline($img, $s(130), $s(156), $s(164), $s(118), $violet);

    imagepng($img, $outDir . "/icon-{$size}.png");
    imagedestroy($img);
    fwrite(STDOUT, "Generated icon-{$size}.png\n");
}
