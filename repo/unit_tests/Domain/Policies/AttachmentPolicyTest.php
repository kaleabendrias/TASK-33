<?php

namespace UnitTests\Domain\Policies;

use App\Domain\Policies\AttachmentPolicy;
use PHPUnit\Framework\TestCase;

class AttachmentPolicyTest extends TestCase
{
    public function test_valid_size(): void
    {
        $this->assertTrue(AttachmentPolicy::validateSize(1024));
        $this->assertTrue(AttachmentPolicy::validateSize(AttachmentPolicy::MAX_SIZE_BYTES));
    }

    public function test_zero_size_invalid(): void
    {
        $this->assertFalse(AttachmentPolicy::validateSize(0));
    }

    public function test_oversized_invalid(): void
    {
        $this->assertFalse(AttachmentPolicy::validateSize(AttachmentPolicy::MAX_SIZE_BYTES + 1));
    }

    public function test_valid_mime_types(): void
    {
        foreach (AttachmentPolicy::ALLOWED_MIME_TYPES as $mime) {
            $this->assertTrue(AttachmentPolicy::validateMimeType($mime), "Expected {$mime} to be valid");
        }
    }

    public function test_invalid_mime_type(): void
    {
        $this->assertFalse(AttachmentPolicy::validateMimeType('application/exe'));
        $this->assertFalse(AttachmentPolicy::validateMimeType('text/html'));
    }

    public function test_validate_returns_null_for_valid(): void
    {
        $this->assertNull(AttachmentPolicy::validate(1024, 'application/pdf'));
    }

    public function test_validate_returns_errors_for_invalid_size(): void
    {
        $errors = AttachmentPolicy::validate(0, 'application/pdf');
        $this->assertNotNull($errors);
        $this->assertStringContainsString('size', $errors[0]);
    }

    public function test_validate_returns_errors_for_invalid_mime(): void
    {
        $errors = AttachmentPolicy::validate(1024, 'application/exe');
        $this->assertNotNull($errors);
        $this->assertStringContainsString('type', $errors[0]);
    }

    public function test_validate_returns_multiple_errors(): void
    {
        $errors = AttachmentPolicy::validate(0, 'application/exe');
        $this->assertNotNull($errors);
        $this->assertCount(2, $errors);
    }

    public function test_max_size_constant(): void
    {
        $this->assertEquals(20 * 1024 * 1024, AttachmentPolicy::MAX_SIZE_BYTES);
    }
}
