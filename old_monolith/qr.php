<?php

// qr.php
declare(strict_types=1);

class SaaSQR
{
    public static function getSVG(string $text): string
    {
        $size = 25;
        $matrix = array_fill(0, $size, array_fill(0, $size, 0));
        $hash = crc32($text);
        srand($hash);
        $c = (int)floor(($size - 5) / 2);

        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($y >= $c && $y < $c + 5 && $x >= $c && $x < $c + 5) {
                    continue;
                }
                if (($y < 7 && $x < 7) || ($y < 7 && $x >= $size - 7) || ($y >= $size - 7 && $x < 7)) {
                    $matrix[$y][$x] = ($y == 0 || $y == 6 || $x == 0 || $x == 6 || $y == $size - 7 || $y == $size - 1 || $x == $size - 7 || $x == $size - 1 || ($y >= 2 && $y <= 4 && $x >= 2 && $x <= 4) || ($y >= 2 && $y <= 4 && $x >= $size - 5 && $x <= $size - 3) || ($y >= $size - 5 && $y <= $size - 3 && $x >= 2 && $x <= 4)) ? 1 : 0;
                } else {
                    $matrix[$y][$x] = (($x + $y) % 2 == 0 || rand(0, 100) > 45) ? 1 : 0;
                }
            }
        }
        for ($i = 8; $i < $size - 8; $i++) {
            $matrix[$i] = ($i % 2 == 0) ? 1 : 0;
        }

        $svg = '<svg xmlns="http://w3.org" viewBox="-1 -1 ' . ($size + 2) . ' ' . ($size + 2) . '" width="240" height="240" style="background:#fff;border-radius:16px;padding:8px;"><defs><linearGradient id="g"><stop offset="0%" stop-color="#ff9f1c"/><stop offset="100%" stop-color="#ff6b6b"/></linearGradient></defs><path d="';
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if ($matrix[$y][$x]) {
                    if (($y < 7 && $x < 7) || ($y < 7 && $x >= $size - 7) || ($y >= $size - 7 && $x < 7)) {
                        $svg .= "M{$x},{$y}h1v1h-1z ";
                    } else {
                        $svg .= "M" . ($x + 0.1) . "," . ($y + 0.1) . "h0.8v0.8h-0.8z ";
                    }
                }
            }
        }
        $svg .= '" fill="url(#g)"/><circle cx="12.5" cy="12.5" r="3.2" fill="#fff" stroke="#ff9f1c" stroke-width="0.2"/><g transform="translate(10.3,10.3) scale(0.18)"><path d="M12 2c1.1 0 2 .9 2 2v3.07c3.95.49 7 3.85 7 7.93H3c0-4.08 3.05-7.44 7-7.93V4c0-1.1.9-2 2-2zm0 4c-2.97 0-5.45 2.14-5.92 5h11.84c-.47-2.86-2.95-5-5.92-5zm-10 13h20v2H2v-2z" fill="#ff9f1c"/></g></svg>';
        return $svg;
    }
}
