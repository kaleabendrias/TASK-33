<?php

namespace UnitTests\Infrastructure\Export;

use App\Infrastructure\Export\PdfExporter;
use PHPUnit\Framework\TestCase;

class PdfExporterTest extends TestCase
{
    public function test_export_returns_pdf_response(): void
    {
        $response = PdfExporter::export('report.pdf', 'Test Report', ['A', 'B'], [['1', '2']]);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('report.pdf', $response->headers->get('Content-Disposition'));
    }

    public function test_pdf_starts_with_header(): void
    {
        $response = PdfExporter::export('test.pdf', 'Title', ['H'], [['V']]);
        $this->assertStringStartsWith('%PDF-1.4', $response->getContent());
    }

    public function test_pdf_ends_with_eof(): void
    {
        $response = PdfExporter::export('eof.pdf', 'EOF', ['X'], [['Y']]);
        $this->assertStringEndsWith('%%EOF', $response->getContent());
    }

    public function test_pdf_contains_content(): void
    {
        $response = PdfExporter::export('content.pdf', 'My Report', ['Name'], [['Alice']]);
        $content = $response->getContent();
        $this->assertStringContainsString('My Report', $content);
        $this->assertStringContainsString('Alice', $content);
    }

    public function test_empty_rows(): void
    {
        $response = PdfExporter::export('empty.pdf', 'Empty', ['H'], []);
        $this->assertStringStartsWith('%PDF-1.4', $response->getContent());
    }
}
