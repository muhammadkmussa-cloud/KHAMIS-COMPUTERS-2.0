<?php
declare(strict_types=1);

/**
 * Barcode — dependency-free Code 128 (code set B) encoder.
 *
 * Renders a scannable Code 128 barcode as inline SVG, so labels/receipts need
 * no GD extension, no image library and no CDN. Patterns are the ISO/IEC 15417
 * symbol table (values 0–106); set B covers ASCII 32–126, which is everything a
 * SKU, barcode or serial/IMEI needs.
 */
class Barcode
{
    /**
     * 11-module bar patterns per code value (1 = bar, 0 = space), values 0..106.
     * Index 106 is the Stop symbol (the trailing 2-module bar is added separately).
     */
    private const PATTERNS = [
        '11011001100','11001101100','11001100110','10010011000','10010001100','10001001100',
        '10011001000','10011000100','10001100100','11001001000','11001000100','11000100100',
        '10110011100','10011011100','10011001110','10111001100','10011101100','10011100110',
        '11001110010','11001011100','11001001110','11011100100','11001110100','11101101110',
        '11101001100','11100101100','11100100110','11101100100','11100110100','11100110010',
        '11011011000','11011000110','11000110110','10100011000','10001011000','10001000110',
        '10110001000','10001101000','10001100010','11010001000','11000101000','11000100010',
        '10110111000','10110001110','10001101110','10111011000','10111000110','10001110110',
        '11101110110','11010001110','11000101110','11011101000','11011100010','11011101110',
        '11101011000','11101000110','11100010110','11101101000','11101100010','11100011010',
        '11101111010','11001000010','11110001010','10100110000','10100001100','10010110000',
        '10010000110','10000101100','10000100110','10110010000','10110000100','10011010000',
        '10011000010','10000110100','10000110010','11000010010','11001010000','11110111010',
        '11000010100','10001111010','10100111100','10010111100','10010011110','10111100100',
        '10011110100','10011110010','11110100100','11110010100','11110010010','11011011110',
        '11011110110','11110110110','10101111000','10100011110','10001011110','10111101000',
        '10111100010','11110101000','11110100010','10111011110','10111101110','11101011110',
        '11110101110',                 // 102
        '11010000100',                 // 103 Start A
        '11010010000',                 // 104 Start B
        '11010011100',                 // 105 Start C
        '11000111010',                 // 106 Stop
    ];

    /** Code-set B symbol values for a text string (unencodable chars become '?'). */
    public static function values(string $text): array
    {
        $values = [104]; // Start B
        $len    = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            $c = ord($text[$i]);
            if ($c < 32 || $c > 126) {
                $c = 63; // '?'
            }
            $values[] = $c - 32;
        }
        return $values;
    }

    /** Weighted modulo-103 checksum over symbol values (start symbol included). */
    public static function checksum(array $values): int
    {
        $sum = $values[0];
        $n   = count($values);
        for ($i = 1; $i < $n; $i++) {
            $sum += $values[$i] * $i;
        }
        return $sum % 103;
    }

    /** Full bit string including quiet zones and the stop's trailing bar. */
    public static function bits(string $text): string
    {
        $values = self::values($text);
        $values[] = self::checksum($values);

        $out = '';
        foreach ($values as $v) {
            $out .= self::PATTERNS[$v];
        }
        $out .= '11'; // termination bar completing the 13-module stop pattern

        return str_repeat('0', 10) . $out . str_repeat('0', 10); // mandatory quiet zones
    }

    /**
     * Render the barcode as inline SVG.
     * @param int $height  bar height in px
     * @param int $module  module width in px
     */
    public static function svg(string $text, int $height = 56, int $module = 2): string
    {
        $bits   = self::bits($text);
        $len    = strlen($bits);
        $width  = $len * $module;
        $bars   = [];
        $i      = 0;
        while ($i < $len) {
            if ($bits[$i] === '1') {
                $start = $i;
                while ($i < $len && $bits[$i] === '1') {
                    $i++;
                }
                $bars[] = '<rect x="' . ($start * $module) . '" y="0" width="'
                    . (($i - $start) * $module) . '" height="' . $height . '"/>';
            } else {
                $i++;
            }
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height
            . '" viewBox="0 0 ' . $width . ' ' . $height . '" style="background:#fff;display:block"'
            . ' shape-rendering="crispEdges">' . implode('', $bars) . '</svg>';
    }
}
