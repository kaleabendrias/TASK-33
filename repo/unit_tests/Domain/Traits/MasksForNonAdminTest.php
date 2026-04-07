<?php

namespace UnitTests\Domain\Traits;

use App\Domain\Traits\MasksForNonAdmin;
use PHPUnit\Framework\TestCase;

class MasksForNonAdminTest extends TestCase
{
    private object $masker;

    protected function setUp(): void
    {
        $this->masker = new class {
            use MasksForNonAdmin;

            // Expose protected methods for testing
            public function testMask(string $v, int $c = 3): string { return $this->mask($v, $c); }
            public function testMaskEmail(?string $e): ?string { return $this->maskEmail($e); }
            public function testMaskPhone(?string $p): ?string { return $this->maskPhone($p); }
        };
    }

    public function test_mask_basic(): void
    {
        $this->assertEquals('Hel**', $this->masker->testMask('Hello'));
    }

    public function test_mask_short_string(): void
    {
        $this->assertEquals('**', $this->masker->testMask('AB'));
    }

    public function test_mask_with_custom_visible_chars(): void
    {
        $this->assertEquals('Hell*', $this->masker->testMask('Hello', 4));
    }

    public function test_mask_email(): void
    {
        $this->assertEquals('te**@example.com', $this->masker->testMaskEmail('test@example.com'));
    }

    public function test_mask_email_null(): void
    {
        $this->assertNull($this->masker->testMaskEmail(null));
    }

    public function test_mask_email_no_at_sign(): void
    {
        $this->assertEquals('inv****', $this->masker->testMaskEmail('invalid'));
    }

    public function test_mask_phone(): void
    {
        $this->assertEquals('*******4567', $this->masker->testMaskPhone('+1-555-4567'));
    }

    public function test_mask_phone_short(): void
    {
        $this->assertEquals('****', $this->masker->testMaskPhone('1234'));
    }

    public function test_mask_phone_null(): void
    {
        $this->assertNull($this->masker->testMaskPhone(null));
    }
}
