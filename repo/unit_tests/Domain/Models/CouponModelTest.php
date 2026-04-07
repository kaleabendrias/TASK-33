<?php

namespace UnitTests\Domain\Models;

use App\Domain\Models\Coupon;
use UnitTests\TestCase;

class CouponModelTest extends TestCase
{
    private function makeCoupon(array $overrides = []): Coupon
    {
        return Coupon::create(array_merge([
            'code' => 'TEST' . mt_rand(100, 999),
            'discount_type' => 'percentage',
            'discount_value' => 10,
            'min_order_amount' => 50,
            'max_uses' => 100,
            'used_count' => 0,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addMonth(),
            'is_active' => true,
        ], $overrides));
    }

    public function test_valid_coupon(): void
    {
        $coupon = $this->makeCoupon();
        $this->assertTrue($coupon->isValid(100));
    }

    public function test_inactive_coupon(): void
    {
        $coupon = $this->makeCoupon(['is_active' => false]);
        $this->assertEquals('Coupon is inactive.', $coupon->isValid(100));
    }

    public function test_future_coupon(): void
    {
        $coupon = $this->makeCoupon(['valid_from' => now()->addDay()]);
        $this->assertEquals('Coupon is not yet valid.', $coupon->isValid(100));
    }

    public function test_expired_coupon(): void
    {
        $coupon = $this->makeCoupon(['valid_until' => now()->subDay()]);
        $this->assertEquals('Coupon has expired.', $coupon->isValid(100));
    }

    public function test_usage_limit_reached(): void
    {
        $coupon = $this->makeCoupon(['max_uses' => 5, 'used_count' => 5]);
        $this->assertEquals('Coupon usage limit reached.', $coupon->isValid(100));
    }

    public function test_below_minimum_order(): void
    {
        $coupon = $this->makeCoupon(['min_order_amount' => 100]);
        $result = $coupon->isValid(50);
        $this->assertStringContainsString('Minimum order', $result);
    }

    public function test_percentage_discount(): void
    {
        $coupon = $this->makeCoupon(['discount_type' => 'percentage', 'discount_value' => 15]);
        $this->assertEquals(15.0, $coupon->calculateDiscount(100));
    }

    public function test_fixed_discount(): void
    {
        $coupon = $this->makeCoupon(['discount_type' => 'fixed', 'discount_value' => 25]);
        $this->assertEquals(25.0, $coupon->calculateDiscount(100));
    }

    public function test_fixed_discount_capped_at_subtotal(): void
    {
        $coupon = $this->makeCoupon(['discount_type' => 'fixed', 'discount_value' => 200]);
        $this->assertEquals(50.0, $coupon->calculateDiscount(50));
    }

    public function test_null_valid_until_is_valid(): void
    {
        $coupon = $this->makeCoupon(['valid_until' => null]);
        $this->assertTrue($coupon->isValid(100));
    }

    public function test_null_max_uses_is_unlimited(): void
    {
        $coupon = $this->makeCoupon(['max_uses' => null, 'used_count' => 99999]);
        $this->assertTrue($coupon->isValid(100));
    }
}
