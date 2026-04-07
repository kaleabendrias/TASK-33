<?php

namespace UnitTests\Domain\Policies;

use App\Domain\Models\PricingBaseline;
use App\Domain\Policies\PricingPolicy;
class PricingPolicyTest extends \Tests\TestCase
{
    public function test_meets_minimum_rate(): void
    {
        $this->assertTrue(PricingPolicy::meetsMinimumRate(10.00));
        $this->assertTrue(PricingPolicy::meetsMinimumRate(100.00));
    }

    public function test_below_minimum_rate(): void
    {
        $this->assertFalse(PricingPolicy::meetsMinimumRate(9.99));
        $this->assertFalse(PricingPolicy::meetsMinimumRate(0));
    }

    public function test_is_active_within_window(): void
    {
        $baseline = new PricingBaseline();
        $baseline->effective_from = now()->subDay();
        $baseline->effective_until = now()->addDay();
        $this->assertTrue(PricingPolicy::isActive($baseline));
    }

    public function test_is_inactive_before_start(): void
    {
        $baseline = new PricingBaseline();
        $baseline->effective_from = now()->addDay();
        $baseline->effective_until = now()->addWeek();
        $this->assertFalse(PricingPolicy::isActive($baseline));
    }

    public function test_is_inactive_after_end(): void
    {
        $baseline = new PricingBaseline();
        $baseline->effective_from = now()->subWeek();
        $baseline->effective_until = now()->subDay();
        $this->assertFalse(PricingPolicy::isActive($baseline));
    }

    public function test_is_active_with_null_end(): void
    {
        $baseline = new PricingBaseline();
        $baseline->effective_from = now()->subDay();
        $baseline->effective_until = null;
        $this->assertTrue(PricingPolicy::isActive($baseline));
    }
}
