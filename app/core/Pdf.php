<?php
declare(strict_types=1);

/**
 * Minimal, dependency-free PDF writer (single page, Helvetica text).
 * Good enough for receipts/invoices with zero Composer dependencies,
 * which keeps the whole app cPanel-friendly.
 */
class Pdf
{
    /**
     * @param array $lines each: ['text'=>string,'x'=>float,'y'=>float,
     *                            'size'=>float,'bold'=>bool]
     */
    public static function generate(array $lines, string $title = ''): string
    {
        $W = 612.0; // US Letter (points)
        $H = 792.0;

        $stream = "BT\n";
        foreach ($lines as $ln) {
            $size = (float) ($ln['size'] ?? 11);
            $font = !empty($ln['bold']) ? 'F2' : 'F1';
            $x    = (float) ($ln['x'] ?? 40);
            $y    = (float) ($ln['y'] ?? 760);
            $text = self::escape((string) ($ln['text'] ?? ''));
            $stream .= "/{$font} {$size} Tf\n1 0 0 1 {$x} {$y} Tm\n({$text}) Tj\n";
        }
        $stream .= "ET";

        $objs    = [];
        $objs[1] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objs[2] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $objs[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 {$W} {$H}] "
                 . "/Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>";
        $objs[4] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>";
        $objs[5] = "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>";
        $objs[6] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream";

        $out     = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objs as $num => $body) {
            $offsets[$num] = strlen($out);
            $out .= $num . " 0 obj\n" . $body . "\nendobj\n";
        }

        $xrefPos = strlen($out);
        $count   = count($objs) + 1;
        $out .= "xref\n0 {$count}\n";
        $out .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objs); $i++) {
            $out .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $out .= "trailer\n<< /Size {$count} /Root 1 0 R >>\nstartxref\n{$xrefPos}\n%%EOF";

        return $out;
    }

    /** Make text safe for a standard Type1 font (WinAnsi-ish, single-byte). */
    private static function escape(string $s): string
    {
        $s = str_replace(
            ['·', '−', '–', '—', '→', '↗', '⇒', '✓', '↩'],
            ['-', '-', '-', '-', '->', '', '=>', 'OK', '<-'],
            $s
        );
        // Strip anything outside printable ASCII + Latin-1 (UTF-8 aware).
        $s = preg_replace('/[^\x20-\x7E\xA0-\xFF]/u', '', $s) ?? $s;
        if (function_exists('iconv')) {
            $c = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s);
            if ($c !== false) {
                $s = $c;
            }
        }
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }
}
