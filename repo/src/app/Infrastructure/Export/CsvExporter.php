<?php

namespace App\Infrastructure\Export;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pure-PHP CSV export — no third-party dependencies.
 */
class CsvExporter
{
    public static function export(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return new StreamedResponse(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'no-store',
        ]);
    }
}
