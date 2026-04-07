<?php

namespace UnitTests\Application\Services;

use App\Application\Services\AttachmentService;
use Illuminate\Validation\ValidationException;
use UnitTests\TestCase;

class AttachmentServiceTest extends TestCase
{
    private AttachmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AttachmentService::class);
    }

    public function test_create_valid_attachment(): void
    {
        $a = $this->service->create('Resource', 1, 'report.pdf', '/tmp/report.pdf', 'application/pdf', 1024, hash('sha256', 'content'));
        $this->assertNotNull($a->id);
        $this->assertEquals('report.pdf', $a->original_filename);
        $this->assertEquals(1024, $a->size_bytes);
    }

    public function test_create_invalid_size_throws(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->create('Resource', 1, 'big.pdf', '/tmp/big.pdf', 'application/pdf', 0, hash('sha256', 'x'));
    }

    public function test_create_invalid_mime_throws(): void
    {
        $this->expectException(ValidationException::class);
        $this->service->create('Resource', 1, 'evil.exe', '/tmp/evil.exe', 'application/exe', 1024, hash('sha256', 'x'));
    }

    public function test_sha256_fingerprint_stored(): void
    {
        $fp = hash('sha256', 'unique-content');
        $a = $this->service->create('Resource', 1, 'doc.pdf', '/tmp/doc.pdf', 'application/pdf', 2048, $fp);
        $this->assertEquals($fp, $a->sha256_fingerprint);
    }
}
