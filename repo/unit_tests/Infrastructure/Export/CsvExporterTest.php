<?php

namespace UnitTests\Infrastructure\Export;

use App\Infrastructure\Export\CsvExporter;
use PHPUnit\Framework\TestCase;

class CsvExporterTest extends TestCase
{
    public function test_export_returns_streamed_response(): void
    {
        $response = CsvExporter::export('test.csv', ['Name', 'Value'], [['Alice', '100'], ['Bob', '200']]);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('test.csv', $response->headers->get('Content-Disposition'));
    }

    public function test_csv_content(): void
    {
        $response = CsvExporter::export('data.csv', ['Col1', 'Col2'], [['A', 'B'], ['C', 'D']]);
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();
        $this->assertStringContainsString('Col1,Col2', $content);
        $this->assertStringContainsString('A,B', $content);
        $this->assertStringContainsString('C,D', $content);
    }

    public function test_empty_rows(): void
    {
        $response = CsvExporter::export('empty.csv', ['H1'], []);
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();
        $this->assertStringContainsString('H1', $content);
        $lines = array_filter(explode("\n", trim($content)));
        $this->assertCount(1, $lines); // header only
    }
}
