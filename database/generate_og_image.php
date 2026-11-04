<?php declare(strict_types=1);

/**
 * OG Image Generator — renders the social-share card used by the
 * OpenGraph / Twitter meta tags.
 *
 * A social preview is the first thing a visitor sees when a link is shared
 * on X, LinkedIn, Slack, WhatsApp or Facebook, so it has to be a real
 * 1200x630 image — a square logo crops to nothing and reads as broken.
 *
 * Usage:
 *   php database/generate_og_image.php
 *
 * Output: assets/images/og/adsbooster-og.png
 */

require_once __DIR__ . '/../config/site_setting_and_config.php';

const OG_WIDTH  = 1200;
const OG_HEIGHT = 630;

$outDir  = __DIR__ . '/../assets/images/og';
$outFile = $outDir . '/adsbooster-og.png';

if (!is_dir($outDir) && !@mkdir($outDir, 0755, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Cannot create {$outDir}\n");
    exit(1);
}

if (!function_exists('imagecreatetruecolor')) {
    fwrite(STDERR, "GD extension is required to render the OG image.\n");
    exit(1);
}

// ── palette (matches the Adsbooster brand) ──────────────────────────────
$bgTop    = [8, 12, 20];       // #080c14
$bgBottom = [13, 21, 36];      // #0d1524
$lime     = [200, 255, 0];     // #c8ff00
$teal     = [56, 189, 248];    // #38bdf8
$white    = [248, 250, 252];   // #f8fafc
$muted    = [148, 163, 184];   // #94a3b8

$img = imagecreatetruecolor(OG_WIDTH, OG_HEIGHT);
imagealphablending($img, true);
imagesavealpha($img, false);

// ── vertical gradient background ─────────────────────────────────────────
for ($y = 0; $y < OG_HEIGHT; $y++) {
    $t = $y / (OG_HEIGHT - 1);
    $c = imagecolorallocate(
        $img,
        (int)round($bgTop[0] + ($bgBottom[0] - $bgTop[0]) * $t),
        (int)round($bgTop[1] + ($bgBottom[1] - $bgTop[1]) * $t),
        (int)round($bgTop[2] + ($bgBottom[2] - $bgTop[2]) * $t)
    );
    imagefilledrectangle($img, 0, $y, OG_WIDTH, $y, $c);
}

// ── radial glows (lime bottom-left, teal top-right) ──────────────────────
/**
 * Soft radial glow: strongest at the centre, fully transparent at the rim.
 * (alpha 127 = invisible, 0 = opaque in GD)
 */
function glow(\GdImage $img, int $cx, int $cy, int $radius, array $rgb, float $strength): void
{
    for ($r = $radius; $r > 0; $r -= 4) {
        $falloff = 1.0 - ($r / $radius);
        $alpha = (int)round(127 * (1.0 - $strength * $falloff * $falloff));
        if ($alpha >= 127) {
            continue;
        }
        $color = imagecolorallocatealpha($img, $rgb[0], $rgb[1], $rgb[2], $alpha);
        imagefilledellipse($img, $cx, $cy, $r * 2, $r * 2, $color);
    }
}
glow($img, -60, OG_HEIGHT + 70, 540, $lime, 0.42);
glow($img, OG_WIDTH + 80, -70, 480, $teal, 0.26);

// ── subtle grid ──────────────────────────────────────────────────────────
$grid = imagecolorallocatealpha($img, 255, 255, 255, 122);
for ($x = 0; $x <= OG_WIDTH; $x += 60) {
    imageline($img, $x, 0, $x, OG_HEIGHT, $grid);
}
for ($y = 0; $y <= OG_HEIGHT; $y += 60) {
    imageline($img, 0, $y, OG_WIDTH, $y, $grid);
}

// ── diagonal light streaks ───────────────────────────────────────────────
$streak = imagecolorallocatealpha($img, 255, 255, 255, 120);
for ($i = 0; $i < 4; $i++) {
    $offset = 220 + ($i * 260);
    $width  = 60 + ($i * 22);
    $pts = [
        $offset, 0,
        $offset + $width, 0,
        $offset + $width - 240, OG_HEIGHT,
        $offset - 240, OG_HEIGHT,
    ];
    imagefilledpolygon($img, $pts, $streak);
}

// ── readability scrim behind the copy ────────────────────────────────────
for ($y = 0; $y < OG_HEIGHT; $y++) {
    $t = $y / (OG_HEIGHT - 1);
    $alpha = (int)round(127 - 38 * $t);   // opaque at the top, slightly lighter below
    $scrim = imagecolorallocatealpha($img, 5, 8, 14, min(127, max(100, $alpha)));
    imageline($img, 0, $y, 860, $y, $scrim);
}

// ── fonts ────────────────────────────────────────────────────────────────
$fontBold  = 'C:/Windows/Fonts/arialbd.ttf';
$fontRegular = 'C:/Windows/Fonts/arial.ttf';
if (!is_file($fontBold)) {
    fwrite(STDERR, "Font not found: {$fontBold}\n");
    exit(1);
}

/**
 * Draw left-aligned text and return its width.
 */
function text(\GdImage $img, string $font, int $size, string $colorHex, float $x, float $y, string $text, float $spacing = 0.0): float
{
    $color = sscanf($colorHex, '#%02x%02x%02x');
    $c = imagecolorallocate($img, $color[0], $color[1], $color[2]);
    $len = strlen($text);
    $cursor = $x;
    for ($i = 0; $i < $len; $i++) {
        $ch = $text[$i];
        if ($ch === ' ') {
            $cursor += $size * 0.32 + $spacing;
            continue;
        }
        imagettftext($img, $size, 0, (int)$cursor, (int)$y, $c, $font, $ch);
        $bbox = imagettfbbox($size, 0, $font, $ch);
        $cursor += ($bbox[2] - $bbox[0]) + $spacing;
    }
    return $cursor - $x;
}

// ── wordmark ─────────────────────────────────────────────────────────────
// A lime rounded-square mark plus the wordmark (the bitmap logo is too
// detailed to stay legible at this size).
$mark = imagecolorallocate($img, $lime[0], $lime[1], $lime[2]);
imagefilledrectangle($img, 78, 58, 118, 98, $mark);
imagefilledrectangle($img, 88, 68, 108, 88, imagecolorallocate($img, 8, 12, 20));
text($img, $fontBold, 27, '#ffffff', 136, 89, 'ADSBOOSTER.NET', 2.2);

// ── headline ─────────────────────────────────────────────────────────────
text($img, $fontBold, 78, '#ffffff', 74, 268, 'Launch Ads.', 0.5);
text($img, $fontBold, 78, '#c8ff00', 74, 356, 'Scale Everywhere.', 0.5);

// ── subline ──────────────────────────────────────────────────────────────
text($img, $fontRegular, 27, '#94a3b8', 78, 424, 'One platform for Meta, TikTok, Google Ads, organic social and AI creatives.', 0.0);

// ── feature pills ────────────────────────────────────────────────────────
$pills = ['PAID ADS', 'ORGANIC SOCIAL', 'AI CREATIVES'];
$px = 78;
$py = 486;
foreach ($pills as $pill) {
    $size = 21;
    $tw = text_width($fontBold, $size, $pill, 2.0);
    $padX = 26;
    $h = 48;
    $w = (int)round($tw + $padX * 2);

    // translucent pill
    $fill = imagecolorallocatealpha($img, 255, 255, 255, 120);
    imagefilledrectangle($img, (int)$px, $py, (int)($px + $w), $py + $h, $fill);
    $border = imagecolorallocatealpha($img, 255, 255, 255, 105);
    imagerectangle($img, (int)$px, $py, (int)($px + $w), $py + $h, $border);

    text($img, $fontBold, $size, '#f8fafc', $px + $padX, $py + 32, $pill, 2.0);
    $px += $w + 16;
}

/**
 * Measure the width of a string in the same way text() draws it.
 */
function text_width(string $font, int $size, string $text, float $spacing = 0.0): float
{
    $len = strlen($text);
    $w = 0.0;
    for ($i = 0; $i < $len; $i++) {
        $ch = $text[$i];
        if ($ch === ' ') {
            $w += $size * 0.32 + $spacing;
            continue;
        }
        $bbox = imagettfbbox($size, 0, $font, $ch);
        $w += ($bbox[2] - $bbox[0]) + $spacing;
    }
    return $w;
}

// ── domain footer ────────────────────────────────────────────────────────
text($img, $fontRegular, 24, '#cbd5e1', 78, 590, 'adsbooster.net', 0.6);

// ── accent bar ───────────────────────────────────────────────────────────
$accent = imagecolorallocate($img, $lime[0], $lime[1], $lime[2]);
imagefilledrectangle($img, 0, OG_HEIGHT - 6, OG_WIDTH, OG_HEIGHT, $accent);

if (!imagepng($img, $outFile, 6)) {
    fwrite(STDERR, "Failed to write {$outFile}\n");
    exit(1);
}
imagedestroy($img);

$size = filesize($outFile);
echo "OG image written: {$outFile} (" . number_format((float)$size) . " bytes, " . OG_WIDTH . 'x' . OG_HEIGHT . ")\n";
