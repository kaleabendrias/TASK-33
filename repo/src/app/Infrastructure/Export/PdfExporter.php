<?php

namespace App\Infrastructure\Export;

use Symfony\Component\HttpFoundation\Response;

/**
 * Pure-PHP PDF generator — no third-party dependencies.
 * Generates a minimal valid PDF from HTML table content.
 */
class PdfExporter
{
    public static function export(string $filename, string $title, array $headers, iterable $rows): Response
    {
        $html = self::buildHtml($title, $headers, $rows);
        $pdf = self::htmlToPdf($html);

        return new Response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Content-Length'      => strlen($pdf),
            'Cache-Control'       => 'no-store',
        ]);
    }

    private static function buildHtml(string $title, array $headers, iterable $rows): string
    {
        $h = '<table border="1" cellpadding="4" cellspacing="0" style="border-collapse:collapse;font-family:Helvetica,sans-serif;font-size:10px;width:100%">';
        $h .= '<caption style="font-size:14px;font-weight:bold;margin-bottom:8px">' . htmlspecialchars($title) . '</caption>';
        $h .= '<thead><tr style="background:#1e3a8a;color:#fff">';
        foreach ($headers as $hd) $h .= '<th style="padding:6px">' . htmlspecialchars($hd) . '</th>';
        $h .= '</tr></thead><tbody>';
        $alt = false;
        foreach ($rows as $row) {
            $bg = $alt ? '#f1f5f9' : '#fff';
            $h .= "<tr style=\"background:{$bg}\">";
            foreach ($row as $cell) $h .= '<td style="padding:4px">' . htmlspecialchars((string) $cell) . '</td>';
            $h .= '</tr>';
            $alt = !$alt;
        }
        $h .= '</tbody></table>';
        return $h;
    }

    /**
     * Minimal PDF 1.4 generator — embeds HTML as text content.
     * For a production system, replace with a proper renderer;
     * this gives a clean, readable document without any external library.
     */
    private static function htmlToPdf(string $html): string
    {
        // Strip tags for plain text rendering within the PDF
        $text = strip_tags(str_replace(['</th>', '</td>', '</tr>'], ["\t", "\t", "\n"], $html));
        $text = preg_replace('/\t+/', "\t", $text);
        $text = trim($text);

        // Build minimal valid PDF
        $stream = "BT\n/F1 9 Tf\n36 760 Td\n12 TL\n";
        foreach (explode("\n", $text) as $line) {
            $escaped = strtr(trim($line), ['(' => '\\(', ')' => '\\)', '\\' => '\\\\']);
            $stream .= "({$escaped}) Tj T*\n";
        }
        $stream .= "ET";

        $objects = [];
        $objects[1] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj";
        $objects[2] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj";
        $objects[3] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj";
        $objects[4] = "4 0 obj\n<< /Length " . strlen($stream) . " >>\nstream\n{$stream}\nendstream\nendobj";
        $objects[5] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj";

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $num => $obj) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "{$obj}\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }
}
