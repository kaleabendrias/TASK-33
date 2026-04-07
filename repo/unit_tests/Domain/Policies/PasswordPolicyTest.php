<?php

namespace UnitTests\Domain\Policies;

use App\Domain\Policies\PasswordPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PasswordPolicyTest extends TestCase
{
    public function test_valid_password_returns_null(): void
    {
        $this->assertNull(PasswordPolicy::validate('StrongPass@123'));
    }

    public function test_too_short(): void
    {
        $errors = PasswordPolicy::validate('Sh@1');
        $this->assertNotNull($errors);
        $this->assertStringContainsString('12 characters', $errors[0]);
    }

    public function test_no_uppercase(): void
    {
        $errors = PasswordPolicy::validate('lowercaseonly@1');
        $this->assertNotNull($errors);
        $this->assertTrue(collect($errors)->contains(fn ($e) => str_contains($e, 'uppercase')));
    }

    public function test_no_lowercase(): void
    {
        $errors = PasswordPolicy::validate('UPPERCASEONLY@1');
        $this->assertNotNull($errors);
        $this->assertTrue(collect($errors)->contains(fn ($e) => str_contains($e, 'lowercase')));
    }

    public function test_no_digit(): void
    {
        $errors = PasswordPolicy::validate('NoDigitsHere@!');
        $this->assertNotNull($errors);
        $this->assertTrue(collect($errors)->contains(fn ($e) => str_contains($e, 'digit')));
    }

    public function test_no_special_char(): void
    {
        $errors = PasswordPolicy::validate('NoSpecial12345');
        $this->assertNotNull($errors);
        $this->assertTrue(collect($errors)->contains(fn ($e) => str_contains($e, 'special')));
    }

    public function test_multiple_violations(): void
    {
        $errors = PasswordPolicy::validate('short');
        $this->assertNotNull($errors);
        $this->assertGreaterThan(1, count($errors));
    }

    public function test_exactly_12_chars_is_valid(): void
    {
        $this->assertNull(PasswordPolicy::validate('Abcdefgh@1!!'));
    }

    public function test_min_length_constant(): void
    {
        $this->assertEquals(12, PasswordPolicy::MIN_LENGTH);
    }
}
