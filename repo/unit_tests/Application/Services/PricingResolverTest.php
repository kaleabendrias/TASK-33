<?php

namespace UnitTests\Application\Services;

use App\Application\Services\PricingResolver;
use App\Domain\Models\BookableItem;
use App\Domain\Models\PricingRule;
use App\Domain\Models\User;
use UnitTests\TestCase;

class PricingResolverTest extends TestCase
{
    private PricingResolver $resolver;
    private BookableItem $item;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new PricingResolver();
        $this->item = BookableItem::create([
            'type' => 'room', 'name' => 'Test Room', 'hourly_rate' => 50,
            'daily_rate' => 200, 'tax_rate' => 0.0000, 'capacity' => 10, 'is_active' => true,
        ]);
    }

    public function test_no_rules_returns_base_price(): void
    {
        $result = $this->resolver->resolveUnitPrice($this->item, 200.00, [
            'date' => '2026-06-01',
        ]);
        $this->assertEquals(200.00, $result['unit_price']);
        $this->assertNull($result['rule']);
    }

    public function test_member_tier_multiplier_applies(): void
    {
        PricingRule::create([
            'name' => 'Gold tier 20% off',
            'bookable_item_id' => $this->item->id,
            'member_tier' => 'gold',
            'effective_from' => '2026-01-01',
            'adjustment_type' => 'discount_pct',
            'adjustment_value' => 20,
            'priority' => 100,
        ]);

        $r = $this->resolver->resolveUnitPrice($this->item, 200.00, [
            'date' => '2026-06-01', 'member_tier' => 'gold',
        ]);
        $this->assertEquals(160.00, $r['unit_price']);

        // Standard tier doesn't match
        $r2 = $this->resolver->resolveUnitPrice($this->item, 200.00, [
            'date' => '2026-06-01', 'member_tier' => 'standard',
        ]);
        $this->assertEquals(200.00, $r2['unit_price']);
    }

    public function test_time_slot_rule(): void
    {
        PricingRule::create([
            'name' => 'Peak hour 1.5x',
            'bookable_item_id' => $this->item->id,
            'time_slot_start' => '17:00',
            'time_slot_end' => '21:00',
            'effective_from' => '2026-01-01',
            'adjustment_type' => 'multiplier',
            'adjustment_value' => 1.5,
            'priority' => 100,
        ]);

        $peak = $this->resolver->resolveUnitPrice($this->item, 100.00, [
            'date' => '2026-06-01', 'start_time' => '18:00', 'end_time' => '20:00',
        ]);
        $this->assertEquals(150.00, $peak['unit_price']);

        $offPeak = $this->resolver->resolveUnitPrice($this->item, 100.00, [
            'date' => '2026-06-01', 'start_time' => '09:00', 'end_time' => '11:00',
        ]);
        $this->assertEquals(100.00, $offPeak['unit_price']);
    }

    public function test_headcount_range_rule(): void
    {
        PricingRule::create([
            'name' => 'Group of 10+ flat $80',
            'bookable_item_id' => $this->item->id,
            'min_headcount' => 10,
            'effective_from' => '2026-01-01',
            'adjustment_type' => 'fixed_price',
            'adjustment_value' => 80,
            'priority' => 100,
        ]);

        $big = $this->resolver->resolveUnitPrice($this->item, 200.00, [
            'date' => '2026-06-01', 'headcount' => 12,
        ]);
        $this->assertEquals(80.00, $big['unit_price']);

        $small = $this->resolver->resolveUnitPrice($this->item, 200.00, [
            'date' => '2026-06-01', 'headcount' => 5,
        ]);
        $this->assertEquals(200.00, $small['unit_price']);
    }

    public function test_package_rule(): void
    {
        PricingRule::create([
            'name' => 'School package fixed $50',
            'bookable_item_id' => $this->item->id,
            'package_code' => 'SCHOOL',
            'effective_from' => '2026-01-01',
            'adjustment_type' => 'fixed_price',
            'adjustment_value' => 50,
            'priority' => 100,
        ]);

        $r = $this->resolver->resolveUnitPrice($this->item, 200.00, [
            'date' => '2026-06-01', 'package_code' => 'SCHOOL',
        ]);
        $this->assertEquals(50.00, $r['unit_price']);
    }

    public function test_priority_wins_over_specificity(): void
    {
        // High specificity but low precedence (priority=200)
        PricingRule::create([
            'name' => 'Specific',
            'bookable_item_id' => $this->item->id,
            'member_tier' => 'gold',
            'package_code' => 'SCHOOL',
            'effective_from' => '2026-01-01',
            'adjustment_type' => 'fixed_price',
            'adjustment_value' => 999,
            'priority' => 200,
        ]);

        // Lower priority number wins (priority=10)
        PricingRule::create([
            'name' => 'Override',
            'bookable_item_id' => $this->item->id,
            'effective_from' => '2026-01-01',
            'adjustment_type' => 'fixed_price',
            'adjustment_value' => 10,
            'priority' => 10,
        ]);

        $r = $this->resolver->resolveUnitPrice($this->item, 200.00, [
            'date' => '2026-06-01', 'member_tier' => 'gold', 'package_code' => 'SCHOOL',
        ]);
        $this->assertEquals(10.00, $r['unit_price']);
    }

    public function test_specificity_breaks_priority_tie(): void
    {
        PricingRule::create([
            'name' => 'Generic 0.9x',
            'effective_from' => '2026-01-01',
            'adjustment_type' => 'multiplier',
            'adjustment_value' => 0.9,
            'priority' => 100,
        ]);
        PricingRule::create([
            'name' => 'Item-specific 0.5x',
            'bookable_item_id' => $this->item->id,
            'effective_from' => '2026-01-01',
            'adjustment_type' => 'multiplier',
            'adjustment_value' => 0.5,
            'priority' => 100,
        ]);

        $r = $this->resolver->resolveUnitPrice($this->item, 200.00, ['date' => '2026-06-01']);
        // Item-specific has higher specificity → wins → 200 * 0.5
        $this->assertEquals(100.00, $r['unit_price']);
    }

    public function test_effective_window_excludes_expired_rule(): void
    {
        PricingRule::create([
            'name' => 'Expired',
            'bookable_item_id' => $this->item->id,
            'effective_from' => '2025-01-01',
            'effective_until' => '2025-12-31',
            'adjustment_type' => 'fixed_price',
            'adjustment_value' => 1,
            'priority' => 1,
        ]);
        $r = $this->resolver->resolveUnitPrice($this->item, 200.00, ['date' => '2026-06-01']);
        $this->assertEquals(200.00, $r['unit_price']);
    }

    public function test_days_of_week_rule(): void
    {
        // 2026-06-06 is a Saturday (ISO day 6)
        PricingRule::create([
            'name' => 'Weekend +25%',
            'bookable_item_id' => $this->item->id,
            'days_of_week' => '6,7',
            'effective_from' => '2026-01-01',
            'adjustment_type' => 'multiplier',
            'adjustment_value' => 1.25,
            'priority' => 100,
        ]);
        $sat = $this->resolver->resolveUnitPrice($this->item, 100.00, ['date' => '2026-06-06']);
        $this->assertEquals(125.00, $sat['unit_price']);

        $mon = $this->resolver->resolveUnitPrice($this->item, 100.00, ['date' => '2026-06-08']);
        $this->assertEquals(100.00, $mon['unit_price']);
    }

    public function test_tier_for_user_helper(): void
    {
        $u = User::create(['username' => 'tieru', 'password' => 'TestPass@12345!', 'full_name' => 'T', 'role' => 'user', 'member_tier' => 'platinum']);
        $this->assertEquals('platinum', PricingResolver::tierForUser($u));
        $this->assertEquals('standard', PricingResolver::tierForUser(null));
    }
}
